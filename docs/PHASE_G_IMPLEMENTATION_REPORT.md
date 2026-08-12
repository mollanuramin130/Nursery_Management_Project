# Phase G — Implementation Report

**Date:** 2026-08-11  
**Status:** Complete (API + Website + Android clients; gateway refund settlement remains ops/admin)

---

## A. Audit Summary

Prior state already had orders list/detail, cancel, `order_status_histories`, shipments, historical line prices, and ownership scoping. Gaps: reorder, dedicated tracking payload, `can_*` flags, cancel reason codes, truthful `refund_pending`, coupon restore on cancel, and client UX parity. See `PHASE_G_ORDERS_TRACKING_AUDIT.md`.

## B. Database Changes

**None required.** Reused `orders`, `order_items`, `order_status_histories`, `payments`, `shipments`, `coupon_redemptions`, inventory, cart.

## C. SQL Migration

`database/phase_g_orders_tracking.sql` — documentation + verification SELECTs only (no DROP/DELETE).

## D. Sample Data

Prefer API smoke (`scripts/phase_g_orders_smoke.php`) which creates real COD orders for the sample user. SQL file does not insert fake paid production payments.

## E. API Changes

| Endpoint | Change |
|----------|--------|
| `GET /orders` | Filters (`status` / `active`), search `q`, thumbs, `can_cancel` / `can_reorder`, payment fields |
| `GET /orders/{id}` | Tracking embed, actions, cancel metadata |
| `GET /orders/{id}/tracking` | **New** timeline + shipment |
| `POST /orders/{id}/cancel` | `reason_code`, stock/coupon/refund_pending transaction |
| `POST /orders/{id}/reorder` | **New** → cart + summary |

## F. Order State Machine

Keep UPPERCASE project statuses via `OrderStateMachine`. Customer cancel through `PACKED` inclusive; blocked from `SHIPPED` onward. Clients cannot set status.

## G. Tracking Implementation

Server builds timeline from status history + canonical fulfillment steps. Detail and `/tracking` share `buildTrackingPayload`. Carrier data only when shipment exists.

## H. Cancellation Implementation

Ownership + eligibility server-side. Reasons mapped from codes. Unpaid → inventory release; committed → restock + delete coupon redemption; transactional.

## I. Refund Handling

Paid Razorpay cancel → payment `refund_pending` (not auto-`refunded`). COD/unpaid → `not_required`. No fake “refund complete” UI.

## J. Reorder Implementation

`POST …/reorder` validates live product/stock/price via `CartService::addItem`. Partial adds with `unavailable` / `price_changed` / `quantity_adjusted`. Never creates an order.

## K. Website Screens

- `OrdersClient`: filters, search, pagination, thumbnails, View/Reorder  
- `OrderDetailClient`: server timeline, cancel modal + reasons, reorder → cart store, truthful cancelled/payment copy  

## L. Android Screens

- `orders_screen`: filter chips, richer cards, Track/Reorder, pull-to-refresh  
- `order_detail_screen`: timeline, cancel bottom sheet, reorder summary → `CartProvider`, refresh  

## M. Cross-Platform Tests

Same REST contract; smoke covers list/detail/tracking/reorder/cancel/IDOR. Manual: place/cancel/reorder on one client and confirm the other for the same user (shared DB/cart).

## N. Security Review

| Check | Result |
|-------|--------|
| IDOR on order/tracking/cancel/reorder | Owner `user_id` scope; foreign id → 404 |
| Status / refund mass-assignment | Client cannot set status/payment |
| Cancel after ship | Rejected with `ORDER_CANCELLATION_NOT_ALLOWED` |
| Reorder price manipulation | Live catalog price only |
| PII | Address snapshot for owner only |
| Invoice fake button | Not added |

## O. Regression Results

| Check | Result |
|-------|--------|
| `php scripts/phase_g_orders_smoke.php` | PASS |
| Website `tsc --noEmit` | Clean |
| Flutter analyze (order files) | Clean (no errors) |
| Phase F checkout smoke | Not re-run this pass; Order module additive |

## P. Known Limitations

- Automatic gateway refund capture not implemented (stays `refund_pending` until admin/ops)  
- No invoice PDF  
- No push notifications for status changes  
- Android list uses first page (`per_page=20`); infinite scroll can be added later  
- Carrier tracking is only as accurate as admin-entered shipment data  

## Q. Production Deployment Requirements

1. Deploy API with Phase G `CheckoutService` / routes  
2. Deploy Website + Android clients together  
3. No DB migration required if Phase F schema already applied  
4. Confirm refund ops process for `refund_pending`  
5. Smoke: list → detail → tracking → cancel → reorder → IDOR with second user  

---

## Definition of Done

- [x] Orders list / pagination / filter / search  
- [x] Detail + ownership  
- [x] Historical prices preserved  
- [x] Tracking timeline (history-backed)  
- [x] Cancel + reasons + transactional side effects  
- [x] Truthful refund / COD handling  
- [x] Reorder via cart with stock/price/partial handling  
- [x] Website + Android same APIs  
- [x] API docs + SQL notes + audit + this report  
- [x] Critical/High IDOR / cancel / reorder issues addressed  
