<?php

namespace Modules\OrderInvoice\app\Services;

use Modules\OrderInvoice\app\Interfaces\OrderServiceInterface;
use Modules\OrderInvoice\app\Interfaces\OrderRepositoryInterface;
use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Modules\OrderInvoice\app\Models\Order;
use Modules\OrderInvoice\app\Models\OrderItem;
use Modules\OrderInvoice\app\Events\OrderConfirmed;
use Modules\OrderInvoice\app\Events\OrderCancelled;
use Modules\OrderInvoice\app\Events\OrderRefunded;
use Modules\ItemMaker\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OrderInvoice\app\Interfaces\OrderItemRepositoryInterface;

/**
 * Order Service
 *
 * سرویس مدیریت سفارشات - Business Logic Layer
 */
class OrderService implements OrderServiceInterface
{
    public function __construct(
        protected OrderRepositoryInterface $repository,
        protected InventoryIntegrationServiceInterface $inventoryService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Query & Retrieval
    // ═══════════════════════════════════════════════════════════

    public function list(array $filters = [], ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $with = ['items.item', 'invoice', 'creator'];

        if ($perPage) {
            return $this->repository->paginate($perPage, $filters, $with);
        }

        return $this->repository->getAll($filters, $with);
    }

    public function getDetails(int $orderId): Order
    {
        $order = $this->repository->findById($orderId, [
            'items.item',
            'invoice',
            'creator',
            'inventoryUsages',
        ]);

        if (!$order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش با شناسه {$orderId} یافت نشد");
        }

        return $order;
    }

    public function getByOrderNumber(string $orderNumber): Order
    {
        $order = $this->repository->findByOrderNumber($orderNumber, [
            'items.item',
            'invoice',
        ]);

        if (!$order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش با شماره {$orderNumber} یافت نشد");
        }

        return $order;
    }

    public function getUserOrders(int $userId, array $filters = []): Collection
    {
        return $this->repository->getUserOrders($userId, $filters, ['items', 'invoice']);
    }

    // ═══════════════════════════════════════════════════════════
    // Order Management
    // ═══════════════════════════════════════════════════════════

    public function createOrder(array $orderData): Order
    {
        return DB::transaction(function () use ($orderData) {
            $items = $orderData['items'] ?? [];
            unset($orderData['items']);
            // Auto-set created_by
            $orderData['created_by'] = $orderData['created_by'] ?? auth()->id();
            $orderData['status'] = 'draft';

            // Create order
            $order = $this->repository->create($orderData);
            if (!empty($items)) {
                foreach ($items as $itemData) {
                    $this->addItem(
                        $order->id,
                        $itemData['item_id'],
                        $itemData['quantity'],
                        $itemData['notes'] ?? null
                    );
                }

                $order->refresh();

                $order->recalculatePricing();
                $order->save();
            }

            Log::info("Order created: {$order->order_number}", [
                'order_id' => $order->id,
                'items_count' => count($items)
            ]);
            $this->clearOrderCache($order->id);
            return $order->fresh(['items', 'invoice']);
        });
    }

    public function updateOrder(int $orderId, array $orderData): Order
    {
        return DB::transaction(function () use ($orderId, $orderData) {
            $order = $this->repository->findById($orderId);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_editable) {
                throw new \Exception("فقط سفارشات در وضعیت پیش‌نویس قابل ویرایش هستند");
            }

            $orderFields = Arr::only($orderData, ['notes', 'discount_percent', 'tax_percent', 'delivery_fee']);
            $order = $this->repository->update($orderId, $orderFields);

            if (isset($orderData['items']) && is_array($orderData['items'])) {
                $this->syncOrderItems($order, $orderData['items']);
            }

            Log::info("Order updated: {$order->order_number}", ['order_id' => $order->id]);
            $this->clearOrderCache($orderId);

            return $order->fresh(['items', 'invoice']);
        });
    }

    private function syncOrderItems(Order $order, array $items): void
    {
        foreach ($items as $itemData) {
            $orderItem = $order->items()
                ->where('item_id', $itemData['item_id'])
                ->first();

            if ($orderItem) {
                $orderItem->update([
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                    'total_price' => $orderItem->unit_price * $itemData['quantity']
                ]);
            } else {
                $this->addItem(
                    $order->id,
                    $itemData['item_id'],
                    $itemData['quantity'],
                    $itemData['notes'] ?? null
                );
            }
        }

        $order->refresh();
        $order->recalculatePricing();
        $order->save();
    }

