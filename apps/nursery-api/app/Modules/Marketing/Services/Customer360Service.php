<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Catalog\Models\ProductView;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\CouponRedemption;
use App\Modules\Review\Models\Review;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Wishlist\Models\Wishlist;
use Illuminate\Support\Facades\Schema;

/**
 * Customer 360 — read-only aggregation from existing commerce tables.
 */
class Customer360Service
{
    public const NON_REVENUE = ['CANCELLED', 'PAYMENT_FAILED'];

    public function __construct(private readonly PreferenceService $preferences) {}

    public function profile(User $user): array
    {
        $orderBase = Order::query()->where('user_id', $user->id);
        $qualifying = (clone $orderBase)->whereNotIn('status', self::NON_REVENUE);

        $totalOrders = (clone $orderBase)->count();
        $qualifyingOrders = (clone $qualifying)->count();
        $totalSpent = (float) (clone $qualifying)->sum('grand_total');
        $aov = $qualifyingOrders > 0 ? round($totalSpent / $qualifyingOrders, 2) : 0.0;
        $lastOrder = (clone $qualifying)->orderByDesc('id')->first(['id', 'order_number', 'status', 'grand_total', 'placed_at', 'created_at', 'coupon_code', 'campaign_id']);

        $prefs = $this->preferences->get($user);

        $lifecycle = $this->deriveLifecycle($user, $qualifyingOrders, $lastOrder?->placed_at ?? $lastOrder?->created_at);

        return [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('slug')->values()->all(),
                'registered_at' => optional($user->created_at)?->toIso8601String(),
                'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
                'lifecycle_stage' => $lifecycle,
            ],
            'orders' => [
                'total_orders' => $totalOrders,
                'qualifying_orders' => $qualifyingOrders,
                'total_spent' => round($totalSpent, 2),
                'average_order_value' => $aov,
                'last_order' => $lastOrder ? [
                    'id' => $lastOrder->id,
                    'order_number' => $lastOrder->order_number,
                    'status' => $lastOrder->status,
                    'grand_total' => (float) $lastOrder->grand_total,
                    'coupon_code' => $lastOrder->coupon_code,
                    'campaign_id' => $lastOrder->campaign_id,
                    'placed_at' => optional($lastOrder->placed_at ?? $lastOrder->created_at)?->toIso8601String(),
                ] : null,
                'recent' => (clone $orderBase)->orderByDesc('id')->limit(15)->get()
                    ->map(fn ($o) => [
                        'id' => $o->id,
                        'order_number' => $o->order_number,
                        'status' => $o->status,
                        'grand_total' => (float) $o->grand_total,
                        'placed_at' => optional($o->placed_at ?? $o->created_at)?->toIso8601String(),
                    ])->values()->all(),
            ],
            'marketing' => [
                'preferences' => $prefs,
                'coupon_redemptions' => Schema::hasTable('coupon_redemptions')
                    ? CouponRedemption::query()->where('user_id', $user->id)->orderByDesc('id')->limit(10)
                        ->get(['id', 'coupon_code', 'order_id', 'discount_amount', 'created_at'])
                        ->map(fn ($r) => [
                            'id' => $r->id,
                            'coupon_code' => $r->coupon_code ?? $r->code ?? null,
                            'order_id' => $r->order_id,
                            'discount_amount' => isset($r->discount_amount) ? (float) $r->discount_amount : null,
                            'created_at' => optional($r->created_at)?->toIso8601String(),
                        ])->values()->all()
                    : [],
            ],
            'loyalty' => $this->loyalty($user->id),
            'subscriptions' => $this->subscriptions($user->id),
            'activity' => [
                'wishlist_count' => Schema::hasTable('wishlists')
                    ? Wishlist::query()->where('user_id', $user->id)->count() : 0,
                'review_count' => Schema::hasTable('reviews')
                    ? Review::query()->where('user_id', $user->id)->count() : 0,
                'recently_viewed_product_ids' => $this->recentViews($user->id),
                'open_cart' => $this->openCart($user->id),
            ],
            'definitions' => [
                'total_spent' => 'SUM(grand_total) excluding CANCELLED and PAYMENT_FAILED',
                'lifecycle_stage' => 'Derived analytically — does not mutate users.status',
            ],
        ];
    }

    private function deriveLifecycle(User $user, int $qualifyingOrders, $lastOrderAt): string
    {
        if ($qualifyingOrders === 0) {
            return 'registered';
        }
        if ($qualifyingOrders === 1) {
            return 'first_purchase';
        }
        $inactiveDays = (int) config('marketing.reactivation.inactive_days', 90);
        if ($lastOrderAt && now()->diffInDays($lastOrderAt) >= $inactiveDays) {
            return 'inactive';
        }
        if ($qualifyingOrders >= 5) {
            return 'loyal';
        }

        return 'repeat';
    }

    private function loyalty(int $userId): array
    {
        if (! Schema::hasTable('loyalty_accounts')) {
            return ['supported' => false];
        }
        $acct = LoyaltyAccount::query()->where('user_id', $userId)->first();

        return [
            'supported' => true,
            'points_balance' => $acct ? (int) $acct->balance : 0,
            'lifetime_earned' => $acct ? (int) $acct->lifetime_earned : 0,
            'status' => $acct->status ?? null,
        ];
    }

    private function subscriptions(int $userId): array
    {
        if (! Schema::hasTable('subscriptions')) {
            return ['supported' => false, 'rows' => []];
        }

        $rows = Subscription::query()->where('user_id', $userId)->orderByDesc('id')->limit(20)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'product_id' => $s->product_id ?? null,
                'next_billing_at' => optional($s->next_billing_at)?->toIso8601String(),
            ])->values()->all();

        return ['supported' => true, 'rows' => $rows];
    }

    private function recentViews(int $userId): array
    {
        if (! Schema::hasTable('product_views')) {
            return [];
        }

        return ProductView::query()->where('user_id', $userId)->orderByDesc('viewed_at')->limit(40)
            ->pluck('product_id')->unique()->take(8)->values()->all();
    }

    private function openCart(int $userId): ?array
    {
        $cart = Cart::query()->where('user_id', $userId)->where('status', 'active')->with('items')->first();
        if (! $cart) {
            return null;
        }

        return [
            'id' => $cart->id,
            'item_count' => $cart->items->sum('quantity'),
            'updated_at' => optional($cart->updated_at)?->toIso8601String(),
            'inactive_hours' => $cart->updated_at ? (int) now()->diffInHours($cart->updated_at) : null,
        ];
    }
}
