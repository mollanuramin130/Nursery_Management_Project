<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Notification\Services\NotificationService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->notifications->list(
            $user,
            filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            $result['data'],
            'Notifications retrieved successfully',
            200,
            $result['meta'],
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->unreadCount($user),
            'Unread count retrieved',
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->markRead($user, $id),
            'Notification marked as read',
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->markAllRead($user),
            'All notifications marked as read',
        );
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:web,android,ios'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->registerDevice($user, $validated),
            'Device registered',
        );
    }

    public function deactivateDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'string', 'max:100'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->notifications->deactivateDevice($user, $validated),
            'Device deactivated',
        );
    }
}
