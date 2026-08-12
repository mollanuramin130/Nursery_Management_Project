<?php

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

Route::middleware('optional.jwt')->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::put('cart/items/{id}', [CartController::class, 'updateItem'])->whereNumber('id');
    Route::delete('cart/items/{id}', [CartController::class, 'removeItem'])->whereNumber('id');
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/apply-coupon', [CartController::class, 'applyCoupon']);
    Route::delete('cart/coupon', [CartController::class, 'removeCoupon']);
});

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::post('cart/items/{id}/move-to-wishlist', [CartController::class, 'moveToWishlist'])
        ->whereNumber('id');
});
