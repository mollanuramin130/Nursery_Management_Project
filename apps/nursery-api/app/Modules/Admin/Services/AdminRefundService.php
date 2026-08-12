<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\Refund;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminRefundService
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function list(?string $status, ?string $q, int $perPage = 20, int $page = 1): array
    {
        $query = Refund::query()
            ->with(['order.user', 'payment'])
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->when($q, function ($qb) use ($q) {
                $qb->where(function ($inner) use ($q) {
                    $inner->where('id', (int) $q)
                        ->orWhere('reason', 'like', "%{$q}%")
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get()->map(function (Refund $r) {
            return [
                'id' => $r->id,
                'order_id' => $r->order_id,
                'order_number' => $r->order?->order_number,
                'payment_id' => $r->payment_id,
                'customer' => [
                    'id' => $r->user_id,
                    'name' => $r->order?->user?->name,
                    'email' => $r->order?->user?->email,
                ],
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
                'status' => $r->status,
                'reason' => $r->reason,
                'mode' => $r->meta['mode'] ?? null,
                'return_request_id' => $r->meta['return_request_id'] ?? null,
                'idempotency_key' => $r->meta['idempotency_key'] ?? null,
                'created_at' => optional($r->created_at)?->toIso8601String(),
                'updated_at' => optional($r->updated_at)?->toIso8601String(),
            ];
        })->values()->all();

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function create(array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($payload, $actorUserId) {
            $order = Order::query()->whereKey($payload['order_id'])->lockForUpdate()->first();
            if (! $order) {
                throw new NotFoundHttpException('Order not found');
            }

            $idempotencyKey = isset($payload['idempotency_key']) && $payload['idempotency_key'] !== ''
                ? (string) $payload['idempotency_key']
                : null;

            if ($idempotencyKey) {
                $existing = Refund::query()
                    ->where('order_id', $order->id)
                    ->where('meta->idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return [
                        'id' => $existing->id,
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'amount' => (float) $existing->amount,
                        'status' => $existing->status,
                        'order_status' => $order->status,
                        'mode' => $existing->meta['mode'] ?? null,
                        'return_request_id' => $existing->meta['return_request_id'] ?? null,
                        'idempotency_key' => $idempotencyKey,
                        'idempotent_replay' => true,
                        'warning' => $existing->meta['warning'] ?? null,
                    ];
                }
            }

            $amount = (float) $payload['amount'];
            if ($amount <= 0) {
                throw new ApiException('Invalid refund amount', 422, 'VALIDATION_ERROR');
            }

            $payment = null;
            if (! empty($payload['payment_id'])) {
                $payment = Payment::query()->where('order_id', $order->id)->whereKey($payload['payment_id'])->first();
            } else {
                $payment = Payment::query()
                    ->where('order_id', $order->id)
                    ->whereIn('status', ['success', 'captured'])
                    ->latest('id')
                    ->first();
            }

            $countedStatuses = ['recorded_local', 'success', 'processed', 'pending'];
            $existingTotal = (float) Refund::query()
                ->where('order_id', $order->id)
                ->whereIn('status', $countedStatuses)
                ->sum('amount');

            $cap = (float) $order->grand_total;
            if ($payment) {
                $cap = min($cap, (float) $payment->amount);
            }

            if (($existingTotal + $amount) > $cap + 0.00001) {
                throw new ApiException(
                    'Refund amount exceeds remaining refundable total (cap '.$cap.', already refunded '.$existingTotal.')',
                    422,
                    'VALIDATION_ERROR',
                );
            }

            // Production must not pretend PSP refunds succeeded. Gateway refunds are a future phase.
            if (app()->environment('production')) {
                throw new ApiException(
                    'Live payment-provider refunds are not enabled. Record offline refunds via ops process or wait for gateway integration.',
                    503,
                    'PAYMENT_GATEWAY_UNAVAILABLE',
                );
            }

            $meta = [
                'mode' => 'local_stub',
                'note' => $payload['note'] ?? null,
                'warning' => 'No payment-provider refund was executed. Order payment state was not mutated as paid-out.',
            ];

            if (! empty($payload['return_request_id'])) {
                $meta['return_request_id'] = (int) $payload['return_request_id'];
            }
            if ($idempotencyKey) {
                $meta['idempotency_key'] = $idempotencyKey;
            }

            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'user_id' => $order->user_id,
                'amount' => $amount,
                'currency' => $order->currency ?? 'INR',
                'status' => 'recorded_local',
                'reason' => $payload['reason'] ?? null,
                'actor_user_id' => $actorUserId,
                'meta' => $meta,
            ]);

            // Do not transition order to REFUNDED on stub — callers (e.g. ReturnService) own order SM.
            AuditLogger::log('refund.create', 'refund', $refund->id, null, $refund->toArray(), $actorUserId);

            $loyaltyResult = null;
            try {
                $loyaltyResult = $this->loyalty->reverseForRefund(
                    $order,
                    $amount,
                    (int) $refund->id,
                    $actorUserId,
                );
            } catch (\Throwable) {
                // Loyalty reversal must not block refund recording.
            }

            return [
                'id' => $refund->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => (float) $refund->amount,
                'status' => $refund->status,
                'order_status' => $order->status,
                'mode' => 'local_stub',
                'return_request_id' => $meta['return_request_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'idempotent_replay' => false,
                'loyalty_reversal' => $loyaltyResult,
                'warning' => 'Local stub only — no payment provider refund executed.',
            ];
        });
    }
}
