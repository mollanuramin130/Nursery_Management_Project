<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ShippingController extends Controller
{
    public function methods(): JsonResponse
    {
        $data = ShippingMethod::query()->active()->orderBy('price')->get()
            ->map(fn (ShippingMethod $m) => $m->toApiArray())
            ->values()
            ->all();

        return ApiResponse::success($data, 'Shipping methods retrieved successfully');
    }
}
