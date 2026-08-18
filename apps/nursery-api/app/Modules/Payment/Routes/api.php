<?php

use App\Modules\Payment\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::post('payments/initiate', [PaymentController::class, 'initiate'])->middleware('throttle:payment-write');
    Route::post('payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:payment-verify');
    Route::get('payments/{id}', [PaymentController::class, 'status'])->whereNumber('id');
    Route::post('orders/{orderId}/retry-payment', [PaymentController::class, 'retry'])
        ->whereNumber('orderId')
        ->middleware('throttle:payment-retry');
});

Route::post('payments/webhooks/{provider}', [PaymentController::class, 'webhook'])
    ->middleware('throttle:webhook');
