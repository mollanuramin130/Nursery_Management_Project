<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\Role;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Read-only role catalog for Admin Users & Roles UI (QA-08).
 * Role CRUD is not supported — assignment uses existing user update API.
 */
class AdminRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,slug,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('slug')->values()->all(),
            ])
            ->values()
            ->all();

        return ApiResponse::success($roles, 'Roles retrieved successfully');
    }
}
