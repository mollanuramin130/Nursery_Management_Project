<?php

namespace App\Shared\Support;

/**
 * Production readiness checks (QA-16).
 * Intended for artisan/CI — does not mutate configuration.
 */
final class ProductionReadinessChecker
{
    /**
     * @return list<array{code: string, severity: string, message: string}>
     */
    public function evaluate(?string $env = null, bool $debug = false, array $config = []): array
    {
        $env = $env ?? (string) config('app.env');
        $debug = $config['debug'] ?? (bool) config('app.debug');
        $findings = [];

        $razorpayKey = (string) ($config['razorpay_key'] ?? env('RAZORPAY_KEY', env('RAZORPAY_KEY_ID', '')));
        $razorpaySecret = (string) ($config['razorpay_secret'] ?? env('RAZORPAY_SECRET', env('RAZORPAY_KEY_SECRET', '')));
        $webhook = (string) ($config['razorpay_webhook'] ?? env('RAZORPAY_WEBHOOK_SECRET', ''));
        $cors = (string) ($config['cors'] ?? env('CORS_ALLOWED_ORIGINS', ''));
        $appUrl = (string) ($config['app_url'] ?? config('app.url'));
        $allowUnsigned = filter_var(
            $config['allow_unsigned_webhooks'] ?? env('PAYMENT_ALLOW_UNSIGNED_WEBHOOKS', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $jwt = (string) ($config['jwt_secret'] ?? env('JWT_SECRET', ''));

        if ($env === 'production' && $debug === true) {
            $findings[] = [
                'code' => 'APP_DEBUG_ON',
                'severity' => 'P0',
                'message' => 'APP_DEBUG must be false in production',
            ];
        }

        if ($env === 'production' && $razorpayKey === '') {
            $findings[] = [
                'code' => 'RAZORPAY_KEY_MISSING',
                'severity' => 'P0',
                'message' => 'RAZORPAY_KEY empty — paid checkout unavailable; local_stub refused in production',
            ];
        }

        if ($env === 'production' && $razorpaySecret === '') {
            $findings[] = [
                'code' => 'RAZORPAY_SECRET_MISSING',
                'severity' => 'P0',
                'message' => 'RAZORPAY_SECRET empty',
            ];
        }

        if ($env === 'production' && $webhook === '') {
            $findings[] = [
                'code' => 'RAZORPAY_WEBHOOK_MISSING',
                'severity' => 'P0',
                'message' => 'RAZORPAY_WEBHOOK_SECRET empty — webhook verification cannot be trusted',
            ];
        }

        if ($env === 'production' && $allowUnsigned) {
            $findings[] = [
                'code' => 'UNSIGNED_WEBHOOKS_ENABLED',
                'severity' => 'P0',
                'message' => 'PAYMENT_ALLOW_UNSIGNED_WEBHOOKS must be false in production',
            ];
        }

        if ($env === 'production' && $razorpayKey !== '' && str_starts_with($razorpayKey, 'rzp_test_')) {
            $findings[] = [
                'code' => 'RAZORPAY_TEST_KEY_IN_PRODUCTION',
                'severity' => 'P0',
                'message' => 'RAZORPAY_KEY appears to be a test key (rzp_test_) — production must use live keys (rzp_live_)',
            ];
        }

        if ($env === 'production' && $jwt === '') {
            $findings[] = [
                'code' => 'JWT_SECRET_MISSING',
                'severity' => 'P0',
                'message' => 'JWT_SECRET must be set in production',
            ];
        }

        if ($env === 'production' && str_contains($cors, 'localhost')) {
            $findings[] = [
                'code' => 'CORS_LOCALHOST',
                'severity' => 'P0',
                'message' => 'CORS_ALLOWED_ORIGINS must not include localhost in production',
            ];
        }

        if ($env === 'production' && (str_contains($cors, '*') || trim($cors) === '')) {
            $findings[] = [
                'code' => 'CORS_INVALID',
                'severity' => 'P0',
                'message' => 'CORS_ALLOWED_ORIGINS must list explicit HTTPS origins (no wildcard / empty)',
            ];
        }

        if ($env === 'production' && ! str_starts_with($appUrl, 'https://')) {
            $findings[] = [
                'code' => 'APP_URL_NOT_HTTPS',
                'severity' => 'P0',
                'message' => 'APP_URL should be https:// in production',
            ];
        }

        return $findings;
    }

    public function isReady(?string $env = null, bool $debug = false, array $config = []): bool
    {
        foreach ($this->evaluate($env, $debug, $config) as $finding) {
            if (($finding['severity'] ?? '') === 'P0') {
                return false;
            }
        }

        return true;
    }
}
