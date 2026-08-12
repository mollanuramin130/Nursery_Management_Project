<?php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApiRootController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'name' => 'GreenLeaf Nursery API',
            'version' => 'v1',
            'docs' => [
                'openapi' => 'openapi.yaml (repo root of nursery-api)',
                'guide' => 'docs/PROJECT_DEVELOPMENT_GUIDE.md §9',
            ],
            'try' => [
                'config' => url('/api/v1/app/config'),
                'login' => url('/api/v1/auth/login'),
            ],
            'endpoints' => [
                'GET /api/v1/app/config',
                'POST /api/v1/auth/register',
                'POST /api/v1/auth/login',
                'POST /api/v1/auth/refresh',
                'POST /api/v1/auth/logout',
                'POST /api/v1/auth/forgot-password',
                'POST /api/v1/auth/reset-password',
                'GET /api/v1/auth/me',
                'GET /api/v1/home',
                'GET /api/v1/categories',
                'GET /api/v1/brands',
                'GET /api/v1/products',
                'GET /api/v1/products/{idOrSlug}',
                'GET /api/v1/plants',
                'GET /api/v1/plants/{idOrSlug}/care',
                'GET /api/v1/search',
                'GET /api/v1/banners',
                'GET /api/v1/campaigns',
                'GET /api/v1/wishlist',
                'GET /api/v1/cart',
                'POST /api/v1/cart/items',
                'GET /api/v1/admin/inventory',
                'POST /api/v1/admin/inventory/adjust',
                'GET /api/v1/customer/addresses',
                'GET /api/v1/shipping/methods',
                'POST /api/v1/checkout/preview',
                'POST /api/v1/orders',
                'GET /api/v1/orders',
                'POST /api/v1/payments/initiate',
                'POST /api/v1/payments/verify',
                'GET /api/v1/coupons',
                'GET /api/v1/products/{id}/reviews',
                'GET /api/v1/notifications',
                'POST /api/v1/devices/register',
                'POST /api/v1/orders/{id}/returns',
            ],
        ], 'GreenLeaf Nursery API v1');
    }
}
