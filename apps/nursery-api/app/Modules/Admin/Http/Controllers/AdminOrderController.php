<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminOrderService;
use App\Modules\Auth\Models\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(private readonly AdminOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->orders->list(
            $request->query('status'),
            $request->query('q'),
            min(100, max(1, (int) $request->query('per_page', 20))),
        );

        return ApiResponse::success($result['data'], 'Orders retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->orders->detail($id),
            'Order retrieved successfully',
        );
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->orders->updateStatus(
                $id,
                $validated,
                $user->id,
                $request->attributes->get('request_id'),
            ),
            'Order status updated',
        );
    }
}
