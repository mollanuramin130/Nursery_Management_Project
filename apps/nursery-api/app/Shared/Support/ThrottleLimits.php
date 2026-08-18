<?php

namespace App\Shared\Support;

/**
 * Environment-aware request ceilings. Production stays protected;
 * local/testing allow legitimate rapid QA without 429 lockouts.
 */
final class ThrottleLimits
{
    public static function apiPerMinute(): int
    {
        return self::resolve('api_per_minute', 180, 400, 2000, 10000);
    }

    public static function loginPerMinute(): int
    {
        return self::resolve('login_per_minute', 12, 30, 60, 120);
    }

    public static function registerPerMinute(): int
    {
        return self::resolve('register_per_minute', 5, 15, 30, 120);
    }

    public static function passwordPerMinute(): int
    {
        return self::resolve('password_per_minute', 5, 15, 20, 120);
    }

    public static function refreshPerMinute(): int
    {
        return self::resolve('refresh_per_minute', 30, 60, 120, 500);
    }

    public static function checkoutPreviewPerMinute(): int
    {
        return self::resolve('checkout_preview_per_minute', 60, 120, 300, 1000);
    }

    public static function orderWritePerMinute(): int
    {
        return self::resolve('order_write_per_minute', 20, 40, 60, 200);
    }

    public static function paymentWritePerMinute(): int
    {
        return self::resolve('payment_write_per_minute', 20, 40, 60, 200);
    }

    public static function webhookPerMinute(): int
    {
        return self::resolve('webhook_per_minute', 120, 180, 300, 1000);
    }

    public static function searchPerMinute(): int
    {
        return self::resolve('search_per_minute', 60, 120, 300, 1000);
    }

    public static function healthPerMinute(): int
    {
        return self::resolve('health_per_minute', 120, 180, 300, 1000);
    }

    public static function plantFinderPerMinute(): int
    {
        return self::resolve('plant_finder_per_minute', 30, 60, 120, 500);
    }

    public static function productViewsPerMinute(): int
    {
        return self::resolve('product_views_per_minute', 120, 180, 300, 1000);
    }

    public static function reviewWritePerMinute(): int
    {
        return self::resolve('review_write_per_minute', 10, 20, 40, 120);
    }

    public static function stockAlertPerMinute(): int
    {
        return self::resolve('stock_alert_per_minute', 30, 60, 120, 300);
    }

    public static function paymentVerifyPerMinute(): int
    {
        return self::resolve('payment_verify_per_minute', 30, 60, 90, 200);
    }

    public static function paymentRetryPerMinute(): int
    {
        return self::resolve('payment_retry_per_minute', 10, 20, 40, 120);
    }

    public static function orderReturnPerMinute(): int
    {
        return self::resolve('order_return_per_minute', 10, 20, 40, 120);
    }

    public static function otpSendPerMinute(): int
    {
        return self::resolve('otp_send_per_minute', 5, 10, 20, 120);
    }

    public static function otpVerifyPerMinute(): int
    {
        return self::resolve('otp_verify_per_minute', 10, 20, 40, 120);
    }

    private static function resolve(
        string $configKey,
        int $production,
        int $staging,
        int $local,
        int $testing,
    ): int {
        $override = (int) config('throttling.'.$configKey, 0);
        if ($override > 0) {
            return $override;
        }

        return match (true) {
            app()->environment('testing') => $testing,
            app()->environment('production') => $production,
            app()->environment('staging') => $staging,
            default => $local,
        };
    }
}
