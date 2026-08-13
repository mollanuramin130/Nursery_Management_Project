# PHASE 20 — API

Base: `/api/v1`

## Existing (Phase 7) — unchanged contract

| Method | Path | Perm |
|--------|------|------|
| GET | `/admin/fulfillment` | fulfillment.view |
| GET | `/admin/fulfillment/queue/{picking\|packing\|ready_to_ship\|in_transit\|exceptions}` | fulfillment.view |
| GET | `/admin/fulfillment/orders/{id}` | fulfillment.view |
| POST | `.../pick/start\|pick\|pick/exception\|pick/complete` | fulfillment.pick |
| POST | `.../pack` | fulfillment.pack |
| POST | `.../ship\|out-for-delivery\|deliver\|fail-delivery\|retry-delivery` | fulfillment.ship |
| GET/POST | `/admin/fulfillment/shipments…` | view / ship |
| GET | `/orders/{id}/tracking` | customer auth |

## Additive (Phase 20)

| Method | Path | Body | Perm |
|--------|------|------|------|
| GET | `/admin/fulfillment/drivers` | — | fulfillment.view |
| POST | `/admin/fulfillment/orders/{id}/pick/scan` | `{ code, increment_by? }` | fulfillment.pick |
| POST | `/admin/fulfillment/orders/{id}/assign-driver` | `{ driver_user_id }` | fulfillment.ship |
| POST | `/admin/fulfillment/orders/{id}/reschedule` | `{ eta_date, note? }` | fulfillment.ship |
| POST | `/admin/fulfillment/orders/{id}/deliver` | optional POD: `{ method, note, otp_last4, photo_url, signature_url }` | fulfillment.ship |

Failure reasons (unchanged): `CUSTOMER_UNAVAILABLE`, `WRONG_ADDRESS`, `DAMAGED_PACKAGE`, `DELIVERY_AREA_ISSUE`, `COURIER_FAILURE`, `OTHER`.

Envelope unchanged: `{ success, message, data, errors, meta }`.
