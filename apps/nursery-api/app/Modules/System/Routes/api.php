<?php

use App\Modules\System\Http\Controllers\ApiRootController;
use App\Modules\System\Http\Controllers\AppConfigController;
use App\Modules\System\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', ApiRootController::class);
Route::get('health', [HealthController::class, 'show'])->middleware('throttle:health');
Route::get('health/live', [HealthController::class, 'live'])->middleware('throttle:health');
Route::get('health/ready', [HealthController::class, 'ready'])->middleware('throttle:health');
Route::get('app/config', [AppConfigController::class, 'show']);
