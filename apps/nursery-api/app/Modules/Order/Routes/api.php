<?php

use App\Modules\Order\Http\Controllers\CheckoutController;
use App\Modules\Order\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::post('checkout/preview', [CheckoutController::class, 'preview'])->middleware('throttle:checkout-preview');
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:order-write');
    Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::get('orders/{id}/tracking', [OrderController::class, 'tracking'])->whereNumber('id');
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id')->middleware('throttle:order-write');
    Route::post('orders/{id}/reorder', [OrderController::class, 'reorder'])->whereNumber('id')->middleware('throttle:order-write');
    Route::get('orders/{id}/returns', [OrderController::class, 'returns'])->whereNumber('id');
    Route::post('orders/{id}/returns', [OrderController::class, 'requestReturn'])->whereNumber('id')->middleware('throttle:order-return');
    Route::get('returns/{id}', [OrderController::class, 'showReturn'])->whereNumber('id');
});
