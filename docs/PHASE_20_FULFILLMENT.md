# PHASE 20 — Fulfillment

## Flow

1. Payment confirmed → order `CONFIRMED` (inventory already committed at payment).
2. `pick/start` → `PROCESSING` + pick lines in `meta.fulfillment.items`.
3. Manual pick update **or** `pick/scan` (SKU verified server-side).
4. Short-pick → `pick/exception` (no silent inventory rewrite).
5. `pick/complete` → packing allowed.
6. `pack` → `PACKED` + customer notification.
7. `ship` → shipment + tracking (idempotent).
8. Optional `assign-driver`.
9. `out-for-delivery` → `deliver` (optional POD) or `fail-delivery` → `retry-delivery` / `reschedule`.

## Multi-warehouse

Fulfill from `orders.warehouse_id`, else default warehouse. No GIS optimizer in this phase.

## Admin surfaces

- **Web:** `/fulfillment/*` desk (scan field, assign driver, reschedule, POD note).
- **Mobile:** `/fulfillment` hub + queues + order actions (More → Fulfillment).
