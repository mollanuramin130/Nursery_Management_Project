<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Services\AddressService;
use App\Modules\Delivery\Models\ShippingMethod;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\CheckoutService;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionCycle;
use App\Modules\Subscription\Models\SubscriptionEvent;
use App\Modules\Subscription\Models\SubscriptionPlan;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscriptionService
{
    public function __construct(
        private readonly AddressService $addresses,
        private readonly InventoryService $inventory,
        private readonly CheckoutService $checkout,
        private readonly NotificationService $notifications,
    ) {}

    public function listPublicPlansForProduct(int $productId): array
    {
        return SubscriptionPlan::query()
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->orderBy('frequency')
            ->get()
            ->map(fn (SubscriptionPlan $p) => $this->serializePlan($p))
            ->values()
            ->all();
    }

    public function listCustomer(User $user, ?string $status, int $perPage): array
    {
        $q = Subscription::query()
            ->where('user_id', $user->id)
            ->with(['product', 'plan'])
            ->orderByDesc('id');
        if ($status) {
            $q->where('status', strtoupper($status));
        }
        $paginator = $q->paginate(min(max($perPage, 1), 50));

        return [
            'data' => collect($paginator->items())->map(fn (Subscription $s) => $this->serialize($s))->values()->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function showCustomer(User $user, int $id): array
    {
        $sub = $this->owned($user, $id);

        return $this->serialize($sub, true);
    }

    /**
     * Create subscription + first cycle order (pay-per-cycle; no auto-debit).
     */
    public function create(User $user, array $payload, ?string $ip = null, ?string $platform = null, ?string $requestId = null): array
    {
        $pending = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'PENDING_PAYMENT')
            ->exists();
        if ($pending) {
            throw new ApiException(
                'Complete or cancel your unpaid order before starting a subscription.',
                409,
                'PENDING_ORDER_EXISTS',
            );
        }

        $plan = SubscriptionPlan::query()->with('product')->whereKey((int) $payload['plan_id'])->first();
        if (! $plan || ! $plan->isActive()) {
            throw new ApiException('Subscription plan is not available', 400, 'BAD_REQUEST');
        }
        $product = $plan->product;
        if (! $product || $product->status !== 'active') {
            throw new ApiException('Product is not available for subscription', 400, 'BAD_REQUEST');
        }

        $qty = max(1, (int) ($payload['quantity'] ?? $plan->quantity_default));
        $frequency = strtoupper((string) ($payload['frequency'] ?? $plan->frequency));
        if (! in_array($frequency, Subscription::FREQUENCIES, true)) {
            throw new ApiException('Invalid frequency', 422, 'VALIDATION_ERROR');
        }
        if ($frequency !== $plan->frequency) {
            // Plan defines frequency; do not allow arbitrary override in MVP.
            throw new ApiException('Frequency must match the selected plan', 400, 'BAD_REQUEST');
        }

        $paymentMethod = $payload['payment_method'] ?? 'razorpay';
        if (! in_array($paymentMethod, ['razorpay', 'cod'], true)) {
            throw new ApiException('Invalid payment method', 422, 'VALIDATION_ERROR');
        }

        $address = $this->addresses->owned($user, (int) $payload['address_id']);
        $shippingMethodId = isset($payload['shipping_method_id']) ? (int) $payload['shipping_method_id'] : null;
        if ($shippingMethodId) {
            $sm = ShippingMethod::query()->active()->find($shippingMethodId);
            if (! $sm) {
                throw new NotFoundHttpException('Shipping method not found');
            }
        }

        try {
            $this->inventory->assertAvailable($product->id, $qty, null);
        } catch (ApiException $e) {
            throw new ApiException('Product is currently unavailable for subscription', 409, 'OUT_OF_STOCK');
        }

        return DB::transaction(function () use ($user, $plan, $product, $qty, $frequency, $paymentMethod, $address, $shippingMethodId, $ip, $platform, $requestId) {
            $sub = Subscription::query()->create([
                'subscription_number' => $this->nextSubscriptionNumber(),
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'frequency' => $frequency,
                'unit_price' => $plan->unit_price, // locked
                'currency' => $plan->currency ?: 'INR',
                'status' => 'PENDING',
                'address_id' => $address->id,
                'shipping_address_json' => $address->toApiArray(),
                'billing_address_json' => $address->toApiArray(),
                'shipping_method_id' => $shippingMethodId,
                'payment_method' => $paymentMethod,
                'cycle_count' => 0,
                'next_billing_at' => null,
                'failed_payment_count' => 0,
                'max_failed_payments' => 3,
            ]);

            $this->recordEvent($sub, 'CREATED', $user->id, [
                'plan_id' => $plan->id,
                'unit_price' => (float) $plan->unit_price,
                'quantity' => $qty,
            ]);

            AuditLogger::log('subscription.create', 'subscription', $sub->id, null, [
                'status' => $sub->status,
                'plan_id' => $plan->id,
            ], $user->id);

            $cycleResult = $this->generateCycleLocked($sub->fresh(), 1, now(), $user->id, $ip, $platform, $requestId);

            $this->notifications->notify(
                $user->id,
                'subscription_created',
                'Subscription created',
                "Subscription {$sub->subscription_number} was created. Complete payment for the first delivery.",
                [
                    'subscription_id' => $sub->id,
                    'subscription_number' => $sub->subscription_number,
                    'order_id' => $cycleResult['order']['id'] ?? null,
                ],
            );

            return [
                'subscription' => $this->serialize($sub->fresh(['product', 'plan', 'cycles']), true),
                'order' => $cycleResult['order'],
                'payment_required' => ($cycleResult['order']['status'] ?? null) === 'PENDING_PAYMENT',
                'billing_model' => 'pay_per_cycle',
                'billing_note' => 'Automatic card charging is not enabled. Each cycle creates an order you must pay.',
            ];
        });
    }

    public function pause(User $user, int $id, ?string $reason = null): array
    {
        return DB::transaction(function () use ($user, $id, $reason) {
            $sub = $this->ownedLocked($user, $id);
            if ($sub->status !== 'ACTIVE') {
                throw new ApiException('Only active subscriptions can be paused', 409, 'CONFLICT');
            }
            $before = $sub->status;
            $sub->status = 'PAUSED';
            $sub->paused_at = now();
            $sub->next_billing_at = null;
            $sub->save();
            $this->recordEvent($sub, 'PAUSED', $user->id, ['reason' => $reason]);
            AuditLogger::log('subscription.pause', 'subscription', $sub->id, ['status' => $before], ['status' => 'PAUSED'], $user->id, ['reason' => $reason]);
            $this->notifications->notify(
                $user->id,
                'subscription_paused',
                'Subscription paused',
                "Subscription {$sub->subscription_number} is paused. No new cycles will be generated.",
                ['subscription_id' => $sub->id],
            );

            return $this->serialize($sub->fresh(['product', 'plan']), true);
        });
    }

    public function resume(User $user, int $id): array
    {
        return DB::transaction(function () use ($user, $id) {
            $sub = $this->ownedLocked($user, $id);
            if ($sub->status !== 'PAUSED') {
                throw new ApiException('Only paused subscriptions can be resumed', 409, 'CONFLICT');
            }
            $before = $sub->status;
            $sub->status = 'ACTIVE';
            $sub->paused_at = null;
            $sub->next_billing_at = $this->addFrequency(now(), $sub->frequency);
            $sub->save();
            $this->recordEvent($sub, 'RESUMED', $user->id, ['next_billing_at' => $sub->next_billing_at?->toIso8601String()]);
            AuditLogger::log('subscription.resume', 'subscription', $sub->id, ['status' => $before], ['status' => 'ACTIVE'], $user->id);
            $this->notifications->notify(
                $user->id,
                'subscription_resumed',
                'Subscription resumed',
                "Subscription {$sub->subscription_number} resumed. Next cycle around ".$sub->next_billing_at?->toDateString().'.',
                ['subscription_id' => $sub->id],
            );

            return $this->serialize($sub->fresh(['product', 'plan']), true);
        });
    }

    public function cancel(User $user, int $id, ?string $reason = null): array
    {
        return DB::transaction(function () use ($user, $id, $reason) {
            $sub = $this->ownedLocked($user, $id);
            if (in_array($sub->status, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true)) {
                throw new ApiException('Subscription is already closed', 409, 'CONFLICT');
            }
            $before = $sub->status;
            $sub->status = 'CANCELLED';
            $sub->cancelled_at = now();
            $sub->cancel_reason = $reason;
            $sub->next_billing_at = null;
            $sub->save();
            $this->recordEvent($sub, 'CANCELLED', $user->id, ['reason' => $reason]);
            AuditLogger::log('subscription.cancel', 'subscription', $sub->id, ['status' => $before], ['status' => 'CANCELLED'], $user->id, ['reason' => $reason]);
            $this->notifications->notify(
                $user->id,
                'subscription_cancelled',
                'Subscription cancelled',
                "Subscription {$sub->subscription_number} was cancelled. Existing orders are not auto-cancelled.",
                ['subscription_id' => $sub->id],
            );

            return $this->serialize($sub->fresh(['product', 'plan']), true);
        });
    }

    public function changeQuantity(User $user, int $id, int $quantity): array
    {
        if ($quantity < 1) {
            throw new ApiException('Quantity must be at least 1', 422, 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($user, $id, $quantity) {
            $sub = $this->ownedLocked($user, $id);
            if (! in_array($sub->status, ['ACTIVE', 'PAUSED', 'PENDING', 'PAYMENT_FAILED'], true)) {
                throw new ApiException('Cannot change quantity for this subscription', 409, 'CONFLICT');
            }
            $before = $sub->quantity;
            $sub->quantity = $quantity;
            $sub->save();
            $this->recordEvent($sub, 'QUANTITY_CHANGED', $user->id, ['from' => $before, 'to' => $quantity]);
            AuditLogger::log('subscription.quantity', 'subscription', $sub->id, ['quantity' => $before], ['quantity' => $quantity], $user->id);

            return $this->serialize($sub->fresh(['product', 'plan']), true);
        });
    }

    public function changeAddress(User $user, int $id, int $addressId): array
    {
        return DB::transaction(function () use ($user, $id, $addressId) {
            $sub = $this->ownedLocked($user, $id);
            if (in_array($sub->status, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true)) {
                throw new ApiException('Cannot change address for a closed subscription', 409, 'CONFLICT');
            }
            $address = $this->addresses->owned($user, $addressId);
            $before = $sub->address_id;
            $sub->address_id = $address->id;
            $sub->shipping_address_json = $address->toApiArray();
            $sub->billing_address_json = $address->toApiArray();
            $sub->save();
            $this->recordEvent($sub, 'ADDRESS_CHANGED', $user->id, ['from' => $before, 'to' => $address->id]);

            return $this->serialize($sub->fresh(['product', 'plan']), true);
        });
    }

    /** Admin wrappers (same mutations with actor). */
    public function adminPause(User $actor, int $id, ?string $reason = null): array
    {
        $sub = Subscription::query()->find($id);
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }
        $owner = User::query()->findOrFail($sub->user_id);

        return $this->pause($owner, $id, $reason ?: 'Paused by admin');
    }

    public function adminResume(User $actor, int $id): array
    {
        $sub = Subscription::query()->find($id);
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }

        return $this->resume(User::query()->findOrFail($sub->user_id), $id);
    }

    public function adminCancel(User $actor, int $id, ?string $reason = null): array
    {
        $sub = Subscription::query()->find($id);
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }

        return $this->cancel(User::query()->findOrFail($sub->user_id), $id, $reason ?: 'Cancelled by admin');
    }

    public function adminList(array $filters, int $perPage): array
    {
        $q = Subscription::query()->with(['product', 'plan', 'user'])->orderByDesc('id');
        if (! empty($filters['status'])) {
            $q->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['product_id'])) {
            $q->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['frequency'])) {
            $q->where('frequency', strtoupper((string) $filters['frequency']));
        }
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($w) use ($term) {
                $w->where('subscription_number', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'));
            });
        }
        $paginator = $q->paginate(min(max($perPage, 1), 50));

        return [
            'data' => collect($paginator->items())->map(fn (Subscription $s) => $this->serialize($s, false, true))->values()->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function adminShow(int $id): array
    {
        $sub = Subscription::query()->with(['product', 'plan', 'user', 'cycles.order', 'events'])->whereKey($id)->first();
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }

        return $this->serialize($sub, true, true);
    }

    public function dashboard(): array
    {
        $base = Subscription::query();

        return [
            'active' => (clone $base)->where('status', 'ACTIVE')->count(),
            'paused' => (clone $base)->where('status', 'PAUSED')->count(),
            'payment_failed' => (clone $base)->where('status', 'PAYMENT_FAILED')->count(),
            'cancelled' => (clone $base)->where('status', 'CANCELLED')->count(),
            'pending' => (clone $base)->where('status', 'PENDING')->count(),
            'due_soon' => (clone $base)->where('status', 'ACTIVE')
                ->where('next_billing_at', '<=', now()->addDays(7))->count(),
        ];
    }

    /**
     * Process due ACTIVE subscriptions (scheduler). Idempotent per cycle.
     */
    public function processDue(int $limit = 50): array
    {
        $ids = Subscription::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->orderBy('next_billing_at')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($ids as $id) {
            try {
                $result = $this->processOne((int) $id);
                $processed++;
                $results[] = $result;
            } catch (ApiException $e) {
                if ($e->errorCode() === 'SKIPPED') {
                    $skipped++;
                } else {
                    $failed++;
                }
                $results[] = ['subscription_id' => $id, 'error' => $e->getMessage(), 'code' => $e->errorCode()];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = ['subscription_id' => $id, 'error' => $e->getMessage()];
            }
        }

        return compact('processed', 'skipped', 'failed', 'results');
    }

    public function processOne(int $subscriptionId): array
    {
        return DB::transaction(function () use ($subscriptionId) {
            $sub = Subscription::query()->whereKey($subscriptionId)->lockForUpdate()->first();
            if (! $sub || $sub->status !== 'ACTIVE') {
                throw new ApiException('Subscription not due', 409, 'SKIPPED');
            }
            if (! $sub->next_billing_at || $sub->next_billing_at->gt(now())) {
                throw new ApiException('Not due yet', 409, 'SKIPPED');
            }

            // Open unpaid cycle already?
            $open = SubscriptionCycle::query()
                ->where('subscription_id', $sub->id)
                ->whereIn('status', ['ORDER_CREATED', 'AWAITING_PAYMENT'])
                ->exists();
            if ($open) {
                throw new ApiException('Open cycle awaiting payment', 409, 'SKIPPED');
            }

            if (Order::query()->where('user_id', $sub->user_id)->where('status', 'PENDING_PAYMENT')->exists()) {
                // Do not stack unpaid orders; push next_billing slightly and notify.
                $sub->next_billing_at = now()->addDay();
                $sub->save();
                $this->notifications->notify(
                    $sub->user_id,
                    'subscription_payment_required',
                    'Complete unpaid order',
                    'Your next subscription cycle is waiting until your unpaid order is completed.',
                    ['subscription_id' => $sub->id],
                );
                throw new ApiException('Customer has unpaid order', 409, 'SKIPPED');
            }

            $nextNumber = (int) SubscriptionCycle::query()->where('subscription_id', $sub->id)->max('cycle_number') + 1;
            $existing = SubscriptionCycle::query()
                ->where('subscription_id', $sub->id)
                ->where('cycle_number', $nextNumber)
                ->first();
            if ($existing) {
                throw new ApiException('Cycle already exists', 409, 'SKIPPED');
            }

            $product = Product::query()->whereKey($sub->product_id)->first();
            if (! $product || $product->status !== 'active') {
                return $this->skipUnavailable($sub, $nextNumber, 'Product unavailable');
            }

            try {
                $this->inventory->assertAvailable($sub->product_id, $sub->quantity, null);
            } catch (ApiException $e) {
                return $this->skipUnavailable($sub, $nextNumber, 'Out of stock');
            }

            $owner = User::query()->findOrFail($sub->user_id);
            $result = $this->generateCycleLocked($sub, $nextNumber, $sub->next_billing_at ?? now(), null, null, null, 'sub-cycle-'.$sub->id.'-'.$nextNumber);

            // Advance schedule even while awaiting payment (prevents tight loop).
            // Actual activation of "paid cycle" updates next_billing again on payment success if still early.
            $sub->next_billing_at = $this->addFrequency($sub->next_billing_at ?? now(), $sub->frequency);
            $sub->save();

            $this->notifications->notify(
                $owner->id,
                'subscription_cycle_created',
                'Subscription order ready',
                "Cycle #{$nextNumber} for {$sub->subscription_number} is ready. Please complete payment.",
                [
                    'subscription_id' => $sub->id,
                    'order_id' => $result['order']['id'] ?? null,
                    'cycle_number' => $nextNumber,
                ],
            );

            return $result;
        });
    }

    /**
     * Called from PaymentService after successful payment of a subscription order.
     */
    public function onOrderPaid(Order $order): void
    {
        if (! $order->subscription_id || ! $order->subscription_cycle_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $cycle = SubscriptionCycle::query()->whereKey($order->subscription_cycle_id)->lockForUpdate()->first();
            $sub = Subscription::query()->whereKey($order->subscription_id)->lockForUpdate()->first();
            if (! $cycle || ! $sub) {
                return;
            }
            if ($cycle->status === 'PAID') {
                return;
            }

            $cycle->status = 'PAID';
            $cycle->processed_at = now();
            $cycle->save();

            $sub->cycle_count = (int) $sub->cycle_count + 1;
            $sub->failed_payment_count = 0;
            if (in_array($sub->status, ['PENDING', 'PAYMENT_FAILED'], true)) {
                $sub->status = 'ACTIVE';
            }
            if ($sub->status === 'ACTIVE' && ! $sub->next_billing_at) {
                $sub->next_billing_at = $this->addFrequency(now(), $sub->frequency);
            }

            $sub->loadMissing('plan');
            if ($sub->plan?->max_cycles && $sub->cycle_count >= (int) $sub->plan->max_cycles) {
                $sub->status = 'COMPLETED';
                $sub->next_billing_at = null;
                $this->recordEvent($sub, 'COMPLETED', null, ['cycle_count' => $sub->cycle_count]);
            }

            $sub->save();
            $this->recordEvent($sub, 'PAYMENT_SUCCEEDED', null, [
                'cycle_id' => $cycle->id,
                'order_id' => $order->id,
            ]);
            $this->notifications->notify(
                $sub->user_id,
                'subscription_payment_succeeded',
                'Subscription payment successful',
                "Payment received for {$sub->subscription_number} cycle #{$cycle->cycle_number}.",
                ['subscription_id' => $sub->id, 'order_id' => $order->id],
            );
        });
    }

    public function onOrderPaymentFailed(Order $order): void
    {
        if (! $order->subscription_id || ! $order->subscription_cycle_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $cycle = SubscriptionCycle::query()->whereKey($order->subscription_cycle_id)->lockForUpdate()->first();
            $sub = Subscription::query()->whereKey($order->subscription_id)->lockForUpdate()->first();
            if (! $cycle || ! $sub) {
                return;
            }
            if ($cycle->status === 'PAID') {
                return;
            }

            $cycle->status = 'FAILED';
            $cycle->failure_reason = 'Payment failed';
            $cycle->processed_at = now();
            $cycle->save();

            $sub->failed_payment_count = (int) $sub->failed_payment_count + 1;
            $sub->status = 'PAYMENT_FAILED';
            if ($sub->failed_payment_count >= (int) $sub->max_failed_payments) {
                $sub->status = 'CANCELLED';
                $sub->cancelled_at = now();
                $sub->cancel_reason = 'Too many failed payments';
                $sub->next_billing_at = null;
                $this->recordEvent($sub, 'CANCELLED', null, ['reason' => 'max_failed_payments']);
            } else {
                $sub->next_billing_at = now()->addDays(2);
                $this->recordEvent($sub, 'PAYMENT_FAILED', null, [
                    'failed_payment_count' => $sub->failed_payment_count,
                    'order_id' => $order->id,
                ]);
            }
            $sub->save();

            $this->notifications->notify(
                $sub->user_id,
                'subscription_payment_failed',
                'Subscription payment failed',
                "Payment failed for {$sub->subscription_number}. Please retry from your orders.",
                ['subscription_id' => $sub->id, 'order_id' => $order->id],
            );
        });
    }

    // —— Plans admin ——

    public function listPlans(array $filters, int $perPage): array
    {
        $q = SubscriptionPlan::query()->with('product')->orderByDesc('id');
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['product_id'])) {
            $q->where('product_id', (int) $filters['product_id']);
        }
        $paginator = $q->paginate(min(max($perPage, 1), 50));

        return [
            'data' => collect($paginator->items())->map(fn (SubscriptionPlan $p) => $this->serializePlan($p, true))->values()->all(),
            'pagination' => $this->paginationMeta($paginator),
        ];
    }

    public function createPlan(array $payload, User $actor): array
    {
        $product = Product::query()->whereKey((int) $payload['product_id'])->first();
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }
        $frequency = strtoupper((string) $payload['frequency']);
        if (! in_array($frequency, Subscription::FREQUENCIES, true)) {
            throw new ApiException('Invalid frequency', 422, 'VALIDATION_ERROR');
        }
        $slug = $payload['slug'] ?? Str::slug($payload['name']).'-'.Str::lower(Str::random(4));
        $plan = SubscriptionPlan::query()->create([
            'product_id' => $product->id,
            'name' => $payload['name'],
            'slug' => $slug,
            'frequency' => $frequency,
            'quantity_default' => max(1, (int) ($payload['quantity_default'] ?? 1)),
            'unit_price' => $payload['unit_price'],
            'compare_at_price' => $payload['compare_at_price'] ?? null,
            'currency' => $payload['currency'] ?? 'INR',
            'status' => $payload['status'] ?? 'draft',
            'max_cycles' => $payload['max_cycles'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);
        AuditLogger::log('subscription_plan.create', 'subscription_plan', $plan->id, null, $plan->toArray(), $actor->id);

        return $this->serializePlan($plan->load('product'), true);
    }

    public function updatePlan(int $id, array $payload, User $actor): array
    {
        $plan = SubscriptionPlan::query()->whereKey($id)->first();
        if (! $plan) {
            throw new NotFoundHttpException('Plan not found');
        }
        $before = $plan->toArray();
        foreach (['name', 'unit_price', 'compare_at_price', 'status', 'quantity_default', 'max_cycles', 'description'] as $field) {
            if (array_key_exists($field, $payload)) {
                $plan->{$field} = $payload[$field];
            }
        }
        if (isset($payload['frequency'])) {
            $freq = strtoupper((string) $payload['frequency']);
            if (! in_array($freq, Subscription::FREQUENCIES, true)) {
                throw new ApiException('Invalid frequency', 422, 'VALIDATION_ERROR');
            }
            $plan->frequency = $freq;
        }
        $plan->save();
        AuditLogger::log('subscription_plan.update', 'subscription_plan', $plan->id, $before, $plan->toArray(), $actor->id);

        return $this->serializePlan($plan->fresh('product'), true);
    }

    // —— internals ——

    private function generateCycleLocked(
        Subscription $sub,
        int $cycleNumber,
        Carbon $scheduledAt,
        ?int $actorUserId,
        ?string $ip,
        ?string $platform,
        ?string $requestId,
    ): array {
        $existing = SubscriptionCycle::query()
            ->where('subscription_id', $sub->id)
            ->where('cycle_number', $cycleNumber)
            ->first();
        if ($existing) {
            if ($existing->order_id) {
                $order = Order::query()->with('items')->find($existing->order_id);

                return [
                    'cycle' => $this->serializeCycle($existing),
                    'order' => $order ? $this->checkout->summaryPublic($order->loadMissing('items')) : null,
                ];
            }
            throw new ApiException('Cycle exists without order', 409, 'CONFLICT');
        }

        $cycle = SubscriptionCycle::query()->create([
            'subscription_id' => $sub->id,
            'cycle_number' => $cycleNumber,
            'scheduled_at' => $scheduledAt,
            'status' => 'PENDING',
            'unit_price' => $sub->unit_price,
            'quantity' => $sub->quantity,
            'amount' => 0,
        ]);

        $orderSummary = $this->checkout->placeSubscriptionOrder($sub, $cycle, $ip, $platform, $requestId);

        $cycle->refresh();
        $this->recordEvent($sub, 'ORDER_CREATED', $actorUserId, [
            'cycle_number' => $cycleNumber,
            'order_id' => $cycle->order_id,
        ]);

        return [
            'cycle' => $this->serializeCycle($cycle),
            'order' => $orderSummary,
        ];
    }

    private function skipUnavailable(Subscription $sub, int $cycleNumber, string $reason): array
    {
        $cycle = SubscriptionCycle::query()->create([
            'subscription_id' => $sub->id,
            'cycle_number' => $cycleNumber,
            'scheduled_at' => $sub->next_billing_at ?? now(),
            'status' => 'SKIPPED',
            'unit_price' => $sub->unit_price,
            'quantity' => $sub->quantity,
            'amount' => 0,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
        $sub->next_billing_at = $this->addFrequency($sub->next_billing_at ?? now(), $sub->frequency);
        $sub->save();
        $this->recordEvent($sub, 'CYCLE_SKIPPED', null, ['reason' => $reason, 'cycle_number' => $cycleNumber]);
        $this->notifications->notify(
            $sub->user_id,
            'subscription_unavailable',
            'Subscription item unavailable',
            "We could not generate cycle #{$cycleNumber} for {$sub->subscription_number}: {$reason}. No substitute was made.",
            ['subscription_id' => $sub->id],
        );

        return ['cycle' => $this->serializeCycle($cycle), 'order' => null, 'skipped' => true];
    }

    public function addFrequency(Carbon $from, string $frequency): Carbon
    {
        return match (strtoupper($frequency)) {
            'WEEKLY' => $from->copy()->addWeek(),
            'BIWEEKLY' => $from->copy()->addWeeks(2),
            'MONTHLY' => $from->copy()->addMonth(),
            'QUARTERLY' => $from->copy()->addMonths(3),
            'YEARLY' => $from->copy()->addYear(),
            default => $from->copy()->addMonth(),
        };
    }

    private function owned(User $user, int $id): Subscription
    {
        $sub = Subscription::query()->with(['product', 'plan', 'cycles.order'])->where('user_id', $user->id)->whereKey($id)->first();
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }

        return $sub;
    }

    private function ownedLocked(User $user, int $id): Subscription
    {
        $sub = Subscription::query()->where('user_id', $user->id)->whereKey($id)->lockForUpdate()->first();
        if (! $sub) {
            throw new NotFoundHttpException('Subscription not found');
        }

        return $sub;
    }

    private function recordEvent(Subscription $sub, string $type, ?int $actorUserId, array $payload = []): void
    {
        SubscriptionEvent::query()->create([
            'subscription_id' => $sub->id,
            'event_type' => $type,
            'actor_user_id' => $actorUserId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    private function nextSubscriptionNumber(): string
    {
        $prefix = 'SUB-'.now()->format('Ymd').'-';
        $latest = Subscription::query()->where('subscription_number', 'like', $prefix.'%')->orderByDesc('subscription_number')->value('subscription_number');
        $seq = 1;
        if ($latest && preg_match('/(\d+)$/', $latest, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function serializePlan(SubscriptionPlan $p, bool $admin = false): array
    {
        $p->loadMissing('product');
        $data = [
            'id' => $p->id,
            'product_id' => $p->product_id,
            'product_name' => $p->product?->name,
            'product_slug' => $p->product?->slug,
            'name' => $p->name,
            'slug' => $p->slug,
            'frequency' => $p->frequency,
            'quantity_default' => (int) $p->quantity_default,
            'unit_price' => (float) $p->unit_price,
            'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null,
            'one_time_price' => $p->product ? (float) $p->product->price : null,
            'currency' => $p->currency,
            'status' => $p->status,
            'max_cycles' => $p->max_cycles,
            'description' => $p->description,
            'billing_model' => 'pay_per_cycle',
        ];
        if ($admin) {
            $data['created_at'] = optional($p->created_at)?->toIso8601String();
        }

        return $data;
    }

    public function serialize(Subscription $s, bool $detail = false, bool $admin = false): array
    {
        $s->loadMissing(['product', 'plan']);
        $data = [
            'id' => $s->id,
            'subscription_number' => $s->subscription_number,
            'status' => $s->status,
            'product_id' => $s->product_id,
            'product_name' => $s->product?->name,
            'product_slug' => $s->product?->slug,
            'plan_id' => $s->subscription_plan_id,
            'plan_name' => $s->plan?->name,
            'quantity' => (int) $s->quantity,
            'frequency' => $s->frequency,
            'unit_price' => (float) $s->unit_price,
            'currency' => $s->currency,
            'payment_method' => $s->payment_method,
            'cycle_count' => (int) $s->cycle_count,
            'next_billing_at' => optional($s->next_billing_at)?->toIso8601String(),
            'failed_payment_count' => (int) $s->failed_payment_count,
            'billing_model' => 'pay_per_cycle',
            'actions' => [
                'can_pause' => $s->status === 'ACTIVE',
                'can_resume' => $s->status === 'PAUSED',
                'can_cancel' => ! in_array($s->status, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true),
                'can_change_quantity' => in_array($s->status, ['ACTIVE', 'PAUSED', 'PENDING', 'PAYMENT_FAILED'], true),
                'can_change_address' => ! in_array($s->status, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true),
            ],
            'created_at' => optional($s->created_at)?->toIso8601String(),
        ];
        if ($admin) {
            $data['user'] = $s->relationLoaded('user') && $s->user ? [
                'id' => $s->user->id,
                'name' => $s->user->name,
                'email' => $s->user->email,
            ] : ['id' => $s->user_id];
        }
        if ($detail) {
            $data['shipping_address'] = collect($s->shipping_address_json)->only([
                'name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country',
            ])->all();
            $data['shipping_method_id'] = $s->shipping_method_id;
            $data['paused_at'] = optional($s->paused_at)?->toIso8601String();
            $data['cancelled_at'] = optional($s->cancelled_at)?->toIso8601String();
            $data['cancel_reason'] = $s->cancel_reason;
            if ($s->relationLoaded('cycles')) {
                $data['cycles'] = $s->cycles->map(fn (SubscriptionCycle $c) => $this->serializeCycle($c))->values()->all();
            }
            if ($s->relationLoaded('events')) {
                $data['events'] = $s->events->take(50)->map(fn (SubscriptionEvent $e) => [
                    'event_type' => $e->event_type,
                    'payload' => $e->payload,
                    'actor_user_id' => $e->actor_user_id,
                    'created_at' => optional($e->created_at)?->toIso8601String(),
                ])->values()->all();
            }
        }

        return $data;
    }

    private function serializeCycle(SubscriptionCycle $c): array
    {
        return [
            'id' => $c->id,
            'cycle_number' => (int) $c->cycle_number,
            'status' => $c->status,
            'scheduled_at' => optional($c->scheduled_at)?->toIso8601String(),
            'order_id' => $c->order_id,
            'order_number' => $c->relationLoaded('order') ? $c->order?->order_number : null,
            'unit_price' => (float) $c->unit_price,
            'quantity' => (int) $c->quantity,
            'amount' => (float) $c->amount,
            'failure_reason' => $c->failure_reason,
            'processed_at' => optional($c->processed_at)?->toIso8601String(),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
