# PHASE 9 — Database Changes

## New tables

### `loyalty_accounts`

| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| user_id | FK users, unique | One account per customer |
| balance | unsigned bigint | Cached balance |
| lifetime_earned | unsigned bigint | |
| lifetime_redeemed | unsigned bigint | Includes reverse |
| status | string(20) | default `active` |
| meta | json nullable | |
| timestamps | | |

### `loyalty_transactions`

| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| loyalty_account_id | FK | |
| user_id | FK | |
| type | string(40) | EARN/REVERSE/ADJUST/… |
| points | bigint | Signed delta |
| balance_after | unsigned bigint | |
| reference_type / reference_id | nullable | e.g. order, refund |
| idempotency_key | string(120) unique nullable | |
| reason | string nullable | |
| actor_user_id | nullable | |
| meta | json nullable | |
| timestamps | | |

**Indexes:** `(user_id, created_at)`, `(reference_type, reference_id)`, `(type, created_at)`, unique `idempotency_key`.

## Altered tables

None required for reviews/wishlist (existing schema reused).

## Migration

- `apps/nursery-api/database/migrations/2026_08_11_230000_phase9_loyalty_tables.php`

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Drops `loyalty_transactions` then `loyalty_accounts`.

## Settings (optional, no migration)

| Key | Default | Meaning |
|-----|---------|---------|
| `loyalty.enabled` | true | Feature flag |
| `loyalty.points_per_rupee` | 0.1 | ₹10 → 1 point |

## Sample / test data

Use Feature tests or:

```sql
-- DEV ONLY
INSERT INTO loyalty_accounts (user_id, balance, lifetime_earned, lifetime_redeemed, status, created_at, updated_at)
VALUES (/* existing customer id */, 0, 0, 0, 'active', NOW(), NOW());
```

Do not insert fake production balances.
