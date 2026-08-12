<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\Refund;
use App\Modules\Admin\Support\AnalyticsDateRange;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SearchEvent;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\ReturnRequest;
use App\Modules\Payment\Models\Payment;
use App\Modules\Promotion\Models\CouponRedemption;
use App\Modules\Review\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsService
{
    /** Statuses excluded from revenue / AOV / units sold. */
    public const NON_REVENUE_STATUSES = ['CANCELLED', 'PAYMENT_FAILED'];

    public function overview(AnalyticsDateRange $range): array
    {
        $current = $this->periodSummary($range);
        $previous = $this->periodSummary($range->previous());

        return [
            'range' => $range->meta(),
            'summary' => $current,
            'comparison' => [
                'previous_range' => $range->previous()->meta(),
                'revenue_change_pct' => $this->pctChange($current['revenue'], $previous['revenue']),
                'orders_change_pct' => $this->pctChange($current['orders'], $previous['orders']),
                'aov_change_pct' => $this->pctChange($current['average_order_value'], $previous['average_order_value']),
                'units_change_pct' => $this->pctChange($current['units_sold'], $previous['units_sold']),
            ],
            'trend' => $this->salesTrend($range),
            'order_status_distribution' => $this->orderStatusDistribution($range),
            'top_products' => $this->topProducts($range, 10, 'revenue'),
            'top_categories' => $this->categoryPerformance($range, 10),
            'operational' => $this->operationalSnapshot(),
            'payment_health' => $this->paymentSummary($range),
            'attention' => $this->demandAttention($range, 15),
            'data_gaps' => $this->dataGaps(),
        ];
    }

    public function sales(AnalyticsDateRange $range, ?string $paymentMethod = null): array
    {
        $summary = $this->periodSummary($range, $paymentMethod);
        $prev = $this->periodSummary($range->previous(), $paymentMethod);

        return [
            'range' => $range->meta(),
            'summary' => $summary,
            'comparison' => [
                'revenue_change_pct' => $this->pctChange($summary['revenue'], $prev['revenue']),
                'orders_change_pct' => $this->pctChange($summary['orders'], $prev['orders']),
            ],
            'trend' => $this->salesTrend($range, $paymentMethod),
            'by_payment_method' => $this->revenueByPaymentMethod($range),
            'by_category' => $this->categoryPerformance($range, 25),
            'by_product' => $this->topProducts($range, 25, 'revenue'),
            'definitions' => [
                'revenue' => 'SUM(orders.grand_total) excluding CANCELLED and PAYMENT_FAILED',
                'discount_total' => 'SUM(orders.discount_total) for revenue-qualifying orders',
                'shipping_total' => 'SUM(orders.shipping_total) for revenue-qualifying orders',
                'tax_total' => 'SUM(orders.tax_total) for revenue-qualifying orders',
                'average_order_value' => 'revenue / qualifying order count',
            ],
        ];
    }

    public function orders(AnalyticsDateRange $range): array
    {
        $base = Order::query()->whereBetween('created_at', [$range->from, $range->to]);

        $total = (clone $base)->count();
        $qualifying = (clone $base)->whereNotIn('status', self::NON_REVENUE_STATUSES)->count();
        $delivered = (clone $base)->where('status', 'DELIVERED')->count();
        $cancelled = (clone $base)->where('status', 'CANCELLED')->count();
        $returned = (clone $base)->whereIn('status', ['RETURN_REQUESTED', 'RETURNED', 'REFUNDED'])->count();
        $pending = (clone $base)->whereIn('status', [
            'PENDING_PAYMENT', 'CONFIRMED', 'PROCESSING', 'PACKED', 'SHIPPED', 'OUT_FOR_DELIVERY',
        ])->count();

        $revenue = (float) (clone $base)->whereNotIn('status', self::NON_REVENUE_STATUSES)->sum('grand_total');
        $aov = $qualifying > 0 ? round($revenue / $qualifying, 2) : 0.0;

        return [
            'range' => $range->meta(),
            'summary' => [
                'total_orders' => $total,
                'qualifying_orders' => $qualifying,
                'delivered_orders' => $delivered,
                'cancelled_orders' => $cancelled,
                'returned_or_refund_path_orders' => $returned,
                'open_pipeline_orders' => $pending,
                'average_order_value' => $aov,
                'revenue' => round($revenue, 2),
            ],
            'status_distribution' => $this->orderStatusDistribution($range),
            'trend' => $this->orderTrend($range),
            'cancellation_trend' => $this->statusTrend($range, 'CANCELLED'),
            'funnel' => $this->transactionalFunnel($range),
        ];
    }

    public function products(AnalyticsDateRange $range, string $sort = 'revenue', int $limit = 50): array
    {
        $sort = in_array($sort, ['revenue', 'units', 'orders'], true) ? $sort : 'revenue';
        $rows = $this->productPerformance($range, $limit, $sort);

        $lowStock = $this->stockHealthRows('low');
        $outOfStock = $this->stockHealthRows('out');

        return [
            'range' => $range->meta(),
            'top' => $rows,
            'lowest_selling' => array_reverse($this->productPerformance($range, min(20, $limit), 'units')),
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'sort' => $sort,
        ];
    }

    public function categories(AnalyticsDateRange $range): array
    {
        return [
            'range' => $range->meta(),
            'rows' => $this->categoryPerformance($range, 100),
        ];
    }

    public function customers(AnalyticsDateRange $range): array
    {
        $customerRole = fn ($q) => $q->where('slug', 'customer');

        $totalCustomers = User::query()->whereHas('roles', $customerRole)->count();

        $newCustomers = User::query()
            ->whereHas('roles', $customerRole)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();

        $orderCounts = Order::query()
            ->select('user_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(grand_total) as spent'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('user_id')
            ->get();

        $buyers = $orderCounts->count();
        $returningInPeriod = $orderCounts->where('order_count', '>=', 2)->count();
        $orders = (int) $orderCounts->sum('order_count');
        $spent = (float) $orderCounts->sum('spent');

        // Lifetime returning: customers with >= 2 qualifying orders ever
        $lifetimeReturning = (int) Order::query()
            ->select('user_id')
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $highValue = $orderCounts
            ->sortByDesc('spent')
            ->take(10)
            ->values()
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'orders' => (int) $r->order_count,
                'spent' => round((float) $r->spent, 2),
            ])
            ->all();

        return [
            'range' => $range->meta(),
            'summary' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomers,
                'buyers_in_period' => $buyers,
                'returning_buyers_in_period' => $returningInPeriod,
                'lifetime_returning_customers' => $lifetimeReturning,
                'orders_per_buyer' => $buyers > 0 ? round($orders / $buyers, 2) : 0.0,
                'average_buyer_spend' => $buyers > 0 ? round($spent / $buyers, 2) : 0.0,
            ],
            'segments' => [
                'definitions' => [
                    'new_customer' => 'User with customer role created within the selected range',
                    'returning_buyer_in_period' => 'Customer with >= 2 revenue-qualifying orders in the selected range',
                    'lifetime_returning_customer' => 'Customer with >= 2 revenue-qualifying orders lifetime',
                    'high_value_in_period' => 'Top spenders by SUM(grand_total) in range (top 10 listed)',
                ],
                'high_value_in_period' => $highValue,
            ],
        ];
    }

    public function inventory(): array
    {
        $items = InventoryItem::query()->with('product')->get();
        $healthy = 0;
        $low = 0;
        $out = 0;
        $critical = 0;
        $totalSellable = 0;
        $rows = [];

        foreach ($items as $item) {
            $sellable = max(0, (int) $item->qty_on_hand - (int) $item->qty_reserved - (int) $item->qty_damaged);
            $threshold = (int) $item->low_stock_threshold;
            $totalSellable += $sellable;

            $status = 'healthy';
            if ($sellable <= 0) {
                $status = 'out_of_stock';
                $out++;
            } elseif ($threshold > 0 && $sellable <= max(1, (int) floor($threshold / 2))) {
                $status = 'critical';
                $critical++;
            } elseif ($sellable <= $threshold) {
                $status = 'low_stock';
                $low++;
            } else {
                $healthy++;
            }

            $rows[] = [
                'product_id' => $item->product_id,
                'sku' => $item->product?->sku,
                'name' => $item->product?->name,
                'qty_on_hand' => (int) $item->qty_on_hand,
                'sellable' => $sellable,
                'low_stock_threshold' => $threshold,
                'stock_status' => $status,
            ];
        }

        $movements = StockMovement::query()
            ->select('type', DB::raw('SUM(qty_delta) as qty'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('type')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->type,
                'qty_delta_sum' => (float) $r->qty,
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'sku_locations' => $items->count(),
                'active_products' => Product::query()->where('status', 'active')->count(),
                'total_sellable_units' => $totalSellable,
                'healthy' => $healthy,
                'low_stock' => $low,
                'critical' => $critical,
                'out_of_stock' => $out,
            ],
            'stock_health_definitions' => [
                'out_of_stock' => 'sellable <= 0',
                'critical' => 'sellable > 0 and sellable <= max(1, floor(threshold/2)) when threshold > 0',
                'low_stock' => 'sellable <= low_stock_threshold (and not critical/out)',
                'healthy' => 'otherwise',
                'threshold_source' => 'inventory_items.low_stock_threshold',
            ],
            'rows' => collect($rows)->sortBy('sellable')->values()->take(100)->all(),
            'movements_last_30_days' => $movements,
        ];
    }

    public function campaigns(): array
    {
        if (! Schema::hasColumn('orders', 'campaign_id')) {
            return [
                'supported' => false,
                'reason' => 'orders.campaign_id column missing — run Phase 17 migrations',
                'coupon_performance_available' => true,
                'hint' => 'Use GET /admin/analytics/coupons for coupon redemption performance.',
            ];
        }

        $rows = Order::query()
            ->select('campaign_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(grand_total) as revenue'))
            ->whereNotNull('campaign_id')
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('campaign_id')
            ->orderByDesc('revenue')
            ->limit(25)
            ->get()
            ->map(function ($r) {
                $c = \App\Modules\Campaign\Models\Campaign::query()->find($r->campaign_id);

                return [
                    'campaign_id' => (int) $r->campaign_id,
                    'title' => $c?->title,
                    'slug' => $c?->slug,
                    'orders' => (int) $r->orders,
                    'revenue' => round((float) $r->revenue, 2),
                ];
            })
            ->values()
            ->all();

        return [
            'supported' => true,
            'note' => 'Only orders with campaign_id set at checkout. Not inferred from coupons alone.',
            'coupon_performance_available' => true,
            'rows' => $rows,
        ];
    }

    public function coupons(AnalyticsDateRange $range): array
    {
        $rows = CouponRedemption::query()
            ->select(
                'coupon_id',
                'coupon_code',
                DB::raw('COUNT(*) as redemptions'),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('coupon_id', 'coupon_code')
            ->orderByDesc('redemptions')
            ->limit(50)
            ->get();

        $codes = $rows->pluck('coupon_code')->filter()->unique()->values()->all();
        $orderStats = [];
        if ($codes !== []) {
            $orderStats = Order::query()
                ->select(
                    'coupon_code',
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(grand_total) as revenue'),
                    DB::raw('SUM(discount_total) as discount_total'),
                )
                ->whereBetween('created_at', [$range->from, $range->to])
                ->whereNotIn('status', self::NON_REVENUE_STATUSES)
                ->whereIn('coupon_code', $codes)
                ->groupBy('coupon_code')
                ->get()
                ->keyBy('coupon_code');
        }

        return [
            'range' => $range->meta(),
            'note' => 'Coupon performance from redemptions and orders.coupon_code — not campaign attribution.',
            'rows' => $rows->map(function ($r) use ($orderStats) {
                $stats = $orderStats[$r->coupon_code] ?? null;

                return [
                    'coupon_id' => $r->coupon_id,
                    'coupon_code' => $r->coupon_code,
                    'redemptions' => (int) $r->redemptions,
                    'orders' => (int) ($stats->orders ?? 0),
                    'revenue' => round((float) ($stats->revenue ?? 0), 2),
                    'discount_total' => round((float) ($stats->discount_total ?? 0), 2),
                ];
            })->values()->all(),
        ];
    }

    public function returns(AnalyticsDateRange $range): array
    {
        $returns = ReturnRequest::query()
            ->whereBetween('created_at', [$range->from, $range->to]);

        $byStatus = (clone $returns)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $refunds = Refund::query()->whereBetween('created_at', [$range->from, $range->to]);
        $refundByStatus = (clone $refunds)
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount) as amount'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'count' => (int) $r->total,
                'amount' => round((float) $r->amount, 2),
            ])
            ->values()
            ->all();

        $refundAmount = round((float) (clone $refunds)->sum('amount'), 2);

        $trend = ReturnRequest::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'returns' => (int) $r->total])
            ->values()
            ->all();

        return [
            'range' => $range->meta(),
            'summary' => [
                'return_requests' => (int) array_sum($byStatus),
                'return_by_status' => $byStatus,
                'refund_amount' => $refundAmount,
                'refund_by_status' => $refundByStatus,
            ],
            'trend' => $trend,
        ];
    }

    public function seasonal(AnalyticsDateRange $range): array
    {
        // No commercial season taxonomy — expose monthly demand for the selected range.
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $rows = Order::query()
            ->select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'orders' => (int) $r->orders,
                'revenue' => round((float) $r->revenue, 2),
            ])
            ->values()
            ->all();

        return [
            'range' => $range->meta(),
            'taxonomy' => 'none',
            'note' => 'Commercial seasons (summer/monsoon/winter/festival) are not configured. Monthly revenue demand is shown instead. Use /admin/analytics/plants for plant-profile taxonomy.',
            'by_month' => $rows,
        ];
    }

    public function payments(AnalyticsDateRange $range): array
    {
        return [
            'range' => $range->meta(),
            'summary' => $this->paymentSummary($range),
            'by_status' => $this->paymentsByField($range, 'status'),
            'by_method' => $this->paymentsByField($range, 'method'),
            'by_provider' => $this->paymentsByField($range, 'provider'),
            'trend' => $this->paymentTrend($range),
            'definitions' => [
                'attempts' => 'COUNT(payments) created in range',
                'success' => "payments.status = 'success'",
                'failed' => "payments.status = 'failed'",
                'pending' => "payments.status = 'pending'",
                'success_rate' => 'success / attempts (null if attempts=0)',
                'refund_amount' => 'SUM(refunds.amount) in range (refunds table — not payment rows)',
            ],
        ];
    }

    public function plants(AnalyticsDateRange $range): array
    {
        $base = $this->plantSalesQuery($range);

        return [
            'range' => $range->meta(),
            'note' => 'Aggregated from order_items on revenue-qualifying orders joined to plant_profiles. Products without plant_profiles are omitted from taxonomy breakdowns.',
            'by_indoor_outdoor' => $this->plantGroup($base, 'plant_profiles.indoor_outdoor'),
            'by_plant_kind' => $this->plantGroup($base, 'plant_profiles.plant_kind'),
            'by_difficulty' => $this->plantGroup($base, 'plant_profiles.difficulty_level'),
            'by_pet_safety' => $this->plantGroup($base, 'plant_profiles.pet_safety'),
            'by_sunlight' => $this->plantGroup($base, 'plant_profiles.sunlight'),
        ];
    }

    public function reviews(AnalyticsDateRange $range): array
    {
        if (! Schema::hasTable('reviews')) {
            return [
                'range' => $range->meta(),
                'supported' => false,
                'reason' => 'reviews table not present',
            ];
        }

        $base = Review::query()->whereBetween('created_at', [$range->from, $range->to]);
        $total = (clone $base)->count();
        $avg = $total > 0 ? round((float) (clone $base)->avg('rating'), 2) : null;

        $distribution = (clone $base)
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->map(fn ($r) => ['rating' => (int) $r->rating, 'count' => (int) $r->total])
            ->values()
            ->all();

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $lowRated = Review::query()
            ->select('product_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as review_count'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) >= 2')
            ->havingRaw('AVG(rating) <= 3')
            ->orderBy('avg_rating')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                $p = Product::query()->find($r->product_id);

                return [
                    'product_id' => (int) $r->product_id,
                    'name' => $p?->name,
                    'sku' => $p?->sku,
                    'avg_rating' => round((float) $r->avg_rating, 2),
                    'review_count' => (int) $r->review_count,
                ];
            })
            ->values()
            ->all();

        return [
            'range' => $range->meta(),
            'summary' => [
                'reviews' => $total,
                'average_rating' => $avg,
                'by_status' => $byStatus,
            ],
            'rating_distribution' => $distribution,
            'low_rated_products' => $lowRated,
            'definitions' => [
                'low_rated_products' => 'Products with >= 2 reviews in range and AVG(rating) <= 3',
            ],
        ];
    }

    public function search(AnalyticsDateRange $range): array
    {
        if (! Schema::hasTable('search_events')) {
            return [
                'range' => $range->meta(),
                'supported' => false,
                'reason' => 'search_events table missing — run migrations',
            ];
        }

        $base = SearchEvent::query()->whereBetween('created_at', [$range->from, $range->to]);
        $total = (clone $base)->count();
        $zero = (clone $base)->where('results_count', 0)->count();

        $top = (clone $base)
            ->select(
                'normalized_query',
                DB::raw('COUNT(*) as searches'),
                DB::raw('AVG(results_count) as avg_results'),
                DB::raw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results'),
            )
            ->groupBy('normalized_query')
            ->orderByDesc('searches')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'query' => $r->normalized_query,
                'searches' => (int) $r->searches,
                'avg_results' => round((float) $r->avg_results, 1),
                'zero_results' => (int) $r->zero_results,
            ])
            ->values()
            ->all();

        $zeroRows = (clone $base)
            ->select('normalized_query', DB::raw('COUNT(*) as searches'))
            ->where('results_count', 0)
            ->groupBy('normalized_query')
            ->orderByDesc('searches')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'query' => $r->normalized_query,
                'searches' => (int) $r->searches,
            ])
            ->values()
            ->all();

        return [
            'range' => $range->meta(),
            'supported' => true,
            'note' => 'Server-side catalog search logging only (GET /search with q length >= 2). Not a full session funnel. Retention: operator-owned.',
            'summary' => [
                'searches' => $total,
                'zero_result_searches' => $zero,
                'zero_result_rate' => $total > 0 ? round($zero / $total, 4) : null,
            ],
            'top_searches' => $top,
            'zero_result_queries' => $zeroRows,
        ];
    }

    public function cohorts(AnalyticsDateRange $range): array
    {
        // Acquisition cohort = calendar month of customer's first revenue-qualifying order.
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', first_order_at)"
            : "DATE_FORMAT(first_order_at, '%Y-%m')";

        $firstOrders = Order::query()
            ->select('user_id', DB::raw('MIN(created_at) as first_order_at'))
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        $cohorts = DB::query()
            ->fromSub($firstOrders, 'fo')
            ->select(DB::raw("{$monthExpr} as cohort_month"), DB::raw('COUNT(*) as customers'))
            ->whereBetween('first_order_at', [$range->from, $range->to])
            ->groupBy('cohort_month')
            ->orderBy('cohort_month')
            ->get()
            ->map(fn ($r) => [
                'cohort_month' => $r->cohort_month,
                'customers' => (int) $r->customers,
            ])
            ->values()
            ->all();

        return [
            'range' => $range->meta(),
            'note' => 'Basic acquisition cohorts by month of first revenue-qualifying order within the selected range. Retention months (M1/M2/M3 repurchase rates) are not expanded here to avoid heavy cross joins on large catalogs; use customer analytics returning buyers for period-level retention signals.',
            'definitions' => [
                'cohort_month' => 'Calendar month of first revenue-qualifying order',
                'customers' => 'Distinct users whose first qualifying order falls in that month AND within the selected range filter on first_order_at',
            ],
            'rows' => $cohorts,
        ];
    }

    public function attention(AnalyticsDateRange $range): array
    {
        return [
            'range' => $range->meta(),
            'high_demand_low_stock' => $this->demandAttention($range, 30),
            'definitions' => [
                'high_demand_low_stock' => 'Products in top sellers by units in range that currently have sellable stock <= low_stock_threshold (live inventory, not historical). Advisory only — does not create purchase orders.',
            ],
        ];
    }

    public function exportRows(string $type, AnalyticsDateRange $range): array
    {
        return match ($type) {
            'sales' => $this->salesTrend($range)['points'],
            'products' => $this->topProducts($range, 200, 'revenue'),
            'orders_status' => $this->orderStatusDistribution($range),
            'returns' => $this->returns($range)['trend'],
            'coupons' => $this->coupons($range)['rows'],
            'payments' => $this->paymentsByField($range, 'status'),
            'search' => $this->search($range)['top_searches'] ?? [],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionalFunnel(AnalyticsDateRange $range): array
    {
        $orders = Order::query()->whereBetween('created_at', [$range->from, $range->to]);
        $created = (clone $orders)->count();
        $paidLike = (clone $orders)->whereNotIn('status', ['PENDING_PAYMENT', 'PAYMENT_FAILED', 'CANCELLED'])->count();
        $paymentFailed = (clone $orders)->where('status', 'PAYMENT_FAILED')->count();
        $delivered = (clone $orders)->where('status', 'DELIVERED')->count();

        $paymentAttempts = 0;
        $paymentSuccess = 0;
        if (Schema::hasTable('payments')) {
            $paymentAttempts = Payment::query()->whereBetween('created_at', [$range->from, $range->to])->count();
            $paymentSuccess = Payment::query()
                ->whereBetween('created_at', [$range->from, $range->to])
                ->where('status', 'success')
                ->count();
        }

        return [
            'supported' => 'partial',
            'note' => 'Behavioral stages (product view → add to cart → checkout start) are NOT tracked. Stages below use transactional tables only.',
            'missing_stages' => [
                'product_view',
                'add_to_cart',
                'checkout_start',
                'payment_initiated_client_event',
            ],
            'stages' => [
                [
                    'key' => 'orders_created',
                    'label' => 'Orders created',
                    'count' => $created,
                ],
                [
                    'key' => 'payment_rows',
                    'label' => 'Payment rows created',
                    'count' => $paymentAttempts,
                ],
                [
                    'key' => 'payments_success',
                    'label' => 'Payments success',
                    'count' => $paymentSuccess,
                ],
                [
                    'key' => 'orders_past_pending',
                    'label' => 'Orders not pending/failed/cancelled',
                    'count' => $paidLike,
                ],
                [
                    'key' => 'payment_failed_orders',
                    'label' => 'Orders PAYMENT_FAILED',
                    'count' => $paymentFailed,
                ],
                [
                    'key' => 'delivered',
                    'label' => 'Orders DELIVERED',
                    'count' => $delivered,
                ],
            ],
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function paymentSummary(AnalyticsDateRange $range): array
    {
        if (! Schema::hasTable('payments')) {
            return [
                'attempts' => 0,
                'success' => 0,
                'failed' => 0,
                'pending' => 0,
                'success_rate' => null,
                'refund_amount' => round((float) Refund::query()->whereBetween('created_at', [$range->from, $range->to])->sum('amount'), 2),
            ];
        }

        $base = Payment::query()->whereBetween('created_at', [$range->from, $range->to]);
        $attempts = (clone $base)->count();
        $success = (clone $base)->where('status', 'success')->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $pending = (clone $base)->where('status', 'pending')->count();

        return [
            'attempts' => $attempts,
            'success' => $success,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $attempts > 0 ? round($success / $attempts, 4) : null,
            'refund_amount' => round((float) Refund::query()->whereBetween('created_at', [$range->from, $range->to])->sum('amount'), 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentsByField(AnalyticsDateRange $range, string $field): array
    {
        if (! Schema::hasTable('payments') || ! in_array($field, ['status', 'method', 'provider'], true)) {
            return [];
        }

        return Payment::query()
            ->select($field, DB::raw('COUNT(*) as total'), DB::raw('SUM(amount) as amount'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy($field)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                $field => $r->{$field},
                'count' => (int) $r->total,
                'amount' => round((float) $r->amount, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentTrend(AnalyticsDateRange $range): array
    {
        if (! Schema::hasTable('payments')) {
            return [];
        }

        return Payment::query()
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as attempts'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'attempts' => (int) $r->attempts,
                'success' => (int) $r->success,
                'failed' => (int) $r->failed,
            ])
            ->values()
            ->all();
    }

    private function plantSalesQuery(AnalyticsDateRange $range)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('plant_profiles', 'plant_profiles.product_id', '=', 'products.id')
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotIn('orders.status', self::NON_REVENUE_STATUSES);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plantGroup($base, string $column): array
    {
        return (clone $base)
            ->select(
                DB::raw("{$column} as dimension"),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as orders'),
            )
            ->groupBy('dimension')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'dimension' => $r->dimension ?: 'unknown',
                'units' => (int) $r->units,
                'revenue' => round((float) $r->revenue, 2),
                'orders' => (int) $r->orders,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demandAttention(AnalyticsDateRange $range, int $limit): array
    {
        $top = $this->productPerformance($range, $limit, 'units');
        $out = [];
        foreach ($top as $row) {
            $item = InventoryItem::query()->where('product_id', $row['product_id'])->first();
            if (! $item) {
                continue;
            }
            $sellable = max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);
            if ($sellable > $item->low_stock_threshold) {
                continue;
            }
            $out[] = [
                'product_id' => $row['product_id'],
                'name' => $row['name'] ?? null,
                'sku' => $row['sku'] ?? null,
                'units_sold' => $row['units'] ?? 0,
                'revenue' => $row['revenue'] ?? 0,
                'sellable' => $sellable,
                'low_stock_threshold' => (int) $item->low_stock_threshold,
                'signal' => 'HIGH_DEMAND_LOW_STOCK',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key: string, status: string, detail: string}>
     */
    private function dataGaps(): array
    {
        return [
            ['key' => 'product_views', 'status' => Schema::hasTable('product_views') ? 'EXISTS' : 'MISSING', 'detail' => 'product_views table + POST /product-views (Phase 16)'],
            ['key' => 'cart_abandonment_funnel', 'status' => 'PARTIAL', 'detail' => 'Abandoned cart automation job (not full browser funnel events)'],
            ['key' => 'campaign_attribution', 'status' => Schema::hasColumn('orders', 'campaign_id') ? 'PARTIAL' : 'MISSING', 'detail' => 'orders.campaign_id at checkout when provided'],
            ['key' => 'commercial_seasons', 'status' => 'MISSING', 'detail' => 'Monthly proxy only; no season taxonomy'],
            ['key' => 'search_logging', 'status' => Schema::hasTable('search_events') ? 'EXISTS' : 'MISSING', 'detail' => 'Server-side search_events when q length >= 2'],
            ['key' => 'payment_transactional', 'status' => 'EXISTS', 'detail' => 'payments + orders tables'],
            ['key' => 'plant_taxonomy_sales', 'status' => 'EXISTS', 'detail' => '/admin/analytics/plants'],
            ['key' => 'stock_alerts', 'status' => Schema::hasTable('stock_alert_subscriptions') ? 'EXISTS' : 'MISSING', 'detail' => 'Back-in-stock subscriptions (Phase 16)'],
            ['key' => 'crm_segments', 'status' => Schema::hasTable('customer_segments') ? 'EXISTS' : 'MISSING', 'detail' => 'Phase 17 customer_segments'],
            ['key' => 'marketing_automations', 'status' => Schema::hasTable('marketing_automations') ? 'EXISTS' : 'MISSING', 'detail' => 'Phase 17 marketing_automations + deliveries'],
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function periodSummary(AnalyticsDateRange $range, ?string $paymentMethod = null): array
    {
        $q = Order::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotIn('status', self::NON_REVENUE_STATUSES);

        if ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        }

        $orders = (clone $q)->count();
        $revenue = (float) (clone $q)->sum('grand_total');
        $discount = (float) (clone $q)->sum('discount_total');
        $shipping = (float) (clone $q)->sum('shipping_total');
        $tax = (float) (clone $q)->sum('tax_total');
        $delivered = Order::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where('status', 'DELIVERED')
            ->count();
        $cancelled = Order::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where('status', 'CANCELLED')
            ->count();

        $units = (int) OrderItem::query()
            ->whereHas('order', function ($oq) use ($range, $paymentMethod) {
                $oq->whereBetween('created_at', [$range->from, $range->to])
                    ->whereNotIn('status', self::NON_REVENUE_STATUSES);
                if ($paymentMethod) {
                    $oq->where('payment_method', $paymentMethod);
                }
            })
            ->sum('quantity');

        $refundAmount = (float) Refund::query()
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum('amount');

        return [
            'orders' => $orders,
            'revenue' => round($revenue, 2),
            'discount_total' => round($discount, 2),
            'shipping_total' => round($shipping, 2),
            'tax_total' => round($tax, 2),
            'units_sold' => $units,
            'average_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
            'delivered_orders' => $delivered,
            'cancelled_orders' => $cancelled,
            'refund_amount' => round($refundAmount, 2),
        ];
    }

    private function salesTrend(AnalyticsDateRange $range, ?string $paymentMethod = null): array
    {
        $granularity = $range->granularity();
        $driver = DB::connection()->getDriverName();

        $bucket = match ($granularity) {
            'week' => $driver === 'sqlite'
                ? "strftime('%Y-W%W', created_at)"
                : "DATE_FORMAT(created_at, '%x-W%v')",
            'month' => $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')",
            default => $driver === 'sqlite'
                ? 'DATE(created_at)'
                : 'DATE(created_at)',
        };

        $q = Order::query()
            ->select(
                DB::raw("{$bucket} as bucket"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('bucket')
            ->orderBy('bucket');

        if ($paymentMethod) {
            $q->where('payment_method', $paymentMethod);
        }

        $points = $q->get()->map(fn ($r) => [
            'bucket' => $r->bucket,
            'orders' => (int) $r->orders,
            'revenue' => round((float) $r->revenue, 2),
        ])->values()->all();

        return [
            'granularity' => $granularity,
            'points' => $points,
        ];
    }

    private function orderTrend(AnalyticsDateRange $range): array
    {
        $rows = Order::query()
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as orders'),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($r) => [
            'day' => $r->day,
            'orders' => (int) $r->orders,
        ])->values()->all();
    }

    private function statusTrend(AnalyticsDateRange $range, string $status): array
    {
        return Order::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->where('status', $status)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'count' => (int) $r->total])
            ->values()
            ->all();
    }

    private function orderStatusDistribution(AnalyticsDateRange $range): array
    {
        return Order::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'count' => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    private function revenueByPaymentMethod(AnalyticsDateRange $range): array
    {
        return Order::query()
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
            )
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotIn('status', self::NON_REVENUE_STATUSES)
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'payment_method' => $r->payment_method ?? 'unknown',
                'orders' => (int) $r->orders,
                'revenue' => round((float) $r->revenue, 2),
            ])
            ->values()
            ->all();
    }

    private function topProducts(AnalyticsDateRange $range, int $limit, string $sort): array
    {
        return $this->productPerformance($range, $limit, $sort);
    }

    private function productPerformance(AnalyticsDateRange $range, int $limit, string $sort): array
    {
        $orderCol = match ($sort) {
            'units' => 'units',
            'orders' => 'order_count',
            default => 'revenue',
        };

        $rows = OrderItem::query()
            ->select(
                'product_id',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(sku) as sku'),
                DB::raw('SUM(quantity) as units'),
                DB::raw('SUM(line_total) as revenue'),
                DB::raw('COUNT(DISTINCT order_id) as order_count'),
            )
            ->whereHas('order', function ($q) use ($range) {
                $q->whereBetween('created_at', [$range->from, $range->to])
                    ->whereNotIn('status', self::NON_REVENUE_STATUSES);
            })
            ->groupBy('product_id')
            ->orderByDesc($orderCol)
            ->limit($limit)
            ->get();

        $productIds = $rows->pluck('product_id')->filter()->all();
        $stock = [];
        if ($productIds !== []) {
            $stock = InventoryItem::query()
                ->whereIn('product_id', $productIds)
                ->get()
                ->groupBy('product_id')
                ->map(function ($items) {
                    return $items->sum(fn ($i) => max(0, $i->qty_on_hand - $i->qty_reserved - $i->qty_damaged));
                })
                ->all();
        }

        return $rows->map(fn ($r) => [
            'product_id' => $r->product_id,
            'name' => $r->name,
            'sku' => $r->sku,
            'units' => (int) $r->units,
            'revenue' => round((float) $r->revenue, 2),
            'orders' => (int) $r->order_count,
            'stock' => (int) ($stock[$r->product_id] ?? 0),
        ])->values()->all();
    }

    private function categoryPerformance(AnalyticsDateRange $range, int $limit): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_categories', 'product_categories.product_id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'product_categories.category_id')
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotIn('orders.status', self::NON_REVENUE_STATUSES)
            ->whereNull('orders.deleted_at')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc(DB::raw('SUM(order_items.line_total)'))
            ->limit($limit)
            ->select(
                'categories.id as category_id',
                'categories.name',
                'categories.slug',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as orders'),
            )
            ->get();

        return $rows->map(fn ($r) => [
            'category_id' => (int) $r->category_id,
            'name' => $r->name,
            'slug' => $r->slug,
            'units' => (int) $r->units,
            'revenue' => round((float) $r->revenue, 2),
            'orders' => (int) $r->orders,
            'average_order_value' => $r->orders > 0 ? round(((float) $r->revenue) / (int) $r->orders, 2) : 0.0,
        ])->values()->all();
    }

    private function operationalSnapshot(): array
    {
        return [
            'pending_payment' => Order::query()->where('status', 'PENDING_PAYMENT')->count(),
            'awaiting_picking' => Order::query()->where('status', 'CONFIRMED')->count(),
            'being_picked' => Order::query()->where('status', 'PROCESSING')->count(),
            'packed_ready' => Order::query()->where('status', 'PACKED')->count(),
            'in_transit' => Order::query()->where('status', 'SHIPPED')->count(),
            'delivery_failed' => Order::query()->where('status', 'DELIVERY_FAILED')->count(),
            'orders_to_ship' => Order::query()->whereIn('status', ['CONFIRMED', 'PROCESSING', 'PACKED'])->count(),
            'out_for_delivery' => Order::query()->where('status', 'OUT_FOR_DELIVERY')->count(),
            'return_requested' => Order::query()->where('status', 'RETURN_REQUESTED')->count(),
            'low_stock_items' => InventoryItem::query()
                ->get()
                ->filter(function (InventoryItem $item) {
                    $sellable = max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);

                    return $sellable <= $item->low_stock_threshold;
                })
                ->count(),
        ];
    }

    private function stockHealthRows(string $kind): array
    {
        return InventoryItem::query()
            ->with('product')
            ->get()
            ->filter(function (InventoryItem $item) use ($kind) {
                $sellable = max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);
                if ($kind === 'out') {
                    return $sellable <= 0;
                }

                return $sellable > 0 && $sellable <= $item->low_stock_threshold;
            })
            ->take(50)
            ->map(fn (InventoryItem $item) => [
                'product_id' => $item->product_id,
                'sku' => $item->product?->sku,
                'name' => $item->product?->name,
                'sellable' => max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged),
                'threshold' => (int) $item->low_stock_threshold,
            ])
            ->values()
            ->all();
    }

    private function pctChange(float|int $current, float|int $previous): ?float
    {
        $previous = (float) $previous;
        $current = (float) $current;
        if (abs($previous) < 0.00001) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
