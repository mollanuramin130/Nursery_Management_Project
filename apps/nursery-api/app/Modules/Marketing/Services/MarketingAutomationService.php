<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Marketing\Models\MarketingAutomation;
use App\Modules\Marketing\Models\MarketingDelivery;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Marketing automations — respects prefs, frequency caps, idempotency.
 * Never marks delivery as sent unless NotificationService returns a row.
 */
class MarketingAutomationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PreferenceService $preferences,
        private readonly CustomerSegmentService $segments,
        private readonly CartService $carts,
    ) {}

    public function list(?string $type = null): array
    {
        return MarketingAutomation::query()
            ->with(['segment:id,key,name', 'campaign:id,title,slug', 'coupon:id,code,name'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('id')
            ->get()
            ->map(fn (MarketingAutomation $a) => $this->present($a))
            ->values()
            ->all();
    }

    public function show(int $id): array
    {
        $a = MarketingAutomation::query()
            ->with(['segment', 'campaign', 'coupon'])
            ->findOrFail($id);

        $row = $this->present($a);
        $row['delivery_stats'] = [
            'sent' => MarketingDelivery::query()->where('automation_id', $id)->where('status', 'sent')->count(),
            'failed' => MarketingDelivery::query()->where('automation_id', $id)->where('status', 'failed')->count(),
            'skipped' => MarketingDelivery::query()->where('automation_id', $id)->where('status', 'skipped')->count(),
        ];

        return $row;
    }

    public function create(array $payload, ?int $actorId): MarketingAutomation
    {
        $automation = MarketingAutomation::query()->create([
            'key' => $payload['key'] ?? Str::slug($payload['name']).'_'.Str::lower(Str::random(4)),
            'name' => $payload['name'],
            'type' => $payload['type'],
            'status' => $payload['status'] ?? 'draft',
            'segment_id' => $payload['segment_id'] ?? null,
            'campaign_id' => $payload['campaign_id'] ?? null,
            'coupon_id' => $payload['coupon_id'] ?? null,
            'channels_json' => $payload['channels'] ?? ['in_app', 'email'],
            'config_json' => $payload['config'] ?? [],
            'title_template' => $payload['title_template'] ?? null,
            'body_template' => $payload['body_template'] ?? null,
            'scheduled_at' => $payload['scheduled_at'] ?? null,
            'created_by' => $actorId,
        ]);
        AuditLogger::log('marketing_automation.create', 'marketing_automation', $automation->id, null, $automation->toArray(), $actorId);

        return $automation;
    }

    public function update(int $id, array $payload, ?int $actorId): MarketingAutomation
    {
        $a = MarketingAutomation::query()->findOrFail($id);
        $before = $a->toArray();
        $map = [
            'name' => 'name',
            'status' => 'status',
            'segment_id' => 'segment_id',
            'campaign_id' => 'campaign_id',
            'coupon_id' => 'coupon_id',
            'title_template' => 'title_template',
            'body_template' => 'body_template',
            'scheduled_at' => 'scheduled_at',
        ];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $payload)) {
                $a->{$col} = $payload[$in];
            }
        }
        if (isset($payload['channels'])) {
            $a->channels_json = $payload['channels'];
        }
        if (isset($payload['config'])) {
            $a->config_json = $payload['config'];
        }
        $a->save();
        AuditLogger::log('marketing_automation.update', 'marketing_automation', $id, $before, $a->toArray(), $actorId);

        return $a;
    }

    public function transition(int $id, string $to, ?int $actorId): MarketingAutomation
    {
        $a = MarketingAutomation::query()->findOrFail($id);
        $from = $a->status;
        $allowed = [
            'draft' => ['active', 'paused', 'archived'],
            'active' => ['paused', 'archived'],
            'paused' => ['active', 'archived'],
            'archived' => [],
        ];
        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new ApiException("Cannot transition automation from {$from} to {$to}", 422, 'VALIDATION_ERROR');
        }
        if ($to === 'active') {
            $this->assertLaunchReady($a);
        }
        $before = $a->toArray();
        $a->status = $to;
        $a->save();
        AuditLogger::log('marketing_automation.'.$to, 'marketing_automation', $id, $before, $a->toArray(), $actorId);

        return $a;
    }

    /**
     * Dispatch to segment (or test user ids). Queues one notification per eligible user.
     *
     * @param  list<int>|null  $testUserIds
     */
    public function dispatch(int $id, ?array $testUserIds, ?int $actorId): array
    {
        $a = MarketingAutomation::query()->with('segment')->findOrFail($id);
        if (! in_array($a->status, ['active', 'draft'], true) && empty($testUserIds)) {
            throw new ApiException('Automation must be active (or use test mode)', 422, 'VALIDATION_ERROR');
        }
        if (empty($testUserIds)) {
            $this->assertLaunchReady($a);
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $batch = (int) config('marketing.batch_size', 100);

        if ($testUserIds) {
            $users = User::query()->whereIn('id', $testUserIds)->limit(20)->get();
        } elseif ($a->segment_id) {
            $users = $this->segments->buildQuery($a->segment->criteria_json ?? ['all' => []])
                ->limit($batch)
                ->get();
        } else {
            throw new ApiException('Segment or test_user_ids required', 422, 'VALIDATION_ERROR');
        }

        foreach ($users as $user) {
            $result = $this->deliverToUser($a, $user, testMode: (bool) $testUserIds);
            match ($result) {
                'sent' => $sent++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $a->last_run_at = now();
        $a->save();
        AuditLogger::log('marketing_automation.dispatch', 'marketing_automation', $id, null, [
            'sent' => $sent, 'skipped' => $skipped, 'failed' => $failed, 'test' => (bool) $testUserIds,
        ], $actorId);

        return compact('sent', 'skipped', 'failed');
    }

    public function processAbandonedCarts(int $limit = 100): array
    {
        $automation = MarketingAutomation::query()
            ->where('type', 'abandoned_cart')
            ->where('status', 'active')
            ->first();

        if (! $automation) {
            return ['processed' => 0, 'note' => 'No active abandoned_cart automation'];
        }

        $hours = (int) ($automation->config_json['inactive_hours'] ?? config('marketing.abandoned_cart.inactive_hours', 24));
        $maxMessages = (int) ($automation->config_json['max_messages'] ?? config('marketing.abandoned_cart.max_messages', 2));
        $minSubtotal = (float) ($automation->config_json['min_subtotal'] ?? config('marketing.abandoned_cart.min_subtotal', 0));
        $cutoff = now()->subHours($hours);

        $carts = Cart::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->where('updated_at', '<=', $cutoff)
            ->with('items')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        $skipped = 0;
        foreach ($carts as $cart) {
            if ($cart->items->isEmpty()) {
                $skipped++;
                continue;
            }
            $presented = $this->carts->present($cart);
            if ((float) ($presented['subtotal'] ?? 0) < $minSubtotal) {
                $skipped++;
                continue;
            }
            $user = User::query()->find($cart->user_id);
            if (! $user) {
                $skipped++;
                continue;
            }
            // Stop if customer already ordered after cart update.
            $orderedAfter = Order::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $cart->updated_at)
                ->whereNotIn('status', ['CANCELLED', 'PAYMENT_FAILED'])
                ->exists();
            if ($orderedAfter) {
                $skipped++;
                continue;
            }
            $prior = MarketingDelivery::query()
                ->where('automation_id', $automation->id)
                ->where('user_id', $user->id)
                ->where('status', 'sent')
                ->count();
            if ($prior >= $maxMessages) {
                $skipped++;
                continue;
            }
            $result = $this->deliverToUser($automation, $user, meta: [
                'cart_id' => $cart->id,
                'subtotal' => $presented['subtotal'] ?? null,
            ]);
            $result === 'sent' ? $sent++ : $skipped++;
        }

        $automation->last_run_at = now();
        $automation->save();

        return ['processed' => $carts->count(), 'sent' => $sent, 'skipped' => $skipped];
    }

    public function sendWelcome(User $user): void
    {
        if (! config('marketing.welcome.enabled', true)) {
            return;
        }
        $automation = MarketingAutomation::query()
            ->where('type', 'welcome')
            ->where('status', 'active')
            ->first();
        if (! $automation) {
            return;
        }
        $this->deliverToUser($automation, $user);
    }

    public function sendPostPurchaseReview(Order $order): void
    {
        if (! config('marketing.post_purchase.enabled', true)) {
            return;
        }
        $automation = MarketingAutomation::query()
            ->where('type', 'post_purchase')
            ->where('status', 'active')
            ->first();
        if (! $automation || ! $order->user_id) {
            return;
        }
        $user = User::query()->find($order->user_id);
        if (! $user) {
            return;
        }
        $this->deliverToUser($automation, $user, meta: [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    public function seedDefaults(): void
    {
        $defaults = [
            [
                'key' => 'welcome_v1',
                'name' => 'Welcome Journey',
                'type' => 'welcome',
                'status' => 'active',
                'title_template' => 'Welcome to GreenLeaf Nursery',
                'body_template' => 'Thanks for joining. Explore plants curated for Indian homes — use Find Your Plant if you need a match.',
                'channels_json' => ['in_app', 'email'],
            ],
            [
                'key' => 'abandoned_cart_v1',
                'name' => 'Abandoned Cart Recovery',
                'type' => 'abandoned_cart',
                'status' => 'draft',
                'title_template' => 'You left something behind',
                'body_template' => 'Your cart is waiting. Checkout when you are ready — no fake urgency.',
                'channels_json' => ['in_app', 'email', 'push'],
                'config_json' => [
                    'inactive_hours' => (int) config('marketing.abandoned_cart.inactive_hours', 24),
                    'max_messages' => (int) config('marketing.abandoned_cart.max_messages', 2),
                    'min_subtotal' => (float) config('marketing.abandoned_cart.min_subtotal', 0),
                ],
            ],
            [
                'key' => 'post_purchase_review_v1',
                'name' => 'Post-purchase Review Request',
                'type' => 'post_purchase',
                'status' => 'draft',
                'title_template' => 'How are your plants doing?',
                'body_template' => 'If your order was delivered, we would love a short review to help other gardeners.',
                'channels_json' => ['in_app', 'email'],
                'config_json' => [
                    'days_after_delivery' => (int) config('marketing.post_purchase.review_request_days_after_delivery', 3),
                ],
            ],
            [
                'key' => 'reactivation_v1',
                'name' => 'Reactivation (90d inactive)',
                'type' => 'reactivation',
                'status' => 'draft',
                'title_template' => 'We saved you a sunny spot',
                'body_template' => 'It has been a while — browse seasonal picks when you are ready. Marketing preferences always apply.',
                'channels_json' => ['in_app', 'email'],
            ],
        ];

        foreach ($defaults as $row) {
            MarketingAutomation::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }

    private function deliverToUser(
        MarketingAutomation $automation,
        User $user,
        bool $testMode = false,
        array $meta = [],
    ): string {
        $prefs = $this->preferences->get($user);
        if (! $testMode) {
            if (! ($prefs['marketing_opt_in'] ?? false) || ! ($prefs['notify_promotions'] ?? false)) {
                $this->recordDelivery($automation, $user, 'skipped', 'opted_out', $meta);

                return 'skipped';
            }
            if ($this->exceededFrequency($user->id)) {
                $this->recordDelivery($automation, $user, 'skipped', 'frequency_cap', $meta);

                return 'skipped';
            }
        }

        $dayKey = now()->format('Y-m-d');
        $idempotency = 'mkt:'.$automation->id.':user:'.$user->id.':'.$dayKey;
        if (! empty($meta['cart_id'])) {
            $idempotency .= ':cart:'.$meta['cart_id'].':n'.(
                MarketingDelivery::query()->where('automation_id', $automation->id)->where('user_id', $user->id)->where('status', 'sent')->count() + 1
            );
        }
        if (! empty($meta['order_id'])) {
            $idempotency .= ':order:'.$meta['order_id'];
        }

        if (MarketingDelivery::query()->where('idempotency_key', $idempotency)->exists()) {
            return 'skipped';
        }

        $title = $automation->title_template ?: $automation->name;
        $body = $automation->body_template ?: $automation->name;
        if ($automation->coupon_id && $automation->coupon) {
            $body .= ' Use code '.$automation->coupon->code.' if eligible.';
        }

        try {
            $notification = $this->notifications->notify(
                $user,
                'campaign_promo',
                $title,
                $body,
                array_merge([
                    'automation_id' => $automation->id,
                    'automation_key' => $automation->key,
                    'campaign_id' => $automation->campaign_id,
                    'coupon_id' => $automation->coupon_id,
                    'test_mode' => $testMode,
                ], $meta),
                $idempotency,
                'marketing',
            );

            if (! $notification) {
                $this->recordDelivery($automation, $user, 'skipped', 'notify_returned_null', $meta, $idempotency);

                return 'skipped';
            }

            $this->recordDelivery(
                $automation,
                $user,
                'sent',
                null,
                $meta,
                $idempotency,
                $notification->id,
            );

            return 'sent';
        } catch (Throwable $e) {
            Log::warning('marketing.deliver_failed', [
                'automation_id' => $automation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $this->recordDelivery($automation, $user, 'failed', 'exception', $meta, $idempotency);

            return 'failed';
        }
    }

    private function exceededFrequency(int $userId): bool
    {
        $maxDay = (int) config('marketing.frequency.max_marketing_messages_per_day', 2);
        $today = MarketingDelivery::query()
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $today >= $maxDay;
    }

    private function recordDelivery(
        MarketingAutomation $automation,
        User $user,
        string $status,
        ?string $skipReason,
        array $meta,
        ?string $idempotency = null,
        ?int $notificationId = null,
    ): void {
        MarketingDelivery::query()->create([
            'automation_id' => $automation->id,
            'campaign_id' => $automation->campaign_id,
            'user_id' => $user->id,
            'channel' => 'multi',
            'status' => $status,
            'idempotency_key' => $idempotency ?: ('skip:'.$automation->id.':'.$user->id.':'.Str::uuid()),
            'notification_id' => $notificationId,
            'skip_reason' => $skipReason,
            'meta_json' => $meta ?: null,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }

    private function assertLaunchReady(MarketingAutomation $a): void
    {
        if (! $a->title_template || ! $a->body_template) {
            throw new ApiException('title_template and body_template are required to activate', 422, 'VALIDATION_ERROR');
        }
        if (in_array($a->type, ['manual_blast', 'reactivation'], true) && ! $a->segment_id) {
            throw new ApiException('segment_id required for this automation type', 422, 'VALIDATION_ERROR');
        }
    }

    private function present(MarketingAutomation $a): array
    {
        return [
            'id' => $a->id,
            'key' => $a->key,
            'name' => $a->name,
            'type' => $a->type,
            'status' => $a->status,
            'segment_id' => $a->segment_id,
            'segment' => $a->segment ? ['id' => $a->segment->id, 'key' => $a->segment->key, 'name' => $a->segment->name] : null,
            'campaign_id' => $a->campaign_id,
            'campaign' => $a->campaign ? ['id' => $a->campaign->id, 'title' => $a->campaign->title, 'slug' => $a->campaign->slug] : null,
            'coupon_id' => $a->coupon_id,
            'coupon' => $a->coupon ? ['id' => $a->coupon->id, 'code' => $a->coupon->code, 'name' => $a->coupon->name] : null,
            'channels' => $a->channels_json ?? [],
            'config' => $a->config_json ?? [],
            'title_template' => $a->title_template,
            'body_template' => $a->body_template,
            'scheduled_at' => optional($a->scheduled_at)?->toIso8601String(),
            'last_run_at' => optional($a->last_run_at)?->toIso8601String(),
            'created_at' => optional($a->created_at)?->toIso8601String(),
            'updated_at' => optional($a->updated_at)?->toIso8601String(),
        ];
    }
}
