# PHASE 20 — Database Proposal

**Date:** 2026-08-12  
**Rule:** Additive only. Reuse Order state machine + `shipments` / `shipment_events` from Phase 7.

---

## Existing (reuse — NO rewrite)

| Table | Role |
|-------|------|
| `orders` + `meta.fulfillment` | Pick/pack state |
| `order_items` | Line quantities |
| `order_status_histories` | Order SM audit |
| `shipments` | Carrier, tracking, ETA, delivery status |
| `shipment_events` | Customer-visible timeline |
| `shipping_zones` / `shipping_methods` | Checkout shipping (not ops slots) |
| `warehouses` / `inventory_items` / `stock_movements` | Stock; reserve@order, commit@payment |
| `return_requests` / `refunds` | Phase 8 reverse logistics |

---

## Additive change (this phase)

### `shipments.assigned_driver_user_id`

| Column | Type | Notes |
|--------|------|-------|
| `assigned_driver_user_id` | `BIGINT UNSIGNED NULL` | FK → `users.id` ON DELETE SET NULL |
| Index | `shipments_assigned_driver_user_id_index` | Ops filters |

**Purpose:** Assign an internal staff user (typically `delivery_manager`) to a shipment. No separate `drivers` table in v1 — staff users are drivers.

### Proof of Delivery (POD)

**No new table.** Store under `shipments.meta.pod`:

```json
{
  "method": "note|otp|photo_url|signature_url",
  "note": "...",
  "otp_last4": null,
  "photo_url": null,
  "signature_url": null,
  "captured_at": "ISO8601",
  "captured_by": 12
}
```

Uses existing media URL patterns if photos are uploaded via existing upload APIs later.

---

## Explicitly NOT created in Phase 20

| Proposed | Decision |
|----------|----------|
| `deliveries` | Duplicate of `shipments` — skip |
| `delivery_slots` | **API GAP** — no capacity/slot booking yet |
| `delivery_zones` ops CRUD | Reuse `shipping_zones` schema; PIN serviceability still gap |
| `drivers` / `delivery_partners` | Use `users` + RBAC; partners/courier adapters still gap |
| `pick_lists` | Queue API + `meta.fulfillment.items` sufficient |
| Continuous GPS tracking | Privacy / not required |

---

## Status

**DATABASE CHANGE REQUIRED:** one nullable FK column on `shipments`.  
Migration: `2026_08_12_070000_phase20_delivery_operations.php`
