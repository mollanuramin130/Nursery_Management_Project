<?php

use App\Modules\Notification\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('devices/register', [NotificationController::class, 'registerDevice']);
    Route::post('devices/deactivate', [NotificationController::class, 'deactivateDevice']);
});
