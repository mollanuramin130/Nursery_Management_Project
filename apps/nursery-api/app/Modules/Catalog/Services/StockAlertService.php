<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\StockAlertSubscription;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class StockAlertService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
    ) {}

    public function subscribe(int $userId, int $productId): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $sellable = $this->inventory->sellableQty($productId);
        if ($sellable > 0 && $product->stock_status !== 'out_of_stock') {
            throw new ApiException('Product is currently available — no alert needed', 422, 'VALIDATION_ERROR');
        }

        $row = StockAlertSubscription::query()->updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId],
            ['status' => 'active', 'notified_at' => null],
        );

        return [
            'id' => $row->id,
            'product_id' => $productId,
            'status' => $row->status,
            'product' => ProductPresenter::card($product->loadMissing('images')),
        ];
    }

    public function unsubscribe(int $userId, int $productId): void
    {
        StockAlertSubscription::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->update(['status' => 'cancelled']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId): array
    {
        return StockAlertSubscription::query()
            ->with('product.images')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (StockAlertSubscription $s) => $s->product)
            ->map(fn (StockAlertSubscription $s) => [
                'id' => $s->id,
                'product_id' => $s->product_id,
                'status' => $s->status,
                'product' => ProductPresenter::card($s->product),
                'created_at' => optional($s->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function statusForUser(int $userId, int $productId): array
    {
        $row = StockAlertSubscription::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->first();

        return [
            'subscribed' => (bool) $row,
            'status' => $row?->status,
        ];
    }

    /**
     * Called after inventory increases. Never throws into inventory flow.
     */
    public function notifyRestock(int $productId): void
    {
        try {
            if ($this->inventory->sellableQty($productId) <= 0) {
                return;
            }

            $product = Product::query()->find($productId);
            if (! $product) {
                return;
            }

            // Keep catalog stock_status consistent when sellable again.
            if ($product->stock_status === 'out_of_stock') {
                $product->stock_status = 'in_stock';
                $product->save();
            }

            $subs = StockAlertSubscription::query()
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->limit(500)
                ->get();

            foreach ($subs as $sub) {
                $this->notifications->notify(
                    $sub->user_id,
                    'stock_back_in_stock',
                    'Back in stock',
                    ($product->name ?? 'A product').' is available again.',
                    [
                        'product_id' => $productId,
                        'product_slug' => $product->slug,
                        'deep_link' => '/product/'.$product->slug,
                    ],
                    'stock-alert:'.$productId.':user:'.$sub->user_id.':'.now()->format('Y-m-d'),
                    'transactional',
                );

                $sub->status = 'notified';
                $sub->notified_at = now();
                $sub->save();
            }
        } catch (Throwable $e) {
            Log::warning('stock_alert.notify_failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
