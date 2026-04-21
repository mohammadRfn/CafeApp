<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\OrderCancelled;
use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Release Inventory Listener
 * 
 * Listen to: OrderCancelled
 * Action: آزادسازی رزرو موجودی
 */
class ReleaseInventory
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
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        try {
            // آزادسازی رزرو
            $released = $this->inventoryService->releaseReservation($order->id);

            if (!$released) {
                throw new \Exception('Failed to release inventory reservation');
            }

            Log::info("Inventory reservation released for cancelled order {$order->order_number}", [
                'order_id' => $order->id,
                'reason' => $event->reason,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to release inventory for order {$order->order_number}: " . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCancelled $event, \Throwable $exception): void
    {
        Log::error("ReleaseInventory listener failed for order {$event->order->order_number}", [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}