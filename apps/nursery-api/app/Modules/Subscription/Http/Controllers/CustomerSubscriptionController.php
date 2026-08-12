<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->subscriptions->listCustomer(
            $user,
            $request->query('status'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success($result['data'], 'Subscriptions retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->subscriptions->showCustomer($user, $id), 'Subscription retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'plan_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'frequency' => ['nullable', 'string'],
            'address_id' => ['required', 'integer'],
            'shipping_method_id' => ['nullable', 'integer'],
            'payment_method' => ['required', 'string', 'in:razorpay,cod'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $data = $this->subscriptions->create(
            $user,
            $payload,
            $request->ip(),
            $request->header('X-Platform'),
            $request->header('X-Request-Id'),
        );

        return ApiResponse::success($data, 'Subscription created', 201);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->pause($user, $id, $payload['reason'] ?? null),
            'Subscription paused',
        );
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->subscriptions->resume($user, $id), 'Subscription resumed');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->cancel($user, $id, $payload['reason'] ?? null),
            'Subscription cancelled',
        );
    }

    public function changeQuantity(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:50']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->changeQuantity($user, $id, (int) $payload['quantity']),
            'Quantity updated',
        );
    }

    public function changeAddress(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['address_id' => ['required', 'integer']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->changeAddress($user, $id, (int) $payload['address_id']),
            'Address updated',
        );
    }

    public function plansForProduct(Request $request, int $productId): JsonResponse
    {
        return ApiResponse::success(
            $this->subscriptions->listPublicPlansForProduct($productId),
            'Subscription plans retrieved',
        );
    }
}
