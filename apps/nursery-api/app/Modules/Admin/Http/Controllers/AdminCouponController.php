<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponRedemption;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $status = $request->query('status');
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $paginator = Coupon::query()
            ->withCount('redemptions')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($paginator->items())
            ->map(fn (Coupon $c) => $this->summary($c))
            ->values()
            ->all();

        return ApiResponse::success($rows, 'Coupons retrieved successfully', 200, [
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
        $coupon = Coupon::query()->find($id);
        if (! $coupon) {
            throw new NotFoundHttpException('Coupon not found');
        }

        return ApiResponse::success($this->detail($coupon), 'Coupon retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:160'],
            'discount_type' => ['required', 'string', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_public' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        $coupon = Coupon::query()->create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'is_public' => $validated['is_public'] ?? true,
            'stackable' => $validated['stackable'] ?? false,
        ]);

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('coupon.create', 'coupon', $coupon->id, null, $coupon->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $coupon->id,
            'code' => $coupon->code,
            'status' => $coupon->status,
        ], 'Coupon created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::query()->find($id);
        if (! $coupon) {
            throw new NotFoundHttpException('Coupon not found');
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:coupons,code,'.$id],
            'name' => ['sometimes', 'string', 'max:160'],
            'discount_type' => ['sometimes', 'string', 'in:percent,fixed'],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_public' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        $before = $coupon->toArray();
        $coupon->fill($validated)->save();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('coupon.update', 'coupon', $id, $before, $coupon->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $coupon->id,
            'code' => $coupon->code,
            'status' => $coupon->status,
        ], 'Coupon updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::query()->find($id);
        if (! $coupon) {
            throw new NotFoundHttpException('Coupon not found');
        }

        $before = $coupon->toArray();
        $coupon->delete();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('coupon.delete', 'coupon', $id, $before, null, $user->id);

        return ApiResponse::success(null, 'Coupon deleted successfully');
    }

    private function summary(Coupon $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'discount_type' => $c->discount_type,
            'discount_value' => (float) $c->discount_value,
            'min_order_amount' => $c->min_order_amount !== null ? (float) $c->min_order_amount : null,
            'max_discount_amount' => $c->max_discount_amount !== null ? (float) $c->max_discount_amount : null,
            'usage_limit_total' => $c->usage_limit_total,
            'usage_limit_per_user' => $c->usage_limit_per_user,
            'used_count' => (int) ($c->redemptions_count ?? CouponRedemption::query()->where('coupon_id', $c->id)->count()),
            'status' => $c->status,
            'is_public' => (bool) $c->is_public,
            'stackable' => (bool) $c->stackable,
            'campaign_id' => $c->campaign_id,
            'starts_at' => optional($c->starts_at)?->toIso8601String(),
            'ends_at' => optional($c->ends_at)?->toIso8601String(),
        ];
    }

    private function detail(Coupon $c): array
    {
        return $this->summary($c);
    }
}
