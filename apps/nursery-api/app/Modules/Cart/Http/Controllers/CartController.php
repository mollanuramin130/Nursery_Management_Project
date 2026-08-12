<?php

namespace App\Modules\Cart\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Cart\Services\CartService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->resolveFromRequest($request, create: false);
        $data = $cart
            ? $this->carts->present($cart)
            : $this->carts->emptyCartPayload($request->header('X-Cart-Token'));

        $response = ApiResponse::success($data, 'Cart retrieved successfully');
        if (! empty($data['cart_token'])) {
            $response->headers->set('X-Cart-Token', $data['cart_token']);
        }

        return $response;
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'product_variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $variantId = $validated['variant_id'] ?? $validated['product_variant_id'] ?? null;

        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->addItem(
            $cart,
            (int) $validated['product_id'],
            (int) $validated['quantity'],
            $variantId !== null ? (int) $variantId : null,
        );

        return ApiResponse::success($data, 'Cart updated successfully', 201)
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->updateItem($cart, $id, (int) $validated['quantity']);

        return ApiResponse::success($data, 'Cart updated successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function removeItem(Request $request, int $id): JsonResponse
    {
        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->removeItem($cart, $id);

        return ApiResponse::success($data, 'Cart updated successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->carts->resolveFromRequest($request, create: false);
        if (! $cart) {
            $data = $this->carts->emptyCartPayload($request->header('X-Cart-Token'));

            return ApiResponse::success($data, 'Cart cleared successfully');
        }

        $data = $this->carts->clear($cart);

        return ApiResponse::success($data, 'Cart cleared successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function moveToWishlist(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->moveItemToWishlist($cart, $user, $id);

        return ApiResponse::success($data, 'Moved to wishlist successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->applyCoupon($cart, $validated['code']);

        return ApiResponse::success($data, 'Coupon applied successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->carts->getOrCreateFromRequest($request);
        $data = $this->carts->removeCoupon($cart);

        return ApiResponse::success($data, 'Coupon removed successfully')
            ->header('X-Cart-Token', $data['cart_token'] ?? '');
    }
}
