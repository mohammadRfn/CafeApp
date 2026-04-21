<?php

namespace Modules\OrderInvoice\app\Services;

use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Modules\OrderInvoice\app\Models\Order;
use Modules\OrderInvoice\app\Models\InventoryUsage;
use Modules\Inventory\Services\StockManagementService;
use Modules\Inventory\Services\InventoryTransactionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Inventory Integration Service
 * 
 * سرویس ارتباط صحیح با Inventory Module
 * مطابق با متدهای واقعی StockManagementService
 */
class InventoryIntegrationService implements InventoryIntegrationServiceInterface
{
    public function __construct(
        protected StockManagementService $stockService,
        protected InventoryTransactionService $transactionService
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Availability Checks
    // ═══════════════════════════════════════════════════════════

    public function checkOrderAvailability(int $orderId): array
    {
        $cacheKey = "order:availability:{$orderId}";
        
        return Cache::remember($cacheKey, 60, function () use ($orderId) {
            $order = Order::with(['items.item.ingredients', 'items.item.boxes'])->findOrFail($orderId);

            $available = true;
            $ingredientShortages = [];
            $boxShortages = [];

            foreach ($order->items as $orderItem) {
                $snapshot = $orderItem->item_snapshot;
                $quantity = $orderItem->quantity;

                // بررسی ingredients - استفاده از getStockStatus
                foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                    $requiredGrams = $ingredient['actual_grams'] * $quantity;
                    
                    $stockStatus = $this->stockService->getStockStatus($ingredient['id']);
                    $availableGrams = $stockStatus ? $stockStatus->available_grams : 0;

                    if ($availableGrams < $requiredGrams) {
                        $available = false;
                        $ingredientShortages[] = [
                            'id' => $ingredient['id'],
                            'name' => $ingredient['name'],
                            'required' => round($requiredGrams, 2),
                            'available' => round($availableGrams, 2),
                            'shortage' => round($requiredGrams - $availableGrams, 2),
                        ];
                    }
                }

                // بررسی boxes - استفاده از getBoxStockStatus
                foreach ($snapshot['recipe']['boxes'] as $box) {
                    $requiredQty = $box['required_quantity'] * $quantity;
                    
                    $boxStockStatus = $this->stockService->getBoxStockStatus($box['id']);
                    $availableQty = $boxStockStatus 
                        ? ($boxStockStatus->quantity - $boxStockStatus->reserved_quantity) 
                        : 0;

                    if ($availableQty < $requiredQty) {
                        $available = false;
                        $boxShortages[] = [
                            'id' => $box['id'],
                            'name' => $box['name'],
                            'required' => $requiredQty,
                            'available' => $availableQty,
                            'shortage' => $requiredQty - $availableQty,
                        ];
                    }
                }
            }

            return [
                'available' => $available,
                'ingredients' => $ingredientShortages,
                'boxes' => $boxShortages,
            ];
        });
    }

