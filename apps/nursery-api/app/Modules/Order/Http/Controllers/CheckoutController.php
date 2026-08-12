<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Services\CheckoutService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
            'shipping_method_id' => ['nullable', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->checkout->preview($user, $validated),
            'Checkout preview generated',
        );
    }
}
