<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->subscriptions->dashboard(), 'Subscription dashboard');
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->subscriptions->adminList([
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
            'product_id' => $request->query('product_id'),
            'frequency' => $request->query('frequency'),
            'q' => $request->query('q'),
        ], (int) $request->query('per_page', 30));

        return ApiResponse::success($result['data'], 'Subscriptions retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->subscriptions->adminShow($id), 'Subscription retrieved');
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->adminPause($user, $id, $payload['reason'] ?? null),
            'Subscription paused',
        );
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->subscriptions->adminResume($user, $id), 'Subscription resumed');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->subscriptions->adminCancel($user, $id, $payload['reason'] ?? null),
            'Subscription cancelled',
        );
    }

    public function plans(Request $request): JsonResponse
    {
        $result = $this->subscriptions->listPlans([
            'status' => $request->query('status'),
            'product_id' => $request->query('product_id'),
        ], (int) $request->query('per_page', 30));

        return ApiResponse::success($result['data'], 'Plans retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'frequency' => ['required', 'string'],
            'quantity_default' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:draft,active,archived'],
            'max_cycles' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->subscriptions->createPlan($payload, $user), 'Plan created', 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'frequency' => ['sometimes', 'string'],
            'quantity_default' => ['sometimes', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:draft,active,archived'],
            'max_cycles' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->subscriptions->updatePlan($id, $payload, $user), 'Plan updated');
    }
}
