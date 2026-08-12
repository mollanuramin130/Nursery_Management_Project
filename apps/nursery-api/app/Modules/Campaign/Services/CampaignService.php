<?php

namespace App\Modules\Campaign\Services;

use App\Modules\Campaign\Models\Campaign;
use App\Modules\Catalog\Support\ProductPresenter;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CampaignService
{
    public function list(?string $status = null, ?string $season = null, int $perPage = 20): array
    {
        $query = Campaign::query()->orderByDesc('priority')->orderByDesc('id');

        $status = $status ?: 'active';
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'scheduled' || $status === 'upcoming') {
            $query->where(function ($q) {
                $q->where('status', 'scheduled')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'active')->where('starts_at', '>', now());
                    });
            })->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        } elseif ($status === 'featured') {
            $query->active()->where('priority', '>=', 80);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($season) {
            $query->where('season_code', $season);
        }

        $paginator = $query->paginate(min(max($perPage, 1), 50));
        $data = collect($paginator->items())
            ->map(fn (Campaign $c) => $this->summary($c))
            ->values()
            ->all();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function featured(int $limit = 6): array
    {
        return Campaign::query()
            ->active()
            ->orderByDesc('priority')
            ->limit(min(max($limit, 1), 20))
            ->get()
            ->map(fn (Campaign $c) => $this->summary($c))
            ->values()
            ->all();
    }

    public function detail(string $slug, int $perPage = 20): array
    {
        $campaign = Campaign::query()->where('slug', $slug)->first();
        if (! $campaign) {
            throw new NotFoundHttpException('Campaign not found');
        }

        $lifecycle = $this->lifecycle($campaign);
        $products = $campaign->products()
            ->where('products.status', 'active')
            ->with('images')
            ->paginate(min(max($perPage, 1), 50));

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'title' => $campaign->title,
            'subtitle' => $campaign->subtitle,
            'short_description' => $campaign->subtitle,
            'description' => $campaign->description,
            'type' => $campaign->type,
            'season_code' => $campaign->season_code,
            'banner_image' => $campaign->image_url,
            'thumbnail_image' => $campaign->image_url,
            'image_url' => $campaign->image_url,
            'starts_at' => optional($campaign->starts_at)?->toIso8601String(),
            'ends_at' => optional($campaign->ends_at)?->toIso8601String(),
            'status' => $lifecycle,
            'db_status' => $campaign->status,
            'priority' => (int) $campaign->priority,
            'is_featured' => (int) $campaign->priority >= 80 && $lifecycle === 'active',
            'products' => collect($products->items())
                ->map(fn ($p) => $this->offerCard($p))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ];
    }

    public function products(string $slug, int $perPage = 20): array
    {
        $detail = $this->detail($slug, $perPage);

        return [
            'data' => $detail['products'],
            'pagination' => $detail['pagination'],
            'campaign' => [
                'id' => $detail['id'],
                'slug' => $detail['slug'],
                'title' => $detail['title'],
                'status' => $detail['status'],
            ],
        ];
    }

    public function summary(Campaign $c): array
    {
        $lifecycle = $this->lifecycle($c);

        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'subtitle' => $c->subtitle,
            'short_description' => $c->subtitle,
            'banner_image' => $c->image_url,
            'thumbnail_image' => $c->image_url,
            'image_url' => $c->image_url,
            'season_code' => $c->season_code,
            'type' => $c->type,
            'starts_at' => optional($c->starts_at)?->toIso8601String(),
            'ends_at' => optional($c->ends_at)?->toIso8601String(),
            'status' => $lifecycle,
            'db_status' => $c->status,
            'priority' => (int) $c->priority,
            'is_featured' => (int) $c->priority >= 80 && $lifecycle === 'active',
        ];
    }

    /**
     * Server-derived lifecycle for clients (authoritative for merchandising UI).
     */
    public function lifecycle(Campaign $c): string
    {
        $now = Carbon::now();
        if (in_array($c->status, ['inactive', 'disabled', 'draft'], true)) {
            return $c->status === 'draft' ? 'draft' : 'disabled';
        }

        if ($c->starts_at && $now->lt($c->starts_at)) {
            return 'scheduled';
        }

        if ($c->ends_at && $now->gt($c->ends_at)) {
            return 'expired';
        }

        if ($c->status === 'scheduled') {
            // Window open but still marked scheduled — treat as active for storefront.
            return 'active';
        }

        return $c->status === 'active' ? 'active' : (string) $c->status;
    }

    public function offerCard($product): array
    {
        $card = ProductPresenter::card($product);
        $price = (float) ($card['price'] ?? 0);
        $compare = $card['compare_at_price'] ?? null;
        $discountAmount = null;
        $discountPct = null;
        if ($compare !== null && (float) $compare > $price) {
            $discountAmount = round((float) $compare - $price, 2);
            $discountPct = (int) round(($discountAmount / (float) $compare) * 100);
        }

        return array_merge($card, [
            'original_price' => $compare !== null ? (float) $compare : null,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $discountPct,
        ]);
    }
}
