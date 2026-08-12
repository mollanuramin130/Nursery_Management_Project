<?php

namespace App\Modules\Review\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Review\Models\Review;
use App\Modules\Review\Models\ReviewImage;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function listForProduct(int $productId, string $sort = 'newest', int $perPage = 20): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $query = Review::query()
            ->with(['user', 'images'])
            ->where('product_id', $productId)
            ->where('status', 'approved');

        match ($sort) {
            'highest' => $query->orderByDesc('rating')->orderByDesc('id'),
            'lowest' => $query->orderBy('rating')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        $paginator = $query->paginate(min(max($perPage, 1), 50));

        $distributionRows = Review::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[(string) $star] = (int) ($distributionRows[$star] ?? 0);
        }

        $data = collect($paginator->items())->map(fn (Review $review) => $this->serializePublic($review))->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'summary' => [
                    'rating_avg' => (float) $product->rating_avg,
                    'rating_count' => (int) $product->rating_count,
                    'distribution' => $distribution,
                ],
            ],
        ];
    }

    public function listForUser(User $user, int $perPage = 20, int $page = 1): array
    {
        $paginator = Review::query()
            ->with(['product', 'images'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 50), ['*'], 'page', max(1, $page));

        $data = collect($paginator->items())->map(function (Review $review) {
            return [
                'id' => $review->id,
                'product_id' => $review->product_id,
                'product_name' => $review->product?->name,
                'product_slug' => $review->product?->slug,
                'rating' => (int) $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'status' => $review->status,
                'verified_purchase' => true,
                'created_at' => optional($review->created_at)?->toIso8601String(),
                'images' => $review->images->pluck('url')->values()->all(),
            ];
        })->values()->all();

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

    public function adminList(?string $status, ?string $q, int $perPage = 20, int $page = 1): array
    {
        $query = Review::query()
            ->with(['user', 'product', 'images'])
            ->when($status, fn ($qb) => $qb->where('status', strtolower($status)))
            ->when($q, function ($qb) use ($q) {
                $term = trim($q);
                $qb->where(function ($inner) use ($term) {
                    $inner->where('id', (int) $term)
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
        $data = collect($paginator->items())->map(fn (Review $r) => $this->serializeAdmin($r))->values()->all();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'dashboard' => $this->dashboard(),
        ];
    }

    public function adminShow(int $id): array
    {
        $review = Review::query()->with(['user', 'product', 'images'])->whereKey($id)->first();
        if (! $review) {
            throw new NotFoundHttpException('Review not found');
        }

        return $this->serializeAdmin($review);
    }

    public function dashboard(): array
    {
        $counts = Review::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
            'hidden' => (int) ($counts['hidden'] ?? 0),
            'total' => Review::query()->count(),
            'avg_rating_approved' => round((float) (Review::query()->where('status', 'approved')->avg('rating') ?? 0), 2),
        ];
    }

    /**
     * @return array{eligible: bool, already_reviewed: bool, reason: string|null}
     */
    public function eligibility(User $user, int $productId): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        if (Review::query()->where('product_id', $productId)->where('user_id', $user->id)->exists()) {
            return [
                'eligible' => false,
                'already_reviewed' => true,
                'reason' => 'You have already reviewed this product',
            ];
        }

        if (! $this->hasPurchased($user, $productId, null)) {
            return [
                'eligible' => false,
                'already_reviewed' => false,
                'reason' => 'You can only review products from delivered orders',
            ];
        }

        return [
            'eligible' => true,
            'already_reviewed' => false,
            'reason' => null,
        ];
    }

    public function create(User $user, int $productId, array $payload): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        if (Review::query()->where('product_id', $productId)->where('user_id', $user->id)->exists()) {
            throw new ApiException('You have already reviewed this product', 409, 'CONFLICT');
        }

        if (! $this->hasPurchased($user, $productId, $payload['order_id'] ?? null)) {
            throw new ApiException('You can only review products from delivered orders', 403, 'FORBIDDEN');
        }

        $rating = (int) $payload['rating'];
        if ($rating < 1 || $rating > 5) {
            throw new ApiException('Rating must be between 1 and 5', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($user, $product, $payload, $rating) {
            $review = Review::query()->create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $payload['order_id'] ?? null,
                'rating' => $rating,
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'status' => 'pending',
            ]);

            $images = $payload['images'] ?? [];
            if (is_array($images)) {
                $sort = 0;
                foreach (array_slice($images, 0, 5) as $url) {
                    if (! is_string($url) || trim($url) === '') {
                        continue;
                    }
                    ReviewImage::query()->create([
                        'review_id' => $review->id,
                        'url' => trim($url),
                        'sort_order' => $sort++,
                    ]);
                }
            }

            AuditLogger::log('review.create', 'review', $review->id, null, [
                'product_id' => $product->id,
                'rating' => $rating,
                'status' => 'pending',
            ], $user->id);

            return [
                'id' => $review->id,
                'product_id' => $product->id,
                'rating' => (int) $review->rating,
                'status' => $review->status,
                'verified_purchase' => true,
                'images' => $review->images()->pluck('url')->values()->all(),
            ];
        });
    }

    public function moderate(int $id, string $status, ?string $note, User $actor): array
    {
        if (! in_array($status, ['approved', 'rejected', 'hidden'], true)) {
            throw new ApiException('Invalid moderation status', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($id, $status, $note, $actor) {
            $review = Review::query()->whereKey($id)->lockForUpdate()->first();
            if (! $review) {
                throw new NotFoundHttpException('Review not found');
            }

            $before = $review->toArray();
            $review->status = $status;
            $review->moderated_by = $actor->id;
            $review->moderated_at = now();
            if ($note) {
                $meta = $review->meta ?? [];
                $meta['moderation_note'] = $note;
                $review->meta = $meta;
            }
            $review->save();

            $this->recomputeProductRating($review->product_id);

            AuditLogger::log('review.moderate', 'review', $review->id, $before, $review->toArray(), $actor->id);

            try {
                $title = match ($status) {
                    'approved' => 'Review published',
                    'rejected' => 'Review not published',
                    'hidden' => 'Review hidden',
                    default => 'Review updated',
                };
                $body = match ($status) {
                    'approved' => 'Your product review is now visible.',
                    'rejected' => $note ?: 'Your product review was not approved.',
                    'hidden' => 'Your product review is no longer visible.',
                    default => 'Your review status was updated.',
                };
                $this->notifications->notify(
                    $review->user_id,
                    'REVIEW_MODERATED',
                    $title,
                    $body,
                    ['review_id' => $review->id, 'product_id' => $review->product_id, 'status' => $status],
                );
            } catch (\Throwable) {
                // Non-blocking
            }

            return [
                'id' => $review->id,
                'status' => $review->status,
                'product_id' => $review->product_id,
            ];
        });
    }

    public function recomputeProductRating(int $productId): void
    {
        $stats = Review::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        Product::query()->whereKey($productId)->update([
            'rating_avg' => round((float) ($stats->avg_rating ?? 0), 2),
            'rating_count' => (int) ($stats->cnt ?? 0),
        ]);
    }

    private function hasPurchased(User $user, int $productId, mixed $orderId): bool
    {
        return OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($q) use ($user, $orderId) {
                $q->where('user_id', $user->id)
                    ->whereIn('status', [
                        'DELIVERED',
                        'RETURN_REQUESTED',
                        'RETURNED',
                        'REFUNDED',
                    ]);
                if (! empty($orderId)) {
                    $q->where('id', $orderId);
                }
            })
            ->exists();
    }

    private function serializePublic(Review $review): array
    {
        $name = $review->user?->name ?? 'Customer';
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $display = $parts[0] ?? 'Customer';
        if (isset($parts[1]) && $parts[1] !== '') {
            $display .= ' '.strtoupper(substr($parts[1], 0, 1)).'.';
        }

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'user_name' => $display,
            'verified_purchase' => true,
            'created_at' => optional($review->created_at)?->toIso8601String(),
            'images' => $review->images->pluck('url')->values()->all(),
        ];
    }

    private function serializeAdmin(Review $review): array
    {
        return [
            'id' => $review->id,
            'product' => [
                'id' => $review->product_id,
                'name' => $review->product?->name,
                'sku' => $review->product?->sku,
                'slug' => $review->product?->slug,
            ],
            'customer' => [
                'id' => $review->user_id,
                'name' => $review->user?->name,
                'email' => $review->user?->email,
            ],
            'order_id' => $review->order_id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'verified_purchase' => true,
            'moderated_by' => $review->moderated_by,
            'moderated_at' => optional($review->moderated_at)?->toIso8601String(),
            'moderation_note' => $review->meta['moderation_note'] ?? null,
            'images' => $review->images->pluck('url')->values()->all(),
            'created_at' => optional($review->created_at)?->toIso8601String(),
            'updated_at' => optional($review->updated_at)?->toIso8601String(),
        ];
    }
}
