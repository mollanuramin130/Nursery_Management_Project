<?php

// Runtime settings must be read through config(), including after config:cache.
return [
    'razorpay_key' => env('RAZORPAY_KEY', ''),
    'razorpay_secret' => env('RAZORPAY_SECRET', ''),
    'razorpay_webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
    'payment_driver' => env('PAYMENT_DRIVER', 'razorpay'),
    'allow_unsigned_webhooks' => env('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS', false),
    'upi_payee_vpa' => env('UPI_PAYEE_VPA', 'greenleaf@razorpay'),
    'min_android_version' => env('APP_MIN_ANDROID_VERSION', '1.0.0'),
    'min_ios_version' => env('APP_MIN_IOS_VERSION', '1.0.0'),
    'force_update' => env('APP_FORCE_UPDATE', false),
    'free_delivery_threshold' => env('FREE_DELIVERY_THRESHOLD', 999),
    'currency' => env('STORE_CURRENCY', 'INR'),
    'currency_symbol' => env('STORE_CURRENCY_SYMBOL', '₹'),
    'cod_enabled' => env('COD_ENABLED', true),
    'support_email' => env('STORE_SUPPORT_EMAIL', 'support@example.com'),
    'support_phone' => env('STORE_SUPPORT_PHONE', ''),
    'customer_web_url' => env('CUSTOMER_WEB_URL', 'http://127.0.0.1:3000'),
    'admin_web_url' => env('ADMIN_WEB_URL', 'http://127.0.0.1:3001'),
];
