<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Brand;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Brand::query()->active()->orderBy('name')->get()->map(fn (Brand $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo_url' => $b->logo_url,
        ])->values()->all();

        return ApiResponse::success($data, 'Brands retrieved successfully');
    }
}
