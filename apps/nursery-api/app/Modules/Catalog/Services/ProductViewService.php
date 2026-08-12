<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductView;
use App\Modules\Catalog\Support\ProductPresenter;
use Throwable;

class ProductViewService
{
    public const MAX_RECENT = 12;

    /**
     * Best-effort record — never throws to callers.
     */
    public function record(?int $userId, ?string $guestToken, int $productId, ?string $platform = null): void
    {
        if (! Product::query()->whereKey($productId)->where('status', 'active')->exists()) {
            return;
        }

        if (! $userId && (! $guestToken || strlen($guestToken) < 8)) {
            return;
        }

        try {
            ProductView::query()->create([
                'user_id' => $userId,
                'guest_token' => $userId ? null : substr($guestToken, 0, 64),
                'product_id' => $productId,
                'platform' => $platform ? substr($platform, 0, 40) : null,
                'viewed_at' => now(),
            ]);

            $this->prune($userId, $userId ? null : $guestToken);
        } catch (Throwable) {
            // Swallow — analytics must not break PDP
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentlyViewed(?int $userId, ?string $guestToken, int $limit = self::MAX_RECENT): array
    {
        if (! $userId && (! $guestToken || strlen($guestToken) < 8)) {
            return [];
        }

        $query = ProductView::query()->orderByDesc('viewed_at');
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_token', substr($guestToken, 0, 64))->whereNull('user_id');
        }

        $productIds = $query->limit(80)->pluck('product_id')->unique()->take($limit)->values()->all();
        if ($productIds === []) {
            return [];
        }

        $products = Product::query()
            ->active()
            ->with('images')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($productIds as $id) {
            $p = $products->get($id);
            if ($p) {
                $out[] = ProductPresenter::card($p);
            }
        }

        return $out;
    }

    private function prune(?int $userId, ?string $guestToken): void
    {
        $keeperQuery = ProductView::query()->orderByDesc('viewed_at');
        if ($userId) {
            $keeperQuery->where('user_id', $userId);
        } else {
            $keeperQuery->where('guest_token', substr((string) $guestToken, 0, 64))->whereNull('user_id');
        }

        // Keep latest MAX_RECENT distinct products; delete older rows beyond a soft cap.
        $ids = (clone $keeperQuery)->limit(200)->pluck('id');
        if ($ids->count() <= 100) {
            return;
        }

        $keep = $ids->take(100);
        ProductView::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId, fn ($q) => $q->where('guest_token', substr((string) $guestToken, 0, 64))->whereNull('user_id'))
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
