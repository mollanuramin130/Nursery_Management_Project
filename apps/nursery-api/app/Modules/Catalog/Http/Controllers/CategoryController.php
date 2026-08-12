<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\CategoryService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        $parentId = $request->filled('parent_id') ? (int) $request->query('parent_id') : null;
        $flat = filter_var($request->query('flat', false), FILTER_VALIDATE_BOOLEAN);

        return ApiResponse::success(
            $this->categories->list($parentId, $flat),
            'Categories retrieved successfully',
        );
    }

    public function show(string $slug): JsonResponse
    {
        return ApiResponse::success(
            $this->categories->findBySlug($slug),
            'Category retrieved successfully',
        );
    }
}
