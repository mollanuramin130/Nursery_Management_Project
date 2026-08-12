<?php

namespace App\Modules\Order\Services;

use App\Modules\Delivery\Contracts\ShippingProvider;
use App\Modules\Delivery\Providers\InternalDeliveryProvider;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\Shipment;
use App\Modules\Order\Models\ShipmentEvent;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FulfillmentService
{
    public const FAILURE_REASONS = [
        'CUSTOMER_UNAVAILABLE',
        'WRONG_ADDRESS',
        'DAMAGED_PACKAGE',
        'DELIVERY_AREA_ISSUE',
        'COURIER_FAILURE',
        'OTHER',
    ];

    private readonly ShippingProvider $shippingProvider;

    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly NotificationService $notifications,
        private readonly LoyaltyService $loyalty,
        ?ShippingProvider $shippingProvider = null,
    ) {
        $this->shippingProvider = $shippingProvider ?? new InternalDeliveryProvider;
    }

    public function dashboard(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->whereIn('status', [
                'CONFIRMED', 'PROCESSING', 'PACKED', 'SHIPPED',
                'OUT_FOR_DELIVERY', 'DELIVERY_FAILED', 'DELIVERED',
            ])
            ->groupBy('status')
            ->pluck('c', 'status');

        $openExceptions = Order::query()
            ->where(function ($q) {
                $q->where('status', 'DELIVERY_FAILED')
                    ->orWhere('meta->fulfillment->has_open_exception', true);
            })
            ->count();

        return [
            'awaiting_picking' => (int) ($counts['CONFIRMED'] ?? 0),
            'being_picked' => (int) ($counts['PROCESSING'] ?? 0),
            'awaiting_packing' => (int) ($counts['PROCESSING'] ?? 0),
            'packed' => (int) ($counts['PACKED'] ?? 0),
            'ready_to_ship' => (int) ($counts['PACKED'] ?? 0),
            'in_transit' => (int) ($counts['SHIPPED'] ?? 0),
            'out_for_delivery' => (int) ($counts['OUT_FOR_DELIVERY'] ?? 0),
            'delivery_exceptions' => (int) ($counts['DELIVERY_FAILED'] ?? 0) + $openExceptions,
            'delivered_today' => Order::query()
                ->where('status', 'DELIVERED')
                ->whereHas('shipment', fn ($s) => $s->whereDate('delivered_at', today()))
                ->count(),
            'warehouse_rule' => 'Fulfill from order.warehouse_id; fallback to default warehouse. Stock already committed at payment — pick/pack do not re-allocate.',
        ];
    }

    public function queue(string $queue, ?int $warehouseId = null, ?string $q = null, int $perPage = 20, int $page = 1): array
    {
        $statuses = match ($queue) {
            'picking' => ['CONFIRMED', 'PROCESSING'],
            'packing' => ['PROCESSING'],
            'ready_to_ship' => ['PACKED'],
            'in_transit' => ['SHIPPED', 'OUT_FOR_DELIVERY'],
            'exceptions' => ['DELIVERY_FAILED'],
            default => throw new ApiException('Unknown fulfillment queue', 422, 'VALIDATION_ERROR'),
        };

        $query = Order::query()
            ->with(['user', 'items', 'shipment'])
            ->whereIn('status', $statuses)
            ->when($warehouseId, fn ($qb) => $qb->where('warehouse_id', $warehouseId))
            ->when($q, function ($qb) use ($q) {
                $qb->where(function ($inner) use ($q) {
                    $inner->where('order_number', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
                });
            })
            ->orderBy('id');

        if ($queue === 'packing') {
            $query->whereNotNull('meta->fulfillment->picking_completed_at');
        }

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));

        $data = collect($paginator->items())->map(fn (Order $o) => $this->serializeQueueRow($o))->values()->all();

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

    public function showOrder(int $orderId): array
    {
        $order = Order::query()->with(['user', 'items', 'shipment.events', 'statusHistories'])->find($orderId);
        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        return $this->serializeFulfillmentDetail($order);
    }

    public function startPicking(int $orderId, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId) {
            $order = Order::query()->with('items')->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            if ($order->status === 'PROCESSING') {
                // Idempotent: already picking
                return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
            }
            if ($order->status !== 'CONFIRMED') {
                throw new ApiException('Order is not ready for picking', 409, 'CONFLICT');
            }

            $items = [];
            foreach ($order->items as $item) {
                $items[(string) $item->id] = [
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'required' => (int) $item->quantity,
                    'picked' => 0,
                ];
            }

            $fulfillment = $this->fulfillmentMeta($order);
            $fulfillment['picking_started_at'] = now()->toIso8601String();
            $fulfillment['picking_started_by'] = $actorUserId;
            $fulfillment['items'] = $items;
            $fulfillment['warehouse_id'] = $order->warehouse_id
                ?? Warehouse::query()->where('is_default', true)->value('id');

            $this->writeFulfillmentMeta($order, $fulfillment);
            $this->stateMachine->transition($order, 'PROCESSING', $actorUserId, 'Picking started');
            AuditLogger::log('fulfillment.pick_start', 'order', $order->id, ['status' => 'CONFIRMED'], ['status' => 'PROCESSING'], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    /**
     * @param  list<array{order_item_id:int, picked:int}>  $lines
     */
    public function updatePickedQuantities(int $orderId, array $lines, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $lines, $actorUserId) {
            $order = Order::query()->with('items')->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'PROCESSING') {
                throw new ApiException('Order is not in picking', 409, 'CONFLICT');
            }

            $fulfillment = $this->fulfillmentMeta($order);
            if (empty($fulfillment['items'])) {
                throw new ApiException('Picking not started', 409, 'CONFLICT');
            }

            foreach ($lines as $line) {
                $key = (string) $line['order_item_id'];
                if (! isset($fulfillment['items'][$key])) {
                    throw new ApiException('Invalid order item for pick', 422, 'VALIDATION_ERROR');
                }
                $picked = (int) $line['picked'];
                $required = (int) $fulfillment['items'][$key]['required'];
                if ($picked < 0) {
                    throw new ApiException('Picked quantity cannot be negative', 422, 'VALIDATION_ERROR');
                }
                if ($picked > $required) {
                    throw new ApiException("Cannot pick more than allocated ({$required})", 422, 'VALIDATION_ERROR');
                }
                $fulfillment['items'][$key]['picked'] = $picked;
            }

            $this->writeFulfillmentMeta($order, $fulfillment);
            AuditLogger::log('fulfillment.pick_update', 'order', $order->id, null, ['lines' => count($lines)], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function recordPickException(
        int $orderId,
        int $orderItemId,
        int $expected,
        int $actual,
        string $note,
        ?int $actorUserId = null,
    ): array {
        return DB::transaction(function () use ($orderId, $orderItemId, $expected, $actual, $note, $actorUserId) {
            $order = Order::query()->with('items')->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'PROCESSING') {
                throw new ApiException('Pick exceptions only during PROCESSING', 409, 'CONFLICT');
            }
            if ($actual > $expected) {
                throw new ApiException('Actual cannot exceed expected for short-pick exception', 422, 'VALIDATION_ERROR');
            }

            $fulfillment = $this->fulfillmentMeta($order);
            $key = (string) $orderItemId;
            if (! isset($fulfillment['items'][$key])) {
                throw new ApiException('Invalid order item', 422, 'VALIDATION_ERROR');
            }

            $fulfillment['items'][$key]['picked'] = $actual;
            $fulfillment['has_open_exception'] = true;
            $fulfillment['exceptions'] = $fulfillment['exceptions'] ?? [];
            $fulfillment['exceptions'][] = [
                'id' => 'pick-'.uniqid(),
                'type' => 'pick_short',
                'order_item_id' => $orderItemId,
                'expected' => $expected,
                'actual' => $actual,
                'note' => $note,
                'status' => 'open',
                'at' => now()->toIso8601String(),
                'actor_user_id' => $actorUserId,
            ];

            $this->writeFulfillmentMeta($order, $fulfillment);
            AuditLogger::log('fulfillment.pick_exception', 'order', $order->id, null, [
                'order_item_id' => $orderItemId,
                'expected' => $expected,
                'actual' => $actual,
            ], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function completePicking(int $orderId, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId) {
            $order = Order::query()->with('items')->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'PROCESSING') {
                throw new ApiException('Order is not in picking', 409, 'CONFLICT');
            }

            $fulfillment = $this->fulfillmentMeta($order);
            foreach ($fulfillment['items'] ?? [] as $row) {
                if ((int) $row['picked'] !== (int) $row['required']) {
                    $hasResolvedShort = collect($fulfillment['exceptions'] ?? [])
                        ->contains(fn ($e) => ($e['order_item_id'] ?? null) == $row['order_item_id']
                            && ($e['type'] ?? '') === 'pick_short'
                            && (int) ($e['actual'] ?? -1) === (int) $row['picked']);
                    if (! $hasResolvedShort) {
                        throw new ApiException(
                            'All lines must be fully picked or have a recorded pick exception',
                            422,
                            'VALIDATION_ERROR',
                        );
                    }
                }
            }

            $fulfillment['picking_completed_at'] = now()->toIso8601String();
            $this->writeFulfillmentMeta($order, $fulfillment);
            AuditLogger::log('fulfillment.pick_complete', 'order', $order->id, null, ['status' => 'PROCESSING'], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function pack(int $orderId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $payload, $actorUserId) {
            $order = Order::query()->with(['items', 'user'])->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'PROCESSING') {
                throw new ApiException('Only PROCESSING orders can be packed', 409, 'CONFLICT');
            }

            $fulfillment = $this->fulfillmentMeta($order);
            if (empty($fulfillment['picking_completed_at'])) {
                throw new ApiException('Complete picking before packing', 409, 'CONFLICT');
            }

            $fulfillment['packed_at'] = now()->toIso8601String();
            $fulfillment['package_count'] = max(1, (int) ($payload['package_count'] ?? 1));
            $fulfillment['weight_grams'] = isset($payload['weight_grams']) ? (int) $payload['weight_grams'] : null;
            $this->writeFulfillmentMeta($order, $fulfillment);

            $this->stateMachine->transition($order, 'PACKED', $actorUserId, $payload['note'] ?? 'Packed');
            AuditLogger::log('fulfillment.pack', 'order', $order->id, ['status' => 'PROCESSING'], ['status' => 'PACKED'], $actorUserId);

            $this->notifyCustomer($order, 'order_packed', 'Order packed', "Order {$order->order_number} is packed and ready to ship.");

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function createShipment(int $orderId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $payload, $actorUserId) {
            $order = Order::query()->with(['items', 'user', 'shipment'])->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            // Idempotent: already shipped
            if (in_array($order->status, ['SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED'], true) && $order->shipment) {
                $payload = $this->serializeShipment($order->shipment->load('events'), $order);
                $payload['idempotent_replay'] = true;

                return $payload;
            }

            if ($order->status !== 'PACKED') {
                throw new ApiException('Order must be PACKED before shipment', 409, 'CONFLICT');
            }

            $existing = Shipment::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing && $existing->tracking_number && $order->status === 'PACKED') {
                // Shipment row exists from partial attempt — reuse
            }

            $providerResult = $this->shippingProvider->createShipment([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'warehouse_id' => $order->warehouse_id,
                'carrier' => $payload['carrier'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'tracking_url' => $payload['tracking_url'] ?? null,
                'eta_date' => $payload['eta_date'] ?? null,
                'weight_grams' => $payload['weight_grams'] ?? ($this->fulfillmentMeta($order)['weight_grams'] ?? null),
            ]);

            if (Shipment::query()
                ->where('tracking_number', $providerResult['tracking_number'])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists()) {
                throw new ApiException('Tracking number already in use', 409, 'CONFLICT');
            }

            $shipment = $existing ?? new Shipment(['order_id' => $order->id]);
            $fulfillment = $this->fulfillmentMeta($order);
            $shipment->fill([
                'status' => 'shipped',
                'carrier' => $providerResult['carrier'],
                'tracking_number' => $providerResult['tracking_number'],
                'tracking_url' => $providerResult['tracking_url'],
                'shipping_method_id' => $order->shipping_method_id,
                'warehouse_id' => $fulfillment['warehouse_id'] ?? $order->warehouse_id,
                'eta_date' => $providerResult['eta_date'] ?? $payload['eta_date'] ?? null,
                'weight_grams' => $payload['weight_grams'] ?? $fulfillment['weight_grams'] ?? null,
                'shipped_at' => $shipment->shipped_at ?? now(),
                'meta' => array_merge($shipment->meta ?? [], $providerResult['meta'] ?? [], [
                    'package_count' => $fulfillment['package_count'] ?? 1,
                    'provider' => $this->shippingProvider->code(),
                ]),
            ]);
            $shipment->save();

            $this->stateMachine->transition($order, 'SHIPPED', $actorUserId, $payload['note'] ?? 'Shipment created');
            $this->addEvent($shipment, 'SHIPPED', 'Shipment created', null, $actorUserId, ['source' => 'admin']);

            AuditLogger::log('fulfillment.ship', 'shipment', $shipment->id, null, [
                'order_id' => $order->id,
                'tracking_number' => $shipment->tracking_number,
            ], $actorUserId);

            $this->notifyCustomer(
                $order->fresh('user'),
                'order_shipped',
                'Order shipped',
                "Order {$order->order_number} has shipped. Tracking: {$shipment->tracking_number}",
            );

            $payload = $this->serializeShipment($shipment->fresh('events'), $order->fresh());
            $payload['idempotent_replay'] = false;

            return $payload;
        });
    }

    public function addTrackingEvent(int $shipmentId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($shipmentId, $payload, $actorUserId) {
            $shipment = Shipment::query()->with('order')->whereKey($shipmentId)->lockForUpdate()->first();
            if (! $shipment) {
                throw new NotFoundHttpException('Shipment not found');
            }

            $status = strtoupper($payload['status']);
            $event = $this->addEvent(
                $shipment,
                $status,
                $payload['description'] ?? null,
                $payload['location'] ?? null,
                $actorUserId,
                ['source' => $payload['source'] ?? 'admin'],
                isset($payload['event_at']) ? $payload['event_at'] : null,
            );

            $map = [
                'PICKED_UP' => null,
                'IN_TRANSIT' => 'SHIPPED',
                'OUT_FOR_DELIVERY' => 'OUT_FOR_DELIVERY',
                'DELIVERED' => 'DELIVERED',
                'FAILED' => 'DELIVERY_FAILED',
                'DELIVERY_FAILED' => 'DELIVERY_FAILED',
                'RETURNED' => null,
            ];

            $order = Order::query()->whereKey($shipment->order_id)->lockForUpdate()->first();
            $target = $map[$status] ?? null;
            if ($order && $target && $order->status !== $target && in_array($target, OrderStateMachine::allowedFrom($order->status), true)) {
                $this->stateMachine->transition($order, $target, $actorUserId, $payload['description'] ?? $status);
                $shipment->status = strtolower($target);
                if ($target === 'DELIVERED') {
                    $shipment->delivered_at = now();
                    $this->loyalty->earnForDeliveredOrder($order->fresh(), $actorUserId);
                }
                $shipment->save();
                $this->notifyForStatus($order->fresh('user'), $target);
            } else {
                $shipment->status = strtolower($status);
                $shipment->save();
            }

            AuditLogger::log('fulfillment.tracking', 'shipment', $shipment->id, null, [
                'status' => $status,
                'event_id' => $event->id,
            ], $actorUserId);

            return $this->serializeShipment($shipment->fresh('events'), $order?->fresh());
        });
    }

    public function markOutForDelivery(int $orderId, ?int $actorUserId = null): array
    {
        return $this->advanceOrder($orderId, 'OUT_FOR_DELIVERY', 'Out for delivery', $actorUserId, 'out_for_delivery');
    }

    public function markDelivered(int $orderId, ?int $actorUserId = null): array
    {
        return $this->advanceOrder($orderId, 'DELIVERED', 'Delivered', $actorUserId, 'delivered');
    }

    public function markDeliveryFailed(int $orderId, string $reason, ?string $note = null, ?int $actorUserId = null): array
    {
        if (! in_array($reason, self::FAILURE_REASONS, true)) {
            throw new ApiException('Invalid delivery failure reason', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($orderId, $reason, $note, $actorUserId) {
            $order = Order::query()->with(['shipment', 'user'])->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'OUT_FOR_DELIVERY') {
                throw new ApiException('Delivery failure only from OUT_FOR_DELIVERY', 409, 'CONFLICT');
            }

            $this->stateMachine->transition($order, 'DELIVERY_FAILED', $actorUserId, $note ?? $reason);
            $shipment = $order->shipment;
            if ($shipment) {
                $meta = $shipment->meta ?? [];
                $meta['failure_reason'] = $reason;
                $meta['failure_note'] = $note;
                $shipment->status = 'delivery_failed';
                $shipment->meta = $meta;
                $shipment->save();
                $this->addEvent($shipment, 'FAILED', $note ?? $reason, null, $actorUserId, [
                    'reason' => $reason,
                    'source' => 'admin',
                ]);
            }

            $fulfillment = $this->fulfillmentMeta($order);
            $fulfillment['has_open_exception'] = true;
            $fulfillment['exceptions'] = $fulfillment['exceptions'] ?? [];
            $fulfillment['exceptions'][] = [
                'id' => 'del-'.uniqid(),
                'type' => 'delivery_failed',
                'reason' => $reason,
                'note' => $note,
                'status' => 'open',
                'at' => now()->toIso8601String(),
            ];
            $this->writeFulfillmentMeta($order, $fulfillment);

            AuditLogger::log('fulfillment.delivery_failed', 'order', $order->id, null, ['reason' => $reason], $actorUserId);
            $this->notifyCustomer($order, 'delivery_failed', 'Delivery attempt failed', "Delivery for {$order->order_number} failed ({$reason}). We will retry or contact you.");

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function retryDelivery(int $orderId, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId) {
            $order = Order::query()->with(['shipment', 'user'])->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status !== 'DELIVERY_FAILED') {
                throw new ApiException('Retry only from DELIVERY_FAILED', 409, 'CONFLICT');
            }

            $this->stateMachine->transition($order, 'OUT_FOR_DELIVERY', $actorUserId, 'Delivery retry');
            if ($order->shipment) {
                $order->shipment->status = 'out_for_delivery';
                $order->shipment->save();
                $this->addEvent($order->shipment, 'OUT_FOR_DELIVERY', 'Delivery reattempt scheduled', null, $actorUserId, ['source' => 'admin']);
            }

            $fulfillment = $this->fulfillmentMeta($order);
            foreach ($fulfillment['exceptions'] ?? [] as $i => $ex) {
                if (($ex['type'] ?? '') === 'delivery_failed' && ($ex['status'] ?? '') === 'open') {
                    $fulfillment['exceptions'][$i]['status'] = 'retrying';
                }
            }
            $this->writeFulfillmentMeta($order, $fulfillment);
            AuditLogger::log('fulfillment.delivery_retry', 'order', $order->id, null, ['status' => 'OUT_FOR_DELIVERY'], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    public function listShipments(?string $status = null, ?string $carrier = null, ?string $q = null, int $perPage = 20, int $page = 1): array
    {
        $query = Shipment::query()->with(['order.user'])->orderByDesc('id');
        if ($status) {
            $query->where('status', strtolower($status));
        }
        if ($carrier) {
            $query->where('carrier', 'like', "%{$carrier}%");
        }
        if ($q) {
            $query->where(function ($inner) use ($q) {
                $inner->where('tracking_number', 'like', "%{$q}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$q}%"));
            });
        }

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
        $data = collect($paginator->items())->map(function (Shipment $s) {
            return [
                'id' => $s->id,
                'order_id' => $s->order_id,
                'order_number' => $s->order?->order_number,
                'customer' => $s->order?->user?->name,
                'carrier' => $s->carrier,
                'tracking_number' => $s->tracking_number,
                'tracking_url' => $s->tracking_url,
                'status' => $s->status,
                'warehouse_id' => $s->warehouse_id,
                'eta_date' => optional($s->eta_date)?->format('Y-m-d'),
                'shipped_at' => optional($s->shipped_at)?->toIso8601String(),
                'delivered_at' => optional($s->delivered_at)?->toIso8601String(),
                'created_at' => optional($s->created_at)?->toIso8601String(),
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

    public function showShipment(int $id): array
    {
        $shipment = Shipment::query()->with(['events', 'order.user', 'order.items'])->find($id);
        if (! $shipment) {
            throw new NotFoundHttpException('Shipment not found');
        }

        return $this->serializeShipment($shipment, $shipment->order);
    }

    public function listExceptions(int $perPage = 20, int $page = 1): array
    {
        $result = $this->queue('exceptions', null, null, $perPage, $page);
        // Also surface PROCESSING orders with open pick exceptions
        $pickExc = Order::query()
            ->with(['user', 'items', 'shipment'])
            ->where('status', 'PROCESSING')
            ->where('meta->fulfillment->has_open_exception', true)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Order $o) => $this->serializeQueueRow($o))
            ->all();

        $result['pick_exceptions'] = $pickExc;

        return $result;
    }

    public function resolveException(int $orderId, string $exceptionId, ?string $note = null, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $exceptionId, $note, $actorUserId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            $fulfillment = $this->fulfillmentMeta($order);
            $found = false;
            foreach ($fulfillment['exceptions'] ?? [] as $i => $ex) {
                if (($ex['id'] ?? '') === $exceptionId) {
                    $fulfillment['exceptions'][$i]['status'] = 'resolved';
                    $fulfillment['exceptions'][$i]['resolved_note'] = $note;
                    $fulfillment['exceptions'][$i]['resolved_at'] = now()->toIso8601String();
                    $found = true;
                }
            }
            if (! $found) {
                throw new NotFoundHttpException('Exception not found');
            }
            $open = collect($fulfillment['exceptions'])->contains(fn ($e) => ($e['status'] ?? '') === 'open');
            $fulfillment['has_open_exception'] = $open;
            $this->writeFulfillmentMeta($order, $fulfillment);
            AuditLogger::log('fulfillment.exception_resolve', 'order', $order->id, null, ['exception_id' => $exceptionId], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    private function advanceOrder(int $orderId, string $to, string $note, ?int $actorUserId, string $shipmentStatus): array
    {
        return DB::transaction(function () use ($orderId, $to, $note, $actorUserId, $shipmentStatus) {
            $order = Order::query()->with(['shipment', 'user'])->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }
            if ($order->status === $to) {
                return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
            }

            $this->stateMachine->transition($order, $to, $actorUserId, $note);
            $shipment = $order->shipment;
            if ($shipment) {
                $shipment->status = $shipmentStatus;
                if ($to === 'DELIVERED') {
                    $shipment->delivered_at = now();
                }
                $shipment->save();
                $this->addEvent($shipment, $to, $note, null, $actorUserId, ['source' => 'admin']);
            }
            if ($to === 'DELIVERED') {
                $this->loyalty->earnForDeliveredOrder($order->fresh(), $actorUserId);
            }
            $this->notifyForStatus($order->fresh('user'), $to);
            AuditLogger::log('fulfillment.status', 'order', $order->id, null, ['status' => $to], $actorUserId);

            return $this->serializeFulfillmentDetail($order->fresh(['user', 'items', 'shipment.events']));
        });
    }

    private function addEvent(
        Shipment $shipment,
        string $status,
        ?string $description,
        ?string $location,
        ?int $actorUserId,
        array $meta = [],
        ?string $eventAt = null,
    ): ShipmentEvent {
        return ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'status' => $status,
            'description' => $description,
            'location' => $location,
            'event_at' => $eventAt ? \Carbon\Carbon::parse($eventAt) : now(),
            'meta' => array_merge($meta, ['actor_user_id' => $actorUserId]),
        ]);
    }

    private function fulfillmentMeta(Order $order): array
    {
        $meta = $order->meta ?? [];

        return is_array($meta['fulfillment'] ?? null) ? $meta['fulfillment'] : [];
    }

    private function writeFulfillmentMeta(Order $order, array $fulfillment): void
    {
        $meta = $order->meta ?? [];
        $meta['fulfillment'] = $fulfillment;
        $order->meta = $meta;
        $order->save();
    }

    private function notifyCustomer(Order $order, string $type, string $title, string $body): void
    {
        if (! $order->user_id) {
            return;
        }
        $this->notifications->notify($order->user_id, $type, $title, $body, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    private function notifyForStatus(Order $order, string $status): void
    {
        match ($status) {
            'PACKED' => $this->notifyCustomer($order, 'order_packed', 'Order packed', "Order {$order->order_number} is packed."),
            'SHIPPED' => $this->notifyCustomer($order, 'order_shipped', 'Order shipped', "Order {$order->order_number} has shipped."),
            'OUT_FOR_DELIVERY' => $this->notifyCustomer($order, 'order_out_for_delivery', 'Out for delivery', "Order {$order->order_number} is out for delivery."),
            'DELIVERED' => $this->notifyCustomer($order, 'order_delivered', 'Order delivered', "Order {$order->order_number} was delivered."),
            'DELIVERY_FAILED' => $this->notifyCustomer($order, 'delivery_failed', 'Delivery failed', "Delivery for {$order->order_number} failed."),
            default => null,
        };
    }

    private function serializeQueueRow(Order $o): array
    {
        $f = $this->fulfillmentMeta($o);

        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'customer' => $o->user?->name,
            'customer_email' => $o->user?->email,
            'warehouse_id' => $f['warehouse_id'] ?? $o->warehouse_id,
            'item_count' => $o->items->count(),
            'units' => (int) $o->items->sum('quantity'),
            'picking_completed' => ! empty($f['picking_completed_at']),
            'has_open_exception' => (bool) ($f['has_open_exception'] ?? false),
            'tracking_number' => $o->shipment?->tracking_number,
            'carrier' => $o->shipment?->carrier,
            'created_at' => optional($o->created_at)?->toIso8601String(),
            'confirmed_at' => optional($o->confirmed_at)?->toIso8601String(),
        ];
    }

    private function serializeFulfillmentDetail(Order $order): array
    {
        $f = $this->fulfillmentMeta($order);
        $items = [];
        foreach ($order->items as $item) {
            $pick = $f['items'][(string) $item->id] ?? null;
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'required' => (int) $item->quantity,
                'picked' => (int) ($pick['picked'] ?? 0),
                'unit_price' => (float) $item->unit_price,
            ];
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer' => [
                'id' => $order->user_id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'shipping_address' => $order->shipping_address_json,
            'warehouse_id' => $f['warehouse_id'] ?? $order->warehouse_id,
            'fulfillment' => [
                'picking_started_at' => $f['picking_started_at'] ?? null,
                'picking_completed_at' => $f['picking_completed_at'] ?? null,
                'packed_at' => $f['packed_at'] ?? null,
                'package_count' => $f['package_count'] ?? null,
                'weight_grams' => $f['weight_grams'] ?? null,
                'has_open_exception' => (bool) ($f['has_open_exception'] ?? false),
                'exceptions' => $f['exceptions'] ?? [],
            ],
            'items' => $items,
            'shipment' => $order->shipment ? $this->serializeShipment($order->shipment, $order) : null,
            'actions' => [
                'can_start_picking' => $order->status === 'CONFIRMED',
                'can_update_pick' => $order->status === 'PROCESSING' && empty($f['picking_completed_at']),
                'can_complete_pick' => $order->status === 'PROCESSING' && empty($f['picking_completed_at']),
                'can_pack' => $order->status === 'PROCESSING' && ! empty($f['picking_completed_at']),
                'can_ship' => $order->status === 'PACKED',
                'can_out_for_delivery' => $order->status === 'SHIPPED',
                'can_deliver' => in_array($order->status, ['SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERY_FAILED'], true),
                'can_fail_delivery' => $order->status === 'OUT_FOR_DELIVERY',
                'can_retry_delivery' => $order->status === 'DELIVERY_FAILED',
            ],
        ];
    }

    private function serializeShipment(Shipment $shipment, ?Order $order = null): array
    {
        $events = $shipment->relationLoaded('events')
            ? $shipment->events
            : $shipment->events()->get();

        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'order_number' => $order?->order_number,
            'customer' => $order?->user?->name,
            'status' => $shipment->status,
            'carrier' => $shipment->carrier,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'shipping_method_id' => $shipment->shipping_method_id,
            'warehouse_id' => $shipment->warehouse_id,
            'eta_date' => optional($shipment->eta_date)?->format('Y-m-d'),
            'weight_grams' => $shipment->weight_grams,
            'shipped_at' => optional($shipment->shipped_at)?->toIso8601String(),
            'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
            'meta' => [
                'package_count' => $shipment->meta['package_count'] ?? null,
                'provider' => $shipment->meta['provider'] ?? null,
                'failure_reason' => $shipment->meta['failure_reason'] ?? null,
            ],
            'events' => $events->map(fn (ShipmentEvent $e) => [
                'id' => $e->id,
                'status' => $e->status,
                'description' => $e->description,
                'location' => $e->location,
                'event_at' => optional($e->event_at)?->toIso8601String(),
            ])->values()->all(),
            'shipping_address' => $order?->shipping_address_json,
        ];
    }
}
