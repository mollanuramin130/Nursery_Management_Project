<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Services\ReturnService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->returns->dashboard(), 'Returns dashboard retrieved');
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->returns->adminList(
            $request->query('status'),
            $request->query('q'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Returns retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->returns->adminShow($id), 'Return retrieved');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->approve($id, $user->id, $validated['note'] ?? null),
            'Return approved',
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->reject($id, $user->id, $validated['reason']),
            'Return rejected',
        );
    }

    public function schedulePickup(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'eta_date' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->schedulePickup($id, $validated, $user->id),
            'Pickup scheduled',
        );
    }

    public function markPickedUp(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->markPickedUp($id, $user->id),
            'Return marked picked up',
        );
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'integer'],
            'items.*.received_qty' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'string', 'in:GOOD,DAMAGED,OPENED,UNSELLABLE'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->receive($id, $validated['items'], $user->id, $validated['note'] ?? null),
            'Return received',
        );
    }

    public function inspect(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'integer'],
            'items.*.accepted_qty' => ['required', 'integer', 'min:0'],
            'items.*.rejected_qty' => ['nullable', 'integer', 'min:0'],
            'items.*.disposition' => ['required', 'string', 'in:SELLABLE,DAMAGED,DISPOSAL'],
            'create_refund' => ['nullable', 'boolean'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->inspect($id, $validated, $user->id),
            'Inspection completed',
        );
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->returns->createRefundForReturn($id, $validated, $user->id),
            'Refund recorded for return',
        );
    }
}
