<?php

use Illuminate\Support\Facades\Route;
use Modules\OrderInvoice\app\Http\Controllers\OrderController;
use Modules\OrderInvoice\app\Http\Controllers\InvoiceController;



Route::middleware(['auth:api', 'role:admin,barista,seniorBarista'])->prefix('v1/orders')->name('orders.')->group(function () {

    // ─────────────────────────────────────────────────────
    // READ Operations (120 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('{id}', [OrderController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('statistics/summary', [OrderController::class, 'statistics'])->name('statistics');
        Route::get('today/list', [OrderController::class, 'today'])->name('today');
        Route::get('{id}/check-availability', [OrderController::class, 'checkAvailability'])->name('check-availability')->whereNumber('id');
    });

    // ─────────────────────────────────────────────────────
    // WRITE Operations (60 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{id}', [OrderController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('{id}', [OrderController::class, 'destroy'])->name('destroy')->whereNumber('id');

        // Order Items
        Route::post('{id}/items', [OrderController::class, 'addItem'])->name('items.add')->whereNumber('id');
        Route::delete('{id}/items/{itemId}', [OrderController::class, 'removeItem'])->name('items.remove')->whereNumber('id')->whereNumber('itemId');
        Route::patch('{id}/items/{itemId}', [OrderController::class, 'updateItemQuantity'])->name('items.update-quantity')->whereNumber('id')->whereNumber('itemId');

        // Pricing
        Route::post('{id}/discount', [OrderController::class, 'applyDiscount'])->name('discount.apply')->whereNumber('id');
        Route::post('{id}/tax', [OrderController::class, 'applyTax'])->name('tax.apply')->whereNumber('id');
        Route::post('{id}/delivery-fee', [OrderController::class, 'setDeliveryFee'])->name('delivery-fee.set')->whereNumber('id');
    });

    // ─────────────────────────────────────────────────────
    // CRITICAL Operations (30 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('{id}/confirm', [OrderController::class, 'confirm'])->name('confirm')->whereNumber('id');
        Route::post('{id}/cancel', [OrderController::class, 'cancel'])->name('cancel')->whereNumber('id');
        Route::post('{id}/complete', [OrderController::class, 'complete'])->name('complete')->whereNumber('id');
        Route::post('{id}/refund', [OrderController::class, 'refund'])->name('refund')->whereNumber('id');
    });
});

// ═══════════════════════════════════════════════════════════
// 💰 Invoices Routes
// ═══════════════════════════════════════════════════════════

Route::middleware(['auth:api', 'role:admin,barista'])->prefix('v1/invoices')->name('invoices.')->group(function () {

    // ─────────────────────────────────────────────────────
    // READ Operations (120 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('{id}', [InvoiceController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('unpaid/list', [InvoiceController::class, 'unpaid'])->name('unpaid');
        Route::get('daily-revenue/report', [InvoiceController::class, 'dailyRevenue'])->name('daily-revenue');
        Route::get('revenue/report', [InvoiceController::class, 'revenue'])->name('revenue');
        Route::get('statistics/summary', [InvoiceController::class, 'statistics'])->name('statistics');
        Route::get('today/list', [InvoiceController::class, 'today'])->name('today');
        Route::get('today/paid', [InvoiceController::class, 'todayPaid'])->name('today-paid');
    });

    // ─────────────────────────────────────────────────────
    // WRITE Operations (60 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('generate', [InvoiceController::class, 'generate'])->name('generate');
    });

    // ─────────────────────────────────────────────────────
    // CRITICAL Operations (30 requests/minute)
    // ─────────────────────────────────────────────────────
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('{id}/pay', [InvoiceController::class, 'pay'])->name('pay')->whereNumber('id');
        Route::delete('{id}', [InvoiceController::class, 'destroy'])->name('destroy')->whereNumber('id');
        Route::post('{id}/refund', [InvoiceController::class, 'refund'])->name('refund')->whereNumber('id');
    });
});
