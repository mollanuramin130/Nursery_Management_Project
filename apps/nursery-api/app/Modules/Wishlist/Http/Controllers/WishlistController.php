<?php

namespace App\Modules\Wishlist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Wishlist\Services\WishlistService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlist) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->wishlist->list($user),
            'Wishlist retrieved successfully',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->wishlist->add($user, (int) $validated['product_id']),
            'Added to wishlist',
            201,
        );
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->wishlist->remove($user, $productId);

        return ApiResponse::success(null, 'Removed from wishlist');
    }

    public function contains(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ids = $this->wishlist->contains($user, $validated['product_ids']);

        return ApiResponse::success(
            ['product_ids' => $ids],
            'Wishlist membership retrieved successfully',
        );
    }

    public function moveToCart(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $data = $this->wishlist->moveToCart(
            $user,
            $productId,
            (int) ($validated['quantity'] ?? 1),
        );

        return ApiResponse::success($data, 'Moved to cart successfully');
    }
}
