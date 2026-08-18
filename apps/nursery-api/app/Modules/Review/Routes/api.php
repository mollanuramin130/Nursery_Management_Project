<?php

use App\Modules\Review\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('products/{id}/reviews', [ReviewController::class, 'index'])->whereNumber('id');

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('customer/reviews', [ReviewController::class, 'mine']);
    Route::get('products/{id}/review-eligibility', [ReviewController::class, 'eligibility'])->whereNumber('id');
    Route::post('products/{id}/reviews', [ReviewController::class, 'store'])->whereNumber('id')->middleware('throttle:review-write');
});
