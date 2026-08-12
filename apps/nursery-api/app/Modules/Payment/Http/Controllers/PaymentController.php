<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Payment\Services\PaymentService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'method' => ['nullable', 'string', 'in:razorpay'],
            'return_url' => ['nullable', 'url'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->payments->initiate($user, $validated),
            'Payment initiated',
        );
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'integer'],
            'provider_payment_id' => ['required', 'string'],
            'provider_order_id' => ['required', 'string'],
            'provider_signature' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->payments->verify($user, $validated),
            'Payment verified and order confirmed',
        );
    }

    public function status(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->payments->status($user, $id),
            'Payment status retrieved',
        );
    }

    public function retry(Request $request, int $orderId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->payments->retry($user, $orderId),
            'Payment retry initiated',
        );
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');

        return ApiResponse::success(
            $this->payments->handleWebhook($provider, $request->all(), $signature),
            'Webhook processed',
        );
    }
}