    public function deleteOrder(int $orderId): bool
    {
        $order = $this->repository->findById($orderId);

        if (!$order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
        }

        // فقط draft قابل حذف
        if (!$order->is_editable) {
            throw new \Exception("فقط سفارشات در وضعیت پیش‌نویس قابل حذف هستند");
        }

        $result = $this->repository->delete($orderId);

        Log::info("Order deleted: {$order->order_number}", ['order_id' => $order->id]);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // Order Items Management
    // ═══════════════════════════════════════════════════════════

    public function addItem(int $orderId, int $itemId, int $quantity, ?string $notes = null): OrderItem
    {
        return DB::transaction(function () use ($orderId, $itemId, $quantity, $notes) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_editable) {
                throw new \Exception("فقط سفارشات در وضعیت پیش‌نویس قابل ویرایش هستند");
            }

            $item = Item::with(['ingredients', 'boxes'])->findOrFail($itemId);

            if (!$item->is_active) {
                throw new \Exception("محصول غیرفعال است");
            }
            $availability = $this->inventoryService->checkItemAvailability($itemId, $quantity);

            if (!$availability['available']) {
                $errors = [];

                if (!empty($availability['ingredients'])) {
                    $errors[] = "مواد اولیه ناکافی:";
                    foreach ($availability['ingredients'] as $ingredient) {
                        $errors[] = "  • {$ingredient['name']}: نیاز {$ingredient['required']} گرم، موجود {$ingredient['available']} گرم (کمبود {$ingredient['shortage']} گرم)";
                    }
                }

                if (!empty($availability['boxes'])) {
                    $errors[] = "بسته‌بندی ناکافی:";
                    foreach ($availability['boxes'] as $box) {
                        $errors[] = "  • {$box['name']}: نیاز {$box['required']} عدد، موجود {$box['available']} عدد (کمبود {$box['shortage']} عدد)";
                    }
                }

                throw new \Exception(
                    "موجودی کافی نیست:\n\n" . implode("\n", $errors)
                );
            }
            $snapshot = OrderItem::createSnapshot($item);

            $unitPrice = $item->actual_sell_price ?? $item->target_sell_price;

            $orderItem = $this->repository->addItem($orderId, [
                'item_id' => $itemId,
                'item_snapshot' => $snapshot,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'notes' => $notes,
            ]);

            $order = $order->fresh(['items']);

            $order->recalculatePricing();
            $order->save();

            Log::info("Item added to order: {$order->order_number}", [
                'order_id' => $order->id,
                'item_id' => $itemId,
                'item_name' => $snapshot['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'order_item_id' => $orderItem->id
            ]);

            return $orderItem->fresh('item');
        });
    }

