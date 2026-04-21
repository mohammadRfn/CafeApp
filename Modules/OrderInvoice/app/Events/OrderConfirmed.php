<?php

namespace Modules\OrderInvoice\app\Events;

use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order Confirmed Event
 * 
 * فایر میشه وقتی Order تایید میشه (draft → confirmed)
 * 
 * Listeners:
 * - ReserveInventory: موجودی رو رزرو میکنه
 */
class OrderConfirmed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order
    ) {}
}