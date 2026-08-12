<?php

use App\Modules\Delivery\Http\Controllers\ShippingController;
use Illuminate\Support\Facades\Route;

Route::middleware('optional.jwt')->group(function () {
    Route::get('shipping/methods', [ShippingController::class, 'methods']);
});
