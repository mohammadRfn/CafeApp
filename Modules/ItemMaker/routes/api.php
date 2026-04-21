<?php

use Illuminate\Support\Facades\Route;
use Modules\ItemMaker\Http\Controllers\ItemController;

Route::middleware(['auth:api', 'role:admin,seniorBarista'])->prefix('v1/items')->name('items.')->group(function () {

    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/', [ItemController::class, 'index'])->name('index');
        Route::get('{id}', [ItemController::class, 'show'])->name('show')->whereNumber('id');
        Route::get('active/list', [ItemController::class, 'active'])->name('active');
        Route::get('featured/list', [ItemController::class, 'featured'])->name('featured');
        Route::get('available/list', [ItemController::class, 'available'])->name('available');
        Route::get('categories/list', [ItemController::class, 'categories'])->name('categories');
        Route::get('statistics/summary', [ItemController::class, 'statistics'])->name('statistics');
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/', [ItemController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '{id}', [ItemController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('{id}', [ItemController::class, 'destroy'])->name('destroy')->whereNumber('id');
        Route::post('{id}/restore', [ItemController::class, 'restore'])->name('restore')->whereNumber('id');
        Route::post('{id}/duplicate', [ItemController::class, 'duplicate'])->name('duplicate')->whereNumber('id');
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('{id}/check-availability', [ItemController::class, 'checkAvailability'])->name('check-availability')->whereNumber('id');
        Route::patch('{id}/toggle-active', [ItemController::class, 'toggleActive'])->name('toggle-active')->whereNumber('id');
    });

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('{id}/recalculate-cost', [ItemController::class, 'recalculateCost'])->name('recalculate-cost')->whereNumber('id');
    });
});