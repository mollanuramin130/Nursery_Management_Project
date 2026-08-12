<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inter-warehouse stock transfers — atomic complete; history via stock_movements.
 */
class StockTransferService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function list(?string $status = null, int $perPage = 30, int $page = 1): array
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'items'])
            ->orderByDesc('id');
        if ($status) {
            $query->where('status', $status);
        }
        $paginator = $query->paginate(min(100, max(1, $perPage)), ['*'], 'page', max(1, $page));
        $data = collect($paginator->items())->map(fn (StockTransfer $t) => $this->present($t))->values()->all();

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

    public function show(int $id): array
    {
        $t = StockTransfer::query()->with(['fromWarehouse', 'toWarehouse', 'items.product'])->find($id);
        if (! $t) {
            throw new NotFoundHttpException('Transfer not found');
        }

        return $this->present($t, true);
    }

    public function create(array $payload, ?int $actorId): array
    {
        if ((int) $payload['from_warehouse_id'] === (int) $payload['to_warehouse_id']) {
            throw new ApiException('Source and destination warehouses must differ', 422, 'VALIDATION_ERROR');
        }
        foreach (['from_warehouse_id', 'to_warehouse_id'] as $key) {
            $wh = Warehouse::query()->find($payload[$key]);
            if (! $wh || ($wh->status ?? 'active') !== 'active') {
                throw new ApiException('Warehouse not found or inactive', 422, 'VALIDATION_ERROR');
            }
        }

        return DB::transaction(function () use ($payload, $actorId) {
            $number = $payload['transfer_number'] ?? ('TR-'.now()->format('Ymd').'-'.str_pad((string) (StockTransfer::query()->count() + 1), 4, '0', STR_PAD_LEFT));
            $transfer = StockTransfer::query()->create([
                'transfer_number' => $number,
                'from_warehouse_id' => $payload['from_warehouse_id'],
                'to_warehouse_id' => $payload['to_warehouse_id'],
                'status' => 'draft',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            foreach ($payload['items'] as $line) {
                StockTransferItem::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['variant_id'] ?? null,
                    'quantity' => (int) $line['quantity'],
                ]);
            }

            AuditLogger::log('stock_transfer.create', 'stock_transfer', $transfer->id, null, $transfer->toArray(), $actorId);

            return $this->show($transfer->id);
        });
    }

    public function ship(int $id, ?int $actorId): array
    {
        return DB::transaction(function () use ($id, $actorId) {
            $t = StockTransfer::query()->with('items')->whereKey($id)->lockForUpdate()->first();
            if (! $t) {
                throw new NotFoundHttpException('Transfer not found');
            }
            if ($t->status !== 'draft') {
                throw new ApiException('Only draft transfers can be shipped', 409, 'CONFLICT');
            }

            foreach ($t->items as $line) {
                $from = InventoryItem::query()
                    ->where('warehouse_id', $t->from_warehouse_id)
                    ->where('product_id', $line->product_id)
                    ->when(
                        $line->product_variant_id,
                        fn ($q) => $q->where('product_variant_id', $line->product_variant_id),
                        fn ($q) => $q->whereNull('product_variant_id'),
                    )
                    ->lockForUpdate()
                    ->first();
                $available = $from ? max(0, (int) $from->qty_on_hand - (int) $from->qty_reserved - (int) $from->qty_damaged) : 0;
                if ($available < (int) $line->quantity) {
                    throw new ApiException('Insufficient sellable stock at source for product '.$line->product_id, 409, 'CONFLICT');
                }
            }

            $t->status = 'in_transit';
            $t->shipped_at = now();
            $t->save();
            AuditLogger::log('stock_transfer.ship', 'stock_transfer', $id, null, ['status' => 'in_transit'], $actorId);

            return $this->show($id);
        });
    }

    public function complete(int $id, ?int $actorId): array
    {
        return DB::transaction(function () use ($id, $actorId) {
            $t = StockTransfer::query()->with('items')->whereKey($id)->lockForUpdate()->first();
            if (! $t) {
                throw new NotFoundHttpException('Transfer not found');
            }
            if (! in_array($t->status, ['draft', 'in_transit'], true)) {
                throw new ApiException('Transfer cannot be completed from status '.$t->status, 409, 'CONFLICT');
            }

            foreach ($t->items as $line) {
                $qty = (int) $line->quantity;
                $refId = (int) ($t->id * 1000 + $line->id);

                $this->inventory->adjust([
                    'warehouse_id' => $t->from_warehouse_id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->product_variant_id,
                    'adjustment' => -$qty,
                    'reason' => 'transfer_out',
                    'note' => "Transfer {$t->transfer_number} out",
                    'reference_type' => 'stock_transfer_out',
                    'reference_id' => $refId,
                ], $actorId);

                $this->inventory->adjust([
                    'warehouse_id' => $t->to_warehouse_id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->product_variant_id,
                    'adjustment' => $qty,
                    'reason' => 'transfer_in',
                    'note' => "Transfer {$t->transfer_number} in",
                    'reference_type' => 'stock_transfer_in',
                    'reference_id' => $refId,
                ], $actorId);
            }

            $t->status = 'completed';
            $t->completed_at = now();
            if (! $t->shipped_at) {
                $t->shipped_at = now();
            }
            $t->save();
            AuditLogger::log('stock_transfer.complete', 'stock_transfer', $id, null, ['status' => 'completed'], $actorId);

            return $this->show($id);
        });
    }

    public function cancel(int $id, ?int $actorId): array
    {
        return DB::transaction(function () use ($id, $actorId) {
            $t = StockTransfer::query()->whereKey($id)->lockForUpdate()->first();
            if (! $t) {
                throw new NotFoundHttpException('Transfer not found');
            }
            if (in_array($t->status, ['completed', 'cancelled'], true)) {
                throw new ApiException('Transfer cannot be cancelled', 409, 'CONFLICT');
            }
            // in_transit cancel allowed only before complete (no stock moved yet in this model until complete)
            $before = $t->status;
            $t->status = 'cancelled';
            $t->save();
            AuditLogger::log('stock_transfer.cancel', 'stock_transfer', $id, ['status' => $before], ['status' => 'cancelled'], $actorId);

            return $this->show($id);
        });
    }

    private function present(StockTransfer $t, bool $detail = false): array
    {
        $items = $t->items->map(fn (StockTransferItem $i) => [
            'id' => $i->id,
            'product_id' => $i->product_id,
            'product_name' => $i->relationLoaded('product') ? $i->product?->name : null,
            'sku' => $i->relationLoaded('product') ? $i->product?->sku : null,
            'product_variant_id' => $i->product_variant_id,
            'quantity' => (int) $i->quantity,
        ])->values()->all();

        return [
            'id' => $t->id,
            'transfer_number' => $t->transfer_number,
            'from_warehouse_id' => $t->from_warehouse_id,
            'from_warehouse_code' => $t->fromWarehouse?->code,
            'to_warehouse_id' => $t->to_warehouse_id,
            'to_warehouse_code' => $t->toWarehouse?->code,
            'status' => $t->status,
            'notes' => $t->notes,
            'item_count' => count($items),
            'items' => $detail || true ? $items : $items,
            'shipped_at' => optional($t->shipped_at)?->toIso8601String(),
            'completed_at' => optional($t->completed_at)?->toIso8601String(),
            'created_at' => optional($t->created_at)?->toIso8601String(),
            'actions' => [
                'can_ship' => $t->status === 'draft',
                'can_complete' => in_array($t->status, ['draft', 'in_transit'], true),
                'can_cancel' => in_array($t->status, ['draft', 'in_transit'], true),
            ],
        ];
    }
}
