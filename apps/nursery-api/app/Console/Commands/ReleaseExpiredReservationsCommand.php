<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\OrderStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired-reservations {--hours=24 : Pending payment age in hours} {--limit=500 : Max orders per run}';

    protected $description = 'Release inventory reserved for abandoned PENDING_PAYMENT orders';

    public function handle(InventoryService $inventory, OrderStateMachine $stateMachine): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $cutoff = now()->subHours($hours);

        $orders = Order::query()
            ->with('items')
            ->where('status', 'PENDING_PAYMENT')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $released = 0;
        foreach ($orders as $order) {
            $lines = $order->items->map(fn (OrderItem $i) => [
                'product_id' => $i->product_id,
                'quantity' => $i->quantity,
                'variant_id' => $i->product_variant_id,
            ])->all();

            try {
                DB::transaction(function () use ($inventory, $stateMachine, $order, $lines) {
                    $locked = Order::query()->with('items')->whereKey($order->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'PENDING_PAYMENT') {
                        return;
                    }

                    // Empty carts still need status cleanup (QA-12).
                    if ($lines !== []) {
                        // MUST use reference_type "order" — same as place/cancel/payment-fail —
                        // so a later cancel cannot over-release another order's reservation.
                        $inventory->release($lines, 'order', $locked->id, null);
                    }

                    $stateMachine->transition(
                        $locked,
                        'PAYMENT_FAILED',
                        null,
                        'Reservation expired (unpaid)',
                    );
                });
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
