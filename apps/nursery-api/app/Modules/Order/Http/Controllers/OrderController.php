<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Services\CheckoutService;
use App\Modules\Order\Services\ReturnService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly ReturnService $returns,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
            'shipping_method_id' => ['required', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', 'string', 'in:razorpay,cod'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $data = $this->checkout->placeOrder(
            $user,
            $validated,
            $request->ip(),
            $request->header('X-Platform'),
            $request->attributes->get('request_id'),
        );

        return ApiResponse::success($data, 'Order created successfully', 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->checkout->listOrders(
            $user,
            $request->query('status'),
            $request->query('q'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success($result['data'], 'Orders retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->checkout->detail($user, $id),
            'Order retrieved successfully',
        );
    }

    public function tracking(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->checkout->tracking($user, $id),
            'Tracking information retrieved',
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_code' => [
                'nullable',
                'string',
                'in:changed_mind,ordered_by_mistake,better_price,delivery_slow,no_longer_needed,other',
            ],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->checkout->cancel(
                $user,
                $id,
                $validated['reason'] ?? null,
                $validated['reason_code'] ?? null,
            ),
            'Order cancelled successfully',
        );
    }

    public function reorder(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->checkout->reorder($user, $id),
            'Items added to cart',
        );
    }

    public function requestReturn(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->request($user, $id, $validated),
            'Return request submitted',
            201,
        );
    }

    public function returns(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->forOrder($user, $id),
            'Return information retrieved',
        );
    }

    public function showReturn(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->showForUser($user, $id),
            'Return retrieved successfully',
        );
    }
}
