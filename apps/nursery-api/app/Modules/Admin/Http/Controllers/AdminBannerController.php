<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Campaign\Models\Banner;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminBannerController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Banner::query()->orderBy('sort_order')->orderByDesc('id')->get()->map(fn (Banner $b) => $this->summary($b))->values()->all();

        return ApiResponse::success($rows, 'Banners retrieved successfully');
    }

    public function show(int $id): JsonResponse
    {
        $banner = Banner::query()->find($id);
        if (! $banner) {
            throw new NotFoundHttpException('Banner not found');
        }

        return ApiResponse::success($this->summary($banner), 'Banner retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'image_url' => ['required', 'string', 'max:500'],
            'placement' => ['required', 'string', 'max:60'],
            'link_type' => ['nullable', 'string', 'max:40'],
            'link_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $banner = Banner::query()->create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('banner.create', 'banner', $banner->id, null, $banner->toArray(), $user->id);

        return ApiResponse::success(['id' => $banner->id, 'title' => $banner->title], 'Banner created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $banner = Banner::query()->find($id);
        if (! $banner) {
            throw new NotFoundHttpException('Banner not found');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'image_url' => ['sometimes', 'string', 'max:500'],
            'placement' => ['sometimes', 'string', 'max:60'],
            'link_type' => ['nullable', 'string', 'max:40'],
            'link_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $before = $banner->toArray();
        $banner->fill($validated)->save();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('banner.update', 'banner', $id, $before, $banner->toArray(), $user->id);

        return ApiResponse::success(['id' => $banner->id, 'status' => $banner->status], 'Banner updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $banner = Banner::query()->find($id);
        if (! $banner) {
            throw new NotFoundHttpException('Banner not found');
        }

        $before = $banner->toArray();
        $banner->delete();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('banner.delete', 'banner', $id, $before, null, $user->id);

        return ApiResponse::success(null, 'Banner deleted successfully');
    }

    private function summary(Banner $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'image_url' => $b->image_url,
            'placement' => $b->placement,
            'link_type' => $b->link_type,
            'link_value' => $b->link_value,
            'sort_order' => $b->sort_order,
            'status' => $b->status,
            'starts_at' => optional($b->starts_at)?->toIso8601String(),
            'ends_at' => optional($b->ends_at)?->toIso8601String(),
        ];
    }
}
