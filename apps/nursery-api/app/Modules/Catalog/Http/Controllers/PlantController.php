<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlantController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q', 'category', 'brand', 'min_price', 'max_price',
            'indoor_outdoor', 'sunlight', 'water_requirement', 'difficulty_level',
            'season', 'availability', 'min_rating', 'on_sale', 'pet_safety', 'tag',
            'sort', 'page', 'per_page',
        ]);
        $filters['product_types'] = ['plant', 'tree', 'seed'];

        $paginator = $this->products->list($filters);
        $data = collect($paginator->items())->map(fn ($p) => ProductPresenter::card($p))->values()->all();

        return ApiResponse::success($data, 'Plants retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        $product = $this->products->findByIdOrSlug($idOrSlug);

        if (! in_array($product->product_type, ['plant', 'tree', 'seed'], true)) {
            throw new NotFoundHttpException('Plant not found');
        }

        return ApiResponse::success(
            ProductPresenter::detail($product),
            'Product retrieved successfully',
        );
    }

    public function care(string $idOrSlug): JsonResponse
    {
        $product = $this->products->findByIdOrSlug($idOrSlug);
        $care = ProductPresenter::care($product);

        if ($care === []) {
            throw new NotFoundHttpException('Plant care not found');
        }

        return ApiResponse::success($care, 'Plant care retrieved successfully');
    }
}