    public function checkItemAvailability(int $itemId, int $quantity): array
    {
        $item = \Modules\ItemMaker\Models\Item::with(['ingredients', 'boxes'])->findOrFail($itemId);

        $available = true;
        $ingredientShortages = [];
        $boxShortages = [];

        foreach ($item->ingredients as $ingredient) {
            $requiredGrams = $ingredient->pivot->actual_grams * $quantity;
            
            $stockStatus = $this->stockService->getStockStatus($ingredient->id);
            $availableGrams = $stockStatus ? $stockStatus->available_grams : 0;

            if ($availableGrams < $requiredGrams) {
                $available = false;
                $ingredientShortages[] = [
                    'id' => $ingredient->id,
                    'name' => $ingredient->ingredient_name,
                    'required' => round($requiredGrams, 2),
                    'available' => round($availableGrams, 2),
                    'shortage' => round($requiredGrams - $availableGrams, 2),
                ];
            }
        }

        foreach ($item->boxes as $box) {
            $requiredQty = $box->pivot->required_quantity * $quantity;
            
            $boxStockStatus = $this->stockService->getBoxStockStatus($box->id);
            $availableQty = $boxStockStatus 
                ? ($boxStockStatus->quantity - $boxStockStatus->reserved_quantity) 
                : 0;

            if ($availableQty < $requiredQty) {
                $available = false;
                $boxShortages[] = [
                    'id' => $box->id,
                    'name' => $box->name,
                    'required' => $requiredQty,
                    'available' => $availableQty,
                    'shortage' => $requiredQty - $availableQty,
                ];
            }
        }

        return [
            'available' => $available,
            'ingredients' => $ingredientShortages,
            'boxes' => $boxShortages,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Reserve Operations (استفاده از متدهای صحیح)
    // ═══════════════════════════════════════════════════════════

    public function reserveForOrder(int $orderId): bool
    {
        return RateLimiter::attempt("inventory:reserve:{$orderId}", 1, function () use ($orderId) {
            return DB::transaction(function () use ($orderId) {
                $order = Order::with('items')->lockForUpdate()->findOrFail($orderId);

                foreach ($order->items as $orderItem) {
                    $snapshot = $orderItem->item_snapshot;
                    $quantity = $orderItem->quantity;

                    // رزرو ingredients با reserveStock
                    foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                        $requiredGrams = $ingredient['actual_grams'] * $quantity;

                        $result = $this->stockService->reserveStock($ingredient['id'], $requiredGrams);
                        
                        if (!$result['success']) {
                            throw new \Exception("موجودی کافی نیست: {$ingredient['name']}");
                        }
                    }

                    // رزرو boxes با reserveBoxStock
                    foreach ($snapshot['recipe']['boxes'] as $box) {
                        $requiredQty = $box['required_quantity'] * $quantity;

                        $result = $this->stockService->reserveBoxStock($box['id'], $requiredQty);
                        
                        if (!$result['success']) {
                            throw new \Exception("موجودی کافی نیست: {$box['name']}");
                        }
                    }
                }

                Cache::forget("order:availability:{$orderId}");
                Log::info("Inventory reserved for order", ['order_id' => $orderId]);

                return true;
            });
        }, 3);
    }

    public function releaseReservation(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::with('items')->findOrFail($orderId);

            foreach ($order->items as $orderItem) {
                $snapshot = $orderItem->item_snapshot;
                $quantity = $orderItem->quantity;

                // آزادسازی ingredients با releaseReservation
                foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                    $requiredGrams = $ingredient['actual_grams'] * $quantity;
                    $this->stockService->releaseReservation($ingredient['id'], $requiredGrams);
                }

                // آزادسازی boxes با releaseBoxReservation
                foreach ($snapshot['recipe']['boxes'] as $box) {
                    $requiredQty = $box['required_quantity'] * $quantity;
                    $this->stockService->releaseBoxReservation($box['id'], $requiredQty);
                }
            }

            Cache::forget("order:availability:{$orderId}");
            Log::info("Inventory reservation released", ['order_id' => $orderId]);

            return true;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Commit Operations (استفاده از createTransaction)
    // ═══════════════════════════════════════════════════════════

    public function commitInventory(int $orderId): bool
    {
        return RateLimiter::attempt("inventory:commit:{$orderId}", 1, function () use ($orderId) {
            return DB::transaction(function () use ($orderId) {
                $order = Order::with('items')->lockForUpdate()->findOrFail($orderId);

                foreach ($order->items as $orderItem) {
                    $snapshot = $orderItem->item_snapshot;
                    $quantity = $orderItem->quantity;

                    // کم کردن واقعی ingredients
                    foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                        $requiredGrams = $ingredient['actual_grams'] * $quantity;

                        $transaction = $this->transactionService->createTransaction([
                            'entity_type' => 'ingredient',
                            'entity_id' => $ingredient['id'],
                            'transaction_type' => 'usage',
                            'input_quantity' => $requiredGrams,
                            'grams_effect' => -$requiredGrams,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'notes' => "مصرف در سفارش {$order->order_number}",
                            'created_by' => auth()->id(),
                        ]);

                        InventoryUsage::recordIngredientUsage(
                            orderId: $order->id,
                            orderItemId: $orderItem->id,
                            ingredientId: $ingredient['id'],
                            quantityGrams: $requiredGrams,
                            transactionId: $transaction->id,
                            usageType: 'commit'
                        );
                    }

                    // کم کردن واقعی boxes
                    foreach ($snapshot['recipe']['boxes'] as $box) {
                        $requiredQty = $box['required_quantity'] * $quantity;

                        $transaction = $this->transactionService->createTransaction([
                            'entity_type' => 'box',
                            'entity_id' => $box['id'],
                            'transaction_type' => 'usage',
                            'input_quantity' => $requiredQty,
                            'quantity_effect' => -$requiredQty,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'notes' => "مصرف در سفارش {$order->order_number}",
                            'created_by' => auth()->id(),
                        ]);

                        InventoryUsage::recordBoxUsage(
                            orderId: $order->id,
                            orderItemId: $orderItem->id,
                            boxId: $box['id'],
                            quantity: $requiredQty,
                            transactionId: $transaction->id,
                            usageType: 'commit'
                        );
                    }
                }

                Log::info("Inventory committed", ['order_id' => $orderId]);
                return true;
            });
        }, 3);
    }

    // ═══════════════════════════════════════════════════════════
    // Rollback Operations
    // ═══════════════════════════════════════════════════════════

