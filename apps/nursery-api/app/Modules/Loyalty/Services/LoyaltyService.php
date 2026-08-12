<?php

namespace App\Modules\Loyalty\Services;

use App\Modules\Admin\Models\Setting;
use App\Modules\Auth\Models\User;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LoyaltyService
{
    public const TYPE_EARN = 'EARN';

    public const TYPE_REDEEM = 'REDEEM';

    public const TYPE_REVERSE = 'REVERSE';

    public const TYPE_ADJUST = 'ADJUST';

    public const TYPE_EXPIRE = 'EXPIRE';

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Points awarded per ₹1 of order grand total (fractional allowed via setting).
     * Default: 0.1 → ₹10 = 1 point.
     */
    public function pointsPerRupee(): float
    {
        $setting = Setting::query()->where('key', 'loyalty.points_per_rupee')->first();
        if (! $setting || $setting->value === null || $setting->value === '') {
            return 0.1;
        }

        $value = (float) $setting->typedValue();

        return $value > 0 ? $value : 0.1;
    }

    public function enabled(): bool
    {
        $setting = Setting::query()->where('key', 'loyalty.enabled')->first();
        if (! $setting) {
            return true;
        }

        return (bool) $setting->typedValue();
    }

    public function forUser(User $user): array
    {
        $account = $this->getOrCreateAccount($user->id);

        return [
            'balance' => (int) $account->balance,
            'lifetime_earned' => (int) $account->lifetime_earned,
            'lifetime_redeemed' => (int) $account->lifetime_redeemed,
            'status' => $account->status,
            'points_per_rupee' => $this->pointsPerRupee(),
            'how_it_works' => [
                'Earn points when an order is delivered (not at checkout).',
                'Points are reversed proportionally when refunds are recorded.',
                'Checkout redemption is not enabled yet — points are engagement rewards only.',
            ],
        ];
    }

    public function historyForUser(User $user, int $perPage = 20, int $page = 1): array
    {
        $account = $this->getOrCreateAccount($user->id);
        $paginator = LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 50), ['*'], 'page', max(1, $page));

        $data = collect($paginator->items())->map(fn (LoyaltyTransaction $t) => $this->serializeTx($t))->values()->all();

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

    public function adminList(?string $q, int $perPage = 20, int $page = 1): array
    {
        $query = LoyaltyAccount::query()->with('user')->orderByDesc('balance');
        if ($q) {
            $term = trim($q);
            $query->where(function ($inner) use ($term) {
                $inner->where('user_id', (int) $term)
                    ->orWhereHas('user', function ($u) use ($term) {
                        $u->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    });
            });
        }

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
        $data = collect($paginator->items())->map(function (LoyaltyAccount $a) {
            return [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'customer' => [
                    'id' => $a->user_id,
                    'name' => $a->user?->name,
                    'email' => $a->user?->email,
                ],
                'balance' => (int) $a->balance,
                'lifetime_earned' => (int) $a->lifetime_earned,
                'lifetime_redeemed' => (int) $a->lifetime_redeemed,
                'status' => $a->status,
                'updated_at' => optional($a->updated_at)?->toIso8601String(),
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
            'dashboard' => $this->dashboard(),
        ];
    }

    public function adminTransactions(?int $userId, ?string $type, int $perPage = 20, int $page = 1): array
    {
        $query = LoyaltyTransaction::query()
            ->with('user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($type, fn ($q) => $q->where('type', strtoupper($type)))
            ->orderByDesc('id');

        $paginator = $query->paginate(min(max($perPage, 1), 100), ['*'], 'page', max(1, $page));
        $data = collect($paginator->items())->map(fn (LoyaltyTransaction $t) => $this->serializeTx($t, true))->values()->all();

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

    public function dashboard(): array
    {
        return [
            'accounts' => LoyaltyAccount::query()->count(),
            'points_outstanding' => (int) LoyaltyAccount::query()->sum('balance'),
            'points_earned' => (int) LoyaltyTransaction::query()->where('type', self::TYPE_EARN)->sum('points'),
            'points_reversed' => (int) abs((int) LoyaltyTransaction::query()->where('type', self::TYPE_REVERSE)->sum('points')),
            'points_adjusted' => (int) LoyaltyTransaction::query()->where('type', self::TYPE_ADJUST)->sum('points'),
            'enabled' => $this->enabled(),
            'points_per_rupee' => $this->pointsPerRupee(),
        ];
    }

    /**
     * Idempotent earn on order delivery.
     *
     * @return array<string, mixed>|null
     */
    public function earnForDeliveredOrder(Order $order, ?int $actorUserId = null): ?array
    {
        if (! $this->enabled() || ! $order->user_id) {
            return null;
        }

        $points = $this->pointsForOrderAmount((float) $order->grand_total);
        if ($points < 1) {
            return null;
        }

        return $this->postTransaction(
            userId: (int) $order->user_id,
            type: self::TYPE_EARN,
            points: $points,
            idempotencyKey: 'earn:order:'.$order->id,
            referenceType: 'order',
            referenceId: $order->id,
            reason: 'Order delivered reward',
            actorUserId: $actorUserId,
            meta: [
                'order_number' => $order->order_number,
                'grand_total' => (float) $order->grand_total,
                'points_per_rupee' => $this->pointsPerRupee(),
            ],
            notify: true,
            notifyTitle: 'Reward points earned',
            notifyBody: "You earned {$points} points for order {$order->order_number}.",
        );
    }

    /**
     * Reverse points proportionally for a refund against an order (idempotent per refund).
     *
     * @return array<string, mixed>|null
     */
    public function reverseForRefund(Order $order, float $refundAmount, int $refundId, ?int $actorUserId = null): ?array
    {
        if (! $this->enabled() || ! $order->user_id || $refundAmount <= 0) {
            return null;
        }

        $earned = LoyaltyTransaction::query()
            ->where('user_id', $order->user_id)
            ->where('type', self::TYPE_EARN)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->sum('points');

        if ($earned < 1) {
            return null;
        }

        $alreadyReversed = (int) abs((int) LoyaltyTransaction::query()
            ->where('user_id', $order->user_id)
            ->where('type', self::TYPE_REVERSE)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->sum('points'));

        $remaining = max(0, (int) $earned - $alreadyReversed);
        if ($remaining < 1) {
            return null;
        }

        $grand = max(0.01, (float) $order->grand_total);
        $ratio = min(1.0, $refundAmount / $grand);
        $toReverse = (int) min($remaining, max(1, (int) floor($earned * $ratio)));

        return $this->postTransaction(
            userId: (int) $order->user_id,
            type: self::TYPE_REVERSE,
            points: -$toReverse,
            idempotencyKey: 'reverse:refund:'.$refundId,
            referenceType: 'order',
            referenceId: $order->id,
            reason: 'Refund points reversal',
            actorUserId: $actorUserId,
            meta: [
                'refund_id' => $refundId,
                'refund_amount' => $refundAmount,
                'earned_for_order' => (int) $earned,
            ],
            notify: true,
            notifyTitle: 'Reward points adjusted',
            notifyBody: "{$toReverse} points were reversed due to a refund on order {$order->order_number}.",
        );
    }

    public function adjust(int $userId, int $points, string $reason, ?int $actorUserId = null, ?string $idempotencyKey = null): array
    {
        if ($points === 0) {
            throw new ApiException('Adjustment points cannot be zero', 422, 'VALIDATION_ERROR');
        }
        if (trim($reason) === '') {
            throw new ApiException('Adjustment reason is required', 422, 'VALIDATION_ERROR');
        }

        $key = $idempotencyKey ?: ('adjust:'.uniqid('', true));

        $result = $this->postTransaction(
            userId: $userId,
            type: self::TYPE_ADJUST,
            points: $points,
            idempotencyKey: $key,
            referenceType: 'admin_adjust',
            referenceId: $actorUserId,
            reason: $reason,
            actorUserId: $actorUserId,
            meta: ['manual' => true],
            notify: true,
            notifyTitle: 'Reward points updated',
            notifyBody: ($points > 0 ? "+{$points}" : (string) $points).' points: '.$reason,
        );

        AuditLogger::log('loyalty.adjust', 'loyalty_account', $result['account_id'], null, $result, $actorUserId);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function postTransaction(
        int $userId,
        string $type,
        int $points,
        string $idempotencyKey,
        ?string $referenceType,
        ?int $referenceId,
        ?string $reason,
        ?int $actorUserId,
        array $meta,
        bool $notify,
        ?string $notifyTitle = null,
        ?string $notifyBody = null,
    ): array {
        return DB::transaction(function () use (
            $userId, $type, $points, $idempotencyKey, $referenceType, $referenceId,
            $reason, $actorUserId, $meta, $notify, $notifyTitle, $notifyBody
        ) {
            $existing = LoyaltyTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return array_merge($this->serializeTx($existing), [
                    'account_id' => $existing->loyalty_account_id,
                    'idempotent_replay' => true,
                ]);
            }

            $account = LoyaltyAccount::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = LoyaltyAccount::query()->create([
                    'user_id' => $userId,
                    'balance' => 0,
                    'lifetime_earned' => 0,
                    'lifetime_redeemed' => 0,
                    'status' => 'active',
                ]);
                $account = LoyaltyAccount::query()->whereKey($account->id)->lockForUpdate()->first();
            }

            $newBalance = (int) $account->balance + $points;
            if ($newBalance < 0) {
                throw new ApiException('Insufficient loyalty balance', 422, 'VALIDATION_ERROR');
            }

            $account->balance = $newBalance;
            if ($points > 0 && $type === self::TYPE_EARN) {
                $account->lifetime_earned = (int) $account->lifetime_earned + $points;
            }
            if ($points < 0 && in_array($type, [self::TYPE_REDEEM, self::TYPE_REVERSE], true)) {
                $account->lifetime_redeemed = (int) $account->lifetime_redeemed + abs($points);
            }
            if ($points > 0 && $type === self::TYPE_ADJUST) {
                $account->lifetime_earned = (int) $account->lifetime_earned + $points;
            }
            $account->save();

            $tx = LoyaltyTransaction::query()->create([
                'loyalty_account_id' => $account->id,
                'user_id' => $userId,
                'type' => $type,
                'points' => $points,
                'balance_after' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'actor_user_id' => $actorUserId,
                'meta' => $meta,
            ]);

            if ($notify && $notifyTitle && $notifyBody) {
                try {
                    $this->notifications->notify(
                        $userId,
                        'LOYALTY_UPDATED',
                        $notifyTitle,
                        $notifyBody,
                        [
                            'points' => $points,
                            'balance' => $newBalance,
                            'type' => $type,
                        ],
                    );
                } catch (\Throwable) {
                    // Non-blocking
                }
            }

            return array_merge($this->serializeTx($tx), [
                'account_id' => $account->id,
                'idempotent_replay' => false,
            ]);
        });
    }

    public function pointsForOrderAmount(float $amount): int
    {
        return (int) max(0, floor($amount * $this->pointsPerRupee()));
    }

    private function getOrCreateAccount(int $userId): LoyaltyAccount
    {
        $account = LoyaltyAccount::query()->where('user_id', $userId)->first();
        if ($account) {
            return $account;
        }

        if (! User::query()->whereKey($userId)->exists()) {
            throw new NotFoundHttpException('User not found');
        }

        return LoyaltyAccount::query()->create([
            'user_id' => $userId,
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_redeemed' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTx(LoyaltyTransaction $t, bool $admin = false): array
    {
        $row = [
            'id' => $t->id,
            'type' => $t->type,
            'points' => (int) $t->points,
            'balance_after' => (int) $t->balance_after,
            'reason' => $t->reason,
            'reference_type' => $t->reference_type,
            'reference_id' => $t->reference_id,
            'created_at' => optional($t->created_at)?->toIso8601String(),
        ];

        if ($admin) {
            $row['user_id'] = $t->user_id;
            $row['customer'] = [
                'id' => $t->user_id,
                'name' => $t->user?->name,
                'email' => $t->user?->email,
            ];
            $row['actor_user_id'] = $t->actor_user_id;
            $row['idempotency_key'] = $t->idempotency_key;
        }

        return $row;
    }
}
