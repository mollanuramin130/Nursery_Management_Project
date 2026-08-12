<?php

namespace App\Modules\Order\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Customer\Services\AddressService;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Review\Models\Review;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponRedemption;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly AddressService $addresses,
        private readonly InventoryService $inventory,
        private readonly OrderStateMachine $stateMachine,
        private readonly NotificationService $notifications,
    ) {}

    public function preview(User $user, array $payload): array
    {
        $cart = $this->activeCart($user);
        $totals = $this->buildTotals(
            $cart,
            $user,
            $payload['coupon_code'] ?? $cart->coupon_code,
            $payload['shipping_method_id'] ?? null,
        );
        $address = $this->addresses->owned($user, (int) $payload['address_id']);
        $shipping = $totals['shipping_method'];

        return [
            'items' => collect($totals['items'])->map(fn ($i) => [
                'product_id' => $i['product_id'],
                'name' => $i['name'],
                'quantity' => $i['quantity'],
                'unit_price' => $i['unit_price'],
                'line_total' => $i['line_total'],
            ])->values()->all(),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'coupon_code' => $totals['coupon_code'],
            'tax_total' => $totals['tax_total'],
            'shipping_total' => $totals['shipping_total'],
            'grand_total' => $totals['grand_total'],
            'currency' => 'INR',
            'free_delivery' => $totals['free_delivery'],
            'shipping_method' => $shipping ? [
                'id' => $shipping->id,
                'name' => $shipping->name,
                'eta_min_days' => (int) $shipping->eta_min_days,
                'eta_max_days' => (int) $shipping->eta_max_days,
                'price' => (float) $shipping->price,
            ] : null,
            'address' => [
                'id' => $address->id,
                'name' => $address->name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ],
        ];
    }

    public function placeOrder(User $user, array $payload, ?string $ip = null, ?string $platform = null, ?string $requestId = null): array
    {
        // Idempotent retry of the same client request.
        if ($requestId) {
            $existingByRequest = Order::query()
                ->where('user_id', $user->id)
                ->where('request_id', $requestId)
                ->first();
            if ($existingByRequest) {
                return $this->summary($existingByRequest->load('items'));
            }
        }

        $pending = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'PENDING_PAYMENT')
            ->orderByDesc('id')
            ->first();
        if ($pending) {
            throw new ApiException(
                'You already have an unpaid order. Complete or cancel it before placing another.',
                409,
                'PENDING_ORDER_EXISTS',
                ['order_id' => $pending->id, 'order_number' => $pending->order_number],
            );
        }

        $cart = $this->activeCart($user);
        if ($cart->items()->count() === 0) {
            throw new ApiException('Cart is empty', 400, 'BAD_REQUEST');
        }

        $address = $this->addresses->owned($user, (int) $payload['address_id']);
        $couponCode = $payload['coupon_code'] ?? $cart->coupon_code;

        return DB::transaction(function () use ($user, $cart, $address, $payload, $couponCode, $ip, $platform, $requestId) {
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->first();
            $totals = $this->buildTotals($cart, $user, $couponCode, $payload['shipping_method_id'] ?? null);

            foreach ($totals['items'] as $line) {
                $this->inventory->assertAvailable($line['product_id'], $line['quantity'], $line['variant_id']);
            }

            $warehouseId = Warehouse::query()->where('is_default', true)->value('id')
                ?? Warehouse::query()->value('id');

            $order = Order::query()->create([
                'order_number' => $this->nextOrderNumber(),
                'user_id' => $user->id,
                'status' => 'PENDING_PAYMENT',
                'currency' => 'INR',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'shipping_total' => $totals['shipping_total'],
                'grand_total' => $totals['grand_total'],
                'coupon_code' => $totals['coupon_code'],
                'campaign_id' => isset($payload['campaign_id']) ? (int) $payload['campaign_id'] : null,
                'payment_method' => $payload['payment_method'] ?? 'razorpay',
                'shipping_method_id' => $totals['shipping_method']?->id,
                'notes' => $payload['notes'] ?? null,
                'shipping_address_json' => $address->toApiArray(),
                'billing_address_json' => $address->toApiArray(),
                'placed_at' => now(),
                'ip' => $ip,
                'platform' => $platform,
                'request_id' => $requestId,
                'warehouse_id' => $warehouseId,
            ]);

            foreach ($totals['items'] as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    'sku' => $line['sku'],
                    'name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'product_type' => $line['product_type'],
                    'thumbnail_url' => $line['thumbnail_url'],
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                ]);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'PENDING_PAYMENT',
                'actor_user_id' => $user->id,
                'note' => 'Order placed',
                'request_id' => $requestId,
            ]);

            $reserveLines = collect($totals['items'])->map(fn ($i) => [
                'product_id' => $i['product_id'],
                'quantity' => $i['quantity'],
                'variant_id' => $i['variant_id'],
            ])->all();

            $this->inventory->reserve($reserveLines, 'order', $order->id, $user->id);

            // Cart is cleared ONLY after successful confirmation (COD or paid).
            // Keeping cart on unpaid Razorpay attempts prevents loss on cancel/fail.

            if (($payload['payment_method'] ?? '') === 'cod') {
                $this->inventory->commit($reserveLines, 'order', $order->id, $user->id);
                $order = $this->stateMachine->transition($order, 'CONFIRMED', $user->id, 'COD accepted', $requestId);
                $this->recordCouponRedemption($order);
                $this->clearUserCart($user);
                $this->notifications->notify(
                    $user->id,
                    'order_confirmed',
                    'Order confirmed',
                    "Order {$order->order_number} is confirmed (COD).",
                    ['order_id' => $order->id, 'order_number' => $order->order_number],
                );
            }

            return $this->summary($order->fresh(['items']));
        });
    }

    /** Clear authenticated cart after a successful order confirmation. */
    public function clearUserCart(User $user): void
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();
        if (! $cart) {
            return;
        }
        $cart->items()->delete();
        $cart->coupon_code = null;
        $cart->save();
    }

    public function recordCouponRedemption(Order $order): void
    {
        if (! $order->coupon_code) {
            return;
        }
        if (CouponRedemption::query()->where('order_id', $order->id)->exists()) {
            return;
        }
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper((string) $order->coupon_code)])
            ->first();
        if (! $coupon) {
            return;
        }
        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $order->discount_total,
        ]);
    }

    public function listOrders(User $user, ?string $status = null, ?string $q = null, int $perPage = 20): array
    {
        $query = Order::query()
            ->where('user_id', $user->id)
            ->withCount('items')
            ->with([
                'items' => fn ($rel) => $rel->orderBy('id'),
                'latestPayment',
                'shipment',
                'returnRequests',
            ])
            ->orderByDesc('id');

        if ($status === 'active') {
            $query->whereIn('status', [
                'PENDING_PAYMENT', 'CONFIRMED', 'PROCESSING', 'PACKED', 'SHIPPED', 'OUT_FOR_DELIVERY',
            ]);
        } elseif ($status) {
            $query->where('status', strtoupper($status));
        }

        if ($q) {
            $term = trim($q);
            $query->where('order_number', 'like', '%'.$term.'%');
        }

        $paginator = $query->paginate(min(max($perPage, 1), 50));
        $returnService = app(ReturnService::class);
        $data = collect($paginator->items())->map(function (Order $o) use ($returnService) {
            $first = $o->items->first();
            $paymentStatus = $o->latestPayment?->status
                ?? ($o->status === 'PENDING_PAYMENT' ? 'pending' : ($o->payment_method === 'cod' && $o->status !== 'CANCELLED' ? 'cod' : null));

            return [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $paymentStatus,
                'payment_method' => $o->payment_method,
                'grand_total' => (float) $o->grand_total,
                'currency' => $o->currency,
                'item_count' => (int) $o->items_count,
                'thumbnail' => $first?->thumbnail_url,
                'preview_name' => $first?->name,
                'placed_at' => optional($o->placed_at ?? $o->created_at)?->toIso8601String(),
                'created_at' => optional($o->created_at)?->toIso8601String(),
                'estimated_delivery' => optional($o->shipment?->eta_date)?->format('Y-m-d'),
                'can_cancel' => $this->canCancel($o),
                'can_reorder' => $this->canReorder($o),
                'can_return' => $returnService->canReturn($o),
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

    public function detail(User $user, int $orderId): array
    {
        $order = Order::query()
            ->with([
                'items.product',
                'statusHistories',
                'latestPayment',
                'shipment',
                'returnRequests.items',
                'refunds',
            ])
            ->where('user_id', $user->id)
            ->whereKey($orderId)
            ->first();

        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        $payment = $order->latestPayment;
        $shipment = $order->shipment;
        $tracking = $this->buildTrackingPayload($order);
        $returnService = app(ReturnService::class);
        $canReturn = $returnService->canReturn($order);
        $reviewableProductIds = $this->reviewableProductIds($user, $order);

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
            'cancel_reason' => $order->cancel_reason,
            'cancelled_at' => optional($order->cancelled_at)?->toIso8601String(),
            'payment' => $payment ? [
                'status' => $payment->status,
                'method' => $payment->method ?? $order->payment_method,
                'amount' => (float) $payment->amount,
                'paid_at' => optional($payment->paid_at)?->toIso8601String(),
            ] : [
                'status' => $order->status === 'PENDING_PAYMENT' ? 'pending' : ($order->payment_method === 'cod' ? 'cod' : null),
                'method' => $order->payment_method,
                'amount' => (float) $order->grand_total,
                'paid_at' => null,
            ],
            'shipping_address' => collect($order->shipping_address_json)->only([
                'name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country',
            ])->all(),
            'shipment' => $shipment ? [
                'status' => $shipment->status,
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'estimated_delivery' => optional($shipment->eta_date)?->format('Y-m-d'),
                'shipped_at' => optional($shipment->shipped_at)?->toIso8601String(),
                'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
            ] : null,
            'tracking' => $tracking,
            'status_history' => $order->statusHistories->map(fn ($h) => [
                'status' => $h->to_status,
                'at' => optional($h->created_at)?->toIso8601String(),
                'note' => $h->note,
            ])->values()->all(),
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'product_slug' => $i->product?->slug,
                'name' => $i->name,
                'sku' => $i->sku,
                'unit_price' => (float) $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => (float) $i->line_total,
                'thumbnail_url' => $i->thumbnail_url,
            ])->values()->all(),
            'actions' => [
                'can_cancel' => $this->canCancel($order),
                'can_reorder' => $this->canReorder($order),
                'can_return' => $canReturn,
            ],
            'can_cancel' => $this->canCancel($order),
            'can_reorder' => $this->canReorder($order),
            'can_return' => $canReturn,
            'return_reasons' => ReturnService::REASONS,
            'returnable_items' => $returnService->returnableItems($order),
            'returns' => $order->returnRequests->map(fn ($r) => $returnService->serialize($r))->values()->all(),
            'refunds' => $order->refunds->map(fn ($r) => [
                'id' => $r->id,
                'amount' => (float) $r->amount,
                'currency' => $r->currency ?? $order->currency,
                'status' => $r->status,
                'reason' => $r->reason,
                'created_at' => optional($r->created_at)?->toIso8601String(),
            ])->values()->all(),
            'reviewable_product_ids' => $reviewableProductIds,
            'placed_at' => optional($order->placed_at ?? $order->created_at)?->toIso8601String(),
            'created_at' => optional($order->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Product IDs on this order that the customer may still review.
     *
     * @return list<int>
     */
    private function reviewableProductIds(User $user, Order $order): array
    {
        if (! in_array($order->status, [
            'DELIVERED', 'RETURN_REQUESTED', 'RETURNED', 'REFUNDED',
        ], true)) {
            return [];
        }

        $productIds = $order->items->pluck('product_id')->filter()->unique()->values()->all();
        if ($productIds === []) {
            return [];
        }

        $already = Review::query()
            ->where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();

        return array_values(array_map('intval', array_diff($productIds, $already)));
    }

    public function tracking(User $user, int $orderId): array
    {
        $order = Order::query()
            ->with(['statusHistories', 'shipment'])
            ->where('user_id', $user->id)
            ->whereKey($orderId)
            ->first();

        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        return $this->buildTrackingPayload($order);
    }

    public function cancel(User $user, int $orderId, ?string $reason = null, ?string $reasonCode = null): array
    {
        return DB::transaction(function () use ($user, $orderId, $reason, $reasonCode) {
            $order = Order::query()
                ->with(['items', 'latestPayment'])
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            if ($order->status === 'CANCELLED') {
                throw new ApiException('Order is already cancelled', 409, 'CONFLICT');
            }

            if (! $this->canCancel($order)) {
                throw new ApiException(
                    'This order can no longer be cancelled.',
                    409,
                    'ORDER_CANCELLATION_NOT_ALLOWED',
                );
            }

            $note = $this->resolveCancelReason($reasonCode, $reason);
            $wasCommitted = in_array($order->status, ['CONFIRMED', 'PROCESSING', 'PACKED'], true);

            $lines = $order->items->map(fn (OrderItem $i) => [
                'product_id' => $i->product_id,
                'quantity' => $i->quantity,
                'variant_id' => $i->product_variant_id,
            ])->all();

            if (in_array($order->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) {
                $this->inventory->release($lines, 'order', $order->id, $user->id);
            } elseif ($wasCommitted) {
                foreach ($lines as $line) {
                    $this->inventory->adjust([
                        'warehouse_id' => $order->warehouse_id ?? Warehouse::query()->where('is_default', true)->value('id'),
                        'product_id' => $line['product_id'],
                        'variant_id' => $line['variant_id'],
                        'adjustment' => $line['quantity'],
                        'reason' => 'return_in',
                        'note' => 'Order cancelled restock',
                    ], $user->id);
                }
                CouponRedemption::query()->where('order_id', $order->id)->delete();
            }

            $paymentStatus = null;
            $payment = $order->latestPayment;
            if ($wasCommitted
                && $payment
                && $payment->status === 'success'
                && ($order->payment_method === 'razorpay' || $payment->provider === 'razorpay')) {
                $payment->status = 'refund_pending';
                $payment->failure_code = 'cancel_refund_pending';
                $payment->failure_message = 'Refund pending after customer cancellation';
                $payment->save();
                $paymentStatus = 'refund_pending';
            } elseif (($order->payment_method ?? '') === 'cod' || ! $payment || $payment->status !== 'success') {
                $paymentStatus = 'not_required';
            } else {
                $paymentStatus = $payment->status;
            }

            $order = $this->stateMachine->transition($order, 'CANCELLED', $user->id, $note);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'cancel_reason' => $order->cancel_reason,
                'cancelled_at' => optional($order->cancelled_at)?->toIso8601String(),
                'payment_status' => $paymentStatus,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $paymentStatus,
                ],
            ];
        });
    }

    /**
     * Add purchasable lines from a past order into the user's cart at current prices.
     *
     * @return array{cart: array, reorder_summary: array}
     */
    public function reorder(User $user, int $orderId): array
    {
        $order = Order::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->whereKey($orderId)
            ->first();

        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        if (! $this->canReorder($order)) {
            throw new ApiException('This order cannot be reordered', 409, 'CONFLICT');
        }

        $cart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'INR', 'status' => 'active', 'cart_token' => null],
        );
        if ($cart->status !== 'active') {
            $cart->status = 'active';
            $cart->save();
        }

        $added = [];
        $unavailable = [];
        $priceChanged = [];
        $quantityAdjusted = [];

        foreach ($order->items as $line) {
            $product = Product::query()->active()->find($line->product_id);
            if (! $product) {
                $unavailable[] = [
                    'product_id' => $line->product_id,
                    'name' => $line->name,
                    'reason' => 'unavailable',
                ];
                continue;
            }

            $available = $this->inventory->sellableQty($product->id, $line->product_variant_id);
            if ($available < 1) {
                $unavailable[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'reason' => 'out_of_stock',
                ];
                continue;
            }

            $qty = min((int) $line->quantity, $available);
            $oldPrice = (float) $line->unit_price;
            $currentPrice = (float) $product->price;

            try {
                $this->carts->addItem($cart, $product->id, $qty, $line->product_variant_id);
            } catch (\Throwable) {
                $unavailable[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'reason' => 'unavailable',
                ];
                continue;
            }

            $added[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $qty,
                'unit_price' => $currentPrice,
            ];

            if (abs($oldPrice - $currentPrice) > 0.009) {
                $priceChanged[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'previous_price' => $oldPrice,
                    'current_price' => $currentPrice,
                ];
            }
            if ($qty < (int) $line->quantity) {
                $quantityAdjusted[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'requested' => (int) $line->quantity,
                    'added' => $qty,
                ];
            }
        }

        if ($added === []) {
            throw new ApiException(
                'No items from this order are currently available',
                409,
                'REORDER_UNAVAILABLE',
                [
                    'reorder_summary' => [
                        'added' => $added,
                        'unavailable' => $unavailable,
                        'price_changed' => $priceChanged,
                        'quantity_adjusted' => $quantityAdjusted,
                    ],
                ],
            );
        }

        return [
            'cart' => $this->carts->present($cart->fresh()),
            'reorder_summary' => [
                'added' => $added,
                'unavailable' => $unavailable,
                'price_changed' => $priceChanged,
                'quantity_adjusted' => $quantityAdjusted,
            ],
        ];
    }

    public function canCancel(Order $order): bool
    {
        return in_array($order->status, [
            'PENDING_PAYMENT', 'PAYMENT_FAILED', 'CONFIRMED', 'PROCESSING', 'PACKED',
        ], true);
    }

    public function canReorder(Order $order): bool
    {
        return $order->items()->exists();
    }

    private function resolveCancelReason(?string $reasonCode, ?string $reason): string
    {
        $map = [
            'changed_mind' => 'Changed my mind',
            'ordered_by_mistake' => 'Ordered by mistake',
            'better_price' => 'Found a better price',
            'delivery_slow' => 'Delivery is taking too long',
            'no_longer_needed' => 'Product no longer needed',
            'other' => 'Other',
        ];

        if ($reasonCode && isset($map[$reasonCode])) {
            if ($reasonCode === 'other' && $reason) {
                return trim($reason);
            }

            return $map[$reasonCode];
        }

        return $reason ? trim($reason) : 'Cancelled by customer';
    }

    private function buildTrackingPayload(Order $order): array
    {
        $order->loadMissing(['statusHistories', 'shipment']);
        $historyByStatus = [];
        foreach ($order->statusHistories as $h) {
            $historyByStatus[$h->to_status] = $h;
        }

        $steps = [
            'CONFIRMED' => ['title' => 'Order confirmed', 'description' => 'Your order has been confirmed.'],
            'PROCESSING' => ['title' => 'Preparing your plants', 'description' => 'Your order is being prepared at our nursery.'],
            'PACKED' => ['title' => 'Packed', 'description' => 'Your plants are packed and ready to ship.'],
            'SHIPPED' => ['title' => 'Order shipped', 'description' => 'Your order has left our nursery.'],
            'OUT_FOR_DELIVERY' => ['title' => 'Out for delivery', 'description' => 'Your order is on the way to you.'],
            'DELIVERED' => ['title' => 'Delivered', 'description' => 'Your order was delivered.'],
        ];

        if ($order->status === 'PENDING_PAYMENT') {
            $steps = [
                'PENDING_PAYMENT' => ['title' => 'Awaiting payment', 'description' => 'Complete payment to confirm your order.'],
            ];
        } elseif ($order->status === 'PAYMENT_FAILED') {
            $steps = [
                'PAYMENT_FAILED' => ['title' => 'Payment failed', 'description' => 'Payment was not completed.'],
            ];
        } elseif ($order->status === 'CANCELLED') {
            $steps = [
                'CANCELLED' => ['title' => 'Order cancelled', 'description' => $order->cancel_reason ?: 'This order was cancelled.'],
            ];
        } elseif ($order->status === 'DELIVERY_FAILED') {
            $steps = [
                'CONFIRMED' => ['title' => 'Order confirmed', 'description' => 'Your order has been confirmed.'],
                'PROCESSING' => ['title' => 'Preparing your plants', 'description' => 'Your order is being prepared at our nursery.'],
                'PACKED' => ['title' => 'Packed', 'description' => 'Your plants are packed and ready to ship.'],
                'SHIPPED' => ['title' => 'Order shipped', 'description' => 'Your order has left our nursery.'],
                'OUT_FOR_DELIVERY' => ['title' => 'Out for delivery', 'description' => 'Your order is on the way to you.'],
                'DELIVERY_FAILED' => ['title' => 'Delivery attempt failed', 'description' => 'We could not complete delivery. A reattempt will be scheduled.'],
            ];
        }

        $rank = array_flip(array_keys($steps));
        $currentRank = $rank[$order->status] ?? -1;
        if (in_array($order->status, ['RETURN_REQUESTED', 'RETURNED', 'REFUNDED'], true)) {
            $currentRank = $rank['DELIVERED'] ?? 999;
        }

        $timeline = [];
        foreach ($steps as $status => $meta) {
            $hist = $historyByStatus[$status] ?? null;
            $stepRank = $rank[$status] ?? -1;
            $completed = false;
            if ($hist) {
                $completed = true;
            } elseif ($currentRank >= 0 && $stepRank <= $currentRank
                && ! in_array($order->status, ['CANCELLED', 'PAYMENT_FAILED', 'PENDING_PAYMENT'], true)) {
                $completed = true;
            }
            if ($order->status === $status) {
                $completed = true;
            }

            $timeline[] = [
                'status' => $status,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'completed' => (bool) $completed,
                'current' => $order->status === $status,
                'created_at' => $hist ? optional($hist->created_at)?->toIso8601String() : null,
            ];
        }

        $order->loadMissing('shipment.events');
        $shipment = $order->shipment;

        $events = [];
        if ($shipment) {
            foreach ($shipment->events as $event) {
                $events[] = [
                    'status' => $event->status,
                    'description' => $event->description,
                    'location' => $event->location,
                    'event_at' => optional($event->event_at)?->toIso8601String(),
                ];
            }
        }

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'current_status' => $order->status,
            'estimated_delivery' => optional($shipment?->eta_date)?->format('Y-m-d'),
            'updated_at' => optional($order->updated_at)?->toIso8601String(),
            'shipment' => $shipment ? [
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'status' => $shipment->status,
                'shipped_at' => optional($shipment->shipped_at)?->toIso8601String(),
                'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
            ] : null,
            'events' => $events,
            'timeline' => $timeline,
        ];
    }

    private function activeCart(User $user): Cart
    {
        $cart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->with(['items.product.images'])->first();
        if (! $cart) {
            throw new ApiException('Cart is empty', 400, 'BAD_REQUEST');
        }

        return $cart;
    }

    private function buildTotals(Cart $cart, User $user, ?string $couponCode, ?int $shippingMethodId): array
    {
        $cart->loadMissing(['items.product.images', 'items.variant']);
        $items = [];
        $subtotal = 0.0;

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }
            $unit = (float) ($item->unit_price_snapshot ?? $item->product->price);
            if ($item->variant?->price !== null) {
                $unit = (float) $item->variant->price;
            }
            $lineTotal = round($unit * $item->quantity, 2);
            $subtotal += $lineTotal;
            $items[] = [
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'sku' => $item->product->sku,
                'name' => $item->product->name,
                'product_type' => $item->product->product_type,
                'thumbnail_url' => $item->product->primaryImageUrl(),
                'unit_price' => $unit,
                'quantity' => $item->quantity,
                'line_total' => $lineTotal,
            ];
        }

        if ($items === []) {
            throw new ApiException('Cart is empty', 400, 'BAD_REQUEST');
        }

        $discount = 0.0;
        $appliedCoupon = null;
        if ($couponCode) {
            $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper($couponCode)])->first();
            if (! $coupon || ! $coupon->isCurrentlyValid()) {
                throw new ApiException('Invalid or expired coupon', 400, 'BAD_REQUEST');
            }
            if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
                throw new ApiException('Cart does not meet coupon minimum order amount', 400, 'BAD_REQUEST');
            }
            $this->assertCouponUsageAvailable($coupon, $user);
            $discount = $coupon->discount_type === 'percent'
                ? round($subtotal * ((float) $coupon->discount_value / 100), 2)
                : (float) $coupon->discount_value;
            if ($coupon->max_discount_amount !== null) {
                $discount = min($discount, (float) $coupon->max_discount_amount);
            }
            $discount = min($discount, $subtotal);
            $appliedCoupon = $coupon->code;
        }

        $threshold = (float) env('FREE_DELIVERY_THRESHOLD', 999);
        $merchandise = round(max(0, $subtotal - $discount), 2);
        $qualifiesFree = $threshold > 0 && $merchandise >= $threshold;

        $shipping = null;
        $shippingTotal = 0.0;
        if ($shippingMethodId) {
            $shipping = ShippingMethod::query()->active()->find($shippingMethodId);
            if (! $shipping) {
                throw new NotFoundHttpException('Shipping method not found');
            }
            $shippingTotal = $qualifiesFree ? 0.0 : (float) $shipping->price;
        }

        $tax = 0.0;
        $grand = round(max(0, $subtotal - $discount + $tax + $shippingTotal), 2);

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'coupon_code' => $appliedCoupon,
            'tax_total' => $tax,
            'shipping_total' => $shippingTotal,
            'grand_total' => $grand,
            'shipping_method' => $shipping,
            'free_delivery' => [
                'enabled' => $threshold > 0,
                'threshold' => $threshold,
                'remaining' => round(max(0, $threshold - $merchandise), 2),
                'qualifies' => $qualifiesFree,
            ],
        ];
    }

    private function assertCouponUsageAvailable(Coupon $coupon, User $user): void
    {
        if ($coupon->usage_limit_total !== null) {
            $total = CouponRedemption::query()->where('coupon_id', $coupon->id)->count();
            if ($total >= (int) $coupon->usage_limit_total) {
                throw new ApiException('This coupon has reached its usage limit', 400, 'BAD_REQUEST');
            }
        }
        if ($coupon->usage_limit_per_user !== null) {
            $perUser = CouponRedemption::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->count();
            if ($perUser >= (int) $coupon->usage_limit_per_user) {
                throw new ApiException('You have already used this coupon the maximum number of times', 400, 'BAD_REQUEST');
            }
        }
    }

    private function nextOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "ORD-{$date}-";

        $latest = Order::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $seq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $seq);
    }

    /**
     * Create a normal order for a subscription cycle (no cart).
     * Reuses inventory reserve/commit and order state machine.
     */
    public function placeSubscriptionOrder(
        \App\Modules\Subscription\Models\Subscription $subscription,
        \App\Modules\Subscription\Models\SubscriptionCycle $cycle,
        ?string $ip = null,
        ?string $platform = null,
        ?string $requestId = null,
    ): array {
        if ($requestId) {
            $existingByRequest = Order::query()
                ->where('user_id', $subscription->user_id)
                ->where('request_id', $requestId)
                ->first();
            if ($existingByRequest) {
                return $this->summary($existingByRequest->load('items'));
            }
        }

        $product = Product::query()->whereKey($subscription->product_id)->first();
        if (! $product || $product->status !== 'active') {
            throw new ApiException('Subscription product unavailable', 409, 'OUT_OF_STOCK');
        }

        $qty = (int) $cycle->quantity;
        $unit = (float) $cycle->unit_price;
        $lineTotal = round($unit * $qty, 2);
        $subtotal = $lineTotal;
        $discount = 0.0;
        $tax = 0.0;

        $threshold = (float) env('FREE_DELIVERY_THRESHOLD', 999);
        $qualifiesFree = $threshold > 0 && $subtotal >= $threshold;
        $shipping = null;
        $shippingTotal = 0.0;
        if ($subscription->shipping_method_id) {
            $shipping = ShippingMethod::query()->active()->find($subscription->shipping_method_id);
            if ($shipping) {
                $shippingTotal = $qualifiesFree ? 0.0 : (float) $shipping->price;
            }
        }
        $grand = round(max(0, $subtotal - $discount + $tax + $shippingTotal), 2);

        $this->inventory->assertAvailable($product->id, $qty, null);

        $warehouseId = Warehouse::query()->where('is_default', true)->value('id')
            ?? Warehouse::query()->value('id');

        $user = User::query()->findOrFail($subscription->user_id);

        $order = Order::query()->create([
            'order_number' => $this->nextOrderNumber(),
            'user_id' => $subscription->user_id,
            'status' => 'PENDING_PAYMENT',
            'currency' => $subscription->currency ?: 'INR',
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'shipping_total' => $shippingTotal,
            'grand_total' => $grand,
            'payment_method' => $subscription->payment_method ?: 'razorpay',
            'shipping_method_id' => $shipping?->id ?? $subscription->shipping_method_id,
            'notes' => 'Subscription '.$subscription->subscription_number.' cycle #'.$cycle->cycle_number,
            'shipping_address_json' => $subscription->shipping_address_json,
            'billing_address_json' => $subscription->billing_address_json,
            'placed_at' => now(),
            'ip' => $ip,
            'platform' => $platform,
            'request_id' => $requestId,
            'warehouse_id' => $warehouseId,
            'subscription_id' => $subscription->id,
            'subscription_cycle_id' => $cycle->id,
            'meta' => [
                'source' => 'subscription',
                'subscription_id' => $subscription->id,
                'subscription_cycle_id' => $cycle->id,
                'cycle_number' => $cycle->cycle_number,
            ],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => $unit,
            'quantity' => $qty,
            'line_total' => $lineTotal,
            'product_type' => $product->product_type,
            'thumbnail_url' => $product->primaryImageUrl(),
            'tax_amount' => 0,
            'discount_amount' => 0,
            'meta' => ['subscription_cycle_id' => $cycle->id],
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'PENDING_PAYMENT',
            'actor_user_id' => $subscription->user_id,
            'note' => 'Subscription cycle order',
            'request_id' => $requestId,
        ]);

        $reserveLines = [[
            'product_id' => $product->id,
            'quantity' => $qty,
            'variant_id' => null,
        ]];
        $this->inventory->reserve($reserveLines, 'order', $order->id, $subscription->user_id);

        $cycle->order_id = $order->id;
        $cycle->amount = $grand;
        $cycle->status = 'AWAITING_PAYMENT';
        $cycle->processed_at = now();
        $cycle->save();

        if (($subscription->payment_method ?? '') === 'cod') {
            $this->inventory->commit($reserveLines, 'order', $order->id, $subscription->user_id);
            $order = $this->stateMachine->transition($order, 'CONFIRMED', $subscription->user_id, 'COD subscription cycle');
            $this->notifications->notify(
                $user->id,
                'order_confirmed',
                'Order confirmed',
                "Order {$order->order_number} is confirmed (COD subscription).",
                ['order_id' => $order->id, 'order_number' => $order->order_number],
            );
            app(\App\Modules\Subscription\Services\SubscriptionService::class)->onOrderPaid($order->fresh());
        }

        return $this->summary($order->fresh(['items']));
    }

    public function summaryPublic(Order $order): array
    {
        return $this->summary($order);
    }

    private function summary(Order $order): array
    {
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
            'payment_method' => $order->payment_method,
            'subscription_id' => $order->subscription_id,
            'subscription_cycle_id' => $order->subscription_cycle_id,
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->name,
                'sku' => $i->sku,
                'unit_price' => (float) $i->unit_price,
                'quantity' => $i->quantity,
                'line_total' => (float) $i->line_total,
            ])->values()->all(),
            'created_at' => optional($order->created_at)?->toIso8601String(),
        ];
    }
}
