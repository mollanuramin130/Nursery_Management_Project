# QA_DATABASE_CHANGE_PROPOSAL

**QA-00 … QA-11:** No mandatory schema change for those phases (see history below).

---

## QA-12 — Performance indexes (APPROVED for additive migration)

### Problem

1. `InventoryService::hasMovement()` filters `stock_movements` by `reference_type`, `reference_id`, `type`, `product_id` on every reserve/commit/release (checkout, cancel, expired-reservation cron). No composite index → table scans as movements grow.
2. Dashboard/analytics payment queries filter `payments` by `created_at` + `status` without a supporting composite index.

### Existing schema

- `stock_movements`: indexes on product/warehouse/created_at/type (no reference composite).
- `payments`: FK indexes + unique `idempotency_key` / `provider_payment_id` (no `(created_at, status)`).

### Proposed change

Additive indexes only (no column changes):

1. `stock_movements_ref_lookup_index` on `(reference_type, reference_id, type, product_id)`
2. `payments_created_at_status_index` on `(created_at, status)`

### Migration

`database/migrations/2026_08_12_180000_qa12_performance_indexes.php`

### Rollback

`down()` drops both indexes.

### Affected API

Faster reserve/release/idempotency checks; faster dashboard payment KPIs. **No response contract change.**

### Affected clients

None (transparent).

### Performance impact

Positive for checkout concurrency and admin dashboard under growth. Write overhead on insert is minimal.

### Risk

Low — additive indexes; verify migrate on MySQL + SQLite test suite.

### Test plan

- `php artisan migrate`
- `Qa05InventoryFulfillmentTest` + `Qa12PerformanceTest` + prior QA regression

---

## Historical notes

**QA-00–QA-11:** NO DATABASE CHANGE REQUIRED for bugs fixed in those phases (config, services, clients, middleware).
