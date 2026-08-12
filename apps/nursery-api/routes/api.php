<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/*
|--------------------------------------------------------------------------
|
| Module route files are loaded under the v1 prefix. Keep contracts aligned
| with docs/PROJECT_DEVELOPMENT_GUIDE.md §9.
|
*/

Route::prefix('v1')->group(function () {
    require app_path('Modules/System/Routes/api.php');
    require app_path('Modules/Auth/Routes/api.php');
    require app_path('Modules/Catalog/Routes/api.php');
    require app_path('Modules/Campaign/Routes/api.php');
    require app_path('Modules/Wishlist/Routes/api.php');
    require app_path('Modules/Cart/Routes/api.php');
    require app_path('Modules/Inventory/Routes/api.php');
    require app_path('Modules/Customer/Routes/api.php');
    require app_path('Modules/Delivery/Routes/api.php');
    require app_path('Modules/Order/Routes/api.php');
    require app_path('Modules/Payment/Routes/api.php');
    require app_path('Modules/Review/Routes/api.php');
    require app_path('Modules/Loyalty/Routes/api.php');
    require app_path('Modules/Subscription/Routes/api.php');
    require app_path('Modules/Notification/Routes/api.php');
    require app_path('Modules/Promotion/Routes/api.php');
    require app_path('Modules/Admin/Routes/api.php');
});
