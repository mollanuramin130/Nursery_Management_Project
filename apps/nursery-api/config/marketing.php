<?php

return [
    /*
    | Marketing automation defaults — documented in PHASE_17_MARKETING_RUNBOOK.md
    */
    'frequency' => [
        'max_marketing_messages_per_day' => (int) env('MARKETING_MAX_PER_DAY', 2),
        'max_campaign_messages_per_week' => (int) env('MARKETING_MAX_PER_WEEK', 5),
    ],

    'abandoned_cart' => [
        'inactive_hours' => (int) env('ABANDONED_CART_HOURS', 24),
        'max_messages' => (int) env('ABANDONED_CART_MAX_MESSAGES', 2),
        'min_subtotal' => (float) env('ABANDONED_CART_MIN_SUBTOTAL', 0),
        'cooldown_hours' => (int) env('ABANDONED_CART_COOLDOWN_HOURS', 48),
    ],

    'welcome' => [
        'enabled' => (bool) env('MARKETING_WELCOME_ENABLED', true),
        'delay_minutes' => (int) env('MARKETING_WELCOME_DELAY_MINUTES', 5),
    ],

    'post_purchase' => [
        'review_request_days_after_delivery' => (int) env('MARKETING_REVIEW_DAYS', 3),
        'enabled' => (bool) env('MARKETING_POST_PURCHASE_ENABLED', true),
    ],

    'reactivation' => [
        'inactive_days' => (int) env('MARKETING_REACTIVATION_DAYS', 90),
    ],

    'batch_size' => (int) env('MARKETING_BATCH_SIZE', 100),
];
