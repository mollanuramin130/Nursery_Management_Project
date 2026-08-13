<?php

namespace App\Modules\Notification\Support;

/**
 * Central channel strategy — do not hard-code in controllers.
 */
class NotificationChannelMap
{
    /**
     * @return array{category: string, channels: list<string>}
     */
    public static function forType(string $type): array
    {
        $type = strtolower($type);

        $map = [
            'order_confirmed' => ['transactional', ['in_app', 'email', 'push']],
            'payment_confirmed' => ['transactional', ['in_app', 'email', 'push']],
            'payment_confirmed_admin' => ['transactional', ['in_app', 'push']],
            'new_order' => ['transactional', ['in_app', 'push']],
            'customer_cancelled' => ['transactional', ['in_app', 'push']],
            'order_processing' => ['transactional', ['in_app', 'push']],
            'order_packed' => ['transactional', ['in_app', 'push']],
            'order_shipped' => ['transactional', ['in_app', 'email', 'push']],
            'order_out_for_delivery' => ['transactional', ['in_app', 'push']],
            'order_delivered' => ['transactional', ['in_app', 'email', 'push']],
            'delivery_failed' => ['transactional', ['in_app', 'email', 'push']],
            'delivery_rescheduled' => ['transactional', ['in_app', 'push']],
            'order_cancelled' => ['transactional', ['in_app', 'email', 'push']],
            'payment_failed' => ['transactional', ['in_app', 'email', 'push']],
            'payment_refunded' => ['transactional', ['in_app', 'email', 'push']],
            'return_updated' => ['transactional', ['in_app', 'email', 'push']],
            'return_requested' => ['transactional', ['in_app', 'email', 'push']],
            'return_approved' => ['transactional', ['in_app', 'email', 'push']],
            'return_rejected' => ['transactional', ['in_app', 'email', 'push']],
            'refund_updated' => ['transactional', ['in_app', 'email', 'push']],
            'refund_initiated' => ['transactional', ['in_app', 'email', 'push']],
            'refund_completed' => ['transactional', ['in_app', 'email', 'push']],
            'return_requested_admin' => ['transactional', ['in_app', 'push']],
            'review_moderated' => ['transactional', ['in_app', 'email']],
            'loyalty_updated' => ['transactional', ['in_app', 'push']],
            'subscription_created' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_paused' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_resumed' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_cancelled' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_payment_required' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_cycle_created' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_payment_succeeded' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_payment_failed' => ['transactional', ['in_app', 'email', 'push']],
            'subscription_unavailable' => ['transactional', ['in_app', 'email', 'push']],
            'campaign_promo' => ['marketing', ['in_app', 'email', 'push']],
            'marketing' => ['marketing', ['in_app', 'email', 'push']],
            'stock_back_in_stock' => ['transactional', ['in_app', 'email', 'push']],
        ];

        if (! isset($map[$type])) {
            return ['category' => 'transactional', 'channels' => ['in_app']];
        }

        [$category, $channels] = $map[$type];

        return ['category' => $category, 'channels' => $channels];
    }
}
