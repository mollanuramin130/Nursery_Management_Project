<?php

/**
 * Rate-limit ceilings. 0 = use environment defaults in ThrottleLimits.
 *
 * Local/testing: high enough for legitimate QA (login → shop → checkout).
 * Production: keep DoS / brute-force protection.
 */
return [
    'api_per_minute' => (int) env('THROTTLE_API_PER_MINUTE', 0),
    'login_per_minute' => (int) env('THROTTLE_LOGIN_PER_MINUTE', 0),
    'register_per_minute' => (int) env('THROTTLE_REGISTER_PER_MINUTE', 0),
    'password_per_minute' => (int) env('THROTTLE_PASSWORD_PER_MINUTE', 0),
    'refresh_per_minute' => (int) env('THROTTLE_REFRESH_PER_MINUTE', 0),
    'checkout_preview_per_minute' => (int) env('THROTTLE_CHECKOUT_PREVIEW_PER_MINUTE', 0),
    'order_write_per_minute' => (int) env('THROTTLE_ORDER_WRITE_PER_MINUTE', 0),
    'payment_write_per_minute' => (int) env('THROTTLE_PAYMENT_WRITE_PER_MINUTE', 0),
    'webhook_per_minute' => (int) env('THROTTLE_WEBHOOK_PER_MINUTE', 0),
    'search_per_minute' => (int) env('THROTTLE_SEARCH_PER_MINUTE', 0),
    'health_per_minute' => (int) env('THROTTLE_HEALTH_PER_MINUTE', 0),
    'plant_finder_per_minute' => (int) env('THROTTLE_PLANT_FINDER_PER_MINUTE', 0),
    'product_views_per_minute' => (int) env('THROTTLE_PRODUCT_VIEWS_PER_MINUTE', 0),
    'review_write_per_minute' => (int) env('THROTTLE_REVIEW_WRITE_PER_MINUTE', 0),
    'stock_alert_per_minute' => (int) env('THROTTLE_STOCK_ALERT_PER_MINUTE', 0),
    'payment_verify_per_minute' => (int) env('THROTTLE_PAYMENT_VERIFY_PER_MINUTE', 0),
    'payment_retry_per_minute' => (int) env('THROTTLE_PAYMENT_RETRY_PER_MINUTE', 0),
    'order_return_per_minute' => (int) env('THROTTLE_ORDER_RETURN_PER_MINUTE', 0),
];
