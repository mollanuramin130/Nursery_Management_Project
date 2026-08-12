<?php

namespace App\Modules\Campaign\Services;

use App\Modules\Campaign\Models\Campaign;
use App\Modules\Catalog\Models\Product;
use App\Modules\Promotion\Models\Coupon;

class OfferService
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function feed(?string $productType = null, int $perPage = 12): array
    {
        $featured = $this->campaigns->featured(6);

        $upcoming = Campaign::query()
            ->where(function ($q) {
                $q->where('status', 'scheduled')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'active')->where('starts_at', '>', now());
                    });
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('starts_at')
            ->limit(6)
            ->get()
            ->map(fn (Campaign $c) => $this->campaigns->summary($c))
            ->values()
            ->all();

        $saleQuery = Product::query()
            ->active()
            ->with('images')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->orderByDesc('rating_count')
            ->orderByDesc('rating_avg');

        if ($productType) {
            $saleQuery->where('product_type', $productType);
        }

        $paginator = $saleQuery->paginate(min(max($perPage, 1), 48));
        $saleProducts = collect($paginator->items())
            ->map(fn ($p) => $this->campaigns->offerCard($p))
            ->values()
            ->all();

        $coupons = Coupon::query()
            ->where('status', 'active')
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'name' => $c->name ?? $c->code,
                'discount_type' => $c->discount_type,
                'discount_value' => (float) $c->discount_value,
                'min_order_amount' => $c->min_order_amount !== null ? (float) $c->min_order_amount : null,
                'ends_at' => optional($c->ends_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'featured_campaigns' => $featured,
            'upcoming_campaigns' => $upcoming,
            'sale_products' => $saleProducts,
            'public_coupons' => $coupons,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
