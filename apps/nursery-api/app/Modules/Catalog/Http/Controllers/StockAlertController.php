<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\StockAlertService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function __construct(private readonly StockAlertService $alerts) {}

    public function store(Request $request, int $id): JsonResponse
    {
        $data = $this->alerts->subscribe((int) $request->user()->id, $id);

        return ApiResponse::success($data, 'You will be notified when this product is back in stock', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->alerts->unsubscribe((int) $request->user()->id, $id);

        return ApiResponse::success(null, 'Stock alert cancelled');
    }

    public function status(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->alerts->statusForUser((int) $request->user()->id, $id),
            'Stock alert status retrieved successfully',
        );
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->alerts->listForUser((int) $request->user()->id),
            'Stock alerts retrieved successfully',
        );
    }
}
