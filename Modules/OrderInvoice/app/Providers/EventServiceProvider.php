<?php

namespace Modules\OrderInvoice\Providers;
use Modules\OrderInvoice\app\Events\OrderConfirmed;
use Modules\OrderInvoice\app\Events\OrderCancelled;
use Modules\OrderInvoice\app\Events\OrderRefunded;
use Modules\OrderInvoice\app\Events\InvoicePaid;
use Modules\OrderInvoice\app\Listeners\ReserveInventory;
use Modules\OrderInvoice\app\Listeners\ReleaseInventory;
use Modules\OrderInvoice\app\Listeners\CommitInventory;
use Modules\OrderInvoice\app\Listeners\UpdateOrderStatus;
use Modules\OrderInvoice\app\Listeners\RollbackInventory;
use Modules\OrderInvoice\app\Listeners\RefundInvoice;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // OrderConfirmed::class => [
        //     ReserveInventory::class,
        // ],
        
        // OrderCancelled::class => [
        //     ReleaseInventory::class,
        // ],
        
        InvoicePaid::class => [
            UpdateOrderStatus::class,  // ✅ اول این
            CommitInventory::class,     // ✅ بعد این
        ],
        
        OrderRefunded::class => [
            RollbackInventory::class,
            RefundInvoice::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
