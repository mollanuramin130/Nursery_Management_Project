# QA-05 Stock Lifecycle Matrix

**Source of truth:** `InventoryService` + `CheckoutService` + `PaymentService` + `OrderStateMachine` + `FulfillmentService`  
**Date:** 2026-08-12

## Inventory model (actual columns)

| Field | Meaning |
|-------|---------|
| `qty_on_hand` | Physical units in warehouse |
| `qty_reserved` | Soft-hold for unpaid / pending orders |
| `qty_damaged` | Unsellable but still on-hand |
| **sellable** | `max(0, on_hand − reserved − damaged)` |

Cart add does **not** reserve stock. Reservation starts at `POST /orders`.

## Stock lifecycle

| Event | on_hand | reserved | sellable | Movements |
|------|--------:|--------:|---------:|-----------|
| Initial (example 10) | 10 | 0 | 10 | — |
| Add to cart | 10 | 0 | 10 | none |
| Place order (online) | 10 | +Q | −Q | `reserve` ref=`order` |
| Payment success | −Q | −Q | same | `sale` (commit) |
| Payment failure | 10 | −Q →0 | restore | `release` ref=`order` |
| COD place | −Q immediately | 0 after commit | −Q | `reserve` then `sale` |
| Cancel PENDING/FAILED | — | release | restore | `release` |
| Cancel CONFIRMED/PROCESSING/PACKED | +Q via `return_in` adjust | — | restore | `return_in` |
| Cancel after SHIPPED | rejected | — | — | none |
| Expired unpaid (cron) | — | release | restore | `release` ref=`order` → status `PAYMENT_FAILED` |
| Pick / pack / ship | no stock change | — | — | fulfillment meta only (stock already committed) |
| Deliver / fail / retry | no stock change | — | — | order + shipment status |
| Return approved restock | +Q | — | +Q | `return_in` (`ReturnService`) |

## Order state machine (code)

```
PENDING_PAYMENT → CONFIRMED | PAYMENT_FAILED | CANCELLED
PAYMENT_FAILED → PENDING_PAYMENT | CANCELLED
CONFIRMED → PROCESSING | CANCELLED
PROCESSING → PACKED | CANCELLED
PACKED → SHIPPED | CANCELLED
SHIPPED → OUT_FOR_DELIVERY | DELIVERED
OUT_FOR_DELIVERY → DELIVERED | DELIVERY_FAILED
DELIVERY_FAILED → OUT_FOR_DELIVERY | DELIVERED
DELIVERED → RETURN_REQUESTED
RETURN_REQUESTED → RETURNED | REFUNDED | DELIVERED
RETURNED → REFUNDED
CANCELLED / REFUNDED → (terminal)
```

## Shipment status (lowercase)

Examples: `shipped`, `out_for_delivery`, `delivered`, `failed` — **do not** equate to order `UPPER_SNAKE` strings.

## Concurrency

`lockForUpdate()` on inventory rows during reserve/commit/release/adjust. Last-unit: one reserve succeeds, other gets insufficient inventory.

## Idempotency

- Reserve/commit/release skip if matching `stock_movements` row exists for type+reference+product(+qty).
- Fulfillment pick start idempotent when already `PROCESSING`.
- Ship idempotent when already shipped with shipment row.
- Order place idempotent via `X-Request-Id` / `orders.request_id`.
