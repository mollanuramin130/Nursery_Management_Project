<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Models\Banner;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $placement = $request->query('placement', 'home');

        $data = Banner::query()->active()
            ->where('placement', $placement)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'image_url' => $b->image_url,
                'link_type' => $b->link_type,
                'link_value' => $b->link_value,
                'sort_order' => $b->sort_order,
            ])->values()->all();

        return ApiResponse::success($data, 'Banners retrieved successfully');
    }
}
