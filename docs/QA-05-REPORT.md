# QA-05 REPORT — Inventory + Fulfillment + Delivery

## 1. Status

**COMPLETE**

## 2. Scope

Regression and reliability of inventory reservation/commit/release, fulfillment (pick/pack/ship), delivery (OFD/fail/retry/deliver), cancellation restock, and customer/admin order visibility. No greenfield workflows. No Razorpay. No Customer Mobile Returns (QA-07).

## 3. Bugs investigated

| ID | Classification | Finding |
|----|----------------|---------|
| QA-05-001 | BUG / INVENTORY | `inventory:release-expired-reservations` released with `reference_type=order_expired`, so a later cancel on `PAYMENT_FAILED` could `release` again under `order` and steal another order’s reservation |
| Oversell / negative stock | — | Existing `lockForUpdate` + sellable checks; Phase6 + QA-05 last-unit tests PASS |
| Invalid transitions | — | `OrderStateMachine` rejects; verified |
| Shipment vs order casing | INTENTIONAL | Shipment `shipped` lowercase; order `SHIPPED` UPPER — documented |

## 4. Root causes

1. **QA-05-001:** Cron used a different movement reference than place/cancel/payment-fail, defeating release idempotency.
2. Cron also set `PAYMENT_FAILED` without `OrderStateMachine` (no status history).

## 5. Bugs fixed

- **QA-05-001** — Expired reservation release now uses `reference_type=order`, transitions via `OrderStateMachine`, wraps in transaction + `lockForUpdate`.

## 6. Files changed

| Area | Path |
|------|------|
| API | `app/Console/Commands/ReleaseExpiredReservationsCommand.php` |
| API tests | `tests/Feature/Qa05InventoryFulfillmentTest.php` |
| Admin Mobile | `test/fulfillment_actions_test.dart` |
| Docs | `QA-05-*.md`, stock lifecycle, bug register, feature matrix, roadmap |

## 7. API changes

**NONE** (behavior fix inside existing artisan command only).

## 8. Database changes

**NONE**

## 9–12. Client verification

| Client | Result | Evidence |
|--------|--------|----------|
| Customer Web | PASS | Tracking/detail API; no regression to checkout guards |
| Customer Mobile | PASS (API) / UI device UNVERIFIED | Same order APIs; device UI not re-run this phase |
| Admin Web | PASS | Live fulfillment E2E on `ORD-20260812-00007` |
| Admin Mobile | PASS (unit + API contract) / UI UNVERIFIED | Action-gate unit tests; shared admin fulfillment API |

## 13–15. Tests / regression

| Suite | Result |
|-------|--------|
| PHPUnit Qa05 + Phase6/7/18/20 + Qa02/03/04 (54) | PASS |
| Web unit | PASS |
| Customer Flutter cart/checkout | PASS |
| Admin Mobile fulfillment + permissions | PASS |
| Live fulfill → customer DELIVERED | PASS |

## 16. Real device

Customer/Admin Mobile interactive fulfillment UI: **UNVERIFIED** this phase (API + unit covered).

## 17. Security / permissions

View-only staff cannot start picking (403) — PASS.

## 18. Remaining / UNVERIFIED

- Admin Mobile / Customer Mobile interactive device UI for fulfillment/tracking
- Full return restock path live (covered in ReturnService code; mobile returns → QA-07)
- True parallel-process concurrency (lock tested sequentially with last-unit)

## 19. Risk

Low for core inventory/fulfillment. Medium if Octane/long-lived workers retain sticky auth in tests only (production PHP-FPM request-scoped).

## 20. QA-06 readiness

**YES**
