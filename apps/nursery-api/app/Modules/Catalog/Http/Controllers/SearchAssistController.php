<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\SearchAssistService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAssistController extends Controller
{
    public function __construct(private readonly SearchAssistService $assist) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return ApiResponse::success([
                'query' => '',
                'categories' => [],
                'filters' => [],
                'popular_products' => [],
                'popular_searches' => [],
                'plant_finder' => [
                    'available' => true,
                    'path' => '/find-your-plant',
                    'hint' => 'Use Find Your Plant for guided matching.',
                ],
            ], 'Search assist retrieved successfully');
        }

        return ApiResponse::success(
            $this->assist->assist($q),
            'Search assist retrieved successfully',
        );
    }
}
