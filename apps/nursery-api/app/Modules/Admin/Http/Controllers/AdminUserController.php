<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $role = $request->query('role');
        $status = $request->query('status');
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = User::query()
            ->with('roles')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role, fn ($query) => $query->whereHas('roles', fn ($r) => $r->where('slug', $role)))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);

        $users = collect($paginator->items())->map(fn (User $u) => $this->summary($u))->values()->all();

        return ApiResponse::success($users, 'Users retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->with('roles')->find($id);
        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $orderStats = \App\Modules\Order\Models\Order::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('CANCELLED','PAYMENT_FAILED') THEN grand_total ELSE 0 END) as total_spent")
            ->first();

        $recentOrders = \App\Modules\Order\Models\Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'order_number', 'status', 'grand_total', 'placed_at', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'grand_total' => (float) $o->grand_total,
                'placed_at' => optional($o->placed_at ?? $o->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success(array_merge($this->summary($user), [
            'created_at' => optional($user->created_at)?->toIso8601String(),
            'total_orders' => (int) ($orderStats->total_orders ?? 0),
            'total_spent' => (float) ($orderStats->total_spent ?? 0),
            'recent_orders' => $recentOrders,
        ]), 'User retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', 'string', 'in:active,inactive,blocked'],
            'role_slugs' => ['required', 'array', 'min:1'],
            'role_slugs.*' => ['string', 'exists:roles,slug'],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);
        $user->status = $validated['status'] ?? 'active';
        $user->save();

        $roleIds = Role::query()->whereIn('slug', $validated['role_slugs'])->pluck('id')->all();
        $user->roles()->sync($roleIds);

        /** @var User $actor */
        $actor = $request->user();
        AuditLogger::log('user.create', 'user', $user->id, null, [
            'email' => $user->email,
            'roles' => $validated['role_slugs'],
        ], $actor->id);

        return ApiResponse::success([
            'id' => $user->id,
            'email' => $user->email,
            'roles' => $validated['role_slugs'],
        ], 'User created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:190', 'unique:users,email,'.$id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['sometimes', 'string', 'in:active,inactive,blocked'],
            'role_slugs' => ['sometimes', 'array', 'min:1'],
            'role_slugs.*' => ['string', 'exists:roles,slug'],
        ]);

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roleSlugs(),
        ];

        $user->fill(collect($validated)->only(['name', 'email', 'phone'])->all());
        if (array_key_exists('status', $validated)) {
            $user->status = $validated['status'];
        }
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        if (array_key_exists('role_slugs', $validated)) {
            $roleIds = Role::query()->whereIn('slug', $validated['role_slugs'])->pluck('id')->all();
            $user->roles()->sync($roleIds);
        }

        /** @var User $actor */
        $actor = $request->user();
        AuditLogger::log('user.update', 'user', $user->id, $before, [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roleSlugs(),
        ], $actor->id);

        return ApiResponse::success([
            'id' => $user->id,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->roleSlugs(),
        ], 'User updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        /** @var User $actor */
        $actor = $request->user();
        if ($actor->id === $user->id) {
            return ApiResponse::error('Cannot delete your own account', 409, 'CONFLICT');
        }

        $before = ['email' => $user->email, 'roles' => $user->roleSlugs()];
        $user->delete();
        AuditLogger::log('user.delete', 'user', $id, $before, null, $actor->id);

        return ApiResponse::success(null, 'User deleted successfully');
    }

    private function summary(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'status' => $u->status,
            'roles' => $u->roles->pluck('slug')->values()->all(),
            'last_login_at' => optional($u->last_login_at)?->toIso8601String(),
            'created_at' => optional($u->created_at)?->toIso8601String(),
        ];
    }
}
