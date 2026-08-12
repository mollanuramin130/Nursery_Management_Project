<?php

namespace App\Console\Commands;

use App\Modules\Marketing\Models\MarketingDelivery;
use App\Modules\Marketing\Services\MarketingAutomationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Shipment;
use Illuminate\Console\Command;

/**
 * Post-purchase review requests — only after configured days since delivery.
 */
class ProcessPostPurchaseCommand extends Command
{
    protected $signature = 'marketing:process-post-purchase {--limit=100}';

    protected $description = 'Send post-purchase review requests for deliveries that aged past the configured delay';

    public function handle(MarketingAutomationService $marketing): int
    {
        $days = (int) config('marketing.post_purchase.review_request_days_after_delivery', 3);
        $cutoff = now()->subDays($days);
        $limit = (int) $this->option('limit');

        $orderIds = Shipment::query()
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->orderByDesc('delivered_at')
            ->limit($limit * 3)
            ->pluck('order_id')
            ->unique()
            ->values();

        $sent = 0;
        $skipped = 0;
        foreach ($orderIds->take($limit) as $orderId) {
            $order = Order::query()->find($orderId);
            if (! $order || $order->status !== 'DELIVERED' || ! $order->user_id) {
                $skipped++;
                continue;
            }
            $already = MarketingDelivery::query()
                ->where('user_id', $order->user_id)
                ->where('status', 'sent')
                ->where('meta_json->order_id', $order->id)
                ->exists();
            if ($already) {
                $skipped++;
                continue;
            }
            $before = MarketingDelivery::query()->where('user_id', $order->user_id)->where('status', 'sent')->count();
            $marketing->sendPostPurchaseReview($order);
            $after = MarketingDelivery::query()->where('user_id', $order->user_id)->where('status', 'sent')->count();
            $after > $before ? $sent++ : $skipped++;
        }

        $this->info(json_encode(compact('sent', 'skipped', 'days')));

        return self::SUCCESS;
    }
}
