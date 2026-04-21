<?php

namespace Modules\OrderInvoice\app\Events;

use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order Refunded Event
 * 
 * فایر میشه وقتی Order برگشت میخوره (refund)
 * 
 * Listeners:
 * - RollbackInventory: موجودی رو برمیگردونه (اگه type = 'returned')
 * - RefundInvoice: Invoice رو به refunded تغییر میده
 */
class OrderRefunded
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public string $refundType, // 'consumed' یا 'returned'
        public ?string $reason = null
    ) {}
}