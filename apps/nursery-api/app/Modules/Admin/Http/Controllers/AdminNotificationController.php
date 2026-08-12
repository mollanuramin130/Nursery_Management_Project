<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Notification\Services\NotificationService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->notifications->dashboard(), 'Notification dashboard');
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->notifications->adminList([
            'type' => $request->query('type'),
            'category' => $request->query('category'),
            'user_id' => $request->query('user_id'),
            'is_read' => $request->query('is_read'),
            'q' => $request->query('q'),
        ], (int) $request->query('per_page', 30));

        return ApiResponse::success($result['data'], 'Notifications retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->notifications->adminShow($id), 'Notification retrieved');
    }

    public function templates(): JsonResponse
    {
        return ApiResponse::success($this->notifications->listTemplates(), 'Templates retrieved');
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,draft,archived'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->updateTemplate($id, $payload, $user),
            'Template updated',
        );
    }

    public function send(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:60'],
            'data' => ['nullable', 'array'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->adminSend($user, $payload),
            'Notification queued',
            201,
        );
    }
}
