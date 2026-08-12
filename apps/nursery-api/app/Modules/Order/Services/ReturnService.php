<?php

namespace App\Modules\Order\Services;

use App\Modules\Admin\Models\Refund;
use App\Modules\Admin\Models\Setting;
use App\Modules\Admin\Services\AdminRefundService;
use App\Modules\Auth\Models\User;
use App\Modules\Delivery\Contracts\ShippingProvider;
use App\Modules\Delivery\Providers\InternalDeliveryProvider;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\ReturnItem;
use App\Modules\Order\Models\ReturnRequest;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReturnService
{
    public const REASONS = [
        ['code' => 'damaged', 'label' => 'Damaged or unhealthy plant'],
        ['code' => 'wrong_item', 'label' => 'Wrong item received'],
        ['code' => 'not_as_described', 'label' => 'Not as described'],
        ['code' => 'changed_mind', 'label' => 'Changed my mind'],
        ['code' => 'other', 'label' => 'Other'],
    ];

    public const CONDITIONS = ['GOOD', 'DAMAGED', 'OPENED', 'UNSELLABLE'];

    public const DISPOSITIONS = ['SELLABLE', 'DAMAGED', 'DISPOSAL'];

    /** Statuses that consume returnable quantity for an order item. */
    public const ACTIVE_STATUSES = [
        'RETURN_REQUESTED',
        'APPROVED',
        'PICKUP_SCHEDULED',
        'PICKED_UP',
        'RECEIVED',
        'UNDER_INSPECTION',
        'COMPLETED',
    ];

    public const OPEN_STATUSES = [
        'RETURN_REQUESTED',
        'APPROVED',
        'PICKUP_SCHEDULED',
        'PICKED_UP',
        'RECEIVED',
        'UNDER_INSPECTION',
    ];

    private const TRANSITIONS = [
        'RETURN_REQUESTED' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['PICKUP_SCHEDULED', 'RECEIVED', 'REJECTED', 'CANCELLED'],
        'PICKUP_SCHEDULED' => ['PICKED_UP', 'RECEIVED', 'CANCELLED'],
        'PICKED_UP' => ['RECEIVED'],
        'RECEIVED' => ['UNDER_INSPECTION', 'COMPLETED'],
        'UNDER_INSPECTION' => ['COMPLETED'],
        'REJECTED' => [],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    private readonly ShippingProvider $shippingProvider;

    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
        private readonly AdminRefundService $refunds,
        ?ShippingProvider $shippingProvider = null,
    ) {
        $this->shippingProvider = $shippingProvider ?? new InternalDeliveryProvider;
    }

    public function returnWindowDays(): ?int
    {
        $setting = Setting::query()->where('key', 'returns.window_days')->first();
        if (! $setting || $setting->value === null || $setting->value === '') {
            return null;
        }

        return (int) $setting->typedValue();
    }

    public function canReturn(Order $order): bool
    {
        if ($order->status !== 'DELIVERED') {
            return false;
        }

        $windowDays = $this->returnWindowDays();
        if ($windowDays !== null) {
            $deliveredAt = $this->deliveredAt($order);
            if ($deliveredAt && $deliveredAt->copy()->addDays($windowDays)->isPast()) {
                return false;
            }
        }

        return $this->hasReturnableQuantity($order);
    }

    public function listForUser(User $user, int $perPage = 20): array
    {
        $paginator = ReturnRequest::query()
            ->with(['items.orderItem', 'order'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 50));

        $data = collect($paginator->items())
            ->map(fn (ReturnRequest $r) => $this->serialize($r, false))
            ->values()
            ->all();

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

    public function forOrder(User $user, int $orderId): array
    {
        $order = Order::query()->with(['items', 'shipment'])->where('user_id', $user->id)->whereKey($orderId)->first();
        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        $returns = ReturnRequest::query()
            ->with(['items.orderItem'])
            ->where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReturnRequest $r) => $this->serialize($r, false))
            ->values()
            ->all();

        return [
            'order_id' => $order->id,
            'can_return' => $this->canReturn($order),
            'return_window_days' => $this->returnWindowDays(),
            'return_reasons' => self::REASONS,
            'returnable_items' => $this->returnableItems($order),
            'returns' => $returns,
        ];
    }

    public function showForUser(User $user, int $returnId): array
    {
        $return = ReturnRequest::query()
            ->with(['items.orderItem', 'order'])
            ->where('user_id', $user->id)
            ->whereKey($returnId)
            ->first();

        if (! $return) {
            throw new NotFoundHttpException('Return request not found');
        }

        return $this->serialize($return, false);
    }

    public function request(User $user, int $orderId, array $payload): array
    {
        return DB::transaction(function () use ($user, $orderId, $payload) {
            $order = Order::query()
                ->with(['items', 'shipment'])
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            if ($order->status !== 'DELIVERED') {
                throw new ApiException('Only delivered orders can be returned', 409, 'CONFLICT');
            }

            $windowDays = $this->returnWindowDays();
            if ($windowDays !== null) {
                $deliveredAt = $this->deliveredAt($order);
                if ($deliveredAt && $deliveredAt->copy()->addDays($windowDays)->isPast()) {
                    throw new ApiException('Return window has expired', 409, 'CONFLICT');
                }
            }

            if (ReturnRequest::query()
                ->where('order_id', $order->id)
                ->whereIn('status', self::OPEN_STATUSES)
                ->lockForUpdate()
                ->exists()) {
                throw new ApiException('An open return request already exists for this order', 409, 'CONFLICT');
            }

            if (empty($payload['items']) || ! is_array($payload['items'])) {
                throw new ApiException('Return items are required', 422, 'VALIDATION_ERROR');
            }

            $return = ReturnRequest::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'RETURN_REQUESTED',
                'notes' => $payload['notes'] ?? null,
                'meta' => [
                    'timeline' => [[
                        'status' => 'RETURN_REQUESTED',
                        'at' => now()->toIso8601String(),
                        'note' => $payload['notes'] ?? 'Return requested',
                        'actor_user_id' => $user->id,
                    ]],
                ],
            ]);

            foreach ($payload['items'] as $line) {
                /** @var OrderItem|null $orderItem */
                $orderItem = $order->items->firstWhere('id', (int) $line['order_item_id']);
                if (! $orderItem) {
                    throw new ApiException('Invalid order item for return', 422, 'VALIDATION_ERROR');
                }

                $qty = (int) $line['quantity'];
                $returnable = $this->returnableQtyForItem($order, (int) $orderItem->id);
                if ($qty < 1 || $qty > $returnable) {
                    throw new ApiException(
                        "Invalid return quantity for item {$orderItem->id} (returnable: {$returnable})",
                        422,
                        'VALIDATION_ERROR',
                    );
                }

                ReturnItem::query()->create([
                    'return_request_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $qty,
                    'reason' => $this->normalizeReason($line['reason'] ?? null),
                    'meta' => [],
                ]);
            }

            $this->stateMachine->transition($order, 'RETURN_REQUESTED', $user->id, $payload['notes'] ?? 'Return requested');

            AuditLogger::log(
                'return.request',
                'return_request',
                $return->id,
                null,
                ['order_id' => $order->id, 'status' => 'RETURN_REQUESTED'],
                $user->id,
            );

            $this->notify(
                $user,
                'RETURN_UPDATED',
                'Return request submitted',
                'We received your return request for order '.$order->order_number.'.',
                ['order_id' => $order->id, 'return_id' => $return->id, 'status' => 'RETURN_REQUESTED'],
            );

            return [
                'return_id' => $return->id,
                'order_id' => $order->id,
                'status' => 'RETURN_REQUESTED',
            ];
        });
    }

    public function adminList(?string $status, ?string $q, int $perPage = 20, int $page = 1): array
    {
        $query = ReturnRequest::query()
            ->with(['items.orderItem', 'order.user', 'user'])
            ->when($status, fn ($qb) => $qb->where('status', strtoupper($status)))
            ->when($q, function ($qb) use ($q) {
                $term = trim($q);
                $qb->where(function ($inner) use ($term) {
                    $inner->where('id', (int) $term)
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                        ->orWhereHas('user', function ($u) use ($term) {
                            $u->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));

        $data = collect($paginator->items())
            ->map(fn (ReturnRequest $r) => $this->serialize($r, true))
            ->values()
            ->all();

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

    public function adminShow(int $returnId): array
    {
        $return = ReturnRequest::query()
            ->with(['items.orderItem', 'order.user', 'order.items', 'user'])
            ->whereKey($returnId)
            ->first();

        if (! $return) {
            throw new NotFoundHttpException('Return request not found');
        }

        return $this->serialize($return, true);
    }

    public function dashboard(): array
    {
        $counts = ReturnRequest::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'requested' => (int) ($counts['RETURN_REQUESTED'] ?? 0),
            'approved' => (int) ($counts['APPROVED'] ?? 0),
            'pickup_scheduled' => (int) ($counts['PICKUP_SCHEDULED'] ?? 0),
            'picked_up' => (int) ($counts['PICKED_UP'] ?? 0),
            'received' => (int) ($counts['RECEIVED'] ?? 0),
            'under_inspection' => (int) ($counts['UNDER_INSPECTION'] ?? 0),
            'completed' => (int) ($counts['COMPLETED'] ?? 0),
            'rejected' => (int) ($counts['REJECTED'] ?? 0),
            'open_total' => ReturnRequest::query()->whereIn('status', self::OPEN_STATUSES)->count(),
            'suggested_refund_note' => 'Use suggestRefundAmount after inspection accepted quantities are set.',
        ];
    }

    public function approve(int $returnId, ?int $actorUserId = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($returnId, $actorUserId, $note) {
            $return = $this->lockReturn($returnId);
            $this->transitionReturn($return, 'APPROVED', $actorUserId, $note ?? 'Return approved');
            $return->decided_at = now();
            $return->decided_by = $actorUserId;
            $return->save();

            AuditLogger::log(
                'return.approve',
                'return_request',
                $return->id,
                ['status' => 'RETURN_REQUESTED'],
                ['status' => 'APPROVED'],
                $actorUserId,
            );

            $this->notifyReturn($return, 'Return approved', 'Your return request was approved. Pickup will be scheduled shortly.');

            return $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);
        });
    }

    public function reject(int $returnId, ?int $actorUserId = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($returnId, $actorUserId, $reason) {
            $return = $this->lockReturn($returnId);
            $from = $return->status;
            $this->assertTransition($from, 'REJECTED');

            $meta = $return->meta ?? [];
            $meta['rejection_reason'] = $reason;
            $return->meta = $meta;
            $return->decided_at = now();
            $return->decided_by = $actorUserId;
            $return->status = 'REJECTED';
            $return->save();
            $this->pushTimeline($return, 'REJECTED', $actorUserId, $reason ?? 'Return rejected');

            $order = Order::query()->whereKey($return->order_id)->lockForUpdate()->first();
            if ($order && $order->status === 'RETURN_REQUESTED') {
                $this->stateMachine->transition($order, 'DELIVERED', $actorUserId, $reason ?? 'Return rejected');
            }

            AuditLogger::log(
                'return.reject',
                'return_request',
                $return->id,
                ['status' => $from],
                ['status' => 'REJECTED', 'reason' => $reason],
                $actorUserId,
            );

            $this->notifyReturn($return, 'Return rejected', $reason ?: 'Your return request was rejected.');

            return $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);
        });
    }

    public function schedulePickup(int $returnId, array $payload = [], ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($returnId, $payload, $actorUserId) {
            $return = $this->lockReturn($returnId);
            $return->loadMissing(['order']);

            $order = $return->order;
            if (! $order) {
                throw new NotFoundHttpException('Order not found for return');
            }

            $shipmentPayload = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'warehouse_id' => $order->warehouse_id,
                'carrier' => $payload['carrier'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'tracking_url' => $payload['tracking_url'] ?? null,
                'eta_date' => $payload['eta_date'] ?? null,
            ];

            $tracking = $this->shippingProvider->createShipment($shipmentPayload);

            $meta = $return->meta ?? [];
            $meta['reverse_shipment'] = [
                'carrier' => $tracking['carrier'],
                'tracking_number' => $tracking['tracking_number'],
                'tracking_url' => $tracking['tracking_url'] ?? null,
                'eta_date' => $tracking['eta_date'] ?? null,
                'provider' => $this->shippingProvider->code(),
                'scheduled_at' => now()->toIso8601String(),
                'scheduled_by' => $actorUserId,
                'meta' => $tracking['meta'] ?? [],
            ];
            if (! empty($payload['pickup_notes'])) {
                $meta['pickup_notes'] = $payload['pickup_notes'];
            }
            $return->meta = $meta;
            $return->save();

            $this->transitionReturn($return, 'PICKUP_SCHEDULED', $actorUserId, $payload['note'] ?? 'Pickup scheduled');

            AuditLogger::log(
                'return.schedule_pickup',
                'return_request',
                $return->id,
                null,
                ['reverse_shipment' => $meta['reverse_shipment']],
                $actorUserId,
            );

            $this->notifyReturn(
                $return,
                'Return pickup scheduled',
                'Pickup for your return has been scheduled. Tracking: '.$tracking['tracking_number'],
            );

            return $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);
        });
    }

    public function markPickedUp(int $returnId, ?int $actorUserId = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($returnId, $actorUserId, $note) {
            $return = $this->lockReturn($returnId);
            $this->transitionReturn($return, 'PICKED_UP', $actorUserId, $note ?? 'Items picked up');

            $meta = $return->meta ?? [];
            $meta['picked_up_at'] = now()->toIso8601String();
            $return->meta = $meta;
            $return->save();

            AuditLogger::log('return.picked_up', 'return_request', $return->id, null, ['status' => 'PICKED_UP'], $actorUserId);
            $this->notifyReturn($return, 'Return picked up', 'Your return items have been collected.');

            return $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);
        });
    }

    /**
     * @param  list<array{return_item_id:int, received_qty:int, condition:string}>  $lines
     */
    public function receive(int $returnId, array $lines, ?int $actorUserId = null, ?string $note = null): array
    {
        return DB::transaction(function () use ($returnId, $lines, $actorUserId, $note) {
            $return = $this->lockReturn($returnId);
            $return->loadMissing(['items']);

            if (empty($lines)) {
                throw new ApiException('Received lines are required', 422, 'VALIDATION_ERROR');
            }

            $itemsById = $return->items->keyBy('id');

            foreach ($lines as $line) {
                $itemId = (int) ($line['return_item_id'] ?? 0);
                /** @var ReturnItem|null $item */
                $item = $itemsById->get($itemId);
                if (! $item) {
                    throw new ApiException('Invalid return item', 422, 'VALIDATION_ERROR');
                }

                $receivedQty = (int) ($line['received_qty'] ?? 0);
                if ($receivedQty < 0 || $receivedQty > (int) $item->quantity) {
                    throw new ApiException('Invalid received quantity', 422, 'VALIDATION_ERROR');
                }

                $condition = strtoupper((string) ($line['condition'] ?? 'GOOD'));
                if (! in_array($condition, self::CONDITIONS, true)) {
                    throw new ApiException('Invalid condition', 422, 'VALIDATION_ERROR');
                }

                $itemMeta = $item->meta ?? [];
                $itemMeta['received_qty'] = $receivedQty;
                $itemMeta['condition'] = $condition;
                $itemMeta['received_at'] = now()->toIso8601String();
                $item->meta = $itemMeta;
                $item->save();
            }

            $return->received_at = now();
            $return->save();

            $this->transitionReturn($return, 'RECEIVED', $actorUserId, $note ?? 'Return received at warehouse');

            AuditLogger::log('return.receive', 'return_request', $return->id, null, ['lines' => $lines], $actorUserId);
            $this->notifyReturn($return, 'Return received', 'Your returned items were received at our warehouse.');

            return $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);
        });
    }

    /**
     * @param  array{
     *   items?: list<array{return_item_id:int, accepted_qty:int, rejected_qty:int, disposition:string}>,
     *   create_refund?: bool,
     *   refund_amount?: float|null,
     *   note?: string|null
     * }  $payload
     */
    public function inspect(int $returnId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($returnId, $payload, $actorUserId) {
            $return = $this->lockReturn($returnId);
            $return->loadMissing(['items.orderItem', 'order']);

            if (! in_array($return->status, ['RECEIVED', 'UNDER_INSPECTION'], true)) {
                throw new ApiException("Cannot inspect return in status {$return->status}", 409, 'CONFLICT');
            }

            if ($return->status === 'RECEIVED') {
                $this->transitionReturn($return, 'UNDER_INSPECTION', $actorUserId, 'Inspection started');
            }

            $lines = $payload['items'] ?? [];
            if (empty($lines)) {
                throw new ApiException('Inspection items are required', 422, 'VALIDATION_ERROR');
            }

            $itemsById = $return->items->keyBy('id');
            $warehouseId = $this->resolveWarehouseId($return->order);
            $inspection = [];

            foreach ($lines as $line) {
                $itemId = (int) ($line['return_item_id'] ?? 0);
                /** @var ReturnItem|null $item */
                $item = $itemsById->get($itemId);
                if (! $item) {
                    throw new ApiException('Invalid return item', 422, 'VALIDATION_ERROR');
                }

                $accepted = (int) ($line['accepted_qty'] ?? 0);
                $rejected = (int) ($line['rejected_qty'] ?? 0);
                $received = (int) (($item->meta['received_qty'] ?? null) ?? $item->quantity);

                if ($accepted < 0 || $rejected < 0 || ($accepted + $rejected) > $received) {
                    throw new ApiException(
                        "accepted + rejected must be <= received ({$received}) for return item {$itemId}",
                        422,
                        'VALIDATION_ERROR',
                    );
                }

                $disposition = strtoupper((string) ($line['disposition'] ?? 'SELLABLE'));
                if (! in_array($disposition, self::DISPOSITIONS, true)) {
                    throw new ApiException('Invalid disposition', 422, 'VALIDATION_ERROR');
                }

                $orderItem = $item->orderItem;
                if (! $orderItem) {
                    throw new ApiException('Order item missing for return line', 422, 'VALIDATION_ERROR');
                }

                if ($accepted > 0) {
                    $this->applyDispositionStock(
                        $disposition,
                        $warehouseId,
                        (int) $orderItem->product_id,
                        $orderItem->product_variant_id ? (int) $orderItem->product_variant_id : null,
                        $accepted,
                        (int) $item->id,
                        $actorUserId,
                    );
                }

                $itemMeta = $item->meta ?? [];
                $itemMeta['accepted_qty'] = $accepted;
                $itemMeta['rejected_qty'] = $rejected;
                $itemMeta['disposition'] = $disposition;
                $itemMeta['inspected_at'] = now()->toIso8601String();
                $item->meta = $itemMeta;
                $item->save();

                $inspection[] = [
                    'return_item_id' => $item->id,
                    'accepted_qty' => $accepted,
                    'rejected_qty' => $rejected,
                    'disposition' => $disposition,
                ];
            }

            $meta = $return->meta ?? [];
            $meta['inspection'] = [
                'completed_at' => now()->toIso8601String(),
                'inspected_by' => $actorUserId,
                'items' => $inspection,
                'note' => $payload['note'] ?? null,
            ];
            $return->meta = $meta;
            $return->completed_at = now();
            $return->save();

            $this->transitionReturn($return, 'COMPLETED', $actorUserId, $payload['note'] ?? 'Inspection completed');

            $order = Order::query()->whereKey($return->order_id)->lockForUpdate()->first();
            if ($order && $order->status === 'RETURN_REQUESTED') {
                $this->stateMachine->transition($order, 'RETURNED', $actorUserId, 'Return inspection completed');
            }

            AuditLogger::log(
                'return.inspect',
                'return_request',
                $return->id,
                null,
                ['inspection' => $meta['inspection']],
                $actorUserId,
            );

            $this->notifyReturn($return, 'Return inspection complete', 'Your return has been inspected and completed.');

            $result = $this->serialize($return->fresh(['items.orderItem', 'order.user', 'user']), true);

            if (! empty($payload['create_refund'])) {
                $amount = isset($payload['refund_amount'])
                    ? (float) $payload['refund_amount']
                    : $this->suggestRefundAmount($return->fresh(['items.orderItem']));

                $result['refund'] = $this->createRefundForReturn(
                    $return->id,
                    [
                        'amount' => $amount,
                        'reason' => $payload['note'] ?? 'Return refund',
                        'idempotency_key' => $payload['idempotency_key'] ?? ('return-'.$return->id.'-refund'),
                    ],
                    $actorUserId,
                );
            }

            return $result;
        });
    }

    public function createRefundForReturn(int $returnId, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($returnId, $payload, $actorUserId) {
            $return = $this->lockReturn($returnId);
            $return->loadMissing(['order', 'items.orderItem']);

            if ($return->status !== 'COMPLETED') {
                throw new ApiException('Refunds require a completed return inspection', 409, 'CONFLICT');
            }

            $amount = isset($payload['amount'])
                ? (float) $payload['amount']
                : $this->suggestRefundAmount($return);

            if ($amount <= 0) {
                throw new ApiException('Refund amount must be positive', 422, 'VALIDATION_ERROR');
            }

            $idempotencyKey = (string) ($payload['idempotency_key'] ?? ('return-'.$return->id.'-refund'));

            $refundResult = $this->refunds->create([
                'order_id' => $return->order_id,
                'amount' => $amount,
                'reason' => $payload['reason'] ?? ('Return #'.$return->id),
                'note' => $payload['note'] ?? null,
                'payment_id' => $payload['payment_id'] ?? null,
                'return_request_id' => $return->id,
                'idempotency_key' => $idempotencyKey,
            ], $actorUserId);

            $status = (string) ($refundResult['status'] ?? '');
            $order = Order::query()->whereKey($return->order_id)->lockForUpdate()->first();
            if ($order && in_array($status, ['recorded_local', 'success', 'processed'], true)) {
                if ($order->status === 'RETURN_REQUESTED') {
                    $this->stateMachine->transition($order, 'RETURNED', $actorUserId, 'Return completed before refund');
                    $order = $order->fresh();
                }
                if ($order && $order->status === 'RETURNED') {
                    $this->stateMachine->transition($order, 'REFUNDED', $actorUserId, 'Refund recorded for return');
                }
            }

            $meta = $return->meta ?? [];
            $meta['refund'] = [
                'refund_id' => $refundResult['id'] ?? null,
                'amount' => $refundResult['amount'] ?? $amount,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'at' => now()->toIso8601String(),
            ];
            $return->meta = $meta;
            $return->save();
            $this->pushTimeline($return, $return->status, $actorUserId, 'Refund linked: '.$status);

            AuditLogger::log(
                'return.refund',
                'return_request',
                $return->id,
                null,
                $meta['refund'],
                $actorUserId,
            );

            if ($return->user_id) {
                $this->notify(
                    $return->user_id,
                    'REFUND_UPDATED',
                    'Refund processed',
                    'A refund related to your return has been recorded.',
                    [
                        'order_id' => $return->order_id,
                        'return_id' => $return->id,
                        'refund_id' => $refundResult['id'] ?? null,
                        'status' => $status,
                    ],
                );
            }

            return $refundResult;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ReturnRequest $return, bool $admin = false): array
    {
        $return->loadMissing(['items.orderItem', 'order']);

        $meta = $return->meta ?? [];
        $reverse = $meta['reverse_shipment'] ?? null;

        $pickupTracking = null;
        if (is_array($reverse)) {
            $pickupTracking = [
                'carrier' => $reverse['carrier'] ?? null,
                'tracking_number' => $reverse['tracking_number'] ?? null,
                'tracking_url' => $reverse['tracking_url'] ?? null,
                'eta_date' => $reverse['eta_date'] ?? null,
                'status' => $return->status,
            ];
        }

        $items = $return->items->map(function (ReturnItem $i) use ($admin) {
            $row = [
                'id' => $i->id,
                'order_item_id' => $i->order_item_id,
                'product_id' => $i->orderItem?->product_id,
                'name' => $i->orderItem?->name,
                'sku' => $i->orderItem?->sku,
                'unit_price' => $i->orderItem ? (float) $i->orderItem->unit_price : null,
                'quantity' => (int) $i->quantity,
                'reason' => $i->reason,
            ];

            if ($admin) {
                $row['received_qty'] = $i->meta['received_qty'] ?? null;
                $row['condition'] = $i->meta['condition'] ?? null;
                $row['accepted_qty'] = $i->meta['accepted_qty'] ?? null;
                $row['rejected_qty'] = $i->meta['rejected_qty'] ?? null;
                $row['disposition'] = $i->meta['disposition'] ?? null;
                $row['meta'] = $i->meta;
            } else {
                $row['received_qty'] = $i->meta['received_qty'] ?? null;
                $row['accepted_qty'] = $i->meta['accepted_qty'] ?? null;
            }

            return $row;
        })->values()->all();

        $payload = [
            'id' => $return->id,
            'order_id' => $return->order_id,
            'order_number' => $return->order?->order_number,
            'status' => $return->status,
            'notes' => $return->notes,
            'created_at' => optional($return->created_at)?->toIso8601String(),
            'updated_at' => optional($return->updated_at)?->toIso8601String(),
            'decided_at' => optional($return->decided_at)?->toIso8601String(),
            'received_at' => optional($return->received_at)?->toIso8601String(),
            'completed_at' => optional($return->completed_at)?->toIso8601String(),
            'items' => $items,
            'pickup_tracking' => $pickupTracking,
            'timeline' => $meta['timeline'] ?? [],
        ];

        if ($admin) {
            $payload['user'] = [
                'id' => $return->user_id,
                'name' => $return->user?->name ?? $return->order?->user?->name,
                'email' => $return->user?->email ?? $return->order?->user?->email,
            ];
            $payload['decided_by'] = $return->decided_by;
            $payload['rejection_reason'] = $meta['rejection_reason'] ?? null;
            $payload['reverse_shipment'] = $reverse;
            $payload['inspection'] = $meta['inspection'] ?? null;
            $payload['refund'] = $meta['refund'] ?? null;
            $payload['suggested_refund_amount'] = $this->suggestRefundAmount($return);
            $payload['actions'] = $this->availableActions($return->status);
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    public function suggestRefundAmount(ReturnRequest $return): float
    {
        $return->loadMissing(['items.orderItem']);

        $total = 0.0;
        foreach ($return->items as $item) {
            $unit = (float) ($item->orderItem?->unit_price ?? 0);
            $qty = (int) (($item->meta['accepted_qty'] ?? null) ?? $item->quantity);
            $total += $unit * max(0, $qty);
        }

        return round($total, 2);
    }

    public function returnableQtyForItem(Order $order, int $orderItemId): int
    {
        /** @var OrderItem|null $orderItem */
        $orderItem = $order->relationLoaded('items')
            ? $order->items->firstWhere('id', $orderItemId)
            : OrderItem::query()->where('order_id', $order->id)->whereKey($orderItemId)->first();

        if (! $orderItem) {
            return 0;
        }

        $used = (int) ReturnItem::query()
            ->where('order_item_id', $orderItemId)
            ->whereHas('returnRequest', function ($q) use ($order) {
                $q->where('order_id', $order->id)
                    ->whereIn('status', self::ACTIVE_STATUSES);
            })
            ->sum('quantity');

        return max(0, (int) $orderItem->quantity - $used);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function returnableItems(Order $order): array
    {
        $order->loadMissing('items');

        return $order->items->map(function (OrderItem $item) use ($order) {
            $returnable = $this->returnableQtyForItem($order, (int) $item->id);

            return [
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'returnable_qty' => $returnable,
                'thumbnail_url' => $item->thumbnail_url,
            ];
        })->filter(fn (array $row) => $row['returnable_qty'] > 0)->values()->all();
    }

    public function assertTransition(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new ApiException("Cannot transition return from {$from} to {$to}", 409, 'CONFLICT');
        }
    }

    public function pushTimeline(ReturnRequest $return, string $status, ?int $actorUserId = null, ?string $note = null): void
    {
        $meta = $return->meta ?? [];
        $timeline = $meta['timeline'] ?? [];
        $timeline[] = [
            'status' => $status,
            'at' => now()->toIso8601String(),
            'note' => $note,
            'actor_user_id' => $actorUserId,
        ];
        $meta['timeline'] = $timeline;
        $return->meta = $meta;
        $return->save();
    }

    public function notify(User|int $user, string $type, string $title, string $body, array $data = []): void
    {
        try {
            $this->notifications->notify($user, $type, $title, $body, $data);
        } catch (\Throwable) {
            // Notification failure must not block return workflow.
        }
    }

    public function normalizeReason(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        $raw = strtolower(trim($reason));
        $aliases = [
            'damaged' => 'damaged',
            'damage' => 'damaged',
            'wrong_item' => 'wrong_item',
            'wrong_product' => 'wrong_item',
            'not_as_described' => 'not_as_described',
            'not_as_expected' => 'not_as_described',
            'changed_mind' => 'changed_mind',
            'customer_changed_mind' => 'changed_mind',
            'other' => 'other',
        ];

        if (isset($aliases[$raw])) {
            return $aliases[$raw];
        }

        $codes = array_column(self::REASONS, 'code');
        if (in_array($raw, $codes, true)) {
            return $raw;
        }

        // Persist controlled codes only; free-text falls back to other with original in notes via caller.
        return 'other';
    }

    public function transitionReturn(ReturnRequest $return, string $toStatus, ?int $actorUserId = null, ?string $note = null): ReturnRequest
    {
        $this->assertTransition($return->status, $toStatus);
        $return->status = $toStatus;
        $return->save();
        $this->pushTimeline($return, $toStatus, $actorUserId, $note);

        return $return;
    }

    private function lockReturn(int $returnId): ReturnRequest
    {
        $return = ReturnRequest::query()->whereKey($returnId)->lockForUpdate()->first();
        if (! $return) {
            throw new NotFoundHttpException('Return request not found');
        }

        return $return;
    }

    private function hasReturnableQuantity(Order $order): bool
    {
        $order->loadMissing('items');
        foreach ($order->items as $item) {
            if ($this->returnableQtyForItem($order, (int) $item->id) > 0) {
                return true;
            }
        }

        return false;
    }

    private function deliveredAt(Order $order): ?\Illuminate\Support\Carbon
    {
        $order->loadMissing('shipment');
        if ($order->shipment?->delivered_at) {
            return $order->shipment->delivered_at;
        }

        $history = $order->statusHistories()
            ->where('to_status', 'DELIVERED')
            ->orderByDesc('id')
            ->first();

        return $history?->created_at;
    }

    private function resolveWarehouseId(?Order $order): int
    {
        $warehouseId = $order?->warehouse_id
            ?? Warehouse::query()->where('is_default', true)->value('id')
            ?? Warehouse::query()->value('id');

        if (! $warehouseId) {
            throw new ApiException('No warehouse available for restock', 409, 'CONFLICT');
        }

        return (int) $warehouseId;
    }

    private function applyDispositionStock(
        string $disposition,
        int $warehouseId,
        int $productId,
        ?int $variantId,
        int $qty,
        int $returnItemId,
        ?int $actorUserId,
    ): void {
        if ($disposition === 'DISPOSAL') {
            return;
        }

        $this->inventory->adjust([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'adjustment' => $qty,
            'reason' => 'return_in',
            'note' => 'Customer return restock',
            'reference_type' => 'return_item',
            'reference_id' => $returnItemId,
        ], $actorUserId);

        if ($disposition === 'DAMAGED') {
            $this->inventory->adjust([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'adjustment' => -$qty,
                'reason' => 'damaged',
                'note' => 'Returned item marked damaged',
                'reference_type' => 'return_item',
                'reference_id' => $returnItemId,
            ], $actorUserId);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function availableActions(string $status): array
    {
        return [
            'can_approve' => $status === 'RETURN_REQUESTED',
            'can_reject' => in_array($status, ['RETURN_REQUESTED', 'APPROVED', 'PICKUP_SCHEDULED'], true),
            'can_schedule_pickup' => $status === 'APPROVED',
            'can_mark_picked_up' => $status === 'PICKUP_SCHEDULED',
            'can_receive' => in_array($status, ['APPROVED', 'PICKUP_SCHEDULED', 'PICKED_UP'], true),
            'can_inspect' => in_array($status, ['RECEIVED', 'UNDER_INSPECTION'], true),
            'can_refund' => $status === 'COMPLETED',
        ];
    }

    private function notifyReturn(ReturnRequest $return, string $title, string $body): void
    {
        if (! $return->user_id) {
            return;
        }

        $this->notify(
            $return->user_id,
            'RETURN_UPDATED',
            $title,
            $body,
            [
                'order_id' => $return->order_id,
                'return_id' => $return->id,
                'status' => $return->status,
            ],
        );
    }
}
