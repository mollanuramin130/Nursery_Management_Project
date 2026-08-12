<?php

use App\Modules\System\Http\Controllers\ApiRootController;
use App\Modules\System\Http\Controllers\AppConfigController;
use App\Modules\System\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', ApiRootController::class);
Route::get('health', [HealthController::class, 'show'])->middleware('throttle:60,1');
Route::get('health/live', [HealthController::class, 'live'])->middleware('throttle:120,1');
Route::get('health/ready', [HealthController::class, 'ready'])->middleware('throttle:60,1');
Route::get('app/config', [AppConfigController::class, 'show']);
