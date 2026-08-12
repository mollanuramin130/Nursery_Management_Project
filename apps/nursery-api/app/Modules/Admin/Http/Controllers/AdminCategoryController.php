<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Category::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Category $c) => [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'name' => $c->name,
            'slug' => $c->slug,
            'image_url' => $c->image_url,
            'sort_order' => $c->sort_order,
            'status' => $c->status,
        ])->values()->all();

        return ApiResponse::success($rows, 'Categories retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::query()->create([
            ...$validated,
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'status' => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('category.create', 'category', $category->id, null, $category->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
        ], 'Category created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::query()->find($id);
        if (! $category) {
            throw new NotFoundHttpException('Category not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'slug' => ['sometimes', 'string', 'max:180', 'unique:categories,slug,'.$id],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ]);

        $before = $category->toArray();
        $category->fill($validated)->save();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('category.update', 'category', $id, $before, $category->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'status' => $category->status,
        ], 'Category updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::query()->find($id);
        if (! $category) {
            throw new NotFoundHttpException('Category not found');
        }

        $before = $category->toArray();
        $category->delete();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('category.delete', 'category', $id, $before, null, $user->id);

        return ApiResponse::success(null, 'Category deleted successfully');
    }
}
