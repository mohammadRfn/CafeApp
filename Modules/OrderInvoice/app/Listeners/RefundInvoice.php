<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\OrderRefunded;
use Illuminate\Support\Facades\Log;

/**
 * Refund Invoice Listener
 * 
 * Listen to: OrderRefunded
 * Action: Invoice رو به 'refunded' تغییر میده
 */
class RefundInvoice
{
    /**
     * Handle the event.
     */
    public function handle(OrderRefunded $event): void
    {
        $order = $event->order;

        try {
            // اگه Invoice داره، refund کن
            if ($invoice = $order->invoice) {
                $invoice->refund();

                Log::info("Invoice refunded for order {$order->order_number}", [
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'refund_type' => $event->refundType,
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to refund invoice for order {$order->order_number}: " . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}