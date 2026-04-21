<?php

namespace Modules\OrderInvoice\app\Repositories;

use Modules\OrderInvoice\app\Interfaces\OrderRepositoryInterface;
use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Order Repository
 * 
 * مدیریت دسترسی داده‌های سفارش
 */
class OrderRepository implements OrderRepositoryInterface
{
    /**
     * مدت زمان کش (ثانیه)
     */
    protected int $cacheTTL = 1800; // 30 minutes

    // ═══════════════════════════════════════════════════════════
    // CRUD Operations
    // ═══════════════════════════════════════════════════════════

    public function getAll(array $filters = [], array $with = []): Collection
    {
        $query = Order::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $this->applyFilters($query, $filters);

        return $query->latestFirst()->get();
    }

    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = Order::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $this->applyFilters($query, $filters);

        return $query->latestFirst()->paginate($perPage);
    }

    public function findById(int $id, array $with = []): ?Order
    {

        $query = Order::query();

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function findByOrderNumber(string $orderNumber, array $with = []): ?Order
    {
        $cacheKey = "order:number:{$orderNumber}:" . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($orderNumber, $with) {
            $query = Order::query();

            if (!empty($with)) {
                $query->with($with);
            }

            return $query->where('order_number', $orderNumber)->first();
        });
    }

    public function create(array $data): Order
    {
        $order = Order::create($data);

        $this->clearCache();

        return $order->fresh();
    }

    public function update(int $id, array $data): Order
    {
        $order = Order::findOrFail($id);
        $order->update($data);

        $this->clearOrderCache($id);

        return $order->fresh();
    }

    public function delete(int $id): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->delete();

        $this->clearOrderCache($id);

        return $result;
    }

    public function restore(int $id): bool
    {
        $order = Order::withTrashed()->findOrFail($id);
        $result = $order->restore();

        $this->clearOrderCache($id);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // Query Methods
    // ═══════════════════════════════════════════════════════════

    public function getByStatus(string $status, array $with = []): Collection
    {
        $cacheKey = "orders:status:{$status}:" . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($status, $with) {
            return Order::where('status', $status)
                ->when(!empty($with), fn($q) => $q->with($with))
                ->latestFirst()
                ->get();
        });
    }

    public function getUserOrders(int $userId, array $filters = [], array $with = []): Collection
    {
        $query = Order::where('created_by', $userId);

        if (!empty($with)) {
            $query->with($with);
        }

        $this->applyFilters($query, $filters);

        return $query->latestFirst()->get();
    }

    public function getToday(array $filters = []): Collection
    {
        $query = Order::today();

        $this->applyFilters($query, $filters);

        return $query->latestFirst()->get();
    }

    public function getBetweenDates(string $startDate, string $endDate, array $filters = []): Collection
    {
        $query = Order::betweenDates($startDate, $endDate);

        $this->applyFilters($query, $filters);

        return $query->latestFirst()->get();
    }

    public function search(string $query, array $filters = []): Collection
    {
        $q = Order::query();

        // جستجو در order_number و notes
        $q->where(function ($q) use ($query) {
            $q->where('order_number', 'like', "%{$query}%")
                ->orWhere('notes', 'like', "%{$query}%");
        });

        $this->applyFilters($q, $filters);

        return $q->latestFirst()->get();
    }

    /**
     * سفارشات امروز
     * استفاده در OrderService->getTodayOrders()
     */
    public function getTodayOrders(array $with = []): Collection
    {
        return Order::today()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->latestFirst()
            ->get();
    }

    /**
     * سفارشات پرداخت شده
     * استفاده در OrderService->getTodayPaidOrders()
     */
    public function getPaidOrders(array $with = []): Collection
    {
        return Order::paid()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->latestFirst()
            ->get();
    }

    /**
     * سفارشات پیش‌نویس
     */
    public function getDraftOrders(array $with = []): Collection
    {
        return Order::draft()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->latestFirst()
            ->get();
    }

    /**
     * سفارشات تایید شده
     */
    public function getConfirmedOrders(array $with = []): Collection
    {
        return Order::confirmed()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->latestFirst()
            ->get();
    }

    /**
     * سفارشات تکمیل شده
     */
    public function getCompletedOrders(array $with = []): Collection
    {
        return Order::completed()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->latestFirst()
            ->get();
    }

    // ═══════════════════════════════════════════════════════════
    // Order Items Management (Aggregate Methods)
    // ═══════════════════════════════════════════════════════════

    /**
     * افزودن آیتم به سفارش
     * 
     * این متد در OrderRepository است چون Order یک Aggregate Root است
     * و OrderItems بخشی از این aggregate هستند.
     * 
     * استفاده در OrderService->addItem()
     */
    public function addItem(int $orderId, array $itemData): \Modules\OrderInvoice\app\Models\OrderItem
    {
        return DB::transaction(function () use ($orderId, $itemData) {
            $orderItem = \Modules\OrderInvoice\app\Models\OrderItem::create(
                array_merge($itemData, ['order_id' => $orderId])
            );

            $this->clearOrderCache($orderId);

            return $orderItem->fresh('item');
        });
    }

    /**
     * حذف آیتم از سفارش
     * 
     * استفاده در OrderService->removeItem()
     */
    public function removeItem(int $orderId, int $orderItemId): bool
    {
        \Log::info("Repository: Starting removeItem", [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId
        ]);

        $orderItem = \Modules\OrderInvoice\app\Models\OrderItem::where('id', $orderItemId)
            ->where('order_id', $orderId)
            ->first();

        if (!$orderItem) {
            \Log::warning("Repository: Order item not found", [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId
            ]);
            return false;
        }

        \Log::info("Repository: Order item found", [
            'item_id' => $orderItem->item_id,
            'quantity' => $orderItem->quantity
        ]);

        $deleted = $orderItem->delete();

        \Log::info("Repository: Delete result", ['deleted' => $deleted]);

        $this->clearAllOrderCache($orderId);

        $stillExists = \Modules\OrderInvoice\app\Models\OrderItem::find($orderItemId);
        \Log::info("Repository: Verify deletion", [
            'still_exists' => $stillExists ? 'YES - ERROR!' : 'NO - SUCCESS'
        ]);

        return $deleted;
    }

    /**
     * بروزرسانی آیتم
     * 
     * استفاده در OrderService->updateItemQuantity()
     */
    public function updateItem(int $orderItemId, array $data): \Modules\OrderInvoice\app\Models\OrderItem
    {
        $orderItem = \Modules\OrderInvoice\app\Models\OrderItem::findOrFail($orderItemId);
        $orderItem->update($data);

        $this->clearOrderCache($orderItem->order_id);

        return $orderItem->fresh('item');
    }

    // ═══════════════════════════════════════════════════════════
    // Status Updates
    // ═══════════════════════════════════════════════════════════

    public function updateStatus(int $id, string $status): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update(['status' => $status]);

        $this->clearOrderCache($id);

        return $result;
    }

    public function markAsConfirmed(int $id): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->clearOrderCache($id);

        return $result;
    }

    public function markAsPaid(int $id): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->clearOrderCache($id);

        return $result;
    }

    public function markAsCompleted(int $id): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->clearOrderCache($id);

        return $result;
    }

    public function markAsCancelled(int $id): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->clearOrderCache($id);

        return $result;
    }

    public function markAsRefunded(int $id, string $refundType, ?string $reason = null): bool
    {
        $order = Order::findOrFail($id);
        $status = $refundType === 'consumed' ? 'refunded_consumed' : 'refunded_returned';

        $result = $order->update([
            'status' => $status,
            'refund_type' => $refundType,
            'refund_reason' => $reason,
            'refunded_at' => now(),
        ]);

        $this->clearOrderCache($id);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // Pricing Operations
    // ═══════════════════════════════════════════════════════════

    public function updatePricing(int $id, array $pricingData): bool
    {
        $order = Order::findOrFail($id);
        $result = $order->update($pricingData);

        $this->clearOrderCache($id);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════

    public function getStatistics(array $filters = []): array
    {
        $cacheKey = 'orders:statistics:' . md5(json_encode($filters));

        return Cache::remember($cacheKey, 600, function () use ($filters) {
            $query = Order::query();
            $this->applyFilters($query, $filters);

            return [
                'total' => (clone $query)->count(),
                'draft' => (clone $query)->draft()->count(),
                'confirmed' => (clone $query)->confirmed()->count(),
                'paid' => (clone $query)->paid()->count(),
                'completed' => (clone $query)->completed()->count(),
                'cancelled' => (clone $query)->cancelled()->count(),
                'refunded' => (clone $query)->refunded()->count(),
                'total_amount' => (clone $query)->paid()->sum('total_amount'),
                'today_orders' => Order::today()->count(),
                'today_revenue' => Order::paid()->today()->sum('total_amount'),
            ];
        });
    }

    public function orderNumberExists(string $orderNumber): bool
    {
        return Order::where('order_number', $orderNumber)->exists();
    }

    // ═══════════════════════════════════════════════════════════
    // Helper Methods
    // ═══════════════════════════════════════════════════════════

    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (isset($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }
    }
    protected function clearAllOrderCache(int $orderId): void
    {
        $keys = [
            "order:{$orderId}",
            "order:{$orderId}:" . md5(json_encode([])),
            "order:{$orderId}:" . md5(json_encode(['items'])),
            "order:{$orderId}:" . md5(json_encode(['items.item'])),
            "order:{$orderId}:" . md5(json_encode(['items', 'invoice'])),
            "order:{$orderId}:" . md5(json_encode(['items.item', 'invoice', 'creator'])),
            "order:{$orderId}:" . md5(json_encode(['items.item', 'invoice', 'creator', 'inventoryUsages'])),
            "order_items:order:{$orderId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->clearCache();
    }

    protected function clearOrderCache(int $id): void
    {
        Cache::forget("order:{$id}");
        $this->clearCache();
    }

    protected function clearCache(): void
    {
        Cache::forget('orders:statistics');
        // Clear other cached keys if needed
    }
}
