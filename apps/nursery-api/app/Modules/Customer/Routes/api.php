<?php

use App\Modules\Customer\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->prefix('customer')->group(function () {
    Route::put('profile', [CustomerController::class, 'updateProfile']);
    Route::get('preferences', [CustomerController::class, 'getPreferences']);
    Route::put('preferences', [CustomerController::class, 'updatePreferences']);
    Route::get('returns', [CustomerController::class, 'listReturns']);
    Route::get('addresses', [CustomerController::class, 'listAddresses']);
    Route::post('addresses', [CustomerController::class, 'storeAddress']);
    Route::put('addresses/{id}', [CustomerController::class, 'updateAddress'])->whereNumber('id');
    Route::delete('addresses/{id}', [CustomerController::class, 'destroyAddress'])->whereNumber('id');
});