    public function removeItem(int $orderId, int $orderItemId): bool
    {
        return DB::transaction(function () use ($orderId, $orderItemId) {
            $order = $this->repository->findById($orderId);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_editable) {
                throw new \Exception("فقط سفارشات در وضعیت پیش‌نویس قابل ویرایش هستند");
            }

            $deleted = $this->repository->removeItem($orderId, $orderItemId);

            if (!$deleted) {
                Log::warning("Order item not found for deletion", [
                    'order_id' => $orderId,
                    'order_item_id' => $orderItemId
                ]);
                return false;
            }

            $order = Order::with('items')->find($orderId);

            $order->recalculatePricing();
            $order->save();

            Log::info("Item removed from order: {$order->order_number}", [
                'order_id' => $order->id,
                'order_item_id' => $orderItemId,
                'remaining_items' => $order->items->count()
            ]);
            $this->clearOrderCache($orderId);
            return true;
        });
    }

    public function updateItemQuantity(int $orderId, int $orderItemId, int $newQuantity): OrderItem
    {
        return DB::transaction(function () use ($orderId, $orderItemId, $newQuantity) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_editable) {
                throw new \Exception("فقط سفارشات در وضعیت پیش‌نویس قابل ویرایش هستند");
            }

            // $orderItem = $order->items->firstWhere('id', $orderItemId);
            $orderItem = $order->items()->find($orderItemId);
            if (!$orderItem) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("آیتم در سفارش یافت نشد");
            }

            $oldQuantity = $orderItem->quantity;
            $itemId = $orderItem->item_id;

            if ($newQuantity > $oldQuantity) {
                $additionalQuantity = $newQuantity - $oldQuantity;

                $availability = $this->inventoryService->checkItemAvailability($itemId, $additionalQuantity);

                if (!$availability['available']) {
                    $errors = [];

                    if (!empty($availability['ingredients'])) {
                        $errors[] = "مواد اولیه ناکافی برای افزایش تعداد:";
                        foreach ($availability['ingredients'] as $ingredient) {
                            $errors[] = "  • {$ingredient['name']}: نیاز {$ingredient['required']} گرم، موجود {$ingredient['available']} گرم";
                        }
                    }

                    if (!empty($availability['boxes'])) {
                        $errors[] = "بسته‌بندی ناکافی برای افزایش تعداد:";
                        foreach ($availability['boxes'] as $box) {
                            $errors[] = "  • {$box['name']}: نیاز {$box['required']} عدد، موجود {$box['available']} عدد";
                        }
                    }

                    throw new \Exception(
                        "موجودی کافی نیست:\n\n" . implode("\n", $errors)
                    );
                }
            }

            $orderItem = $this->repository->updateItem($orderItemId, [
                'quantity' => $newQuantity,
            ]);

            // $orderItem->calculateTotal();
            // $orderItem->save();
            $orderItem->quantity = $newQuantity;
            $orderItem->total_price = $orderItem->unit_price * $newQuantity; 
            $orderItem->save();

            $order->recalculatePricing();
            $order->save();

            Log::info("Item quantity updated: {$order->order_number}", [
                'order_id' => $order->id,
                'order_item_id' => $orderItemId,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
            ]);
            $this->clearOrderCache($orderId);
            return $orderItem->fresh();
        });
    }


    // ═══════════════════════════════════════════════════════════
    // Pricing Management
    // ═══════════════════════════════════════════════════════════

    public function applyDiscount(int $orderId, float $discountPercent): Order
    {
        return DB::transaction(function () use ($orderId, $discountPercent) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if ($discountPercent < 0 || $discountPercent > 100) {
                throw new \InvalidArgumentException("درصد تخفیف باید بین 0 تا 100 باشد");
            }

            // ✅ ست کن
            $order->discount_percent = $discountPercent;

            // ✅ محاسبه کن
            $order->recalculatePricing();

            // ✅ ذخیره کن
            $order->save();
            $this->clearOrderCache($orderId);
            Log::info("Discount applied to order", [
                'order_id' => $order->id,
                'discount_percent' => $discountPercent,
                'discount_amount' => $order->discount_amount,
                'total_amount' => $order->total_amount,
            ]);

            return $order->fresh(['items']);
        });
    }

    public function applyTax(int $orderId, float $taxPercent): Order
    {
        return DB::transaction(function () use ($orderId, $taxPercent) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if ($taxPercent < 0 || $taxPercent > 100) {
                throw new \InvalidArgumentException("درصد مالیات باید بین 0 تا 100 باشد");
            }

            // ✅ ست کن
            $order->tax_percent = $taxPercent;

            // ✅ محاسبه کن
            $order->recalculatePricing();

            // ✅ ذخیره کن
            $order->save();
            $this->clearOrderCache($orderId);
            Log::info("Tax applied to order", [
                'order_id' => $order->id,
                'tax_percent' => $taxPercent,
                'tax_amount' => $order->tax_amount,
                'total_amount' => $order->total_amount,
            ]);

            return $order->fresh(['items']);
        });
    }

    public function setDeliveryFee(int $orderId, float $fee): Order
    {
        return DB::transaction(function () use ($orderId, $fee) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if ($fee < 0) {
                throw new \InvalidArgumentException("هزینه ارسال نمی‌تواند منفی باشد");
            }

            // ✅ ست کن
            $order->delivery_fee = $fee;

            // ✅ محاسبه کن
            $order->recalculatePricing();

            // ✅ ذخیره کن
            $order->save();
            $this->clearOrderCache($orderId);
            Log::info("Delivery fee set for order", [
                'order_id' => $order->id,
                'delivery_fee' => $fee,
                'total_amount' => $order->total_amount,
            ]);

            return $order->fresh(['items']);
        });
    }

    public function recalculatePricing(int $orderId): Order
    {
        $order = $this->repository->findById($orderId, ['items']);

        if (!$order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
        }

        $order->recalculatePricing();
        $order->save();

        return $order->fresh();
    }
    private function clearOrderCache(int $orderId): void
    {
        // همه patternهای ممکن رو پاک کن
        Cache::forget("order:{$orderId}");
        Cache::forget("order:{$orderId}:*");

        // common relations
        $relations = [['items'], ['creator'], ['invoice'], ['items', 'creator']];
        foreach ($relations as $with) {
            $key = "order:{$orderId}:" . md5(json_encode($with));
            Cache::forget($key);
        }
    }
    // ═══════════════════════════════════════════════════════════
    // Order Workflow
    // ═══════════════════════════════════════════════════════════

    public function confirmOrder(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->repository->findById($orderId, ['items']);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_confirmable) {
                throw new \Exception("سفارش قابل تایید نیست");
            }

            if ($order->items->count() === 0) {
                throw new \Exception("سفارش خالی است");
            }

            // Confirm order
            $order->confirm();

            // Fire event → ReserveInventory listener
            event(new OrderConfirmed($order));
            $this->clearOrderCache($orderId);

            Log::info("Order confirmed: {$order->order_number}", ['order_id' => $order->id]);

            return $order->fresh();
        });
    }

    public function cancelOrder(int $orderId, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($orderId, $reason) {
            $order = $this->repository->findById($orderId);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_cancellable) {
                throw new \Exception("سفارش قابل لغو نیست");
            }

            // Cancel order
            $order->cancel($reason);

            // Fire event → ReleaseInventory listener
            event(new OrderCancelled($order, $reason));
            $this->clearOrderCache($orderId);

            Log::info("Order cancelled: {$order->order_number}", [
                'order_id' => $order->id,
                'reason' => $reason,
            ]);

            return $order->fresh();
        });
    }

    public function completeOrder(int $orderId): Order
    {
        $order = $this->repository->findById($orderId);

        if (!$order) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
        }

        if ($order->status !== 'paid') {
            throw new \Exception("فقط سفارشات پرداخت شده قابل تکمیل هستند");
        }

        $order->complete();
        $this->clearOrderCache($orderId);

        Log::info("Order completed: {$order->order_number}", ['order_id' => $order->id]);

        return $order->fresh();
    }

    public function refundOrder(int $orderId, string $refundType, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($orderId, $refundType, $reason) {
            $order = $this->repository->findById($orderId);

            if (!$order) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
            }

            if (!$order->is_refundable) {
                throw new \Exception("سفارش قابل برگشت نیست");
            }

            if (!in_array($refundType, ['consumed', 'returned'])) {
                throw new \InvalidArgumentException("نوع برگشت نامعتبر است");
            }

            // Refund order
            $order->refund($refundType, $reason);

            if ($refundType === 'returned') {
                // ✅ returned → event fire → inventory + invoice listeners
                event(new OrderRefunded($order, $refundType, $reason));
                Log::info("Full refund (returned) - events fired", ['order_id' => $orderId]);
            } else {
                // ✅ consumed → فقط status → بدون event → بدون inventory
                Log::info("Financial refund (consumed) - status only", ['order_id' => $orderId]);
            }

            Log::info("Order refunded ({$refundType}): {$order->order_number}", [
                'order_id' => $order->id,
                'refund_type' => $refundType,
                'reason' => $reason,
            ]);

            return $order->fresh();
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Validation & Checks
    // ═══════════════════════════════════════════════════════════

    public function checkAvailability(int $orderId): array
    {
        return $this->inventoryService->checkOrderAvailability($orderId);
    }

    public function validateOrder(int $orderId): array
    {
        $order = $this->repository->findById($orderId, ['items']);

        if (!$order) {
            return [
                'valid' => false,
                'errors' => ['سفارش یافت نشد'],
            ];
        }

        $errors = [];

        if ($order->items->count() === 0) {
            $errors[] = 'سفارش خالی است';
        }

        if ($order->total_amount <= 0) {
            $errors[] = 'مبلغ سفارش نامعتبر است';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Statistics & Reports
    // ═══════════════════════════════════════════════════════════

    public function getStatistics(array $filters = []): array
    {
        return $this->repository->getStatistics($filters);
    }

    public function getTodayOrders(): Collection
    {
        return $this->repository->getTodayOrders(['items', 'invoice']); //getTodayOrders
    }

    public function getTodayPaidOrders(): Collection
    {
        return $this->repository->getPaidOrders(['items', 'invoice'])  //getPaidOrders
            ->filter(fn($order) => $order->paid_at?->isToday());
    }
}
