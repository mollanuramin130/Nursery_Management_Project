<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('audit_logs')->orderByDesc('id');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }
        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', (int) $request->query('actor_user_id'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));
        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get()->map(fn ($r) => [
            'id' => $r->id,
            'actor_user_id' => $r->actor_user_id,
            'action' => $r->action,
            'entity_type' => $r->entity_type,
            'entity_id' => $r->entity_id,
            'ip' => $r->ip,
            'request_id' => $r->request_id,
            'created_at' => $r->created_at,
        ])->values()->all();

        return ApiResponse::success($rows, 'Audit logs retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }
}
