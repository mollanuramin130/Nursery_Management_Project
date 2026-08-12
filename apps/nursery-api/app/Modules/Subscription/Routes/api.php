<?php

use App\Modules\Subscription\Http\Controllers\CustomerSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('products/{productId}/subscription-plans', [CustomerSubscriptionController::class, 'plansForProduct'])
    ->whereNumber('productId');

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('subscriptions', [CustomerSubscriptionController::class, 'index']);
    Route::post('subscriptions', [CustomerSubscriptionController::class, 'store']);
    Route::get('subscriptions/{id}', [CustomerSubscriptionController::class, 'show'])->whereNumber('id');
    Route::post('subscriptions/{id}/pause', [CustomerSubscriptionController::class, 'pause'])->whereNumber('id');
    Route::post('subscriptions/{id}/resume', [CustomerSubscriptionController::class, 'resume'])->whereNumber('id');
    Route::post('subscriptions/{id}/cancel', [CustomerSubscriptionController::class, 'cancel'])->whereNumber('id');
    Route::post('subscriptions/{id}/change-quantity', [CustomerSubscriptionController::class, 'changeQuantity'])->whereNumber('id');
    Route::post('subscriptions/{id}/change-address', [CustomerSubscriptionController::class, 'changeAddress'])->whereNumber('id');
});
