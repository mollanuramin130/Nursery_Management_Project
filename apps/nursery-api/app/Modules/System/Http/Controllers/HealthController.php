<?php

namespace App\Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $db = $this->databaseStatus();

        $payload = [
            'app' => 'healthy',
            'database' => $db,
            'time' => now()->toIso8601String(),
        ];

        if ($db !== 'healthy') {
            return ApiResponse::error('Degraded', 503, 'SERVICE_UNAVAILABLE', null, $payload);
        }

        return ApiResponse::success($payload, 'OK');
    }

    /** Process is up — no dependency checks (for liveness probes). */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /** Ready to serve traffic — database reachable; cache best-effort. */
    public function ready(): JsonResponse
    {
        $db = $this->databaseStatus();
        $cache = 'unknown';
        try {
            Cache::put('health:ready', '1', 5);
            $cache = Cache::get('health:ready') === '1' ? 'healthy' : 'unhealthy';
        } catch (Throwable) {
            $cache = 'unhealthy';
        }

        $ok = $db === 'healthy';
        $payload = [
            'status' => $ok ? 'ok' : 'degraded',
            'database' => $db,
            'cache' => $cache,
        ];

        if (! $ok) {
            return response()->json($payload, 503);
        }

        return response()->json($payload);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'healthy';
        } catch (Throwable) {
            return 'unhealthy';
        }
    }
}
