# PHASE 3 — Database Changes

## Summary

Additive index migration only. **No new tables. No column drops. No breaking schema changes.**

Migration: `apps/nursery-api/database/migrations/2026_08_11_200000_phase_3_production_indexes.php`

Also: sqlite-safe tweaks to existing migrations for automated tests (fulltext skip on sqlite; payments unique index detection without MySQL `SHOW INDEX`).

---

## Indexes added

| Table | Index | Columns | Query supported | Reason | Benefit |
|-------|-------|---------|-----------------|--------|---------|
| `coupons` | `coupons_status_index` | `status` | Admin coupon filters | Status list/filter | Faster filtered scans |
| `coupons` | `coupons_validity_index` | `starts_at`, `ends_at` | Validity window checks | Schedule queries | Range lookup help |
| `stock_movements` | `stock_movements_product_created_index` | `product_id`, `created_at` | Inventory history by product | Admin movements | Avoid full scans |
| `stock_movements` | `stock_movements_warehouse_created_index` | `warehouse_id`, `created_at` | Warehouse history | Ops filters | Same |
| `orders` | `orders_user_status_created_index` | `user_id`, `status`, `created_at` | Customer orders + admin filters | Common list pattern | Composite selectivity |
| `audit_logs` | `audit_logs_created_at_index` | `created_at` | Audit pagination | Time-ordered feed | Faster DESC id/time lists |
| `audit_logs` | `audit_logs_entity_index` | `entity_type`, `entity_id` | Entity drill-down | Admin filters | Lookup by resource |

---

## Considered but not applied

| Change | Why deferred |
|--------|----------------|
| Unique `orders.request_id` | Risk of failing on historical empty-string duplicates; app-level idempotency remains |
| Gateway refund tables | No PSP refund product yet |

---

## Backward compatibility

- Customer Website: none
- Mobile: none
- Admin: none (indexes only)

## Existing alternatives considered

Query patterns already had some single-column indexes (`orders.status`, `orders(user_id, created_at)`). Phase 3 adds composites matching Admin/customer filters without rewriting queries.
