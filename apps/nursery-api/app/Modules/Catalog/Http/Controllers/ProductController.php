<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Modules\Catalog\Support\SearchEventRecorder;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q', 'category', 'product_type', 'brand', 'min_price', 'max_price',
            'indoor_outdoor', 'sunlight', 'water_requirement', 'difficulty_level',
            'season', 'availability', 'min_rating', 'on_sale', 'pet_safety', 'tag',
            'sort', 'page', 'per_page',
        ]);

        $paginator = $this->products->list($filters);
        $data = collect($paginator->items())->map(fn ($p) => ProductPresenter::card($p))->values()->all();

        $applied = collect($filters)->except(['page', 'per_page', 'sort'])->filter()->all();

        SearchEventRecorder::record($request, (string) ($filters['q'] ?? ''), (int) $paginator->total());

        return ApiResponse::success($data, 'Products retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters_applied' => $applied,
        ]);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        $product = $this->products->findByIdOrSlug($idOrSlug);

        return ApiResponse::success(
            ProductPresenter::detail($product),
            'Product retrieved successfully',
        );
    }

    public function related(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->products->related($id),
            'Related products retrieved successfully',
        );
    }

    public function recommendations(Request $request, int $id): JsonResponse
    {
        $type = $request->query('type', 'similar');
        $data = $this->products->recommendations($id, $type);

        return ApiResponse::success($data, 'Recommendations retrieved successfully', 200, [
            'recommendation_type' => $type,
        ]);
    }
}
