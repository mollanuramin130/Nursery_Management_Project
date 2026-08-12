<?php

namespace App\Modules\Review\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Review\Services\ReviewService;
use App\Shared\Rules\SecureImageUrl;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $result = $this->reviews->listForProduct(
            $id,
            (string) $request->query('sort', 'newest'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            $result['data'],
            'Reviews retrieved successfully',
            200,
            $result['meta'],
        );
    }

    public function mine(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->reviews->listForUser(
            $user,
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Your reviews retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function eligibility(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->reviews->eligibility($user, $id),
            'Review eligibility retrieved',
        );
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [new SecureImageUrl],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->reviews->create($user, $id, $validated),
            'Review submitted successfully',
            201,
        );
    }
}
