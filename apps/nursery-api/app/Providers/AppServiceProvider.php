<?php

namespace App\Providers;

use App\Modules\Campaign\Models\Banner;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\HomeService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $invalidateHome = static fn () => HomeService::forgetCache();

        Product::saved($invalidateHome);
        Product::deleted($invalidateHome);
        Category::saved($invalidateHome);
        Category::deleted($invalidateHome);
        Banner::saved($invalidateHome);
        Banner::deleted($invalidateHome);
        Campaign::saved($invalidateHome);
        Campaign::deleted($invalidateHome);

        // Password reset emails must open Customer Web (API has no HTML reset route).
        ResetPassword::createUrlUsing(function (object $user, string $token) {
            $base = rtrim((string) env('CUSTOMER_WEB_URL', 'http://127.0.0.1:3000'), '/');
            $email = method_exists($user, 'getEmailForPasswordReset')
                ? $user->getEmailForPasswordReset()
                : (string) ($user->email ?? '');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $email,
            ]);
        });
    }
}