    public function rollbackConsumed(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::with('items')->findOrFail($orderId);

            foreach ($order->items as $orderItem) {
                $snapshot = $orderItem->item_snapshot;
                $quantity = $orderItem->quantity;

                foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                    $requiredGrams = $ingredient['actual_grams'] * $quantity;

                    $transaction = $this->transactionService->createTransaction([
                        'entity_type' => 'ingredient',
                        'entity_id' => $ingredient['id'],
                        'transaction_type' => 'adjustment',
                        'input_quantity' => 0,
                        'grams_effect' => 0,
                        'reference_type' => 'order_refund',
                        'reference_id' => $order->id,
                        'notes' => "برگشت مصرف شده - {$order->order_number}",
                        'created_by' => auth()->id(),
                    ]);

                    InventoryUsage::recordIngredientUsage(
                        orderId: $order->id,
                        orderItemId: $orderItem->id,
                        ingredientId: $ingredient['id'],
                        quantityGrams: $requiredGrams,
                        transactionId: $transaction->id,
                        usageType: 'rollback'
                    );
                }

                foreach ($snapshot['recipe']['boxes'] as $box) {
                    $requiredQty = $box['required_quantity'] * $quantity;

                    $transaction = $this->transactionService->createTransaction([
                        'entity_type' => 'box',
                        'entity_id' => $box['id'],
                        'transaction_type' => 'adjustment',
                        'input_quantity' => 0,
                        'quantity_effect' => 0,
                        'reference_type' => 'order_refund',
                        'reference_id' => $order->id,
                        'notes' => "برگشت مصرف شده - {$order->order_number}",
                        'created_by' => auth()->id(),
                    ]);

                    InventoryUsage::recordBoxUsage(
                        orderId: $order->id,
                        orderItemId: $orderItem->id,
                        boxId: $box['id'],
                        quantity: $requiredQty,
                        transactionId: $transaction->id,
                        usageType: 'rollback'
                    );
                }
            }

            Log::info("Inventory rollback (consumed)", ['order_id' => $orderId]);
            return true;
        });
    }

    public function rollbackReturned(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::with('items')->findOrFail($orderId);

            foreach ($order->items as $orderItem) {
                $snapshot = $orderItem->item_snapshot;
                $quantity = $orderItem->quantity;

                foreach ($snapshot['recipe']['ingredients'] as $ingredient) {
                    $requiredGrams = $ingredient['actual_grams'] * $quantity;

                    $transaction = $this->transactionService->createTransaction([
                        'entity_type' => 'ingredient',
                        'entity_id' => $ingredient['id'],
                        'transaction_type' => 'adjustment',
                        'input_quantity' => $requiredGrams,
                        'grams_effect' => $requiredGrams,
                        'reference_type' => 'order_refund',
                        'reference_id' => $order->id,
                        'notes' => "برگشت سالم - {$order->order_number}",
                        'created_by' => auth()->id(),
                    ]);

                    InventoryUsage::recordIngredientUsage(
                        orderId: $order->id,
                        orderItemId: $orderItem->id,
                        ingredientId: $ingredient['id'],
                        quantityGrams: $requiredGrams,
                        transactionId: $transaction->id,
                        usageType: 'rollback'
                    );
                }

                foreach ($snapshot['recipe']['boxes'] as $box) {
                    $requiredQty = $box['required_quantity'] * $quantity;

                    $transaction = $this->transactionService->createTransaction([
                        'entity_type' => 'box',
                        'entity_id' => $box['id'],
                        'transaction_type' => 'adjustment',
                        'input_quantity' => $requiredQty,
                        'quantity_effect' => $requiredQty,
                        'reference_type' => 'order_refund',
                        'reference_id' => $order->id,
                        'notes' => "برگشت سالم - {$order->order_number}",
                        'created_by' => auth()->id(),
                    ]);

                    InventoryUsage::recordBoxUsage(
                        orderId: $order->id,
                        orderItemId: $orderItem->id,
                        boxId: $box['id'],
                        quantity: $requiredQty,
                        transactionId: $transaction->id,
                        usageType: 'rollback'
                    );
                }
            }

            Log::info("Inventory rollback (returned)", ['order_id' => $orderId]);
            return true;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Tracking
    // ═══════════════════════════════════════════════════════════

    public function getOrderUsageHistory(int $orderId): array
    {
        $usages = InventoryUsage::where('order_id', $orderId)
            ->with(['ingredient', 'box'])
            ->get();

        return [
            'ingredients' => $usages->where('entity_type', 'ingredient')->values(),
            'boxes' => $usages->where('entity_type', 'box')->values(),
        ];
    }

    public function getIngredientUsageToday(int $ingredientId): float
    {
        return InventoryUsage::ingredientUsageToday($ingredientId);
    }

    public function getBoxUsageToday(int $boxId): int
    {
        return InventoryUsage::boxUsageToday($boxId);
    }
}