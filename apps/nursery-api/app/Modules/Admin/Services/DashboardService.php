<?php

namespace App\Modules\Admin\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\ReturnRequest;
use App\Modules\Order\Services\ReturnService;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function summary(): array
    {
        $today = now()->toDateString();

        $salesToday = (float) Order::query()
            ->whereDate('created_at', $today)
            ->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED'])
            ->sum('grand_total');

        $ordersToday = Order::query()->whereDate('created_at', $today)->count();
        $pendingPayment = Order::query()->where('status', 'PENDING_PAYMENT')->count();
        $toShip = Order::query()->whereIn('status', ['CONFIRMED', 'PROCESSING', 'PACKED'])->count();
        $paymentFailedToday = Order::query()
            ->whereDate('created_at', $today)
            ->where('status', 'PAYMENT_FAILED')
            ->count();
        $deliveryFailedOpen = Order::query()->where('status', 'DELIVERY_FAILED')->count();
        $openReturns = ReturnRequest::query()
            ->whereIn('status', ReturnService::OPEN_STATUSES)
            ->count();

        $failedPaymentsToday = 0;
        if (Schema::hasTable('payments')) {
            $failedPaymentsToday = Payment::query()
                ->whereDate('created_at', $today)
                ->where('status', 'failed')
                ->count();
        }

        $failedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $lowStock = InventoryItem::query()
            ->select('id', 'product_id', 'qty_on_hand', 'qty_reserved', 'qty_damaged', 'low_stock_threshold')
            ->get()
            ->filter(function (InventoryItem $item) {
                $sellable = max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);

                return $sellable <= $item->low_stock_threshold;
            })
            ->count();

        $customers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'customer'))
            ->count();

        $activeProducts = Product::query()->where('status', 'active')->count();

        $sales7d = Order::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(grand_total) as total'), DB::raw('COUNT(*) as orders'))
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED'])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'total' => (float) $r->total,
                'orders' => (int) $r->orders,
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                'sales_today' => $salesToday,
                'orders_today' => $ordersToday,
                'pending_payment' => $pendingPayment,
                'orders_to_ship' => $toShip,
                'low_stock_items' => $lowStock,
                'customers' => $customers,
                'active_products' => $activeProducts,
                'payment_failed_orders_today' => $paymentFailedToday,
                'failed_payments_today' => $failedPaymentsToday,
                'delivery_failed_open' => $deliveryFailedOpen,
                'open_returns' => $openReturns,
                'failed_jobs' => $failedJobs,
            ],
            'definitions' => [
                'sales_today' => 'SUM(orders.grand_total) for orders created today excluding CANCELLED and PAYMENT_FAILED (gross order value, not profit, not refund-adjusted)',
                'orders_today' => 'COUNT of all orders created today (any status)',
                'pending_payment' => 'Orders currently in PENDING_PAYMENT (all-time open)',
                'failed_payments_today' => 'payments.status=failed created today',
            ],
            'sales_last_7_days' => $sales7d,
        ];
    }
}
