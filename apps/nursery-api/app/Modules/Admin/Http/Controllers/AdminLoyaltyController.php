<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->loyalty->dashboard(), 'Loyalty dashboard retrieved');
    }

    public function accounts(Request $request): JsonResponse
    {
        $result = $this->loyalty->adminList(
            $request->query('q'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Loyalty accounts retrieved', 200, [
            'pagination' => $result['pagination'],
            'dashboard' => $result['dashboard'],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $result = $this->loyalty->adminTransactions(
            $request->query('user_id') ? (int) $request->query('user_id') : null,
            $request->query('type'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Loyalty transactions retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->loyalty->adjust(
                (int) $validated['user_id'],
                (int) $validated['points'],
                $validated['reason'],
                $user->id,
                $validated['idempotency_key'] ?? null,
            ),
            'Loyalty balance adjusted',
        );
    }
}
