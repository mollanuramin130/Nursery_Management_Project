<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminProductService;
use App\Modules\Auth\Models\User;
use App\Shared\Rules\SecureImageUrl;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(private readonly AdminProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->products->list(
            $request->query('status'),
            $request->query('q'),
            min(100, max(1, (int) $request->query('per_page', 20))),
        );

        return ApiResponse::success($result['data'], 'Products retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->products->detail($id),
            'Product retrieved successfully',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'sku' => ['required', 'string', 'max:80', 'unique:products,sku'],
            'slug' => ['nullable', 'string', 'max:220', 'unique:products,slug'],
            'product_type' => ['required', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,active,archived'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:80'],
            'description' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.warehouse_id' => ['required_with:inventory', 'integer', 'exists:warehouses,id'],
            'inventory.qty_on_hand' => ['nullable', 'integer', 'min:0'],
            'inventory.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'plant' => ['nullable', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->products->create($validated, $user->id),
            'Product created successfully',
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'sku' => ['sometimes', 'string', 'max:80', 'unique:products,sku,'.$id],
            'slug' => ['sometimes', 'string', 'max:220', 'unique:products,slug,'.$id],
            'product_type' => ['sometimes', 'string', 'max:40'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:draft,active,archived'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:80'],
            'description' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_new' => ['sometimes', 'boolean'],
            'plant' => ['nullable', 'array'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->products->update($id, $validated, $user->id),
            'Product updated successfully',
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->products->delete($id, $user->id);

        return ApiResponse::success(null, 'Product deleted successfully');
    }

    public function images(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*.url' => ['required', new SecureImageUrl],
            'images.*.alt' => ['nullable', 'string', 'max:200'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->products->addImages($id, $validated['images'], $user->id),
            'Product images added',
            201,
        );
    }
}
