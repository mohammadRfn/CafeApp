<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\InvoicePaid;
use Illuminate\Support\Facades\Log;

/**
 * Update Order Status Listener
 * 
 * Listen to: InvoicePaid
 * Action: Order رو به 'paid' تغییر میده
 */
class UpdateOrderStatus
{
    /**
     * Handle the event.
     */
    public function __invoke(InvoicePaid $event): void
    {
        $order = $event->order;

        try {
            Log::info("UpdateOrderStatus: STARTED", [
                'order_id' => $order->id,
                'current_status' => $order->status,
            ]);

            $result = $order->markAsPaid();

            Log::info("UpdateOrderStatus: markAsPaid result", [
                'order_id' => $order->id,
                'result' => $result,
                'new_status' => $order->status,
                'paid_at' => $order->paid_at,
            ]);

            if (!$result) {
                throw new \Exception("markAsPaid returned false");
            }

            Log::info("UpdateOrderStatus: SUCCESS", [
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error("UpdateOrderStatus: FAILED", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
