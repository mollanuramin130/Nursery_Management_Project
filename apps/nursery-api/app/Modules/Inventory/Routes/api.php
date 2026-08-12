<?php

use App\Modules\Inventory\Http\Controllers\AdminInventoryController;
use App\Modules\Inventory\Http\Controllers\AdminWarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('inventory/dashboard', [AdminInventoryController::class, 'dashboard'])
        ->middleware('permission:inventory.view');
    Route::get('inventory', [AdminInventoryController::class, 'index'])
        ->middleware('permission:inventory.view');
    Route::get('inventory/reorder-suggestions', [AdminInventoryController::class, 'reorderSuggestions'])
        ->middleware('permission:inventory.view');
    Route::get('inventory/dead-stock', [AdminInventoryController::class, 'deadStock'])
        ->middleware('permission:inventory.view');
    Route::get('inventory/movements', [AdminInventoryController::class, 'movements'])
        ->middleware('permission:inventory.view');

    Route::get('inventory/transfers', [AdminInventoryController::class, 'transfers'])
        ->middleware('permission:inventory.view,inventory.transfer');
    Route::post('inventory/transfers', [AdminInventoryController::class, 'transferStore'])
        ->middleware('permission:inventory.transfer,inventory.adjust');
    Route::get('inventory/transfers/{id}', [AdminInventoryController::class, 'transferShow'])
        ->whereNumber('id')
        ->middleware('permission:inventory.view,inventory.transfer');
    Route::post('inventory/transfers/{id}/ship', [AdminInventoryController::class, 'transferShip'])
        ->whereNumber('id')
        ->middleware('permission:inventory.transfer,inventory.adjust');
    Route::post('inventory/transfers/{id}/complete', [AdminInventoryController::class, 'transferComplete'])
        ->whereNumber('id')
        ->middleware('permission:inventory.transfer,inventory.adjust');
    Route::post('inventory/transfers/{id}/cancel', [AdminInventoryController::class, 'transferCancel'])
        ->whereNumber('id')
        ->middleware('permission:inventory.transfer,inventory.adjust');

    Route::get('inventory/{id}', [AdminInventoryController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:inventory.view');
    Route::post('inventory/adjust', [AdminInventoryController::class, 'adjust'])
        ->middleware('permission:inventory.adjust');
    Route::post('inventory/reconcile', [AdminInventoryController::class, 'reconcile'])
        ->middleware('permission:inventory.adjust');
    Route::put('inventory/{id}/threshold', [AdminInventoryController::class, 'updateThreshold'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust');

    Route::get('warehouses', [AdminWarehouseController::class, 'index'])
        ->middleware('permission:inventory.view,warehouses.view');
    Route::post('warehouses', [AdminWarehouseController::class, 'store'])
        ->middleware('permission:inventory.adjust,warehouses.manage');
    Route::put('warehouses/{id}', [AdminWarehouseController::class, 'update'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,warehouses.manage');
});
