<?php

namespace App\Modules\Wishlist\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Modules\Wishlist\Models\Wishlist;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WishlistService
{
    public function __construct(private readonly CartService $carts) {}

    public function list(User $user): array
    {
        // Soft cap — wishlist stays an array for clients; unbounded lists are rejected.
        return Wishlist::query()
            ->where('user_id', $user->id)
            ->with(['product.images'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (Wishlist $item) => $item->product !== null)
            ->map(fn (Wishlist $item) => [
                'wishlist_item_id' => $item->id,
                'added_at' => optional($item->created_at)?->toIso8601String(),
                'product' => ProductPresenter::card($item->product),
            ])
            ->values()
            ->all();
    }

    public function add(User $user, int $productId): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $existing = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            throw new ApiException('Product already in wishlist', 409, 'CONFLICT');
        }

        $item = Wishlist::query()->create([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        return [
            'wishlist_item_id' => $item->id,
            'product_id' => $productId,
        ];
    }

    public function remove(User $user, int $productId): void
    {
        $item = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if (! $item) {
            throw new NotFoundHttpException('Wishlist item not found');
        }

        $item->delete();
    }

    /**
     * Lightweight membership check for PDP hearts without loading the full list.
     *
     * @param  list<int>  $productIds
     * @return list<int> product ids that are wishlisted
     */
    public function contains(User $user, array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $productIds),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        return Wishlist::query()
            ->where('user_id', $user->id)
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Atomically add wishlist product to the user's cart, then remove wishlist row.
     * On stock failure the wishlist item is preserved.
     *
     * @return array{cart: array, product_id: int}
     */
    public function moveToCart(User $user, int $productId, int $quantity = 1): array
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {
            $wish = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $wish) {
                throw new NotFoundHttpException('Wishlist item not found');
            }

            $cart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'INR', 'status' => 'active', 'cart_token' => null],
            );
            if ($cart->status !== 'active') {
                $cart->status = 'active';
                $cart->save();
            }

            // Stock validation happens inside addItem; failure rolls back wishlist delete.
            $presented = $this->carts->addItem($cart, $productId, max(1, $quantity), null);
            $wish->delete();

            return [
                'cart' => $presented,
                'product_id' => $productId,
            ];
        });
    }
}
