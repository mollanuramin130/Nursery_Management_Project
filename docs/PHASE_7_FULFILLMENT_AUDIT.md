# PHASE 7 — Fulfillment Architecture Audit

**Date:** 2026-08-11

## Decision: single lifecycle

Fulfillment is **integrated into the existing `OrderStateMachine`**.

| Warehouse step | Order status |
|----------------|--------------|
| Ready to pick | `CONFIRMED` |
| Picking | `PROCESSING` |
| Packed | `PACKED` |
| Shipped | `SHIPPED` |
| Out for delivery | `OUT_FOR_DELIVERY` |
| Delivered | `DELIVERED` |
| Delivery failed | `DELIVERY_FAILED` (Phase 7 addition) |

**Do not** create a parallel fulfillment status machine.

## Inventory

Stock is **committed at payment** (`qty_reserved` → sale). Pick/pack do **not** deduct again. No `quantity_allocated` column.

Warehouse rule: fulfill from `orders.warehouse_id`, else default warehouse.

## Existing assets reused

- `shipments`, `shipment_events` (events unused → activate)
- `GET /orders/{id}/tracking`
- Admin `POST /admin/orders/{id}/status` + shipment upsert
- `NotificationService::notify`
- Permissions foundation (`orders.*`); seed `fulfillment.*`

## Gaps to close in Phase 7

Pick/pack queues & APIs, shipment listing, tracking events, delivery failure, provider abstraction, Admin fulfillment UI, customer notifications on ship/deliver, analytics timings.
