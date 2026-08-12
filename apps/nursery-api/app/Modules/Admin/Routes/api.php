<?php

use App\Modules\Admin\Http\Controllers\AdminAnalyticsController;
use App\Modules\Admin\Http\Controllers\AdminAuditController;
use App\Modules\Admin\Http\Controllers\AdminBannerController;
use App\Modules\Admin\Http\Controllers\AdminCampaignController;
use App\Modules\Admin\Http\Controllers\AdminCategoryController;
use App\Modules\Admin\Http\Controllers\AdminCouponController;
use App\Modules\Admin\Http\Controllers\AdminCrmController;
use App\Modules\Admin\Http\Controllers\AdminFulfillmentController;
use App\Modules\Admin\Http\Controllers\AdminOrderController;
use App\Modules\Admin\Http\Controllers\AdminProductController;
use App\Modules\Admin\Http\Controllers\AdminRefundController;
use App\Modules\Admin\Http\Controllers\AdminReturnController;
use App\Modules\Admin\Http\Controllers\AdminReportController;
use App\Modules\Admin\Http\Controllers\AdminLoyaltyController;
use App\Modules\Admin\Http\Controllers\AdminNotificationController;
use App\Modules\Admin\Http\Controllers\AdminReviewController;
use App\Modules\Admin\Http\Controllers\AdminSubscriptionController;
use App\Modules\Admin\Http\Controllers\AdminSettingsController;
use App\Modules\Admin\Http\Controllers\AdminStockAlertController;
use App\Modules\Admin\Http\Controllers\AdminSupplierController;
use App\Modules\Admin\Http\Controllers\AdminUserController;
use App\Modules\Admin\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])
        ->middleware('permission:reports.view');

    Route::get('products', [AdminProductController::class, 'index'])
        ->middleware('permission:products.read');
    Route::post('products', [AdminProductController::class, 'store'])
        ->middleware('permission:products.write');
    Route::get('products/{id}', [AdminProductController::class, 'show'])
        ->middleware('permission:products.read');
    Route::put('products/{id}', [AdminProductController::class, 'update'])
        ->middleware('permission:products.write');
    Route::delete('products/{id}', [AdminProductController::class, 'destroy'])
        ->middleware('permission:products.write');
    Route::post('products/{id}/images', [AdminProductController::class, 'images'])
        ->middleware('permission:products.write');

    Route::get('categories', [AdminCategoryController::class, 'index'])
        ->middleware('permission:products.write');
    Route::post('categories', [AdminCategoryController::class, 'store'])
        ->middleware('permission:products.write');
    Route::put('categories/{id}', [AdminCategoryController::class, 'update'])
        ->middleware('permission:products.write');
    Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy'])
        ->middleware('permission:products.write');

    Route::get('orders', [AdminOrderController::class, 'index'])
        ->middleware('permission:orders.view');
    Route::get('orders/{id}', [AdminOrderController::class, 'show'])
        ->middleware('permission:orders.view');
    Route::post('orders/{id}/status', [AdminOrderController::class, 'status'])
        ->middleware('permission:orders.update_status');

    Route::get('fulfillment', [AdminFulfillmentController::class, 'dashboard'])
        ->middleware('permission:fulfillment.view');
    Route::get('fulfillment/exceptions', [AdminFulfillmentController::class, 'exceptions'])
        ->middleware('permission:fulfillment.view');
    Route::get('fulfillment/shipments', [AdminFulfillmentController::class, 'shipments'])
        ->middleware('permission:fulfillment.view');
    Route::get('fulfillment/shipments/{id}', [AdminFulfillmentController::class, 'showShipment'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.view');
    Route::post('fulfillment/shipments/{id}/tracking', [AdminFulfillmentController::class, 'addTracking'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::get('fulfillment/queue/{queue}', [AdminFulfillmentController::class, 'queue'])
        ->whereIn('queue', ['picking', 'packing', 'ready_to_ship', 'in_transit', 'exceptions'])
        ->middleware('permission:fulfillment.view');
    Route::get('fulfillment/orders/{id}', [AdminFulfillmentController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.view');
    Route::post('fulfillment/orders/{id}/pick/start', [AdminFulfillmentController::class, 'startPicking'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.pick');
    Route::post('fulfillment/orders/{id}/pick', [AdminFulfillmentController::class, 'updatePick'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.pick');
    Route::post('fulfillment/orders/{id}/pick/exception', [AdminFulfillmentController::class, 'pickException'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.pick');
    Route::post('fulfillment/orders/{id}/pick/complete', [AdminFulfillmentController::class, 'completePicking'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.pick');
    Route::post('fulfillment/orders/{id}/pack', [AdminFulfillmentController::class, 'pack'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.pack');
    Route::post('fulfillment/orders/{id}/ship', [AdminFulfillmentController::class, 'createShipment'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::post('fulfillment/orders/{id}/out-for-delivery', [AdminFulfillmentController::class, 'outForDelivery'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::post('fulfillment/orders/{id}/deliver', [AdminFulfillmentController::class, 'deliver'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::post('fulfillment/orders/{id}/fail-delivery', [AdminFulfillmentController::class, 'failDelivery'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::post('fulfillment/orders/{id}/retry-delivery', [AdminFulfillmentController::class, 'retryDelivery'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.ship');
    Route::post('fulfillment/orders/{id}/exceptions/resolve', [AdminFulfillmentController::class, 'resolveException'])
        ->whereNumber('id')
        ->middleware('permission:fulfillment.manage');

    Route::post('refunds', [AdminRefundController::class, 'store'])
        ->middleware('permission:payments.refund');
    Route::get('refunds', [AdminRefundController::class, 'index'])
        ->middleware('permission:payments.refund');

    Route::get('returns/dashboard', [AdminReturnController::class, 'dashboard'])
        ->middleware('permission:returns.view');
    Route::get('returns', [AdminReturnController::class, 'index'])
        ->middleware('permission:returns.view');
    Route::get('returns/{id}', [AdminReturnController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:returns.view');
    Route::post('returns/{id}/approve', [AdminReturnController::class, 'approve'])
        ->whereNumber('id')
        ->middleware('permission:returns.approve');
    Route::post('returns/{id}/reject', [AdminReturnController::class, 'reject'])
        ->whereNumber('id')
        ->middleware('permission:returns.reject');
    Route::post('returns/{id}/schedule-pickup', [AdminReturnController::class, 'schedulePickup'])
        ->whereNumber('id')
        ->middleware('permission:returns.manage');
    Route::post('returns/{id}/picked-up', [AdminReturnController::class, 'markPickedUp'])
        ->whereNumber('id')
        ->middleware('permission:returns.manage');
    Route::post('returns/{id}/receive', [AdminReturnController::class, 'receive'])
        ->whereNumber('id')
        ->middleware('permission:returns.inspect');
    Route::post('returns/{id}/inspect', [AdminReturnController::class, 'inspect'])
        ->whereNumber('id')
        ->middleware('permission:returns.inspect');
    Route::post('returns/{id}/refund', [AdminReturnController::class, 'refund'])
        ->whereNumber('id')
        ->middleware('permission:payments.refund');

    Route::get('campaigns', [AdminCampaignController::class, 'index'])
        ->middleware('permission:campaigns.manage');
    Route::post('campaigns', [AdminCampaignController::class, 'store'])
        ->middleware('permission:campaigns.manage');
    Route::get('campaigns/{id}', [AdminCampaignController::class, 'show'])
        ->middleware('permission:campaigns.manage');
    Route::put('campaigns/{id}', [AdminCampaignController::class, 'update'])
        ->middleware('permission:campaigns.manage');
    Route::delete('campaigns/{id}', [AdminCampaignController::class, 'destroy'])
        ->middleware('permission:campaigns.manage');

    // Phase 17 CRM / Marketing Automation
    Route::get('marketing/dashboard', [AdminCrmController::class, 'marketingDashboard'])
        ->middleware('permission:marketing.view');
    Route::get('customers/{id}/360', [AdminCrmController::class, 'customer360'])
        ->whereNumber('id')
        ->middleware('permission:customers.view,users.manage');
    Route::get('customer-segments', [AdminCrmController::class, 'segments'])
        ->middleware('permission:customers.segment');
    Route::post('customer-segments', [AdminCrmController::class, 'segmentStore'])
        ->middleware('permission:customers.segment');
    Route::get('customer-segments/{id}', [AdminCrmController::class, 'segmentShow'])
        ->whereNumber('id')
        ->middleware('permission:customers.segment');
    Route::put('customer-segments/{id}', [AdminCrmController::class, 'segmentUpdate'])
        ->whereNumber('id')
        ->middleware('permission:customers.segment');
    Route::post('customer-segments/{id}/archive', [AdminCrmController::class, 'segmentArchive'])
        ->whereNumber('id')
        ->middleware('permission:customers.segment');
    Route::post('customer-segments/{id}/duplicate', [AdminCrmController::class, 'segmentDuplicate'])
        ->whereNumber('id')
        ->middleware('permission:customers.segment');
    Route::get('customer-segments/{id}/members', [AdminCrmController::class, 'segmentMembers'])
        ->whereNumber('id')
        ->middleware('permission:customers.segment');
    Route::get('marketing/automations', [AdminCrmController::class, 'automations'])
        ->middleware('permission:marketing.view');
    Route::post('marketing/automations', [AdminCrmController::class, 'automationStore'])
        ->middleware('permission:marketing.manage');
    Route::get('marketing/automations/{id}', [AdminCrmController::class, 'automationShow'])
        ->whereNumber('id')
        ->middleware('permission:marketing.view');
    Route::put('marketing/automations/{id}', [AdminCrmController::class, 'automationUpdate'])
        ->whereNumber('id')
        ->middleware('permission:marketing.manage');
    Route::post('marketing/automations/{id}/activate', [AdminCrmController::class, 'automationActivate'])
        ->whereNumber('id')
        ->middleware('permission:marketing.launch');
    Route::post('marketing/automations/{id}/pause', [AdminCrmController::class, 'automationPause'])
        ->whereNumber('id')
        ->middleware('permission:marketing.manage');
    Route::post('marketing/automations/{id}/dispatch', [AdminCrmController::class, 'automationDispatch'])
        ->whereNumber('id')
        ->middleware('permission:marketing.launch');

    Route::get('banners', [AdminBannerController::class, 'index'])
        ->middleware('permission:campaigns.manage');
    Route::post('banners', [AdminBannerController::class, 'store'])
        ->middleware('permission:campaigns.manage');
    Route::get('banners/{id}', [AdminBannerController::class, 'show'])
        ->middleware('permission:campaigns.manage');
    Route::put('banners/{id}', [AdminBannerController::class, 'update'])
        ->middleware('permission:campaigns.manage');
    Route::delete('banners/{id}', [AdminBannerController::class, 'destroy'])
        ->middleware('permission:campaigns.manage');

    Route::get('coupons', [AdminCouponController::class, 'index'])
        ->middleware('permission:campaigns.manage');
    Route::post('coupons', [AdminCouponController::class, 'store'])
        ->middleware('permission:campaigns.manage');
    Route::get('coupons/{id}', [AdminCouponController::class, 'show'])
        ->middleware('permission:campaigns.manage');
    Route::put('coupons/{id}', [AdminCouponController::class, 'update'])
        ->middleware('permission:campaigns.manage');
    Route::delete('coupons/{id}', [AdminCouponController::class, 'destroy'])
        ->middleware('permission:campaigns.manage');

    Route::get('reviews/dashboard', [AdminReviewController::class, 'dashboard'])
        ->middleware('permission:reviews.view');
    Route::get('reviews', [AdminReviewController::class, 'index'])
        ->middleware('permission:reviews.view');
    Route::get('reviews/{id}', [AdminReviewController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:reviews.view');
    Route::post('reviews/{id}/moderate', [AdminReviewController::class, 'moderate'])
        ->whereNumber('id')
        ->middleware('permission:reviews.moderate');

    Route::get('loyalty/dashboard', [AdminLoyaltyController::class, 'dashboard'])
        ->middleware('permission:loyalty.view');
    Route::get('loyalty/accounts', [AdminLoyaltyController::class, 'accounts'])
        ->middleware('permission:loyalty.view');
    Route::get('loyalty/transactions', [AdminLoyaltyController::class, 'transactions'])
        ->middleware('permission:loyalty.view');
    Route::post('loyalty/adjust', [AdminLoyaltyController::class, 'adjust'])
        ->middleware('permission:loyalty.adjust');

    Route::get('subscriptions/dashboard', [AdminSubscriptionController::class, 'dashboard'])
        ->middleware('permission:subscriptions.view');
    Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])
        ->middleware('permission:subscriptions.view');
    Route::get('subscriptions/{id}', [AdminSubscriptionController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:subscriptions.view');
    Route::post('subscriptions/{id}/pause', [AdminSubscriptionController::class, 'pause'])
        ->whereNumber('id')
        ->middleware('permission:subscriptions.manage');
    Route::post('subscriptions/{id}/resume', [AdminSubscriptionController::class, 'resume'])
        ->whereNumber('id')
        ->middleware('permission:subscriptions.manage');
    Route::post('subscriptions/{id}/cancel', [AdminSubscriptionController::class, 'cancel'])
        ->whereNumber('id')
        ->middleware('permission:subscriptions.manage');
    Route::get('subscription-plans', [AdminSubscriptionController::class, 'plans'])
        ->middleware('permission:subscriptions.view');
    Route::post('subscription-plans', [AdminSubscriptionController::class, 'storePlan'])
        ->middleware('permission:subscriptions.manage');
    Route::put('subscription-plans/{id}', [AdminSubscriptionController::class, 'updatePlan'])
        ->whereNumber('id')
        ->middleware('permission:subscriptions.manage');

    Route::get('notifications/dashboard', [AdminNotificationController::class, 'dashboard'])
        ->middleware('permission:notifications.view');
    Route::get('notifications', [AdminNotificationController::class, 'index'])
        ->middleware('permission:notifications.view');
    Route::post('notifications/send', [AdminNotificationController::class, 'send'])
        ->middleware('permission:notifications.send');
    Route::get('notifications/{id}', [AdminNotificationController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:notifications.view');
    Route::get('notification-templates', [AdminNotificationController::class, 'templates'])
        ->middleware('permission:notifications.view');
    Route::put('notification-templates/{id}', [AdminNotificationController::class, 'updateTemplate'])
        ->whereNumber('id')
        ->middleware('permission:notifications.manage_templates');

    Route::get('users', [AdminUserController::class, 'index'])
        ->middleware('permission:users.manage');
    Route::post('users', [AdminUserController::class, 'store'])
        ->middleware('permission:users.manage');
    Route::get('users/{id}', [AdminUserController::class, 'show'])
        ->middleware('permission:users.manage');
    Route::put('users/{id}', [AdminUserController::class, 'update'])
        ->middleware('permission:users.manage');
    Route::delete('users/{id}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:users.manage');

    Route::get('suppliers', [AdminSupplierController::class, 'index'])
        ->middleware('permission:inventory.adjust,suppliers.view,suppliers.manage');
    Route::get('suppliers/{id}', [AdminSupplierController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,suppliers.view,suppliers.manage');
    Route::post('suppliers', [AdminSupplierController::class, 'store'])
        ->middleware('permission:inventory.adjust,suppliers.manage');
    Route::put('suppliers/{id}', [AdminSupplierController::class, 'update'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,suppliers.manage');
    Route::delete('suppliers/{id}', [AdminSupplierController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,suppliers.manage');
    Route::post('suppliers/{id}/products', [AdminSupplierController::class, 'upsertProduct'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,suppliers.manage');
    Route::get('purchase-orders', [AdminSupplierController::class, 'purchaseOrders'])
        ->middleware('permission:inventory.adjust,purchase_orders.view,purchase_orders.manage');
    Route::post('purchase-orders', [AdminSupplierController::class, 'storePurchaseOrder'])
        ->middleware('permission:inventory.adjust,purchase_orders.manage');
    Route::get('purchase-orders/{id}', [AdminSupplierController::class, 'showPurchaseOrder'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,purchase_orders.view,purchase_orders.manage');
    Route::post('purchase-orders/{id}/approve', [AdminSupplierController::class, 'approvePurchaseOrder'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,purchase_orders.manage');
    Route::post('purchase-orders/{id}/cancel', [AdminSupplierController::class, 'cancelPurchaseOrder'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,purchase_orders.manage');
    Route::post('purchase-orders/{id}/receive', [AdminSupplierController::class, 'receivePurchaseOrder'])
        ->whereNumber('id')
        ->middleware('permission:inventory.adjust,purchase_orders.manage,inventory.receive');

    Route::get('stock-alerts', [AdminStockAlertController::class, 'index'])
        ->middleware('permission:inventory.view');

    Route::get('reports/{type}', [AdminReportController::class, 'show'])
        ->middleware('permission:reports.view');

    Route::prefix('analytics')->middleware('permission:reports.view')->group(function () {
        Route::get('overview', [AdminAnalyticsController::class, 'overview']);
        Route::get('sales', [AdminAnalyticsController::class, 'sales']);
        Route::get('orders', [AdminAnalyticsController::class, 'orders']);
        Route::get('products', [AdminAnalyticsController::class, 'products']);
        Route::get('categories', [AdminAnalyticsController::class, 'categories']);
        Route::get('customers', [AdminAnalyticsController::class, 'customers']);
        Route::get('inventory', [AdminAnalyticsController::class, 'inventory']);
        Route::get('campaigns', [AdminAnalyticsController::class, 'campaigns']);
        Route::get('coupons', [AdminAnalyticsController::class, 'coupons']);
        Route::get('returns', [AdminAnalyticsController::class, 'returns']);
        Route::get('seasonal', [AdminAnalyticsController::class, 'seasonal']);
        Route::get('payments', [AdminAnalyticsController::class, 'payments']);
        Route::get('plants', [AdminAnalyticsController::class, 'plants']);
        Route::get('reviews', [AdminAnalyticsController::class, 'reviewsAnalytics']);
        Route::get('search', [AdminAnalyticsController::class, 'search']);
        Route::get('cohorts', [AdminAnalyticsController::class, 'cohorts']);
        Route::get('attention', [AdminAnalyticsController::class, 'attention']);
        Route::get('export', [AdminAnalyticsController::class, 'export'])
            ->middleware('permission:reports.export');
    });

    Route::get('settings', [AdminSettingsController::class, 'index'])
        ->middleware('permission:users.manage');
    Route::put('settings', [AdminSettingsController::class, 'update'])
        ->middleware('permission:users.manage');

    Route::get('audit-logs', [AdminAuditController::class, 'index'])
        ->middleware('permission:users.manage');
});
