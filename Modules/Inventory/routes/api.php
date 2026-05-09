<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryTransactionController;
use Modules\Inventory\Http\Controllers\PriceController;
use Modules\Inventory\Http\Controllers\StockManagementController;
use Modules\Inventory\Http\Controllers\ReportingController;

Route::middleware(['auth:api', 'role:admin,seniorBartisa'])->prefix('v1/inventory')->name('inventory.')->group(function () {

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('transactions', [InventoryTransactionController::class, 'createTransaction'])
            ->name('transactions.create');
        Route::get('transactions', [InventoryTransactionController::class, 'recent'])
            ->name('transactions.recent');
        Route::post('products/create', [InventoryTransactionController::class, 'createProduct'])->name('products.create');
        Route::post('products/stock', [InventoryTransactionController::class, 'createStock'])->name('products.stock');
        Route::prefix('boxes')->group(function () {
            Route::post('/', [InventoryTransactionController::class, 'createBox']);
            Route::post('stock', [InventoryTransactionController::class, 'createBoxStock']);
            Route::get('{boxId}/status', [StockManagementController::class, 'boxStatus']);
            Route::post('{boxId}/reserve', [StockManagementController::class, 'reserveBox']);
            Route::post('{boxId}/release', [StockManagementController::class, 'releaseBox']);
        });
    });

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('stock/{ingredientId}/reserve', [StockManagementController::class, 'reserve'])->name('stock.reserve');
        Route::post('stock/{ingredientId}/release', [StockManagementController::class, 'release'])->name('stock.release');
        // Route::post('stock/{boxId}/allocate', [StockManagementController::class, 'allocate'])->name('stock.allocate');
        Route::get('stock/{ingredientId}', [StockManagementController::class, 'status'])->name('stock.status');
    });

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('prices/update', [PriceController::class, 'updatePrice'])->name('prices.update');
        // Route::post('transactions/bulk', [InventoryTransactionController::class, 'bulkTransactions'])->name('transactions.bulk');
        Route::delete('transactions/{entityType}/{transactionId}/rollback', [InventoryTransactionController::class, 'rollback'])
            ->where(['entityType' => '(ingredient|box)'])
            ->name('transactions.rollback');
    });

    Route::middleware('throttle:120,1')->group(function () {
        Route::get('prices/{ingredientId}/{unitId}', [PriceController::class, 'current'])
        ->name('prices.current')
        ->whereNumber('ingredientId')
        ->whereNumber('unitId');
        Route::get('prices/{ingredientId}/history', [PriceController::class, 'history'])
        ->name('prices.history')
        ->whereNumber('ingredientId');

        // Route::get('prices/summary/{ingredientId}', [PriceController::class, 'summary'])
        //     ->name('prices.summary')
        //     ->whereNumber('ingredientId');

        Route::get('reports/low-stock', [ReportingController::class, 'lowStock'])->name('reports.low-stock');
        Route::get('reports/inventory-value', [ReportingController::class, 'inventoryValue'])->name('reports.inventory-value');
        Route::get('reports/movement', [ReportingController::class, 'stockMovement'])->name('reports.movement');
        // Route::get('reports/abc-analysis', [ReportingController::class, 'abcAnalysis'])->name('reports.abc');
        Route::get('ingredients', [InventoryTransactionController::class, 'listIngredients'])
            ->name('ingredients.list');

        Route::get('boxes', [InventoryTransactionController::class, 'listBoxes'])
            ->name('boxes.list');
    });
});
