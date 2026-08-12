<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminRefundService;
use App\Modules\Auth\Models\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRefundController extends Controller
{
    public function __construct(private readonly AdminRefundService $refunds) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->refunds->list(
            $request->query('status'),
            $request->query('q'),
            min(100, max(1, (int) $request->query('per_page', 20))),
            max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success($result['data'], 'Refunds retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'return_request_id' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->refunds->create($validated, $user->id),
            'Refund created successfully',
            201,
        );
    }
}
