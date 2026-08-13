<?php

namespace App\Modules\Payment\Services;

use App\Integrations\Payment\PaymentGatewayManager;
use App\Integrations\Payment\RazorpayGateway;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\OrderNotificationDispatcher;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Services\CheckoutService;
use App\Modules\Order\Services\OrderStateMachine;
use App\Modules\Payment\Models\Payment;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderStateMachine $stateMachine,
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
        private readonly CheckoutService $checkout,
        private readonly OrderNotificationDispatcher $orderNotifications,
    ) {}

    public function initiate(User $user, array $payload): array
    {
        $order = Order::query()->where('user_id', $user->id)->whereKey($payload['order_id'])->first();
        if (! $order) {
            throw new NotFoundHttpException('Order not found');
        }

        if ($order->status !== 'PENDING_PAYMENT') {
            throw new ApiException('Order is not awaiting payment', 409, 'CONFLICT');
        }

        $method = $payload['method'] ?? $order->payment_method ?? 'razorpay';
        if ($method === 'cod') {
            throw new ApiException('COD orders do not require online payment initiation', 400, 'BAD_REQUEST');
        }

        // Normalize online methods onto Razorpay PSP (upi is a channel, not a separate driver).
        $onlineMethod = in_array($method, ['upi', 'razorpay'], true) ? $method : 'razorpay';
        $upiMode = (string) ($payload['mode'] ?? ($onlineMethod === 'upi' ? 'dynamic_qr' : 'checkout'));
        if ($onlineMethod === 'upi' && ! in_array($upiMode, ['dynamic_qr', 'upi_intent', 'checkout'], true)) {
            $upiMode = 'dynamic_qr';
        }
        if ($onlineMethod === 'razorpay') {
            $upiMode = 'checkout';
        }

        $gateway = $this->gateways->driver('razorpay');

        return DB::transaction(function () use ($user, $order, $onlineMethod, $upiMode, $gateway, $payload) {
            $existingSuccess = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', 'success')
                ->first();
            if ($existingSuccess) {
                throw new ApiException('Order already paid', 409, 'CONFLICT');
            }

            // Amount authority = order.grand_total only (also on pending reuse — QA-31).
            if (isset($payload['amount']) && abs((float) $payload['amount'] - (float) $order->grand_total) > 0.009) {
                throw new ApiException('Payment amount mismatch', 409, 'CONFLICT');
            }

            // Reuse a pending gateway order when possible (idempotent re-open).
            $pending = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->whereNotNull('provider_order_id')
                ->latest('id')
                ->first();

            if ($pending) {
                $hasKeys = (string) env('RAZORPAY_KEY', '') !== '' && (string) env('RAZORPAY_SECRET', '') !== '';
                if (! $hasKeys && app()->environment('production')) {
                    throw new ApiException(
                        'Payment gateway is not configured',
                        503,
                        'PAYMENT_GATEWAY_UNAVAILABLE',
                    );
                }

                $clientPayload = [
                    'key' => (string) env('RAZORPAY_KEY', 'rzp_test_local'),
                    'order_id' => $pending->provider_order_id,
                    'amount' => (int) round(((float) $pending->amount) * 100),
                    'currency' => strtoupper((string) $pending->currency),
                    'name' => env('APP_NAME', 'GreenLeaf Nursery'),
                    'mode' => $hasKeys ? 'live_or_test' : 'local_stub',
                ];
                if ($gateway instanceof RazorpayGateway) {
                    $clientPayload = $gateway->presentPendingUpiPayload($clientPayload, $pending->meta);
                }

                return $this->presentInitiate($pending, $user, $clientPayload);
            }

            Log::info('payment.initiate', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => (float) $order->grand_total,
                'method' => $onlineMethod,
                'upi_mode' => $upiMode,
            ]);

            $created = $gateway->createOrder(
                (float) $order->grand_total,
                $order->currency,
                $order->order_number,
                ['order_id' => (string) $order->id],
            );

            $meta = null;
            if ($onlineMethod === 'upi' && $gateway instanceof RazorpayGateway) {
                $created = $gateway->withUpiChannel(
                    $created,
                    (float) $order->grand_total,
                    (string) $order->currency,
                    $upiMode,
                    (string) $order->order_number,
                    ['order_id' => (string) $order->id],
                );
                $meta = $created['meta'] ?? null;
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'provider' => $gateway->provider(),
                'method' => $onlineMethod,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'status' => 'pending',
                'idempotency_key' => 'pay_'.Str::uuid()->toString(),
                'provider_order_id' => $created['provider_order_id'],
                'raw_response_json' => $created['raw'] ?? null,
                'meta' => $meta,
            ]);

            return $this->presentInitiate($payment, $user, $created['client_payload']);
        });
    }

    public function verify(User $user, array $payload): array
    {
        $payment = Payment::query()
            ->where('user_id', $user->id)
            ->whereKey($payload['payment_id'])
            ->first();

        if (! $payment) {
            throw new NotFoundHttpException('Payment not found');
        }

        if ($payment->status === 'success') {
            // Recover stuck PAYMENT_FAILED orders when verify is retried after capture (QA-30).
            return $this->finalizeSuccess(
                $payment,
                (string) ($payment->provider_payment_id ?: $payload['provider_payment_id']),
                $payload['provider_signature'] ?? $payment->provider_signature,
                $user->id,
            );
        }

        if ($payment->provider_order_id
            && ! hash_equals((string) $payment->provider_order_id, (string) $payload['provider_order_id'])) {
            throw new ApiException('Payment order mismatch', 400, 'PAYMENT_FAILED');
        }

        // Idempotency: same gateway payment already recorded as success elsewhere.
        $dup = Payment::query()
            ->where('provider_payment_id', $payload['provider_payment_id'])
            ->where('status', 'success')
            ->where('id', '!=', $payment->id)
            ->first();
        if ($dup) {
            throw new ApiException('Payment already processed', 409, 'CONFLICT');
        }

        Log::info('payment.verify.attempt', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider_order_id' => $payload['provider_order_id'],
        ]);

        $gateway = $this->gateways->driver($payment->provider);
        $ok = $gateway->verifySignature(
            $payload['provider_order_id'],
            $payload['provider_payment_id'],
            $payload['provider_signature'],
        );

        if (! $ok) {
            Log::warning('payment.verify.failed', ['payment_id' => $payment->id]);
            $this->failPayment($payment, 'signature_mismatch', 'Payment signature verification failed');

            throw new ApiException('Payment verification failed', 400, 'PAYMENT_FAILED');
        }

        return $this->finalizeSuccess($payment, $payload['provider_payment_id'], $payload['provider_signature'], $user->id);
    }

    public function status(User $user, int $paymentId): array
    {
        $payment = Payment::query()
            ->with('order')
            ->where('user_id', $user->id)
            ->whereKey($paymentId)
            ->first();
        if (! $payment) {
            throw new NotFoundHttpException('Payment not found');
        }

        return $this->presentVerify($payment, $payment->order);
    }

    /**
     * Re-open a failed/pending online order for another payment attempt.
     */
    public function retry(User $user, int $orderId): array
    {
        return DB::transaction(function () use ($user, $orderId) {
            $order = Order::query()->with('items')->where('user_id', $user->id)->whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            if (! in_array($order->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) {
                throw new ApiException('Order cannot retry payment', 409, 'CONFLICT');
            }

            if (($order->payment_method ?? '') === 'cod') {
                throw new ApiException('COD orders cannot retry online payment', 400, 'BAD_REQUEST');
            }

            if ($order->status === 'PAYMENT_FAILED') {
                $lines = $order->items->map(fn (OrderItem $i) => [
                    'product_id' => $i->product_id,
                    'quantity' => $i->quantity,
                    'variant_id' => $i->product_variant_id,
                ])->all();
                foreach ($lines as $line) {
                    $this->inventory->assertAvailable($line['product_id'], $line['quantity'], $line['variant_id']);
                }
                $this->inventory->reserve($lines, 'order', $order->id, $user->id);
                $order = $this->stateMachine->transition($order, 'PENDING_PAYMENT', $user->id, 'Payment retry');
            }

            // Mark previous pending payments failed so initiate creates a fresh gateway order.
            Payment::query()
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'failure_code' => 'superseded',
                    'failure_message' => 'Superseded by payment retry',
                ]);

            return $this->initiate($user, ['order_id' => $order->id, 'method' => 'razorpay']);
        });
    }

    public function handleWebhook(string $provider, array $payload, ?string $signature = null): array
    {
        if ($provider !== 'razorpay') {
            throw new ApiException('Unsupported webhook provider', 400, 'BAD_REQUEST');
        }

        $secret = (string) env('RAZORPAY_WEBHOOK_SECRET', '');
        $allowUnsigned = filter_var(env('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS', false), FILTER_VALIDATE_BOOLEAN);

        if ($secret === '') {
            if (app()->environment('production') || ! $allowUnsigned) {
                throw new ApiException(
                    'Webhook secret not configured',
                    503,
                    'PAYMENT_GATEWAY_UNAVAILABLE',
                );
            }
            Log::warning('payment.webhook.unsigned_accepted', [
                'env' => app()->environment(),
                'allow_flag' => true,
            ]);
        } else {
            $expected = hash_hmac('sha256', request()->getContent(), $secret);
            if (! $signature || ! hash_equals($expected, $signature)) {
                throw new ApiException('Invalid webhook signature', 401, 'UNAUTHENTICATED');
            }
        }

        $event = $payload['event'] ?? null;
        $entity = data_get($payload, 'payload.payment.entity') ?? data_get($payload, 'payload.order.entity');
        $providerPaymentId = is_array($entity) ? ($entity['id'] ?? null) : null;
        $providerOrderId = is_array($entity)
            ? ($entity['order_id'] ?? null)
            : null;

        // For order.paid events the entity may be the order itself (id = provider order id).
        if (! $providerOrderId && is_array($entity) && ($event === 'order.paid' || str_starts_with((string) $event, 'order.'))) {
            $providerOrderId = $entity['id'] ?? null;
        }

        Log::info('payment.webhook', [
            'event' => $event,
            'provider_order_id' => $providerOrderId,
            'provider_payment_id' => $providerPaymentId,
        ]);

        // Never bind an unbound "latest payment" — that is an IDOR/cross-order risk.
        if (! is_string($providerOrderId) || $providerOrderId === '') {
            Log::warning('payment.webhook.missing_provider_order_id', ['event' => $event]);

            return ['handled' => false, 'reason' => 'missing_provider_order_id'];
        }

        $payment = Payment::query()
            ->where('provider_order_id', $providerOrderId)
            ->latest('id')
            ->first();

        if (! $payment) {
            return ['handled' => false];
        }

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            // Idempotent: also recovers order when payment already success but order was stuck (QA-30).
            $this->finalizeSuccess($payment, $providerPaymentId ?? ('wh_'.$payment->id), $signature, null);

            return ['handled' => true, 'idempotent' => $payment->status === 'success'];
        }

        if (in_array($event, ['payment.failed'], true)) {
            $this->failPayment($payment, 'webhook_failed', 'Payment failed via webhook');

            return ['handled' => true];
        }

        return ['handled' => false];
    }

    private function finalizeSuccess(Payment $payment, string $providerPaymentId, ?string $signature, ?int $actorUserId): array
    {
        return DB::transaction(function () use ($payment, $providerPaymentId, $signature, $actorUserId) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            $order = Order::query()->with('items')->whereKey($payment->order_id)->lockForUpdate()->first();

            if ($payment->status !== 'success') {
                // Amount authority: payment row amount must match order grand total.
                if (abs((float) $payment->amount - (float) $order->grand_total) > 0.009) {
                    throw new ApiException('Payment amount mismatch', 409, 'CONFLICT');
                }

                $payment->status = 'success';
                $payment->provider_payment_id = $providerPaymentId;
                $payment->provider_signature = $signature;
                $payment->paid_at = now();
            } elseif ($providerPaymentId !== '' && ! $payment->provider_payment_id) {
                $payment->provider_payment_id = $providerPaymentId;
            }

            if ($payment->status === 'success') {
                $payment->failure_code = null;
                $payment->failure_message = null;
                $payment->save();
            }

            // Confirm order even when payment was already success but order stayed PAYMENT_FAILED (QA-30).
            $order = $this->confirmOrderAfterSuccessfulPayment($order, $actorUserId);

            Log::info('payment.verify.succeeded', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return $this->presentVerify($payment->fresh(), $order->fresh());
        });
    }

    private function confirmOrderAfterSuccessfulPayment(Order $order, ?int $actorUserId): Order
    {
        if (! in_array($order->status, ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) {
            return $order;
        }

        $lines = $order->items->map(fn (OrderItem $i) => [
            'product_id' => $i->product_id,
            'quantity' => $i->quantity,
            'variant_id' => $i->product_variant_id,
        ])->all();

        if ($order->status === 'PAYMENT_FAILED') {
            foreach ($lines as $line) {
                $this->inventory->assertAvailable($line['product_id'], $line['quantity'], $line['variant_id']);
            }
            $this->inventory->reserve($lines, 'order', $order->id, $actorUserId);
        }

        $this->inventory->commit($lines, 'order', $order->id, $actorUserId);
        $order = $this->stateMachine->transition($order, 'CONFIRMED', $actorUserId, 'Payment success');
        $this->checkout->recordCouponRedemption($order);

        $owner = User::query()->find($order->user_id);
        if ($owner) {
            $this->checkout->clearUserCart($owner);
        }

        $this->orderNotifications->notifyPaymentConfirmed($order->fresh());
        $this->orderNotifications->notifyCustomerStatus($order->fresh(), 'CONFIRMED');

        if ($order->subscription_id) {
            app(\App\Modules\Subscription\Services\SubscriptionService::class)->onOrderPaid($order->fresh());
        }

        return $order->fresh();
    }

    private function failPayment(Payment $payment, string $code, string $message): void
    {
        DB::transaction(function () use ($payment, $code, $message) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            $order = Order::query()->with('items')->whereKey($payment->order_id)->lockForUpdate()->first();

            if ($payment->status === 'success') {
                return;
            }

            // Another payment row already succeeded for this order — ignore stale failures.
            $siblingSuccess = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', 'success')
                ->where('id', '!=', $payment->id)
                ->exists();
            if ($siblingSuccess || $order->status === 'CONFIRMED') {
                return;
            }

            $payment->status = 'failed';
            $payment->failure_code = $code;
            $payment->failure_message = $message;
            $payment->save();

            if ($order->status === 'PENDING_PAYMENT') {
                $lines = $order->items->map(fn (OrderItem $i) => [
                    'product_id' => $i->product_id,
                    'quantity' => $i->quantity,
                    'variant_id' => $i->product_variant_id,
                ])->all();
                $this->inventory->release($lines, 'order', $order->id, null);
                $this->stateMachine->transition($order, 'PAYMENT_FAILED', null, $message);
                if ($order->subscription_id) {
                    app(\App\Modules\Subscription\Services\SubscriptionService::class)
                        ->onOrderPaymentFailed($order->fresh());
                }
            }

            // Cart intentionally NOT cleared — customer can retry checkout or retry payment.
            Log::info('payment.failed', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'code' => $code,
            ]);
        });

        try {
            $order = Order::query()->find($payment->order_id);
            if ($order && $order->user_id) {
                $this->notifications->notify(
                    $order->user_id,
                    'payment_failed',
                    'Payment failed',
                    "Payment for order {$order->order_number} failed. You can retry from your orders.",
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => 'PAYMENT_FAILED',
                    ],
                );
            }
        } catch (\Throwable) {
            // Never break payment failure handling.
        }
    }

    private function presentInitiate(Payment $payment, User $user, array $clientPayload): array
    {
        $clientPayload['prefill'] = [
            'email' => $user->email,
            'contact' => $user->phone,
            'name' => $user->name,
        ];

        return [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider' => $payment->provider,
            'provider_order_id' => $payment->provider_order_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'method' => $payment->method,
            'channel' => $clientPayload['channel'] ?? ($payment->method === 'upi' ? 'upi' : null),
            'upi_mode' => $clientPayload['upi_mode'] ?? data_get($payment->meta, 'upi_mode'),
            'expires_at' => $clientPayload['expires_at'] ?? data_get($payment->meta, 'expires_at'),
            'client_payload' => $clientPayload,
        ];
    }

    private function presentVerify(Payment $payment, ?Order $order): array
    {
        return [
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'payment_status' => $payment->status,
            'order_status' => $order?->status,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $payment->status,
                'grand_total' => (float) $order->grand_total,
                'currency' => $order->currency,
            ] : null,
        ];
    }
}
