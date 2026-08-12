<?php

use App\Modules\Loyalty\Http\Controllers\CustomerLoyaltyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->prefix('customer')->group(function () {
    Route::get('loyalty', [CustomerLoyaltyController::class, 'show']);
    Route::get('loyalty/transactions', [CustomerLoyaltyController::class, 'history']);
});
