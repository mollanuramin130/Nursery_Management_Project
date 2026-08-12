# PHASE 7 — Database Changes

**Date:** 2026-08-11

## Migration

`2026_08_11_210000_phase7_fulfillment_indexes.php`

### `shipments`

| Change | Detail |
|--------|--------|
| Unique | `tracking_number` (`shipments_tracking_number_unique`) — multiple NULLs allowed in MySQL/SQLite |
| Index | `(status, created_at)` |
| Index | `carrier` |

**Reason:** uniqueness for tracking; filter/list shipments.  
**Existing alternative:** application-only checks (insufficient under concurrency).  
**Safety:** fails if duplicate non-null tracking numbers already exist — clean data before migrate.  
**Rollback:** drop unique + indexes.

### `shipment_events`

| Change | Detail |
|--------|--------|
| Index | `(shipment_id, event_at)` |

**Reason:** timeline queries.  
**Rollback:** drop index.

## Tables reused (no new columns)

| Table | Phase 7 use |
|-------|-------------|
| `orders` | Status machine + `meta.fulfillment` JSON |
| `order_status_histories` | Transitions |
| `shipments` | Carrier/tracking/ETA |
| `shipment_events` | Tracking timeline (now written) |
| `shipping_methods` | Checkout methods (unchanged) |
| `permissions` / `role_permission` | New `fulfillment.*` slugs via seeder |

## No new tables

Pick lines / exceptions intentionally stored in `orders.meta.fulfillment` for v1 simplicity.

## Rollback considerations

1. Revert code deploying fulfillment APIs.  
2. Roll back migration indexes.  
3. Leave historical `shipment_events` and `DELIVERY_FAILED` statuses in place (harmless).
