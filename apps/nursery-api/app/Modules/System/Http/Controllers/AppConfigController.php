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
            'min_android_version' => config('store.min_android_version'),
            'min_ios_version' => config('store.min_ios_version'),
            'force_update' => filter_var(config('store.force_update'), FILTER_VALIDATE_BOOLEAN),
            'maintenance_mode' => (bool) app()->isDownForMaintenance(),
            'commerce' => [
                'free_delivery_threshold' => (float) config('store.free_delivery_threshold'),
                'currency' => config('store.currency'),
                'currency_symbol' => config('store.currency_symbol'),
            ],
            'payments' => [
                'driver' => config('store.payment_driver'),
                'online_enabled' => (string) config('store.razorpay_key') !== ''
                    || ! app()->environment('production'),
                'methods' => [
                    [
                        'id' => 'razorpay',
                        'name' => 'Online Payment',
                        'enabled' => (string) config('store.razorpay_key') !== ''
                            || ! app()->environment('production'),
                    ],
                    [
                        'id' => 'cod',
                        'name' => 'Cash on Delivery',
                        'enabled' => filter_var(config('store.cod_enabled'), FILTER_VALIDATE_BOOLEAN),
                    ],
                ],
            ],
            'feature_flags' => [
                'wishlist_enabled' => true,
                'cod_enabled' => filter_var(config('store.cod_enabled'), FILTER_VALIDATE_BOOLEAN),
                'online_payments_enabled' => (string) config('store.razorpay_key') !== ''
                    || ! app()->environment('production'),
                'reviews_enabled' => true,
            ],
            'support' => [
                'email' => config('store.support_email'),
                'phone' => config('store.support_phone'),
            ],
        ], 'App config retrieved');
    }
}
