<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\PurchaseOrder;
use App\Modules\Admin\Models\PurchaseOrderItem;
use App\Modules\Admin\Models\Supplier;
use App\Modules\Inventory\Models\SupplierProduct;
use App\Modules\Inventory\Services\InventoryService;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminSupplierService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function listSuppliers(): array
    {
        return Supplier::query()->orderBy('name')->get()->map(function (Supplier $s) {
            $poCount = PurchaseOrder::query()->where('supplier_id', $s->id)->count();
            $productCount = SupplierProduct::query()->where('supplier_id', $s->id)->count();

            return [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'email' => $s->email,
                'phone' => $s->phone,
                'city' => $s->city,
                'state' => $s->state,
                'contact_person' => $s->contact_person,
                'gstin' => $s->gstin,
                'status' => $s->status,
                'product_count' => $productCount,
                'purchase_order_count' => $poCount,
            ];
        })->values()->all();
    }

    public function showSupplier(int $id): array
    {
        $s = Supplier::query()->find($id);
        if (! $s) {
            throw new NotFoundHttpException('Supplier not found');
        }
        $products = SupplierProduct::query()->with('product')->where('supplier_id', $id)->orderBy('id')->get()
            ->map(fn (SupplierProduct $sp) => [
                'id' => $sp->id,
                'product_id' => $sp->product_id,
                'product_name' => $sp->product?->name,
                'sku' => $sp->product?->sku,
                'supplier_sku' => $sp->supplier_sku,
                'unit_cost' => (float) $sp->unit_cost,
                'lead_time_days' => $sp->lead_time_days,
                'moq' => $sp->moq,
                'status' => $sp->status,
            ])->values()->all();

        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'email' => $s->email,
            'phone' => $s->phone,
            'city' => $s->city,
            'state' => $s->state,
            'contact_person' => $s->contact_person,
            'gstin' => $s->gstin,
            'status' => $s->status,
            'products' => $products,
            'performance' => $this->supplierPerformance($id),
        ];
    }

    public function supplierPerformance(int $supplierId): array
    {
        $pos = PurchaseOrder::query()->where('supplier_id', $supplierId)->get();
        if ($pos->isEmpty()) {
            return [
                'orders' => 0,
                'total_purchase_value' => 0,
                'on_time_deliveries' => null,
                'late_deliveries' => null,
                'average_lead_time_days' => null,
                'note' => 'Insufficient data',
            ];
        }

        $received = $pos->whereIn('status', ['received', 'partially_received']);
        $onTime = 0;
        $late = 0;
        $leadSamples = [];
        foreach ($received as $po) {
            if (! $po->expected_at || ! $po->received_at) {
                continue;
            }
            if ($po->received_at->lte($po->expected_at)) {
                $onTime++;
            } else {
                $late++;
            }
            if ($po->created_at && $po->received_at) {
                $leadSamples[] = $po->created_at->diffInDays($po->received_at);
            }
        }

        $hasTiming = ($onTime + $late) > 0;

        return [
            'orders' => $pos->count(),
            'total_purchase_value' => round((float) $pos->sum('grand_total'), 2),
            'on_time_deliveries' => $hasTiming ? $onTime : null,
            'late_deliveries' => $hasTiming ? $late : null,
            'average_lead_time_days' => $leadSamples !== []
                ? round(array_sum($leadSamples) / count($leadSamples), 1)
                : null,
            'note' => $hasTiming ? null : 'Insufficient expected/received date data for on-time metrics',
        ];
    }

    public function upsertSupplierProduct(int $supplierId, array $payload, ?int $actorId): array
    {
        $supplier = Supplier::query()->find($supplierId);
        if (! $supplier) {
            throw new NotFoundHttpException('Supplier not found');
        }

        $row = SupplierProduct::query()->updateOrCreate(
            [
                'supplier_id' => $supplierId,
                'product_id' => (int) $payload['product_id'],
            ],
            [
                'supplier_sku' => $payload['supplier_sku'] ?? null,
                'unit_cost' => $payload['unit_cost'] ?? 0,
                'lead_time_days' => $payload['lead_time_days'] ?? null,
                'moq' => $payload['moq'] ?? null,
                'status' => $payload['status'] ?? 'active',
            ]
        );
        AuditLogger::log('supplier_product.upsert', 'supplier_product', $row->id, null, $row->toArray(), $actorId);

        return [
            'id' => $row->id,
            'supplier_id' => $row->supplier_id,
            'product_id' => $row->product_id,
            'supplier_sku' => $row->supplier_sku,
            'unit_cost' => (float) $row->unit_cost,
            'lead_time_days' => $row->lead_time_days,
            'moq' => $row->moq,
            'status' => $row->status,
        ];
    }

    public function createSupplier(array $payload, ?int $actorUserId = null): array
    {
        $supplier = Supplier::query()->create($payload);
        AuditLogger::log('supplier.create', 'supplier', $supplier->id, null, $supplier->toArray(), $actorUserId);

        return ['id' => $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name];
    }

    public function updateSupplier(int $id, array $payload, ?int $actorUserId = null): array
    {
        $supplier = Supplier::query()->find($id);
        if (! $supplier) {
            throw new NotFoundHttpException('Supplier not found');
        }

        $before = $supplier->toArray();
        $supplier->fill($payload)->save();
        AuditLogger::log('supplier.update', 'supplier', $id, $before, $supplier->toArray(), $actorUserId);

        return ['id' => $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name, 'status' => $supplier->status];
    }

    public function deleteSupplier(int $id, ?int $actorUserId = null): void
    {
        $supplier = Supplier::query()->find($id);
        if (! $supplier) {
            throw new NotFoundHttpException('Supplier not found');
        }
        $before = $supplier->toArray();
        $supplier->delete();
        AuditLogger::log('supplier.delete', 'supplier', $id, $before, null, $actorUserId);
    }

    public function createPurchaseOrder(array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($payload, $actorUserId) {
            $subtotal = 0.0;
            foreach ($payload['items'] as $item) {
                $subtotal += ((float) $item['unit_cost']) * ((int) $item['quantity']);
            }
            $tax = (float) ($payload['tax_total'] ?? 0);
            $poNumber = $payload['po_number'] ?? ('PO-'.now()->format('Ymd').'-'.str_pad((string) (PurchaseOrder::query()->count() + 1), 4, '0', STR_PAD_LEFT));

            $status = $payload['status'] ?? 'ordered';
            if (! in_array($status, ['draft', 'ordered', 'approved'], true)) {
                $status = 'ordered';
            }

            $po = PurchaseOrder::query()->create([
                'po_number' => $poNumber,
                'supplier_id' => $payload['supplier_id'],
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'status' => $status,
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'grand_total' => $subtotal + $tax,
                'expected_at' => $payload['expected_at'] ?? null,
                'created_by' => $actorUserId,
            ]);

            foreach ($payload['items'] as $item) {
                $qty = (int) $item['quantity'];
                $unit = (float) $item['unit_cost'];
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['variant_id'] ?? null,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $unit,
                    'line_total' => $qty * $unit,
                ]);
            }

            AuditLogger::log('purchase_order.create', 'purchase_order', $po->id, null, $po->toArray(), $actorUserId);

            return $this->showPurchaseOrder($po->id);
        });
    }

    public function showPurchaseOrder(int $id): array
    {
        $po = PurchaseOrder::query()->with(['supplier', 'items.product'])->find($id);
        if (! $po) {
            throw new NotFoundHttpException('Purchase order not found');
        }

        $items = $po->items->map(function (PurchaseOrderItem $item) {
            $remaining = max(0, (int) $item->quantity_ordered - (int) $item->quantity_received);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'product_variant_id' => $item->product_variant_id,
                'quantity_ordered' => (int) $item->quantity_ordered,
                'quantity_received' => (int) $item->quantity_received,
                'quantity_remaining' => $remaining,
                'unit_cost' => (float) $item->unit_cost,
                'line_total' => (float) $item->line_total,
            ];
        })->values()->all();

        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'supplier' => $po->supplier?->name,
            'warehouse_id' => $po->warehouse_id,
            'status' => $po->status,
            'subtotal' => (float) $po->subtotal,
            'tax_total' => (float) $po->tax_total,
            'grand_total' => (float) $po->grand_total,
            'expected_at' => optional($po->expected_at)?->toIso8601String(),
            'received_at' => optional($po->received_at)?->toIso8601String(),
            'created_at' => optional($po->created_at)?->toIso8601String(),
            'items' => $items,
            'actions' => [
                'can_receive' => in_array($po->status, ['ordered', 'approved', 'partially_received'], true),
                'can_cancel' => in_array($po->status, ['draft', 'ordered', 'approved'], true),
                'can_approve' => $po->status === 'draft' || $po->status === 'ordered',
            ],
        ];
    }

    public function approvePurchaseOrder(int $id, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($id, $actorUserId) {
            $po = PurchaseOrder::query()->whereKey($id)->lockForUpdate()->first();
            if (! $po) {
                throw new NotFoundHttpException('Purchase order not found');
            }
            if (! in_array($po->status, ['draft', 'ordered'], true)) {
                throw new ApiException('Purchase order cannot be approved in status '.$po->status, 409, 'CONFLICT');
            }
            $before = $po->status;
            $po->status = 'approved';
            $po->save();
            AuditLogger::log('purchase_order.approve', 'purchase_order', $id, ['status' => $before], ['status' => 'approved'], $actorUserId);

            return $this->showPurchaseOrder($id);
        });
    }

    public function cancelPurchaseOrder(int $id, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($id, $actorUserId) {
            $po = PurchaseOrder::query()->whereKey($id)->lockForUpdate()->first();
            if (! $po) {
                throw new NotFoundHttpException('Purchase order not found');
            }
            if (in_array($po->status, ['received', 'cancelled'], true)) {
                throw new ApiException('Purchase order cannot be cancelled', 409, 'CONFLICT');
            }
            if ($po->status === 'partially_received') {
                throw new ApiException('Partially received POs cannot be cancelled; receive remaining or keep open', 409, 'CONFLICT');
            }
            $before = $po->status;
            $po->status = 'cancelled';
            $po->save();
            AuditLogger::log('purchase_order.cancel', 'purchase_order', $id, ['status' => $before], ['status' => 'cancelled'], $actorUserId);

            return $this->showPurchaseOrder($id);
        });
    }

    /**
     * Partial or full receiving.
     *
     * @param  array{items?: list<array{purchase_order_item_id:int, quantity:int, damaged?:int}>}|null  $payload
     */
    public function receivePurchaseOrder(int $id, ?array $payload = null, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($id, $payload, $actorUserId) {
            $po = PurchaseOrder::query()->with('items')->whereKey($id)->lockForUpdate()->first();
            if (! $po) {
                throw new NotFoundHttpException('Purchase order not found');
            }

            if ($po->status === 'received') {
                return $this->showPurchaseOrder($id);
            }
            if ($po->status === 'cancelled') {
                throw new ApiException('Cannot receive a cancelled purchase order', 409, 'CONFLICT');
            }
            if (! $po->warehouse_id) {
                throw new ApiException('Purchase order has no warehouse', 422, 'VALIDATION_ERROR');
            }

            $lines = $payload['items'] ?? null;
            if ($lines === null) {
                // Backward-compatible: receive all remaining.
                $lines = $po->items->map(fn (PurchaseOrderItem $item) => [
                    'purchase_order_item_id' => $item->id,
                    'quantity' => max(0, (int) $item->quantity_ordered - (int) $item->quantity_received),
                    'damaged' => 0,
                ])->filter(fn ($l) => $l['quantity'] > 0)->values()->all();
            }

            if ($lines === []) {
                throw new ApiException('No quantities to receive', 422, 'VALIDATION_ERROR');
            }

            foreach ($lines as $line) {
                /** @var PurchaseOrderItem|null $item */
                $item = $po->items->firstWhere('id', (int) $line['purchase_order_item_id']);
                if (! $item) {
                    throw new ApiException('Invalid purchase order item', 422, 'VALIDATION_ERROR');
                }
                $qty = (int) $line['quantity'];
                $damaged = (int) ($line['damaged'] ?? 0);
                if ($qty < 1) {
                    continue;
                }
                if ($damaged < 0 || $damaged > $qty) {
                    throw new ApiException('Damaged quantity must be between 0 and received quantity', 422, 'VALIDATION_ERROR');
                }
                $remaining = (int) $item->quantity_ordered - (int) $item->quantity_received;
                if ($qty > $remaining) {
                    throw new ApiException('Cannot receive more than remaining quantity for item '.$item->id, 422, 'VALIDATION_ERROR');
                }

                $accepted = $qty - $damaged;
                if ($accepted > 0) {
                    $this->inventory->adjust([
                        'warehouse_id' => $po->warehouse_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'adjustment' => $accepted,
                        'reason' => 'purchase_in',
                        'note' => "PO {$po->po_number} receive",
                        'reference_type' => 'purchase_order_item',
                        'reference_id' => $item->id * 100000 + ((int) $item->quantity_received + $accepted),
                    ], $actorUserId);
                }
                if ($damaged > 0) {
                    // Record damaged as on-hand then mark damaged (unsellable).
                    $this->inventory->adjust([
                        'warehouse_id' => $po->warehouse_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'adjustment' => $damaged,
                        'reason' => 'purchase_in',
                        'note' => "PO {$po->po_number} damaged receipt",
                        'reference_type' => 'purchase_order_item_damaged_in',
                        'reference_id' => $item->id * 100000 + ((int) $item->quantity_received + $qty),
                    ], $actorUserId);
                    $this->inventory->adjust([
                        'warehouse_id' => $po->warehouse_id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'adjustment' => -$damaged,
                        'reason' => 'damaged',
                        'note' => "PO {$po->po_number} damaged",
                        'reference_type' => 'purchase_order_item_damaged',
                        'reference_id' => $item->id * 100000 + ((int) $item->quantity_received + $qty),
                    ], $actorUserId);
                }

                $item->quantity_received = (int) $item->quantity_received + $qty;
                $item->save();
            }

            $po->load('items');
            $allReceived = $po->items->every(
                fn (PurchaseOrderItem $i) => (int) $i->quantity_received >= (int) $i->quantity_ordered
            );
            $anyReceived = $po->items->contains(
                fn (PurchaseOrderItem $i) => (int) $i->quantity_received > 0
            );

            if ($allReceived) {
                $po->status = 'received';
                $po->received_at = now();
            } elseif ($anyReceived) {
                $po->status = 'partially_received';
            }
            $po->save();

            AuditLogger::log('purchase_order.receive', 'purchase_order', $po->id, null, [
                'status' => $po->status,
                'lines' => count($lines),
            ], $actorUserId);

            return $this->showPurchaseOrder($po->id);
        });
    }

    public function listPurchaseOrders(?string $status = null, int $perPage = 30, int $page = 1): array
    {
        $query = PurchaseOrder::query()->with(['supplier', 'items'])->orderByDesc('id');
        if ($status) {
            $query->where('status', $status);
        }
        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));

        $data = collect($paginator->items())->map(function (PurchaseOrder $po) {
            $ordered = (int) $po->items->sum('quantity_ordered');
            $received = (int) $po->items->sum('quantity_received');

            return [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier' => $po->supplier?->name,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $po->warehouse_id,
                'status' => $po->status,
                'item_count' => $po->items->count(),
                'units_ordered' => $ordered,
                'units_received' => $received,
                'grand_total' => (float) $po->grand_total,
                'expected_at' => optional($po->expected_at)?->toIso8601String(),
                'received_at' => optional($po->received_at)?->toIso8601String(),
                'created_at' => optional($po->created_at)?->toIso8601String(),
            ];
        })->values()->all();

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
}
