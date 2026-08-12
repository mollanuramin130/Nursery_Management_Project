<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Services\WarehouseService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouses) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->warehouses->list(), 'Warehouses retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:warehouses,code'],
            'name' => ['required', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->warehouses->create($validated, $user->id),
            'Warehouse created successfully',
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:warehouses,code,'.$id],
            'name' => ['sometimes', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->warehouses->update($id, $validated, $user->id),
            'Warehouse updated successfully',
        );
    }
}
