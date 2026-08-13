<?php

namespace App\Modules\Cart\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponRedemption;
use App\Modules\Wishlist\Models\Wishlist;
use App\Shared\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function resolveFromRequest(Request $request, bool $create = false): ?Cart
    {
        /** @var User|null $user */
        $user = $request->user('api') ?? auth('api')->user();

        if ($user) {
            $cart = Cart::query()->where('user_id', $user->id)->first();
            if ($cart) {
                if ($cart->status !== 'active') {
                    $cart->status = 'active';
                    $cart->save();
                }

                return $cart;
            }

            if (! $create) {
                return null;
            }

            return Cart::query()->create([
                'user_id' => $user->id,
                'cart_token' => null,
                'currency' => 'INR',
                'status' => 'active',
            ]);
        }

        $token = $request->header('X-Cart-Token');
        if ($token) {
            $cart = Cart::query()->where('cart_token', $token)->where('status', 'active')->first();
            if ($cart || ! $create) {
                return $cart;
            }
        }

        if (! $create) {
            return null;
        }

        return Cart::query()->create([
            'user_id' => null,
            'cart_token' => $token ?: ('cart_'.Str::lower(Str::random(24))),
            'currency' => 'INR',
            'status' => 'active',
        ]);
    }

    public function getOrCreateFromRequest(Request $request): Cart
    {
        return $this->resolveFromRequest($request, create: true);
    }

    public function emptyCartPayload(?string $cartToken = null): array
    {
        return [
            'id' => null,
            'cart_token' => $cartToken,
            'currency' => 'INR',
            'items' => [],
            'item_count' => 0,
            'subtotal' => 0.0,
            'discount_total' => 0.0,
            'coupon_code' => null,
            'tax_total' => 0.0,
            'shipping_total' => 0.0,
            'grand_total' => 0.0,
            'free_delivery' => $this->freeDeliveryMeta(0.0),
            'warnings' => [],
            'checkout_blocked' => false,
        ];
    }

    public function present(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.variant']);

        $pairs = [];
        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }
            $pairs[] = [
                'product_id' => (int) $item->product_id,
                'variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            ];
        }
        $sellableMap = $this->inventory->sellableQtyMap($pairs);

        $items = [];
        $subtotal = 0.0;
        $itemCount = 0;

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $unit = (float) ($item->unit_price_snapshot ?? $item->product->price);
            if ($item->variant && $item->variant->price !== null) {
                $unit = (float) $item->variant->price;
            }

            $lineTotal = round($unit * $item->quantity, 2);
            $subtotal += $lineTotal;
            $itemCount += $item->quantity;

            $vid = $item->product_variant_id !== null ? (int) $item->product_variant_id : null;
            $maxQty = $sellableMap[$this->inventory->sellableKey((int) $item->product_id, $vid)] ?? 0;

            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'name' => $item->product->name,
                'slug' => $item->product->slug,
                'thumbnail_url' => $item->product->primaryImageUrl(),
                'unit_price' => $unit,
                'quantity' => $item->quantity,
                'line_total' => $lineTotal,
                'stock_status' => $item->product->stock_status,
                'max_qty' => $maxQty,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discount = $this->calculateDiscount($cart, $subtotal);

        // Soft-invalidate coupon when cart no longer meets minimum.
        if ($cart->coupon_code && $discount <= 0.0) {
            $coupon = Coupon::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper((string) $cart->coupon_code)])
                ->first();
            if ($coupon && $coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
                $cart->coupon_code = null;
                $cart->save();
            }
        }

        $tax = 0.0;
        $shipping = 0.0;
        $grand = round(max(0, $subtotal - $discount + $tax + $shipping), 2);

        // Same free-delivery basis as CheckoutService: merchandise after discount.
        $merchandise = round(max(0, $subtotal - $discount), 2);

        $warnings = $this->buildWarnings($items, $cart->coupon_code);

        return [
            'id' => $cart->id,
            'cart_token' => $cart->cart_token,
            'currency' => $cart->currency ?? 'INR',
            'items' => $items,
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'discount_total' => round($discount, 2),
            'coupon_code' => $cart->coupon_code,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'grand_total' => $grand,
            'free_delivery' => $this->freeDeliveryMeta($merchandise),
            'warnings' => $warnings,
            'checkout_blocked' => collect($warnings)->contains(fn ($w) => ($w['severity'] ?? false) === true),
        ];
    }

    /**
     * Smart cart warnings from live item fields — no fabricated messaging.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{code: string, severity: string, message: string, product_id?: int, blocking: bool}>
     */
    private function buildWarnings(array $items, ?string $couponCode): array
    {
        $warnings = [];

        foreach ($items as $item) {
            $status = (string) ($item['stock_status'] ?? '');
            $qty = (int) ($item['quantity'] ?? 0);
            $max = (int) ($item['max_qty'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            $name = (string) ($item['name'] ?? 'Item');

            if ($status === 'out_of_stock' || $max <= 0) {
                $warnings[] = [
                    'code' => 'product_unavailable',
                    'severity' => 'error',
                    'message' => "{$name} is unavailable. Remove it before checkout.",
                    'product_id' => $productId,
                    'blocking' => true,
                ];
            } elseif ($qty > $max) {
                $warnings[] = [
                    'code' => 'quantity_unavailable',
                    'severity' => 'error',
                    'message' => "Only {$max} of {$name} available. Update quantity before checkout.",
                    'product_id' => $productId,
                    'blocking' => true,
                ];
            } elseif ($status === 'low_stock' || ($max > 0 && $max <= 3)) {
                $warnings[] = [
                    'code' => 'low_stock',
                    'severity' => 'warning',
                    'message' => "Low stock: {$name} (up to {$max} available).",
                    'product_id' => $productId,
                    'blocking' => false,
                ];
            }
        }

        if ($couponCode) {
            // Coupon already soft-cleared when min order unmet; if still present, note savings context only via discount.
        }

        return $warnings;
    }

    /**
     * Free-delivery progress for cart/checkout UI.
     * $merchandiseAfterDiscount must be subtotal − discount (shipping still resolved at checkout).
     */
    public function freeDeliveryMeta(float $merchandiseAfterDiscount): array
    {
        $threshold = (float) env('FREE_DELIVERY_THRESHOLD', 999);
        $remaining = round(max(0, $threshold - $merchandiseAfterDiscount), 2);

        return [
            'enabled' => $threshold > 0,
            'threshold' => $threshold,
            'remaining' => $remaining,
            'qualifies' => $threshold > 0 && $merchandiseAfterDiscount >= $threshold,
        ];
    }

    public function addItem(Cart $cart, int $productId, int $quantity, ?int $variantId = null): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($quantity < 1) {
            throw new ApiException('Quantity must be at least 1', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($cart, $product, $quantity, $variantId) {
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->when(
                    $variantId,
                    fn ($q) => $q->where('product_variant_id', $variantId),
                    fn ($q) => $q->whereNull('product_variant_id'),
                )
                ->lockForUpdate()
                ->first();

            $newQty = ($item?->quantity ?? 0) + $quantity;
            $this->inventory->assertAvailable($product->id, $newQty, $variantId);

            if ($item) {
                $item->quantity = $newQty;
                $item->unit_price_snapshot = $product->price;
                $item->save();
            } else {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_price_snapshot' => $product->price,
                ]);
            }

            return $this->present($cart->fresh());
        });
    }

    public function updateItem(Cart $cart, int $itemId, int $quantity): array
    {
        if ($quantity < 1) {
            throw new ApiException('Quantity must be at least 1', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($cart, $itemId, $quantity) {
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new NotFoundHttpException('Cart item not found');
            }

            $this->inventory->assertAvailable($item->product_id, $quantity, $item->product_variant_id);
            $item->quantity = $quantity;
            $item->save();

            return $this->present($cart->fresh());
        });
    }

    public function removeItem(Cart $cart, int $itemId): array
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->whereKey($itemId)->first();
        if (! $item) {
            throw new NotFoundHttpException('Cart item not found');
        }

        $item->delete();

        return $this->present($cart->fresh());
    }

    public function applyCoupon(Cart $cart, string $code): array
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first();
        if (! $coupon || ! $coupon->isCurrentlyValid()) {
            throw new ApiException('Invalid or expired coupon', 400, 'BAD_REQUEST');
        }

        $presented = $this->present($cart);
        if ($coupon->min_order_amount !== null && $presented['subtotal'] < (float) $coupon->min_order_amount) {
            throw new ApiException('Cart does not meet coupon minimum order amount', 400, 'BAD_REQUEST');
        }

        // Align with checkout: reject exhausted coupons at apply-time when possible.
        if ($cart->user_id) {
            $user = User::query()->find((int) $cart->user_id);
            if ($user) {
                $this->assertCouponUsageAvailable($coupon, $user);
            }
        } else {
            $this->assertCouponUsageAvailable($coupon, null);
        }

        $cart->coupon_code = $coupon->code;
        $cart->save();

        return $this->present($cart->fresh());
    }

    /**
     * Shared coupon usage-cap check for cart apply + checkout.
     * Per-user limits require an authenticated user; total limits always apply.
     */
    public function assertCouponUsageAvailable(Coupon $coupon, ?User $user): void
    {
        if ($coupon->usage_limit_total !== null) {
            $total = CouponRedemption::query()->where('coupon_id', $coupon->id)->count();
            if ($total >= (int) $coupon->usage_limit_total) {
                throw new ApiException('This coupon has reached its usage limit', 400, 'BAD_REQUEST');
            }
        }
        if ($user && $coupon->usage_limit_per_user !== null) {
            $perUser = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->count();
            if ($perUser >= (int) $coupon->usage_limit_per_user) {
                throw new ApiException('You have already used this coupon the maximum number of times', 400, 'BAD_REQUEST');
            }
        }
    }

    public function removeCoupon(Cart $cart): array
    {
        $cart->coupon_code = null;
        $cart->save();

        return $this->present($cart->fresh());
    }

    public function clear(Cart $cart): array
    {
        return DB::transaction(function () use ($cart) {
            CartItem::query()->where('cart_id', $cart->id)->delete();
            $cart->coupon_code = null;
            $cart->save();

            return $this->present($cart->fresh());
        });
    }

    /**
     * Atomically move a cart line to the authenticated user's wishlist.
     * If the product is already wishlisted, only the cart line is removed.
     */
    public function moveItemToWishlist(Cart $cart, User $user, int $itemId): array
    {
        if ((int) $cart->user_id !== (int) $user->id) {
            throw new ApiException('Sign in to move items to your wishlist', 401, 'UNAUTHENTICATED');
        }

        return DB::transaction(function () use ($cart, $user, $itemId) {
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new NotFoundHttpException('Cart item not found');
            }

            $product = Product::query()->active()->find($item->product_id);
            if (! $product) {
                throw new NotFoundHttpException('Product not found');
            }

            $existing = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                Wishlist::query()->create([
                    'user_id' => $user->id,
                    'product_id' => $item->product_id,
                ]);
            }

            $item->delete();

            return $this->present($cart->fresh());
        });
    }

    public function mergeGuestCart(User $user, ?string $cartToken): void
    {
        if (! $cartToken) {
            return;
        }

        DB::transaction(function () use ($user, $cartToken) {
            $guest = Cart::query()
                ->where('cart_token', $cartToken)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $guest) {
                return;
            }

            $userCart = Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'INR', 'status' => 'active'],
            );
            $userCart->status = 'active';
            $userCart->save();


            $userCart = Cart::query()->whereKey($userCart->id)->lockForUpdate()->first();

            foreach ($guest->items()->lockForUpdate()->get() as $guestItem) {
                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->when(
                        $guestItem->product_variant_id,
                        fn ($q) => $q->where('product_variant_id', $guestItem->product_variant_id),
                        fn ($q) => $q->whereNull('product_variant_id'),
                    )
                    ->lockForUpdate()
                    ->first();

                $available = $this->inventory->sellableQty(
                    $guestItem->product_id,
                    $guestItem->product_variant_id,
                );

                if ($available < 1) {
                    $guestItem->delete();
                    if ($existing) {
                        $existing->delete();
                    }

                    continue;
                }

                if ($existing) {
                    $existing->quantity = min($existing->quantity + $guestItem->quantity, $available);
                    $existing->save();
                    $guestItem->delete();
                } else {
                    $guestItem->quantity = min($guestItem->quantity, $available);
                    $guestItem->cart_id = $userCart->id;
                    $guestItem->save();
                }
            }

            if ($guest->coupon_code && ! $userCart->coupon_code) {
                $userCart->coupon_code = $guest->coupon_code;
                $userCart->save();
            }

            $guest->status = 'converted';
            $guest->save();
            $guest->delete();
        });
    }

    private function calculateDiscount(Cart $cart, float $subtotal): float
    {
        if (! $cart->coupon_code) {
            return 0.0;
        }

        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper((string) $cart->coupon_code)])
            ->first();
        if (! $coupon || ! $coupon->isCurrentlyValid()) {
            return 0.0;
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            return 0.0;
        }

        $discount = $coupon->discount_type === 'percent'
            ? round($subtotal * ((float) $coupon->discount_value / 100), 2)
            : (float) $coupon->discount_value;

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return min($discount, $subtotal);
    }
}
