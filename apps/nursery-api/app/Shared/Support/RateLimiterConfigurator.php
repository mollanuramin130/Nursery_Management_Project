<?php

namespace App\Shared\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Named limiters used by throttleApi() and route middleware `throttle:{name}`.
 * Health + Razorpay webhooks skip the global API bucket (they have their own).
 */
final class RateLimiterConfigurator
{
    public static function register(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if (self::skipsGlobalApiLimit($request)) {
                return Limit::none();
            }

            return Limit::perMinute(max(1, ThrottleLimits::apiPerMinute()))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(max(1, ThrottleLimits::loginPerMinute()))
                ->by($request->ip().'|'.$email);
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::registerPerMinute()))
                ->by($request->ip());
        });

        RateLimiter::for('password', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::passwordPerMinute()))
                ->by($request->ip());
        });

        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::refreshPerMinute()))
                ->by($request->ip());
        });

        RateLimiter::for('checkout-preview', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::checkoutPreviewPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('order-write', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::orderWritePerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('payment-write', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::paymentWritePerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('payment-verify', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::paymentVerifyPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('payment-retry', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::paymentRetryPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('order-return', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::orderReturnPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::webhookPerMinute()))
                ->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::searchPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::healthPerMinute()))
                ->by($request->ip());
        });

        RateLimiter::for('plant-finder', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::plantFinderPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('product-views', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::productViewsPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('recently-viewed', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::searchPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('review-write', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::reviewWritePerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('stock-alert', function (Request $request) {
            return Limit::perMinute(max(1, ThrottleLimits::stockAlertPerMinute()))
                ->by(self::identity($request));
        });

        RateLimiter::for('otp-send', function (Request $request) {
            $mobile = strtolower(trim((string) $request->input('mobile', '')));

            return Limit::perMinute(max(1, ThrottleLimits::otpSendPerMinute()))
                ->by($request->ip().'|'.$mobile);
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $mobile = strtolower(trim((string) $request->input('mobile', '')));

            return Limit::perMinute(max(1, ThrottleLimits::otpVerifyPerMinute()))
                ->by($request->ip().'|'.$mobile);
        });
    }

    public static function skipsGlobalApiLimit(Request $request): bool
    {
        return $request->is(
            'api/v1/health',
            'api/v1/health/*',
            'api/v1/payments/webhooks/*',
        );
    }

    private static function identity(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());
    }
}
