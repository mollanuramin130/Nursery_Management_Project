<?php

$defaultOrigins = 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001,http://127.0.0.1:3001';
$raw = (string) env('CORS_ALLOWED_ORIGINS', $defaultOrigins);
$origins = array_values(array_filter(array_map('trim', explode(',', $raw))));

$isProduction = env('APP_ENV') === 'production';

if ($origins === [] && ! $isProduction) {
    $origins = ['http://localhost:3000', 'http://127.0.0.1:3000'];
}

// Production with empty CORS_ALLOWED_ORIGINS → empty allow-list (fail closed).

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Cart-Token', 'X-Request-Id'],
    'max_age' => 0,
    'supports_credentials' => false,
];
