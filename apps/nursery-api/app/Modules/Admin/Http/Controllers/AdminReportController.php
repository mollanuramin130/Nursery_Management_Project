<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\ReportService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function show(Request $request, string $type): JsonResponse
    {
        return ApiResponse::success(
            $this->reports->report($type, $request->query('from'), $request->query('to')),
            'Report retrieved successfully',
        );
    }
}
