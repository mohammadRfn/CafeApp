<?php

use Illuminate\Support\Facades\Route;
use Modules\UserCreator\Http\Controllers\UserCreatorController;

Route::middleware(['auth:api', 'role:admin'])->prefix('v1/UserCreator')->name('UserCreator')->group(function () {
    Route::post('/',[UserCreatorController::class, 'store'])->name('store');
    Route::get('/', [UserCreatorController::class, 'index'])->name('index');
    Route::delete('{id}', [UserCreatorController::class, 'destroy'])->name('destroy');
});
