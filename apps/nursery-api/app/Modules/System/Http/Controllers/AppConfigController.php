<?php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success([
            'min_android_version' => env('APP_MIN_ANDROID_VERSION', '1.0.0'),
            'min_ios_version' => env('APP_MIN_IOS_VERSION', '1.0.0'),
            'force_update' => filter_var(env('APP_FORCE_UPDATE', false), FILTER_VALIDATE_BOOLEAN),
            'maintenance_mode' => (bool) app()->isDownForMaintenance(),
            'commerce' => [
                'free_delivery_threshold' => (float) env('FREE_DELIVERY_THRESHOLD', 999),
                'currency' => env('STORE_CURRENCY', 'INR'),
                'currency_symbol' => env('STORE_CURRENCY_SYMBOL', '₹'),
            ],
            'payments' => [
                'driver' => env('PAYMENT_DRIVER', 'razorpay'),
                'online_enabled' => (string) env('RAZORPAY_KEY', '') !== ''
                    || ! app()->environment('production'),
                'methods' => [
                    [
                        'id' => 'razorpay',
                        'name' => 'Online Payment',
                        'enabled' => (string) env('RAZORPAY_KEY', '') !== ''
                            || ! app()->environment('production'),
                    ],
                    [
                        'id' => 'cod',
                        'name' => 'Cash on Delivery',
                        'enabled' => filter_var(env('COD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                    ],
                ],
            ],
            'feature_flags' => [
                'wishlist_enabled' => true,
                'cod_enabled' => filter_var(env('COD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'online_payments_enabled' => (string) env('RAZORPAY_KEY', '') !== ''
                    || ! app()->environment('production'),
                'reviews_enabled' => true,
            ],
            'support' => [
                'email' => env('STORE_SUPPORT_EMAIL', 'support@example.com'),
                'phone' => env('STORE_SUPPORT_PHONE', ''),
            ],
        ], 'App config retrieved');
    }
}
