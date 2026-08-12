<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Services\CampaignService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->campaigns->list(
            $request->query('status'),
            $request->query('season'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success($result['data'], 'Campaigns retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 6);

        return ApiResponse::success(
            $this->campaigns->featured($limit),
            'Featured campaigns retrieved successfully',
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $detail = $this->campaigns->detail($slug, (int) $request->query('per_page', 20));
        $pagination = $detail['pagination'];
        unset($detail['pagination']);

        return ApiResponse::success($detail, 'Campaign retrieved successfully', 200, [
            'pagination' => $pagination,
        ]);
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $result = $this->campaigns->products($slug, (int) $request->query('per_page', 20));

        return ApiResponse::success($result['data'], 'Campaign products retrieved successfully', 200, [
            'pagination' => $result['pagination'],
            'campaign' => $result['campaign'],
        ]);
    }
}
