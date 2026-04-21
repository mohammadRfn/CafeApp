<?php

use Illuminate\Support\Facades\Route;
use Modules\ItemMaker\Http\Controllers\ItemMakerController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::resource('itemmakers', ItemMakerController::class)->names('itemmaker');
});
