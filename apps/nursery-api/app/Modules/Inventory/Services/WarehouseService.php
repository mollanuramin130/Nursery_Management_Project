<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\Warehouse;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WarehouseService
{
    public function list(): array
    {
        return Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get()->map(function (Warehouse $w) {
            $count = InventoryItem::query()->where('warehouse_id', $w->id)->count();

            return [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'city' => $w->city,
                'is_default' => (bool) $w->is_default,
                'status' => $w->status ?? 'active',
                'inventory_sku_count' => $count,
            ];
        })->values()->all();
    }

    public function create(array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($payload, $actorUserId) {
            if (! empty($payload['is_default'])) {
                Warehouse::query()->update(['is_default' => false]);
            }
            $warehouse = Warehouse::query()->create([
                'code' => $payload['code'],
                'name' => $payload['name'],
                'city' => $payload['city'] ?? null,
                'is_default' => (bool) ($payload['is_default'] ?? false),
                'status' => $payload['status'] ?? 'active',
            ]);
            AuditLogger::log('warehouse.create', 'warehouse', $warehouse->id, null, $warehouse->toArray(), $actorUserId);

            return $this->serialize($warehouse);
        });
    }

    public function update(int $id, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($id, $payload, $actorUserId) {
            $warehouse = Warehouse::query()->find($id);
            if (! $warehouse) {
                throw new NotFoundHttpException('Warehouse not found');
            }
            $before = $warehouse->toArray();
            if (! empty($payload['is_default'])) {
                Warehouse::query()->where('id', '!=', $id)->update(['is_default' => false]);
            }
            $warehouse->fill($payload)->save();
            AuditLogger::log('warehouse.update', 'warehouse', $id, $before, $warehouse->toArray(), $actorUserId);

            return $this->serialize($warehouse->fresh());
        });
    }

    private function serialize(Warehouse $w): array
    {
        return [
            'id' => $w->id,
            'code' => $w->code,
            'name' => $w->name,
            'city' => $w->city,
            'is_default' => (bool) $w->is_default,
            'status' => $w->status ?? 'active',
        ];
    }
}
