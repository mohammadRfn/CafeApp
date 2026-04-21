<?php

namespace Modules\OrderInvoice\app\Events;

use Modules\OrderInvoice\app\Models\Invoice;
use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Invoice Paid Event
 * 
 * فایر میشه وقتی Invoice پرداخت میشه
 * 
 * Listeners:
 * - CommitInventory: موجودی رو واقعاً کم میکنه
 * - UpdateOrderStatus: Order رو به paid تغییر میده
 */
class InvoicePaid
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Invoice $invoice,
        public Order $order,
        public string $paymentMethod
    ) {}
}