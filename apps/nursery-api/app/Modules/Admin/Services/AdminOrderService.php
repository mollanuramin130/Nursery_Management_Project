<?php

namespace App\Modules\Admin\Services;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\OrderNotificationDispatcher;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\Shipment;
use App\Modules\Order\Models\ShipmentEvent;
use App\Modules\Order\Services\OrderStateMachine;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminOrderService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
        private readonly OrderNotificationDispatcher $orderNotifications,
    ) {}

    public function list(?string $status, ?string $q, int $perPage = 20): array
    {
        $paginator = Order::query()
            ->with(['user', 'latestPayment'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('order_number', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'grand_total' => (float) $o->grand_total,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->latestPayment?->status,
                'customer' => [
                    'id' => $o->user_id,
                    'name' => $o->user?->name,
                    'email' => $o->user?->email,
                ],
                'placed_at' => optional($o->placed_at ?? $o->created_at)?->toIso8601String(),
            ])->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function detail(int $id): array
    {
        $order = Order::query()
            ->with(['user', 'items', 'statusHistories', 'latestPayment', 'shipment', 'payments'])
            ->find($id);

        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'tax_total' => (float) $order->tax_total,
            'shipping_total' => (float) $order->shipping_total,
            'grand_total' => (float) $order->grand_total,
            'coupon_code' => $order->coupon_code,
            'payment_method' => $order->payment_method,
            'customer' => [
                'id' => $order->user_id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'shipping_address' => $order->shipping_address_json,
            'billing_address' => $order->billing_address_json,
            'payment' => $order->latestPayment ? [
                'id' => $order->latestPayment->id,
                'status' => $order->latestPayment->status,
                'method' => $order->latestPayment->method,
                'amount' => (float) $order->latestPayment->amount,
                'paid_at' => optional($order->latestPayment->paid_at)?->toIso8601String(),
                'provider' => $order->latestPayment->provider,
                'provider_payment_id' => $order->latestPayment->provider_payment_id,
                'provider_order_id' => $order->latestPayment->provider_order_id,
                'upi_mode' => data_get($order->latestPayment->meta, 'upi_mode'),
                'channel' => data_get($order->latestPayment->meta, 'channel'),
            ] : null,
            'shipment' => $order->shipment ? [
                'status' => $order->shipment->status,
                'carrier' => $order->shipment->carrier,
                'tracking_number' => $order->shipment->tracking_number,
                'tracking_url' => $order->shipment->tracking_url,
                'shipped_at' => optional($order->shipment->shipped_at)?->toIso8601String(),
            ] : null,
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->name,
                'sku' => $i->sku,
                'unit_price' => (float) $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => (float) $i->line_total,
            ])->values()->all(),
            'status_history' => $order->statusHistories->map(fn ($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'note' => $h->note,
                'at' => optional($h->created_at)?->toIso8601String(),
            ])->values()->all(),
            'created_at' => optional($order->created_at)?->toIso8601String(),
        ];
    }

    public function updateStatus(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        return DB::transaction(function () use ($id, $payload, $actorUserId, $requestId) {
            $order = Order::query()->with('items')->whereKey($id)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            $to = strtoupper($payload['status']);
            $before = $order->status;

            if ($to === 'CANCELLED') {
                $this->handleCancelInventory($order, $actorUserId);
            }

            $order = $this->stateMachine->transition(
                $order,
                $to,
                $actorUserId,
                $payload['note'] ?? null,
                $requestId,
            );

            if (in_array($to, ['SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'DELIVERY_FAILED'], true)) {
                $shipment = Shipment::query()->firstOrNew(['order_id' => $order->id]);
                $shipment->fill([
                    'status' => strtolower($to),
                    'carrier' => $payload['carrier'] ?? $shipment->carrier,
                    'tracking_number' => $payload['tracking_number'] ?? $shipment->tracking_number,
                    'tracking_url' => $payload['tracking_url'] ?? $shipment->tracking_url,
                    'shipping_method_id' => $order->shipping_method_id,
                    'warehouse_id' => $order->warehouse_id,
                ]);
                if ($to === 'SHIPPED' && ! $shipment->shipped_at) {
                    $shipment->shipped_at = now();
                }
                if ($to === 'DELIVERED') {
                    $shipment->delivered_at = now();
                }
                $shipment->save();

                ShipmentEvent::query()->create([
                    'shipment_id' => $shipment->id,
                    'status' => $to,
                    'description' => $payload['note'] ?? $to,
                    'location' => null,
                    'event_at' => now(),
                    'meta' => ['source' => 'admin_order_status', 'actor_user_id' => $actorUserId],
                ]);
            }

            AuditLogger::log('order.status', 'order', $order->id, ['status' => $before], ['status' => $order->status], $actorUserId, [
                'note' => $payload['note'] ?? null,
            ]);

            $this->notifyStatusChange($order, $to);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ];
        });
    }

    private function notifyStatusChange(Order $order, string $to): void
    {
        $this->orderNotifications->notifyCustomerStatus($order, $to);
    }

    private function handleCancelInventory(Order $order, ?int $actorUserId): void
    {
        if (! in_array($order->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED', 'CONFIRMED', 'PROCESSING', 'PACKED'], true)) {
            throw new ApiException('Order cannot be cancelled', 409, 'CONFLICT');
        }

        $lines = $order->items->map(fn (OrderItem $i) => [
            'product_id' => $i->product_id,
            'quantity' => $i->quantity,
            'variant_id' => $i->product_variant_id,
        ])->all();

        if (in_array($order->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) {
            $this->inventory->release($lines, 'order', $order->id, $actorUserId);

            return;
        }

        $warehouseId = $order->warehouse_id
            ?? Warehouse::query()->where('is_default', true)->value('id');

        foreach ($lines as $line) {
            $this->inventory->adjust([
                'warehouse_id' => $warehouseId,
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'adjustment' => $line['quantity'],
                'reason' => 'return_in',
                'note' => 'Admin cancelled restock',
            ], $actorUserId);
        }
    }
}
