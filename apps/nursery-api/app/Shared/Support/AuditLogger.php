<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        mixed $before = null,
        mixed $after = null,
        ?int $actorUserId = null,
        array $meta = [],
    ): void {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId ?? auth('api')->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before !== null ? json_encode($before) : null,
            'after_json' => $after !== null ? json_encode($after) : null,
            'ip' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
            'request_id' => request()?->attributes->get('request_id'),
            'meta' => $meta !== [] ? json_encode($meta) : null,
            'created_at' => now(),
        ]);
    }
}
