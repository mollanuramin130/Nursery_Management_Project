<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations {--hours=24 : Pending payment age in hours}';

    protected $description = 'Release inventory reserved for abandoned PENDING_PAYMENT orders';

    public function handle(InventoryService $inventory): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $orders = Order::query()
            ->with('items')
            ->where('status', 'PENDING_PAYMENT')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $released = 0;
        foreach ($orders as $order) {
            $lines = $order->items->map(fn (OrderItem $i) => [
                'product_id' => $i->product_id,
                'quantity' => $i->quantity,
                'variant_id' => $i->product_variant_id,
            ])->all();

            if ($lines === []) {
                continue;
            }

            try {
                $inventory->release($lines, 'order_expired', $order->id, null);
                $order->status = 'PAYMENT_FAILED';
                $order->cancel_reason = 'Reservation expired (unpaid)';
                $order->save();
                $released++;
            } catch (\Throwable $e) {
                Log::warning('inventory.release_expired_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Released reservations for {$released} order(s).");

        return self::SUCCESS;
    }
}
