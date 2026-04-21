<?php

namespace Modules\OrderInvoice\app\Events;

use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order Cancelled Event
 * 
 * فایر میشه وقتی Order لغو میشه (قبل پرداخت)
 * 
 * Listeners:
 * - ReleaseInventory: رزرو موجودی رو آزاد میکنه
 */
class OrderCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public ?string $reason = null
    ) {}
}