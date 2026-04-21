<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\OrderRefunded;
use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Rollback Inventory Listener
 * 
 * Listen to: OrderRefunded
 * Action: برگشت موجودی (بسته به type)
 */
class RollbackInventory
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected InventoryIntegrationServiceInterface $inventoryService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderRefunded $event): void
    {
        $order = $event->order;
        $refundType = $event->refundType;

        try {
            $rolledBack = match($refundType) {
                'consumed' => $this->inventoryService->rollbackConsumed($order->id),
                'returned' => $this->inventoryService->rollbackReturned($order->id),
                default => throw new \InvalidArgumentException("Invalid refund type: {$refundType}"),
            };

            if (!$rolledBack) {
                throw new \Exception("Failed to rollback inventory ({$refundType})");
            }

            Log::info("Inventory rolled back ({$refundType}) for refunded order {$order->order_number}", [
                'order_id' => $order->id,
                'refund_type' => $refundType,
                'reason' => $event->reason,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to rollback inventory for order {$order->order_number}: " . $e->getMessage(), [
                'order_id' => $order->id,
                'refund_type' => $refundType,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderRefunded $event, \Throwable $exception): void
    {
        Log::critical("RollbackInventory listener failed for order {$event->order->order_number}", [
            'order_id' => $event->order->id,
            'refund_type' => $event->refundType,
            'error' => $exception->getMessage(),
        ]);
    }
}