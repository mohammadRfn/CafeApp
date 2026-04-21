<?php

namespace Modules\OrderInvoice\app\Listeners;

use Modules\OrderInvoice\app\Events\InvoicePaid;
use Modules\OrderInvoice\app\Interfaces\InventoryIntegrationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Commit Inventory Listener
 * 
 * Listen to: InvoicePaid
 * Action: کم کردن واقعی موجودی (commit)
 */
class CommitInventory
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
    public function __invoke(InvoicePaid $event): void
    {
        $order = $event->order;

        try {
            Log::info("CommitInventory: STARTED", [
                'order_id' => $order->id,
                'invoice_id' => $event->invoice->id,
            ]);

            // کم کردن واقعی موجودی
            $committed = $this->inventoryService->commitInventory($order->id);

            Log::info("CommitInventory: commitInventory result", [
                'order_id' => $order->id,
                'result' => $committed,
            ]);

            if (!$committed) {
                throw new \Exception('commitInventory returned false');
            }

            Log::info("CommitInventory: SUCCESS", [
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error("CommitInventory: FAILED", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(InvoicePaid $event, \Throwable $exception): void
    {
        Log::critical("CommitInventory listener failed for order {$event->order->order_number}", [
            'order_id' => $event->order->id,
            'invoice_id' => $event->invoice->id,
            'error' => $exception->getMessage(),
        ]);

        // این خیلی critical هست! باید manual check بشه
    }
}
