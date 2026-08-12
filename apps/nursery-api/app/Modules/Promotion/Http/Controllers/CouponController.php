<?php

namespace App\Modules\Promotion\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Promotion\Models\Coupon;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Coupon::query()
            ->where('status', 'active')
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('code')
            ->get()
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'discount_type' => $c->discount_type,
                'discount_value' => (float) $c->discount_value,
                'min_order_amount' => $c->min_order_amount !== null ? (float) $c->min_order_amount : null,
                'max_discount_amount' => $c->max_discount_amount !== null ? (float) $c->max_discount_amount : null,
                'ends_at' => optional($c->ends_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success($data, 'Coupons retrieved successfully');
    }
}
