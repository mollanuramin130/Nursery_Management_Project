<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Services\StockAlertService;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InventoryService
{
    public function sellableQty(int $productId, ?int $variantId = null): int
    {
        $query = InventoryItem::query()->where('product_id', $productId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        return (int) $query->get()->sum(function (InventoryItem $item) {
            return $this->sellable($item);
        });
    }

    public function sellable(InventoryItem $item): int
    {
        return max(0, (int) $item->qty_on_hand - (int) $item->qty_reserved - (int) $item->qty_damaged);
    }

    public function assertAvailable(int $productId, int $quantity, ?int $variantId = null): void
    {
        $available = $this->sellableQty($productId, $variantId);
        if ($quantity > $available) {
            throw ApiException::inventoryInsufficient($available);
        }
    }

    /**
     * @param  list<array{product_id:int, quantity:int, variant_id?:int|null}>  $lines
     */
    public function reserve(array $lines, string $referenceType, int $referenceId, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($lines, $referenceType, $referenceId, $actorUserId) {
            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                $productId = (int) $line['product_id'];
                $variantId = $line['variant_id'] ?? null;
                if ($this->hasMovement('reserve', $referenceType, $referenceId, $productId, $variantId, $qty)) {
                    continue;
                }
                $item = $this->lockDefaultItem($productId, $variantId);
                $sellable = $this->sellable($item);
                if ($qty > $sellable) {
                    throw ApiException::inventoryInsufficient($sellable);
                }
                $before = (int) $item->qty_on_hand;
                $item->qty_reserved += $qty;
                $item->version = (int) $item->version + 1;
                $item->save();

                $this->writeMovement($item, 'reserve', $qty, $referenceType, $referenceId, null, $actorUserId, $before, $before, [
                    'reserved_before' => (int) $item->qty_reserved - $qty,
                    'reserved_after' => (int) $item->qty_reserved,
                ]);
            }
        });
    }

    /**
     * @param  list<array{product_id:int, quantity:int, variant_id?:int|null}>  $lines
     */
    public function commit(array $lines, string $referenceType, int $referenceId, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($lines, $referenceType, $referenceId, $actorUserId) {
            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                $productId = (int) $line['product_id'];
                $variantId = $line['variant_id'] ?? null;
                if ($this->hasMovement('sale', $referenceType, $referenceId, $productId, $variantId, -$qty)) {
                    continue;
                }
                $item = $this->lockDefaultItem($productId, $variantId);
                $before = (int) $item->qty_on_hand;
                $item->qty_on_hand = max(0, $item->qty_on_hand - $qty);
                $item->qty_reserved = max(0, $item->qty_reserved - $qty);
                $item->version = (int) $item->version + 1;
                $item->save();

                $this->writeMovement($item, 'sale', -$qty, $referenceType, $referenceId, null, $actorUserId, $before, (int) $item->qty_on_hand);
            }
        });
    }

    /**
     * @param  list<array{product_id:int, quantity:int, variant_id?:int|null}>  $lines
     */
    public function release(array $lines, string $referenceType, int $referenceId, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($lines, $referenceType, $referenceId, $actorUserId) {
            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                $productId = (int) $line['product_id'];
                $variantId = $line['variant_id'] ?? null;
                if ($this->hasMovement('release', $referenceType, $referenceId, $productId, $variantId, -$qty)) {
                    continue;
                }
                // Do not release if already committed (sale recorded).
                if ($this->hasMovement('sale', $referenceType, $referenceId, $productId, $variantId)) {
                    continue;
                }
                $item = $this->lockDefaultItem($productId, $variantId);
                $before = (int) $item->qty_on_hand;
                $item->qty_reserved = max(0, $item->qty_reserved - $qty);
                $item->version = (int) $item->version + 1;
                $item->save();

                $this->writeMovement($item, 'release', -$qty, $referenceType, $referenceId, null, $actorUserId, $before, $before, [
                    'reserved_before' => (int) $item->qty_reserved + $qty,
                    'reserved_after' => (int) $item->qty_reserved,
                ]);
            }
        });
    }

    private function hasMovement(
        string $type,
        string $referenceType,
        int $referenceId,
        int $productId,
        ?int $variantId = null,
        ?int $qtyDelta = null,
    ): bool {
        $q = StockMovement::query()
            ->where('type', $type)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('product_id', $productId)
            ->when(
                $variantId,
                fn ($query) => $query->where('product_variant_id', $variantId),
                fn ($query) => $query->whereNull('product_variant_id'),
            );
        if ($qtyDelta !== null) {
            $q->where('qty_delta', $qtyDelta);
        }

        return $q->exists();
    }

    private function lockDefaultItem(int $productId, ?int $variantId): InventoryItem
    {
        $query = InventoryItem::query()
            ->where('product_id', $productId)
            ->when(
                $variantId,
                fn ($q) => $q->where('product_variant_id', $variantId),
                fn ($q) => $q->whereNull('product_variant_id'),
            )
            ->orderByDesc('qty_on_hand')
            ->lockForUpdate();

        $item = $query->first();
        if (! $item) {
            throw ApiException::inventoryInsufficient(0);
        }

        return $item;
    }

    public function list(
        ?int $warehouseId = null,
        ?string $q = null,
        ?bool $lowStockOnly = null,
        ?string $status = null,
        int $perPage = 50,
        int $page = 1,
    ): array {
        $query = InventoryItem::query()
            ->with(['product.categories', 'product.images', 'warehouse'])
            ->orderBy('id');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($q) {
            $query->whereHas('product', function ($productQuery) use ($q) {
                $productQuery->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            });
        }

        // Status/low-stock filters need sellable math — filter in PHP after load for correctness on SQLite.
        $all = $query->get()->map(function (InventoryItem $item) use ($lowStockOnly, $status) {
            $sellable = $this->sellable($item);
            $isLow = $sellable <= (int) $item->low_stock_threshold;
            $stockStatus = $sellable <= 0 ? 'OUT_OF_STOCK' : ($isLow ? 'LOW_STOCK' : 'IN_STOCK');

            if ($lowStockOnly === true && ! $isLow) {
                return null;
            }
            if ($status && $stockStatus !== strtoupper($status)) {
                return null;
            }

            return $this->serializeItem($item, $sellable, $isLow, $stockStatus);
        })->filter()->values();

        $total = $all->count();
        $rows = $all->forPage($page, $perPage)->values()->all();

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ];
    }

    public function show(int $id): array
    {
        $item = InventoryItem::query()->with(['product', 'warehouse'])->find($id);
        if (! $item) {
            throw new NotFoundHttpException('Inventory item not found');
        }
        $sellable = $this->sellable($item);
        $isLow = $sellable <= (int) $item->low_stock_threshold;
        $status = $sellable <= 0 ? 'OUT_OF_STOCK' : ($isLow ? 'LOW_STOCK' : 'IN_STOCK');
        $base = $this->serializeItem($item, $sellable, $isLow, $status);

        $movements = StockMovement::query()
            ->where('inventory_item_id', $id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (StockMovement $m) => $this->serializeMovement($m))
            ->values()
            ->all();

        $base['recent_movements'] = $movements;
        $base['definitions'] = [
            'sellable' => 'max(0, qty_on_hand - qty_reserved - qty_damaged)',
            'qty_before_after' => 'Ledger on-hand snapshot; corrections create new movements',
        ];

        return $base;
    }

    public function reorderSuggestions(int $limit = 50): array
    {
        return InventoryItem::query()
            ->with(['product', 'warehouse'])
            ->get()
            ->map(function (InventoryItem $item) {
                $sellable = $this->sellable($item);
                $threshold = (int) $item->low_stock_threshold;
                if ($sellable > $threshold) {
                    return null;
                }
                $suggested = max(1, $threshold * 2 - $sellable);

                return [
                    'inventory_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'warehouse_id' => $item->warehouse_id,
                    'warehouse_code' => $item->warehouse?->code,
                    'sellable' => $sellable,
                    'reorder_level' => $threshold,
                    'suggested_reorder_qty' => $suggested,
                    'stock_status' => $sellable <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK',
                ];
            })
            ->filter()
            ->sortBy('sellable')
            ->take($limit)
            ->values()
            ->all();
    }

    public function movements(
        ?int $productId = null,
        ?int $warehouseId = null,
        int $perPage = 30,
        int $page = 1,
        ?string $type = null,
        ?int $actorUserId = null,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $query = StockMovement::query()->orderByDesc('id');

        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($actorUserId) {
            $query->where('actor_user_id', $actorUserId);
        }
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get()
            ->map(fn (StockMovement $m) => $this->serializeMovement($m))
            ->values()
            ->all();

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function dashboard(): array
    {
        $items = InventoryItem::query()->with('product')->get();
        $skuCount = $items->count();
        $onHandUnits = (int) $items->sum('qty_on_hand');
        $reservedUnits = (int) $items->sum('qty_reserved');
        $low = 0;
        $oos = 0;
        foreach ($items as $item) {
            $sellable = $this->sellable($item);
            if ($sellable <= 0) {
                $oos++;
            } elseif ($sellable <= (int) $item->low_stock_threshold) {
                $low++;
            }
        }

        $pendingPos = 0;
        if (class_exists(\App\Modules\Admin\Models\PurchaseOrder::class)) {
            $pendingPos = \App\Modules\Admin\Models\PurchaseOrder::query()
                ->whereIn('status', ['draft', 'ordered', 'approved', 'partially_received'])
                ->count();
        }

        $pendingTransfers = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('stock_transfers')) {
            $pendingTransfers = \App\Modules\Inventory\Models\StockTransfer::query()
                ->whereIn('status', ['draft', 'in_transit'])
                ->count();
        }

        $recentMovements = StockMovement::query()->orderByDesc('id')->limit(15)->get()
            ->map(fn (StockMovement $m) => $this->serializeMovement($m))
            ->values()
            ->all();

        $recentAdjustments = StockMovement::query()
            ->whereIn('type', ['adjust_in', 'adjust_out', 'damage', 'loss', 'reconciliation'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (StockMovement $m) => $this->serializeMovement($m))
            ->values()
            ->all();

        return [
            'total_skus' => $skuCount,
            'total_on_hand_units' => $onHandUnits,
            'total_reserved_units' => $reservedUnits,
            'low_stock_count' => $low,
            'out_of_stock_count' => $oos,
            'pending_purchase_orders' => $pendingPos,
            'pending_transfers' => $pendingTransfers,
            'recent_movements' => $recentMovements,
            'recent_adjustments' => $recentAdjustments,
            'definitions' => [
                'sellable' => 'max(0, on_hand - reserved - damaged)',
                'low_stock' => 'sellable > 0 AND sellable <= low_stock_threshold',
                'out_of_stock' => 'sellable <= 0',
            ],
        ];
    }

    /**
     * Dead stock: on-hand > 0 and no sale movements in the last N days.
     */
    public function deadStock(int $days = 90, int $limit = 50): array
    {
        $since = now()->subDays(max(1, $days));
        $soldProductIds = StockMovement::query()
            ->where('type', 'sale')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('product_id')
            ->all();

        $rows = InventoryItem::query()
            ->with(['product', 'warehouse'])
            ->where('qty_on_hand', '>', 0)
            ->when($soldProductIds !== [], fn ($q) => $q->whereNotIn('product_id', $soldProductIds))
            ->orderByDesc('qty_on_hand')
            ->limit($limit)
            ->get()
            ->map(function (InventoryItem $item) use ($days) {
                $sellable = $this->sellable($item);

                return [
                    'inventory_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sku' => $item->product?->sku,
                    'warehouse_code' => $item->warehouse?->code,
                    'qty_on_hand' => (int) $item->qty_on_hand,
                    'sellable' => $sellable,
                    'rule' => "No sale movements in last {$days} days with on_hand > 0",
                ];
            })
            ->values()
            ->all();

        return [
            'days' => $days,
            'count' => count($rows),
            'items' => $rows,
            'note' => count($rows) === 0 ? 'No dead-stock matches (or insufficient sales history).' : null,
        ];
    }

    public function adjust(array $payload, ?int $actorUserId = null): array
    {
        $warehouse = Warehouse::query()->find($payload['warehouse_id']);
        if (! $warehouse) {
            throw new NotFoundHttpException('Warehouse not found');
        }
        if (($warehouse->status ?? 'active') !== 'active' && ($payload['reason'] ?? '') === 'purchase_in') {
            throw new ApiException('Cannot receive stock into an inactive warehouse', 409, 'CONFLICT');
        }

        $referenceType = $payload['reference_type'] ?? 'manual';
        $referenceId = isset($payload['reference_id']) ? (int) $payload['reference_id'] : null;
        $reason = $payload['reason'] ?? 'adjust';
        $adjustment = (int) $payload['adjustment'];
        $movementType = $this->resolveMovementType($reason, $adjustment);

        // Idempotency: skip if an identical referenced movement already exists.
        if ($referenceType !== 'manual' && $referenceId) {
            $existing = StockMovement::query()
                ->where('product_id', $payload['product_id'])
                ->where('warehouse_id', $payload['warehouse_id'])
                ->where('type', $movementType)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('qty_delta', $adjustment)
                ->first();
            if ($existing) {
                $item = InventoryItem::query()
                    ->where('warehouse_id', $payload['warehouse_id'])
                    ->where('product_id', $payload['product_id'])
                    ->first();

                return [
                    'product_id' => (int) $payload['product_id'],
                    'warehouse_id' => (int) $payload['warehouse_id'],
                    'qty_on_hand' => (int) ($item?->qty_on_hand ?? 0),
                    'qty_reserved' => (int) ($item?->qty_reserved ?? 0),
                    'qty_damaged' => (int) ($item?->qty_damaged ?? 0),
                    'sellable' => $item ? $this->sellable($item) : 0,
                    'idempotent_replay' => true,
                ];
            }
        }

        $productId = (int) $payload['product_id'];
        $variantId = isset($payload['variant_id']) ? (int) $payload['variant_id'] : null;
        $beforeSellable = $this->sellableQty($productId, $variantId);

        $result = DB::transaction(function () use ($payload, $actorUserId, $adjustment, $reason, $movementType, $referenceType, $referenceId) {
            $item = InventoryItem::query()
                ->where('warehouse_id', $payload['warehouse_id'])
                ->where('product_id', $payload['product_id'])
                ->when(
                    empty($payload['variant_id']),
                    fn ($q) => $q->whereNull('product_variant_id'),
                    fn ($q) => $q->where('product_variant_id', $payload['variant_id']),
                )
                ->lockForUpdate()
                ->first();

            if (! $item) {
                $item = InventoryItem::query()->create([
                    'warehouse_id' => $payload['warehouse_id'],
                    'product_id' => $payload['product_id'],
                    'product_variant_id' => $payload['variant_id'] ?? null,
                    'qty_on_hand' => 0,
                    'qty_reserved' => 0,
                    'qty_damaged' => 0,
                    'low_stock_threshold' => 5,
                ]);
                $item = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->first();
            }

            $beforeOnHand = (int) $item->qty_on_hand;

            if (in_array($reason, ['damaged', 'damage'], true) && $adjustment < 0) {
                // Damaged units remain on-hand physically but are unsellable via qty_damaged.
                $damageQty = abs($adjustment);
                $availableToDamage = max(0, (int) $item->qty_on_hand - (int) $item->qty_damaged);
                if ($damageQty > $availableToDamage) {
                    throw new ApiException('Not enough undamaged on-hand quantity', 409, 'CONFLICT');
                }
                $item->qty_damaged += $damageQty;
            } elseif ($reason === 'loss' && $adjustment < 0) {
                $lossQty = abs($adjustment);
                if ($item->qty_on_hand < $lossQty) {
                    throw new ApiException('Not enough on-hand quantity for loss write-off', 409, 'CONFLICT');
                }
                $item->qty_on_hand -= $lossQty;
            } else {
                $newOnHand = $item->qty_on_hand + $adjustment;
                if ($newOnHand < 0) {
                    throw new ApiException('Adjustment would make on-hand negative', 409, 'CONFLICT');
                }
                $item->qty_on_hand = $newOnHand;
            }

            $item->version = (int) $item->version + 1;
            $item->save();

            $this->writeMovement(
                $item,
                $movementType,
                $adjustment,
                $referenceType,
                $referenceId,
                $payload['note'] ?? null,
                $actorUserId,
                $beforeOnHand,
                (int) $item->qty_on_hand,
            );

            return [
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'qty_on_hand' => $item->qty_on_hand,
                'qty_reserved' => $item->qty_reserved,
                'qty_damaged' => $item->qty_damaged,
                'sellable' => $this->sellable($item),
                'idempotent_replay' => false,
            ];
        });

        $afterSellable = $this->sellableQty($productId, $variantId);
        if ($beforeSellable <= 0 && $afterSellable > 0) {
            app(StockAlertService::class)->notifyRestock($productId);
        }

        return $result;
    }

    /**
     * Physical count reconciliation → corrective movement (never silent overwrite).
     */
    public function reconcile(array $payload, ?int $actorUserId = null): array
    {
        $item = InventoryItem::query()->whereKey($payload['inventory_item_id'])->first();
        if (! $item) {
            throw new NotFoundHttpException('Inventory item not found');
        }

        $physical = (int) $payload['physical_qty'];
        if ($physical < 0) {
            throw new ApiException('Physical quantity cannot be negative', 422, 'VALIDATION_ERROR');
        }

        $system = (int) $item->qty_on_hand;
        $difference = $physical - $system;
        if ($difference === 0) {
            return [
                'inventory_item_id' => $item->id,
                'system_qty' => $system,
                'physical_qty' => $physical,
                'difference' => 0,
                'adjusted' => false,
            ];
        }

        $result = $this->adjust([
            'warehouse_id' => $item->warehouse_id,
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'adjustment' => $difference,
            'reason' => 'reconciliation',
            'note' => $payload['reason'] ?? 'Stock count reconciliation',
            'reference_type' => 'reconciliation',
            'reference_id' => $item->id,
        ], $actorUserId);

        AuditLogger::log(
            'inventory.reconcile',
            'inventory_item',
            $item->id,
            ['qty_on_hand' => $system],
            ['qty_on_hand' => $physical, 'difference' => $difference],
            $actorUserId,
        );

        return [
            'inventory_item_id' => $item->id,
            'system_qty' => $system,
            'physical_qty' => $physical,
            'difference' => $difference,
            'adjusted' => true,
            'inventory' => $result,
        ];
    }

    public function updateThreshold(int $id, int $threshold, ?int $actorUserId = null): array
    {
        $item = InventoryItem::query()->find($id);
        if (! $item) {
            throw new NotFoundHttpException('Inventory item not found');
        }
        $before = $item->low_stock_threshold;
        $item->low_stock_threshold = max(0, $threshold);
        $item->save();
        AuditLogger::log('inventory.threshold', 'inventory_item', $id, ['low_stock_threshold' => $before], ['low_stock_threshold' => $item->low_stock_threshold], $actorUserId);

        return $this->show($id);
    }

    private function resolveMovementType(string $reason, int $adjustment): string
    {
        return match ($reason) {
            'purchase_in', 'purchase' => 'purchase_in',
            'return_in', 'return' => 'return_in',
            'damaged', 'damage' => 'damage',
            'loss' => 'loss',
            'supplier_return' => 'supplier_return',
            'transfer_in' => 'transfer_in',
            'transfer_out' => 'transfer_out',
            'reconciliation' => $adjustment >= 0 ? 'adjust_in' : 'adjust_out',
            default => $adjustment >= 0 ? 'adjust_in' : 'adjust_out',
        };
    }

    private function writeMovement(
        InventoryItem $item,
        string $type,
        int $qtyDelta,
        string $referenceType,
        ?int $referenceId,
        ?string $note,
        ?int $actorUserId,
        ?int $qtyBefore = null,
        ?int $qtyAfter = null,
        array $meta = [],
    ): void {
        StockMovement::query()->create([
            'inventory_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'warehouse_id' => $item->warehouse_id,
            'type' => $type,
            'qty_delta' => $qtyDelta,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'actor_user_id' => $actorUserId,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    private function serializeMovement(StockMovement $m): array
    {
        return [
            'id' => $m->id,
            'inventory_item_id' => $m->inventory_item_id,
            'product_id' => $m->product_id,
            'warehouse_id' => $m->warehouse_id,
            'type' => $m->type,
            'qty_delta' => (int) $m->qty_delta,
            'qty_before' => $m->qty_before !== null ? (int) $m->qty_before : null,
            'qty_after' => $m->qty_after !== null ? (int) $m->qty_after : null,
            'reference_type' => $m->reference_type,
            'reference_id' => $m->reference_id,
            'note' => $m->note,
            'actor_user_id' => $m->actor_user_id,
            'meta' => $m->meta,
            'created_at' => optional($m->created_at)?->toIso8601String(),
        ];
    }

    private function serializeItem(InventoryItem $item, int $sellable, bool $isLow, string $status): array
    {
        return [
            'id' => $item->id,
            'warehouse_id' => $item->warehouse_id,
            'warehouse_code' => $item->warehouse?->code,
            'warehouse_name' => $item->warehouse?->name,
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name,
            'sku' => $item->product?->sku,
            'thumbnail_url' => $item->product?->primaryImageUrl(),
            'categories' => $item->product?->categories?->pluck('name')->values()->all() ?? [],
            'product_variant_id' => $item->product_variant_id,
            'qty_on_hand' => (int) $item->qty_on_hand,
            'qty_reserved' => (int) $item->qty_reserved,
            'qty_damaged' => (int) $item->qty_damaged,
            'quantity_available' => $sellable,
            'sellable' => $sellable,
            'low_stock_threshold' => (int) $item->low_stock_threshold,
            'reorder_level' => (int) $item->low_stock_threshold,
            'is_low_stock' => $isLow,
            'stock_status' => $status,
            'updated_at' => optional($item->updated_at)?->toIso8601String(),
        ];
    }
}
