<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Marketing\Services\Customer360Service;
use App\Modules\Marketing\Services\CustomerSegmentService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use App\Modules\Marketing\Services\MarketingDashboardService;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCrmController extends Controller
{
    public function __construct(
        private readonly Customer360Service $customer360,
        private readonly CustomerSegmentService $segments,
        private readonly MarketingAutomationService $automations,
        private readonly MarketingDashboardService $dashboard,
    ) {}

    public function marketingDashboard(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->overview(), 'Marketing dashboard retrieved successfully');
    }

    public function customer360(int $id): JsonResponse
    {
        $user = User::query()->with(['roles', 'customerProfile'])->find($id);
        if (! $user) {
            throw new NotFoundHttpException('Customer not found');
        }
        if (! $user->roles->contains(fn ($r) => $r->slug === 'customer')) {
            // Still allow 360 for users that placed orders without role edge cases
        }

        return ApiResponse::success($this->customer360->profile($user), 'Customer 360 retrieved successfully');
    }

    public function segments(): JsonResponse
    {
        return ApiResponse::success($this->segments->list(), 'Segments retrieved successfully');
    }

    public function segmentShow(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success($this->segments->show($id), 'Segment retrieved successfully');
    }

    public function segmentStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'key' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'criteria_json' => ['required', 'array'],
            'criteria_json.all' => ['required', 'array', 'min:1'],
            'status' => ['nullable', 'in:active,archived'],
        ]);
        $segment = $this->segments->create($validated, $request->user()?->id);

        return ApiResponse::success($this->segments->show($segment->id), 'Segment created successfully', 201);
    }

    public function segmentUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'criteria_json' => ['sometimes', 'array'],
            'status' => ['sometimes', 'in:active,archived'],
        ]);
        $this->segments->update($id, $validated, $request->user()?->id);

        return ApiResponse::success($this->segments->show($id), 'Segment updated successfully');
    }

    public function segmentArchive(Request $request, int $id): JsonResponse
    {
        $this->segments->archive($id, $request->user()?->id);

        return ApiResponse::success(null, 'Segment archived successfully');
    }

    public function segmentDuplicate(Request $request, int $id): JsonResponse
    {
        $copy = $this->segments->duplicate($id, $request->user()?->id);

        return ApiResponse::success($this->segments->show($copy->id), 'Segment duplicated successfully', 201);
    }

    public function segmentMembers(Request $request, int $id): JsonResponse
    {
        $segment = CustomerSegment::query()->findOrFail($id);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $paginator = $this->segments->members($segment, $page, $perPage);
        $rows = collect($paginator->items())->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status,
            'registered_at' => optional($u->created_at)?->toIso8601String(),
        ])->values()->all();

        return ApiResponse::success($rows, 'Segment members retrieved successfully', 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'segment' => ['id' => $segment->id, 'key' => $segment->key, 'name' => $segment->name],
        ]);
    }

    public function automations(): JsonResponse
    {
        return ApiResponse::success($this->automations->list(request()->query('type')), 'Automations retrieved successfully');
    }

    public function automationShow(int $id): JsonResponse
    {
        return ApiResponse::success($this->automations->show($id), 'Automation retrieved successfully');
    }

    public function automationStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'key' => ['nullable', 'string', 'max:80'],
            'type' => ['required', 'in:abandoned_cart,welcome,post_purchase,reactivation,manual_blast'],
            'status' => ['nullable', 'in:draft,active,paused,archived'],
            'segment_id' => ['nullable', 'integer', 'exists:customer_segments,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'channels' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'title_template' => ['nullable', 'string', 'max:200'],
            'body_template' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $a = $this->automations->create($validated, $request->user()?->id);

        return ApiResponse::success($this->automations->show($a->id), 'Automation created successfully', 201);
    }

    public function automationUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:draft,active,paused,archived'],
            'segment_id' => ['nullable', 'integer', 'exists:customer_segments,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'channels' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
            'title_template' => ['nullable', 'string', 'max:200'],
            'body_template' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        $this->automations->update($id, $validated, $request->user()?->id);

        return ApiResponse::success($this->automations->show($id), 'Automation updated successfully');
    }

    public function automationActivate(Request $request, int $id): JsonResponse
    {
        $this->automations->transition($id, 'active', $request->user()?->id);

        return ApiResponse::success($this->automations->show($id), 'Automation activated');
    }

    public function automationPause(Request $request, int $id): JsonResponse
    {
        $this->automations->transition($id, 'paused', $request->user()?->id);

        return ApiResponse::success($this->automations->show($id), 'Automation paused');
    }

    public function automationDispatch(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'test_user_ids' => ['nullable', 'array', 'max:20'],
            'test_user_ids.*' => ['integer'],
        ]);
        $result = $this->automations->dispatch($id, $validated['test_user_ids'] ?? null, $request->user()?->id);

        return ApiResponse::success($result, 'Automation dispatch completed');
    }
}
