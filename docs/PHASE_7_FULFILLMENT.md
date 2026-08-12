# PHASE 7 — Fulfillment, Shipping & Delivery

**Status:** PARTIALLY COMPLETE  
**Date:** 2026-08-11

## 1. Fulfillment architecture

Fulfillment is **not** a second order system. It is an operational layer on top of `OrderStateMachine` + `shipments` / `shipment_events`.

| Ops step | Order status |
|----------|--------------|
| Ready to pick | `CONFIRMED` |
| Picking | `PROCESSING` |
| Packed | `PACKED` |
| Shipped | `SHIPPED` |
| Out for delivery | `OUT_FOR_DELIVERY` |
| Delivered | `DELIVERED` |
| Delivery failed | `DELIVERY_FAILED` |

Pick progress and exceptions live in `orders.meta.fulfillment` (no parallel allocation table).

## 2. Order state integration

Extended transitions:

- `OUT_FOR_DELIVERY` → `DELIVERED` | `DELIVERY_FAILED`
- `DELIVERY_FAILED` → `OUT_FOR_DELIVERY` | `DELIVERED`

Cancellation still blocked from `SHIPPED` onward (existing rule).

## 3. Inventory allocation

Stock is **committed at payment** (Phase 6). Pick/pack do **not** deduct again.

Warehouse rule: use `orders.warehouse_id`, else default warehouse. No multi-warehouse optimizer.

## 4. Picking

API: start → update quantities → optional short-pick exception → complete.

- Over-pick rejected (`picked > required`)
- Short pick requires recorded exception (does not silent-adjust inventory)

Admin: `/fulfillment/picking`, `/fulfillment/orders/{id}`

## 5. Packing

Requires `picking_completed_at`. Transitions `PROCESSING` → `PACKED`. Stores package count / weight in meta.

Admin: `/fulfillment/packing`

## 6. Shipment

`POST /admin/fulfillment/orders/{id}/ship` via `ShippingProvider` (`InternalDeliveryProvider` first).

- Unique `tracking_number` (DB unique index)
- Idempotent if already shipped
- Writes `shipment_events` + customer notification

## 7. Tracking

- Admin: add events on shipment
- Customer: `GET /orders/{id}/tracking` includes timeline + customer-safe events (no pick exceptions / warehouse notes)

## 8. Delivery

Mark out-for-delivery / delivered / fail / retry through fulfillment endpoints (or legacy order status API).

## 9. Exceptions

- Pick short + delivery failed stored in meta
- Admin `/fulfillment/exceptions`
- Resolve via `fulfillment.manage`
- **No auto-cancel** on delivery failure

## 10. Cancellation

Unchanged inventory rules from Phase 6. Post-ship cancel rejected by state machine.

## 11. Returns integration

Existing `ReturnService` from `DELIVERED` unchanged. Reverse logistics pickup not built — see API gaps.

## 12. Notifications

`NotificationService::notify` on packed / shipped / out for delivery / delivered / delivery failed (fulfillment + AdminOrderService status path).

## 13. RBAC

Seeded: `fulfillment.view|pick|pack|ship|manage`  
Assigned to `admin`, `super_admin`, `order_manager`, `delivery_manager`.

**Ops note:** re-run `RolePermissionSeeder` (or assign permissions) on existing databases.

## 14. Audit logging

`fulfillment.pick_*`, `fulfillment.pack`, `fulfillment.ship`, `fulfillment.tracking`, `fulfillment.delivery_*`, `fulfillment.exception_resolve`.

## 15. Database changes

See `PHASE_7_DATABASE_CHANGES.md` — indexes + unique tracking; no new tables.

## 16. API changes

Admin fulfillment routes under `/api/v1/admin/fulfillment/*`. Customer tracking enhanced with `events`.

## 17. Testing

`tests/Feature/Phase7FulfillmentTest.php` (8 tests).

## 18. Performance

Shipments/queues paginated; indexes on shipment status/carrier/tracking.

## 19. Known limitations

- No external courier APIs / labels / GPS
- No pick/pack scan guns or bin locations
- No multi-package customer UX beyond package_count meta
- No automatic return pickup
- Fulfillment SLA analytics are count-based (not full duration histograms)
- Existing Admin users need new permission rows seeded
