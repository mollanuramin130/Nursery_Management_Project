<?php

namespace App\Modules\Notification\Services;

use App\Jobs\DeliverNotificationChannelsJob;
use App\Modules\Auth\Models\User;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Models\UserDevice;
use App\Modules\Notification\Support\NotificationChannelMap;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationService
{
    public function __construct(private readonly PreferenceService $preferences) {}

    public function list(User $user, bool $unreadOnly = false, int $perPage = 20): array
    {
        $query = Notification::query()->where('user_id', $user->id)->orderByDesc('id');
        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $paginator = $query->paginate(min(max($perPage, 1), 50));
        $unreadCount = Notification::query()->where('user_id', $user->id)->where('is_read', false)->count();

        $data = collect($paginator->items())->map(fn (Notification $n) => $this->serializeCustomer($n))->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'unread_count' => $unreadCount,
            ],
        ];
    }

    public function unreadCount(User $user): array
    {
        return [
            'unread_count' => Notification::query()->where('user_id', $user->id)->where('is_read', false)->count(),
        ];
    }

    public function markRead(User $user, int $id): array
    {
        $notification = Notification::query()->where('user_id', $user->id)->whereKey($id)->first();
        if (! $notification) {
            throw new NotFoundHttpException('Notification not found');
        }

        $notification->is_read = true;
        $notification->read_at = now();
        $notification->save();

        return ['id' => $notification->id, 'is_read' => true];
    }

    public function markAllRead(User $user): array
    {
        Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return ['unread_count' => 0];
    }

    public function registerDevice(User $user, array $payload): array
    {
        $device = null;

        if (! empty($payload['device_id'])) {
            $device = UserDevice::query()
                ->where('user_id', $user->id)
                ->where('device_id', $payload['device_id'])
                ->first();
        }

        if (! $device && ! empty($payload['push_token'])) {
            $device = UserDevice::query()
                ->where('user_id', $user->id)
                ->where('push_token', $payload['push_token'])
                ->first();
        }

        if ($device) {
            $device->fill([
                'platform' => $payload['platform'],
                'device_id' => $payload['device_id'] ?? $device->device_id,
                'push_token' => $payload['push_token'] ?? $device->push_token,
                'app_version' => $payload['app_version'] ?? $device->app_version,
                'is_active' => true,
                'last_seen_at' => now(),
            ])->save();
        } else {
            $device = UserDevice::query()->create([
                'user_id' => $user->id,
                'platform' => $payload['platform'],
                'device_id' => $payload['device_id'] ?? null,
                'push_token' => $payload['push_token'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ]);
        }

        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'is_active' => (bool) $device->is_active,
        ];
    }

    public function deactivateDevice(User $user, array $payload): array
    {
        $q = UserDevice::query()->where('user_id', $user->id);
        if (! empty($payload['device_id'])) {
            $q->where('device_id', $payload['device_id']);
        } elseif (! empty($payload['push_token'])) {
            $q->where('push_token', $payload['push_token']);
        } else {
            throw new ApiException('device_id or push_token required', 422, 'VALIDATION_ERROR');
        }

        $updated = $q->update(['is_active' => false, 'updated_at' => now()]);

        return ['deactivated' => $updated];
    }

    /**
     * Central notification entry — never throws into commerce callers.
     * Creates in-app row (idempotent) then queues email/push.
     */
    public function notify(
        User|int $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $idempotencyKey = null,
        ?string $category = null,
    ): ?Notification {
        try {
            $userId = $user instanceof User ? $user->id : (int) $user;
            $config = NotificationChannelMap::forType($type);
            $category = $category ?: $config['category'];
            $idempotencyKey = $idempotencyKey ?: $this->defaultIdempotencyKey($type, $userId, $data);

            if ($category === 'marketing') {
                $owner = $user instanceof User ? $user : User::query()->find($userId);
                if ($owner) {
                    $prefs = $this->preferences->get($owner);
                    if (! ($prefs['marketing_opt_in'] ?? false) || ! ($prefs['notify_promotions'] ?? false)) {
                        return null;
                    }
                }
            }

            if ($idempotencyKey) {
                $existing = Notification::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $notification = DB::transaction(function () use ($userId, $type, $title, $body, $data, $category, $idempotencyKey) {
                return Notification::query()->create([
                    'user_id' => $userId,
                    'type' => $type,
                    'category' => $category,
                    'title' => $title,
                    'body' => $body,
                    'data_json' => $data,
                    'is_read' => false,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => [
                        'channels' => NotificationChannelMap::forType($type)['channels'],
                    ],
                ]);
            });

            DeliverNotificationChannelsJob::dispatch($notification->id)->afterCommit();

            return $notification;
        } catch (\Throwable $e) {
            Log::error('notification.notify_failed', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function adminList(array $filters, int $perPage): array
    {
        $q = Notification::query()->with('user')->orderByDesc('id');
        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['category'])) {
            $q->where('category', $filters['category']);
        }
        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $q->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', '%'.$term.'%')
                    ->orWhere('body', 'like', '%'.$term.'%')
                    ->orWhere('type', 'like', '%'.$term.'%');
            });
        }

        $paginator = $q->paginate(min(max($perPage, 1), 50));

        return [
            'data' => collect($paginator->items())->map(function (Notification $n) {
                return array_merge($this->serializeCustomer($n), [
                    'category' => $n->category,
                    'idempotency_key' => $n->idempotency_key,
                    'user' => $n->relationLoaded('user') && $n->user ? [
                        'id' => $n->user->id,
                        'email' => $n->user->email,
                        'name' => $n->user->name,
                    ] : ['id' => $n->user_id],
                ]);
            })->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function adminShow(int $id): array
    {
        $n = Notification::query()->with(['user', 'deliveries'])->whereKey($id)->first();
        if (! $n) {
            throw new NotFoundHttpException('Notification not found');
        }

        return array_merge($this->serializeCustomer($n), [
            'category' => $n->category,
            'idempotency_key' => $n->idempotency_key,
            'user' => $n->user ? [
                'id' => $n->user->id,
                'email' => $n->user->email,
                'name' => $n->user->name,
            ] : null,
            'deliveries' => $n->deliveries->map(fn (NotificationDelivery $d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'status' => $d->status,
                'provider' => $d->provider,
                'attempts' => (int) $d->attempts,
                'error_message' => $d->error_message,
                'sent_at' => optional($d->sent_at)?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function dashboard(): array
    {
        return [
            'total' => Notification::query()->count(),
            'unread' => Notification::query()->where('is_read', false)->count(),
            'marketing' => Notification::query()->where('category', 'marketing')->count(),
            'transactional' => Notification::query()->where('category', 'transactional')->count(),
            'deliveries_failed' => NotificationDelivery::query()->where('status', 'failed')->count(),
            'deliveries_sent' => NotificationDelivery::query()->where('status', 'sent')->count(),
        ];
    }

    public function listTemplates(): array
    {
        return NotificationTemplate::query()
            ->orderBy('code')
            ->orderBy('channel')
            ->get()
            ->map(fn (NotificationTemplate $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'channel' => $t->channel,
                'locale' => $t->locale,
                'subject' => $t->subject,
                'body' => $t->body,
                'status' => $t->status,
            ])
            ->values()
            ->all();
    }

    public function updateTemplate(int $id, array $payload, User $actor): array
    {
        $t = NotificationTemplate::query()->whereKey($id)->first();
        if (! $t) {
            throw new NotFoundHttpException('Template not found');
        }
        $before = $t->toArray();
        foreach (['name', 'subject', 'body', 'status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $t->{$field} = $payload[$field];
            }
        }
        $t->save();
        AuditLogger::log('notification_template.update', 'notification_template', $t->id, $before, $t->toArray(), $actor->id);

        return $this->listTemplates();
    }

    /**
     * Manual marketing send (permissioned). Respects opt-in.
     */
    public function adminSend(User $actor, array $payload): array
    {
        $userId = (int) $payload['user_id'];
        $title = (string) $payload['title'];
        $body = (string) $payload['body'];
        $type = (string) ($payload['type'] ?? 'campaign_promo');

        $n = $this->notify(
            $userId,
            $type,
            $title,
            $body,
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'admin-send:'.$actor->id.':'.$userId.':'.md5($title.'|'.$body.'|'.now()->format('YmdHi')),
            'marketing',
        );

        AuditLogger::log('notification.admin_send', 'notification', $n?->id, null, [
            'user_id' => $userId,
            'type' => $type,
        ], $actor->id);

        if (! $n) {
            throw new ApiException('Recipient opted out of marketing or send failed', 409, 'CONFLICT');
        }

        return $this->adminShow($n->id);
    }

    private function defaultIdempotencyKey(string $type, int $userId, array $data): ?string
    {
        $parts = [strtolower($type), (string) $userId];
        $found = false;
        foreach (['order_id', 'order_number', 'subscription_id', 'return_id', 'refund_id', 'review_id', 'cycle_number', 'status'] as $k) {
            if (isset($data[$k]) && $data[$k] !== '' && $data[$k] !== null) {
                $parts[] = $k.':'.$data[$k];
                $found = true;
            }
        }

        return $found ? implode('|', $parts) : null;
    }

    private function serializeCustomer(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'category' => $n->category ?? 'transactional',
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data_json,
            'is_read' => (bool) $n->is_read,
            'created_at' => optional($n->created_at)?->toIso8601String(),
        ];
    }
}
