<?php

use App\Modules\Promotion\Http\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::get('coupons', [CouponController::class, 'index']);
