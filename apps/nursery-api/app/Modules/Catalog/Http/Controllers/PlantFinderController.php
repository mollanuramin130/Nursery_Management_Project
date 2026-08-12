<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\PlantFinderService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlantFinderController extends Controller
{
    public function __construct(private readonly PlantFinderService $finder) {}

    public function options(): JsonResponse
    {
        return ApiResponse::success(
            $this->finder->options(),
            'Plant finder options retrieved',
        );
    }

    public function match(Request $request): JsonResponse
    {
        $result = $this->finder->match($request->all());
        $meta = $result['meta'] ?? [];
        unset($result['meta']);

        $message = empty($result['results'])
            ? 'We could not find an exact match'
            : 'Plant recommendations generated';

        return ApiResponse::success($result, $message, 200, $meta);
    }
}
