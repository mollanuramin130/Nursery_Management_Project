<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Campaign\Models\Campaign;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $q = $request->query('q');

        $rows = Campaign::query()
            ->withCount('products')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Campaign $c) => $this->summary($c))
            ->values()
            ->all();

        return ApiResponse::success($rows, 'Campaigns retrieved successfully');
    }

    public function show(int $id): JsonResponse
    {
        $campaign = Campaign::query()->with(['products:id'])->withCount('products')->find($id);
        if (! $campaign) {
            throw new NotFoundHttpException('Campaign not found');
        }

        return ApiResponse::success($this->detail($campaign), 'Campaign retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'unique:campaigns,slug'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:40'],
            'season_code' => ['nullable', 'string', 'max:40'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['nullable', 'string', 'in:draft,active,inactive'],
            'priority' => ['nullable', 'integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $campaign = Campaign::query()->create([
            'slug' => $validated['slug'] ?? Str::slug($validated['title']),
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'seasonal',
            'season_code' => $validated['season_code'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'status' => $validated['status'] ?? 'draft',
            'priority' => $validated['priority'] ?? 0,
        ]);

        if (! empty($validated['product_ids'])) {
            $sync = [];
            foreach (array_values($validated['product_ids']) as $i => $pid) {
                $sync[$pid] = ['sort_order' => $i];
            }
            $campaign->products()->sync($sync);
        }

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('campaign.create', 'campaign', $campaign->id, null, $campaign->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'status' => $campaign->status,
        ], 'Campaign created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);
        if (! $campaign) {
            throw new NotFoundHttpException('Campaign not found');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'slug' => ['sometimes', 'string', 'max:220', 'unique:campaigns,slug,'.$id],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:40'],
            'season_code' => ['nullable', 'string', 'max:40'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive'],
            'priority' => ['nullable', 'integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $before = $campaign->toArray();
        $campaign->fill(collect($validated)->except('product_ids')->all())->save();

        if (array_key_exists('product_ids', $validated)) {
            $sync = [];
            foreach (array_values($validated['product_ids'] ?? []) as $i => $pid) {
                $sync[$pid] = ['sort_order' => $i];
            }
            $campaign->products()->sync($sync);
        }

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('campaign.update', 'campaign', $id, $before, $campaign->toArray(), $user->id);

        return ApiResponse::success([
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'status' => $campaign->status,
        ], 'Campaign updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::query()->find($id);
        if (! $campaign) {
            throw new NotFoundHttpException('Campaign not found');
        }

        $before = $campaign->toArray();
        $campaign->delete();

        /** @var User $user */
        $user = $request->user();
        AuditLogger::log('campaign.delete', 'campaign', $id, $before, null, $user->id);

        return ApiResponse::success(null, 'Campaign deleted successfully');
    }

    private function summary(Campaign $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'subtitle' => $c->subtitle,
            'type' => $c->type,
            'status' => $c->status,
            'image_url' => $c->image_url,
            'starts_at' => optional($c->starts_at)?->toIso8601String(),
            'ends_at' => optional($c->ends_at)?->toIso8601String(),
            'priority' => $c->priority,
            'product_count' => (int) ($c->products_count ?? 0),
            'created_at' => optional($c->created_at)?->toIso8601String(),
        ];
    }

    private function detail(Campaign $c): array
    {
        return array_merge($this->summary($c), [
            'description' => $c->description,
            'season_code' => $c->season_code,
            'product_ids' => $c->products->pluck('id')->values()->all(),
        ]);
    }
}
