<?php

use App\Modules\Payment\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::post('payments/initiate', [PaymentController::class, 'initiate'])->middleware('throttle:20,1');
    Route::post('payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1');
    Route::get('payments/{id}', [PaymentController::class, 'status'])->whereNumber('id');
    Route::post('orders/{orderId}/retry-payment', [PaymentController::class, 'retry'])
        ->whereNumber('orderId')
        ->middleware('throttle:10,1');
});

Route::post('payments/webhooks/{provider}', [PaymentController::class, 'webhook'])
    ->middleware('throttle:120,1');
