# PHASE 10 — Database Changes

## New tables

### `subscription_plans`
- `product_id` FK → products
- `name`, `slug` (unique), `frequency`, `quantity_default`
- `unit_price`, `compare_at_price`, `currency`
- `status`, `max_cycles`, `description`, `meta`, timestamps
- Indexes: `(product_id, status)`, `frequency`, `status`

### `subscriptions`
- `subscription_number` unique
- `user_id`, `subscription_plan_id`, `product_id` FKs
- `quantity`, `frequency`, **locked** `unit_price`, `currency`, `status`
- Address snapshot JSON + optional `address_id`, `shipping_method_id`, `payment_method`
- `cycle_count`, `next_billing_at`, pause/cancel fields
- `failed_payment_count`, `max_failed_payments`
- Soft deletes
- Indexes: `(user_id, status)`, `(status, next_billing_at)`

### `subscription_cycles`
- `subscription_id`, `cycle_number`
- **UNIQUE** `(subscription_id, cycle_number)`
- `scheduled_at`, `status`, `order_id` (unique nullable FK)
- `unit_price`, `quantity`, `amount`, `failure_reason`, `processed_at`, `meta`

### `subscription_events`
- `subscription_id`, `event_type`, `actor_user_id`, `payload`, `created_at`

## Altered tables

### `orders`
- `subscription_id` nullable indexed
- `subscription_cycle_id` nullable indexed  
  (also mirrored in `meta.source = subscription` for compatibility)

## Migration

`apps/nursery-api/database/migrations/2026_08_12_000100_phase10_subscription_tables.php`

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Drops order columns then events → cycles → subscriptions → plans.

## Constraints summary

| Constraint | Purpose |
|------------|---------|
| Unique cycle `(subscription_id, cycle_number)` | No duplicate cycles/orders |
| Unique `order_id` on cycles | One order per cycle |
| Unique plan `slug` | Stable admin/API identity |
| Unique `subscription_number` | Human-readable ID |
