<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProductViewService;
use App\Modules\Catalog\Services\SearchAssistService;
use App\Modules\Catalog\Services\StockAlertService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductViewController extends Controller
{
    public function __construct(private readonly ProductViewService $views) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'guest_token' => ['nullable', 'string', 'min:8', 'max:64'],
        ]);

        $userId = $request->user()?->id;
        $guest = $userId ? null : ($data['guest_token'] ?? $request->header('X-Guest-Token'));

        $this->views->record(
            $userId,
            is_string($guest) ? $guest : null,
            (int) $data['product_id'],
            $request->header('X-Platform'),
        );

        return ApiResponse::success(['recorded' => true], 'Product view recorded');
    }

    public function recent(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $guest = $userId ? null : ($request->query('guest_token') ?? $request->header('X-Guest-Token'));

        $items = $this->views->recentlyViewed(
            $userId,
            is_string($guest) ? $guest : null,
        );

        return ApiResponse::success($items, 'Recently viewed products retrieved successfully');
    }
}
