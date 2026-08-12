<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\HomeService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __construct(private readonly HomeService $home) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponse::success($this->home->feed(), 'Home retrieved successfully');
    }
}
