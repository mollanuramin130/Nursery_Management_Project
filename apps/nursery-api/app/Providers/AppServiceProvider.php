<?php

namespace App\Providers;

use App\Modules\Campaign\Models\Banner;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\HomeService;
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
    }
}
