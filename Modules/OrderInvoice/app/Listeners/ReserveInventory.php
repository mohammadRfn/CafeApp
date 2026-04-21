<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\OrderConfirmed;
use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Reserve Inventory Listener
 * 
 * Listen to: OrderConfirmed
 * Action: رزرو موجودی برای سفارش
 */
class ReserveInventory
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
    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;

        try {
            // بررسی موجودی
            $availability = $this->inventoryService->checkOrderAvailability($order->id);

            if (!$availability['available']) {
                // اگه موجودی کافی نیست، Order رو به pending برمیگردونیم
                $order->update(['status' => 'pending']);

                Log::warning("Insufficient inventory for order {$order->order_number}", [
                    'order_id' => $order->id,
                    'shortages' => $availability,
                ]);

                return;
            }

            // رزرو موجودی
            $reserved = $this->inventoryService->reserveForOrder($order->id);

            if (!$reserved) {
                throw new \Exception('Failed to reserve inventory');
            }

            Log::info("Inventory reserved for order {$order->order_number}", [
                'order_id' => $order->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to reserve inventory for order {$order->order_number}: " . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            // برگردوندن Order به pending
            $order->update(['status' => 'pending']);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderConfirmed $event, \Throwable $exception): void
    {
        Log::error("ReserveInventory listener failed for order {$event->order->order_number}", [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}