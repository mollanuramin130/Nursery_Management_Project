<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Setting;
use App\Modules\Auth\Models\User;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');

        $rows = Setting::query()
            ->when($group, fn ($q) => $q->where('group_name', $group))
            ->orderBy('group_name')
            ->orderBy('key')
            ->get()
            ->map(fn (Setting $s) => [
                'key' => $s->key,
                'value' => $s->typedValue(),
                'type' => $s->type,
                'group' => $s->group_name,
            ])
            ->values()
            ->all();

        return ApiResponse::success($rows, 'Settings retrieved successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'max:120'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['nullable', 'string', 'in:string,int,integer,float,decimal,bool,boolean,json'],
            'settings.*.group' => ['nullable', 'string', 'max:60'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $updated = [];

        foreach ($validated['settings'] as $row) {
            $type = $row['type'] ?? 'string';
            $value = $row['value'];
            if ($type === 'json' && ! is_string($value)) {
                $value = json_encode($value);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
                $type = 'bool';
            } elseif (is_array($value)) {
                $value = json_encode($value);
                $type = 'json';
            } else {
                $value = $value === null ? null : (string) $value;
            }

            $setting = Setting::query()->firstOrNew(['key' => $row['key']]);
            $before = $setting->exists ? $setting->toArray() : null;
            $setting->fill([
                'value' => $value,
                'type' => $type,
                'group_name' => $row['group'] ?? $setting->group_name ?? 'general',
            ])->save();

            AuditLogger::log('setting.update', 'setting', $setting->id, $before, $setting->toArray(), $user->id);
            $updated[] = [
                'key' => $setting->key,
                'value' => $setting->typedValue(),
                'type' => $setting->type,
                'group' => $setting->group_name,
            ];
        }

        return ApiResponse::success($updated, 'Settings updated successfully');
    }
}
