# PHASE 11 — Database Changes

## Altered: `notifications`

| Column | Notes |
|--------|-------|
| `category` | `transactional` \| `marketing` (default transactional) |
| `idempotency_key` | nullable unique — duplicate event prevention |

Indexes:

- `(user_id, is_read, created_at)`
- `(user_id, created_at)`
- unique `idempotency_key`

## New: `notification_deliveries`

| Column | Notes |
|--------|-------|
| `notification_id` | FK → notifications |
| `channel` | in_app \| email \| push |
| `status` | pending \| queued \| sent \| failed \| skipped |
| `provider` | smtp/log/fcm/database |
| `attempts` | |
| `error_message` | |
| `sent_at` | |
| `meta` | json |

Unique: `(notification_id, channel)`

## Existing (unchanged schema, now used)

- `notification_templates` — seeded + admin editable
- `user_devices` — register/deactivate lifecycle
- `jobs` / `failed_jobs` — channel delivery queue

## Migration

`apps/nursery-api/database/migrations/2026_08_12_010000_phase11_notification_channels.php`

## Rollback

```bash
php artisan migrate:rollback --step=1
```

## Seed

```bash
php artisan db:seed --class=NotificationTemplateSeeder
php artisan db:seed --class=RolePermissionSeeder
```
