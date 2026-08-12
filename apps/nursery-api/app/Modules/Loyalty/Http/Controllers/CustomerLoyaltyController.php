<?php

namespace App\Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->loyalty->forUser($user), 'Loyalty account retrieved');
    }

    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->loyalty->historyForUser(
            $user,
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Loyalty history retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }
}
