<?php

use Illuminate\Support\Facades\Route;
use Modules\OrderInvoice\Http\Controllers\OrderInvoiceController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::resource('orderinvoices', OrderInvoiceController::class)->names('orderinvoice');
});
