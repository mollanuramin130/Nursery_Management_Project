<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Services\OfferService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offers) {}

    public function index(Request $request): JsonResponse
    {
        $feed = $this->offers->feed(
            $request->query('product_type'),
            (int) $request->query('per_page', 12),
        );
        $pagination = $feed['pagination'];
        unset($feed['pagination']);

        return ApiResponse::success($feed, 'Offers retrieved successfully', 200, [
            'pagination' => $pagination,
        ]);
    }
}
