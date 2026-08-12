<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\StockAlertSubscription;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStockAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $query = StockAlertSubscription::query()
            ->with(['product:id,name,sku,slug,stock_status', 'user:id,name,email'])
            ->orderByDesc('updated_at');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $total = $query->count();
        $rows = $query->forPage($page, $perPage)->get()->map(fn (StockAlertSubscription $s) => [
            'id' => $s->id,
            'status' => $s->status,
            'product_id' => $s->product_id,
            'product_name' => $s->product?->name,
            'product_sku' => $s->product?->sku,
            'product_stock_status' => $s->product?->stock_status,
            'user_id' => $s->user_id,
            'user_email' => $s->user?->email,
            'notified_at' => optional($s->notified_at)?->toIso8601String(),
            'created_at' => optional($s->created_at)?->toIso8601String(),
        ])->values()->all();

        return ApiResponse::success($rows, 'Stock alerts retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}
