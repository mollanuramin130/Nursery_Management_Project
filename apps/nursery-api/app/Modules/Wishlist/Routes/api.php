<?php

use App\Modules\Wishlist\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::get('wishlist/contains', [WishlistController::class, 'contains']);
    Route::post('wishlist', [WishlistController::class, 'store']);
    Route::post('wishlist/{productId}/move-to-cart', [WishlistController::class, 'moveToCart'])
        ->whereNumber('productId');
    Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy'])->whereNumber('productId');
});
