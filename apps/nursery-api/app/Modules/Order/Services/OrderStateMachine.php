<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Shared\Exceptions\ApiException;

class OrderStateMachine
{
    private const TRANSITIONS = [
        'PENDING_PAYMENT' => ['CONFIRMED', 'PAYMENT_FAILED', 'CANCELLED'],
        'PAYMENT_FAILED' => ['PENDING_PAYMENT', 'CANCELLED'],
        'CONFIRMED' => ['PROCESSING', 'CANCELLED'],
        'PROCESSING' => ['PACKED', 'CANCELLED'],
        'PACKED' => ['SHIPPED', 'CANCELLED'],
        'SHIPPED' => ['OUT_FOR_DELIVERY', 'DELIVERED'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'DELIVERY_FAILED'],
        'DELIVERY_FAILED' => ['OUT_FOR_DELIVERY', 'DELIVERED'],
        'DELIVERED' => ['RETURN_REQUESTED'],
        // Reject restores delivered; completed return path → RETURNED then REFUNDED.
        'RETURN_REQUESTED' => ['RETURNED', 'REFUNDED', 'DELIVERED'],
        'RETURNED' => ['REFUNDED'],
        'CANCELLED' => [],
        'REFUNDED' => [],
    ];

    public static function allowedFrom(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public function transition(
        Order $order,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $note = null,
        ?string $requestId = null,
    ): Order {
        $from = $order->status;
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new ApiException("Cannot transition order from {$from} to {$toStatus}", 409, 'CONFLICT');
        }

        $order->status = $toStatus;
        if ($toStatus === 'CONFIRMED') {
            $order->confirmed_at = now();
            $order->cancelled_at = null;
        }
        if ($toStatus === 'PENDING_PAYMENT') {
            $order->cancelled_at = null;
            $order->cancel_reason = null;
        }
        if ($toStatus === 'CANCELLED' || $toStatus === 'PAYMENT_FAILED') {
            $order->cancelled_at = now();
            if ($note) {
                $order->cancel_reason = $note;
            }
        }
        $order->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'note' => $note,
            'request_id' => $requestId,
        ]);

        return $order->fresh();
    }
}
