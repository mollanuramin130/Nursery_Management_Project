<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Modules\Catalog\Support\SearchEventRecorder;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q', 'category', 'product_type', 'brand', 'min_price', 'max_price',
            'indoor_outdoor', 'sunlight', 'water_requirement', 'difficulty_level',
            'season', 'availability', 'min_rating', 'on_sale', 'pet_safety', 'tag',
            'sort', 'page', 'per_page',
        ]);

        $paginator = $this->products->list($filters);
        $data = collect($paginator->items())->map(fn ($p) => ProductPresenter::card($p))->values()->all();

        SearchEventRecorder::record($request, (string) ($filters['q'] ?? ''), (int) $paginator->total());

        return ApiResponse::success($data, 'Products retrieved successfully', 200, [
            'query' => $filters['q'] ?? null,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
