<?php

namespace App\Modules\Admin\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function report(string $type, ?string $from = null, ?string $to = null): array
    {
        $fromAt = $from ? now()->parse($from)->startOfDay() : now()->subDays(30)->startOfDay();
        $toAt = $to ? now()->parse($to)->endOfDay() : now()->endOfDay();

        return match ($type) {
            'sales' => $this->sales($fromAt, $toAt),
            'inventory' => $this->inventory(),
            'top_products' => $this->topProducts($fromAt, $toAt),
            default => throw new ApiException('Unknown report type', 404, 'NOT_FOUND'),
        };
    }

    private function sales($fromAt, $toAt): array
    {
        $rows = Order::query()
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('SUM(discount_total) as discounts'),
            )
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED'])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'type' => 'sales',
            'from' => $fromAt->toDateString(),
            'to' => $toAt->toDateString(),
            'totals' => [
                'orders' => (int) $rows->sum('orders'),
                'revenue' => (float) $rows->sum('revenue'),
                'discounts' => (float) $rows->sum('discounts'),
            ],
            'series' => $rows->map(fn ($r) => [
                'day' => $r->day,
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ])->values()->all(),
        ];
    }

    private function inventory(): array
    {
        $items = InventoryItem::query()->with(['product', 'warehouse'])->get();

        $low = [];
        $totalSellable = 0;
        foreach ($items as $item) {
            $sellable = max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);
            $totalSellable += $sellable;
            if ($sellable <= $item->low_stock_threshold) {
                $low[] = [
                    'product_id' => $item->product_id,
                    'sku' => $item->product?->sku,
                    'name' => $item->product?->name,
                    'warehouse' => $item->warehouse?->code,
                    'sellable' => $sellable,
                    'threshold' => $item->low_stock_threshold,
                ];
            }
        }

        return [
            'type' => 'inventory',
            'totals' => [
                'sku_locations' => $items->count(),
                'total_sellable_units' => $totalSellable,
                'low_stock_count' => count($low),
            ],
            'low_stock' => $low,
        ];
    }

    private function topProducts($fromAt, $toAt): array
    {
        $rows = OrderItem::query()
            ->select(
                'product_id',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(sku) as sku'),
                DB::raw('SUM(quantity) as units'),
                DB::raw('SUM(line_total) as revenue'),
            )
            ->whereHas('order', function ($q) use ($fromAt, $toAt) {
                $q->whereBetween('created_at', [$fromAt, $toAt])
                    ->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED']);
            })
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit(20)
            ->get();

        return [
            'type' => 'top_products',
            'from' => $fromAt->toDateString(),
            'to' => $toAt->toDateString(),
            'rows' => $rows->map(fn ($r) => [
                'product_id' => $r->product_id,
                'name' => $r->name,
                'sku' => $r->sku,
                'units' => (int) $r->units,
                'revenue' => (float) $r->revenue,
            ])->values()->all(),
        ];
    }
}
