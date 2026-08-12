<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Campaign\Models\Campaign;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Modules\Marketing\Models\MarketingAutomation;
use App\Modules\Marketing\Models\MarketingDelivery;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\Schema;

class MarketingDashboardService
{
    public function __construct(private readonly CustomerSegmentService $segments) {}

    public function overview(): array
    {
        $automations = MarketingAutomation::query()->get();
        $byStatus = $automations->groupBy('status')->map->count();

        $recentDeliveries = MarketingDelivery::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'automation_id', 'user_id', 'status', 'skip_reason', 'sent_at', 'created_at'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'automation_id' => $d->automation_id,
                'user_id' => $d->user_id,
                'status' => $d->status,
                'skip_reason' => $d->skip_reason,
                'sent_at' => optional($d->sent_at)?->toIso8601String(),
                'created_at' => optional($d->created_at)?->toIso8601String(),
            ])->values()->all();

        $segments = CustomerSegment::query()->where('status', 'active')->orderBy('name')->limit(20)->get()
            ->map(fn (CustomerSegment $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'name' => $s->name,
                // Count is expensive; omit by default. Client can open segment for count.
            ])->values()->all();

        $merchCampaigns = Campaign::query()->orderByDesc('id')->limit(8)->get(['id', 'title', 'slug', 'status', 'starts_at', 'ends_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status,
                'starts_at' => optional($c->starts_at)?->toIso8601String(),
                'ends_at' => optional($c->ends_at)?->toIso8601String(),
            ])->values()->all();

        $attributedOrders = 0;
        $attributedRevenue = 0.0;
        if (Schema::hasColumn('orders', 'campaign_id')) {
            $q = Order::query()->whereNotNull('campaign_id')->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED']);
            $attributedOrders = (clone $q)->count();
            $attributedRevenue = (float) (clone $q)->sum('grand_total');
        }

        return [
            'automations' => [
                'total' => $automations->count(),
                'by_status' => $byStatus,
                'active' => $automations->where('status', 'active')->values()->map(fn ($a) => [
                    'id' => $a->id, 'key' => $a->key, 'name' => $a->name, 'type' => $a->type,
                ])->all(),
                'draft' => $automations->where('status', 'draft')->count(),
            ],
            'segments' => $segments,
            'merchandising_campaigns' => $merchCampaigns,
            'deliveries_last_20' => $recentDeliveries,
            'attribution' => [
                'supported' => Schema::hasColumn('orders', 'campaign_id'),
                'orders_with_campaign_id' => $attributedOrders,
                'revenue_with_campaign_id' => round($attributedRevenue, 2),
                'note' => 'Revenue only counted when orders.campaign_id is set at checkout — never fabricated.',
            ],
            'config' => [
                'abandoned_cart_hours' => config('marketing.abandoned_cart.inactive_hours'),
                'max_marketing_per_day' => config('marketing.frequency.max_marketing_messages_per_day'),
            ],
        ];
    }
}
