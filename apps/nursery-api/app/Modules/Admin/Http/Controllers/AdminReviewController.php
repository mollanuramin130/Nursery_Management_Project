<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Review\Services\ReviewService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->reviews->adminList(
            $request->query('status'),
            $request->query('q'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Reviews retrieved', 200, [
            'pagination' => $result['pagination'],
            'dashboard' => $result['dashboard'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->reviews->adminShow($id), 'Review retrieved');
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->reviews->dashboard(), 'Reviews dashboard retrieved');
    }

    public function moderate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,hidden'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->reviews->moderate($id, $validated['status'], $validated['note'] ?? null, $user),
            'Review moderated successfully',
        );
    }
}
