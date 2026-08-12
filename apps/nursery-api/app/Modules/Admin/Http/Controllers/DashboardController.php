<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\DashboardService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboard->summary(),
            'Dashboard retrieved successfully',
        );
    }
}
