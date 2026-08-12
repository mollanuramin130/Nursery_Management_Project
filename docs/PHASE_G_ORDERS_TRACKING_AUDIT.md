# Phase G — Orders + Tracking + Cancel + Reorder Audit

**Date:** 2026-08-11  
**Status:** Audit complete → **implemented** (see `PHASE_G_IMPLEMENTATION_REPORT.md`, `PHASE_G_ORDERS_TRACKING_API_DOCUMENTATION.md`)  
**Convention:** Keep existing **UPPERCASE** statuses (`PENDING_PAYMENT`, `CONFIRMED`, …)

---

## Executive verdict

| Area | Status |
|------|--------|
| Orders list / detail | **Implemented** (basic) |
| Ownership / IDOR | **OK** (`user_id` scoped) |
| Address + price snapshots | **OK** |
| Status history table | **Exists & written** by state machine |
| Shipments | **Exists** (admin creates on ship) |
| Cancel API | **Exists** — stock release/restock OK |
| Cancel UX (Android) | **Missing** |
| Cancel reason picker | **Missing** |
| `can_cancel` / `can_reorder` | **Missing** from API |
| Tracking endpoint / rich timeline | **Missing** (partial in detail) |
| Reorder | **Missing** |
| Coupon restore on cancel | **Missing** |
| Customer refund on paid cancel | **Missing** (truthful: document `refund_pending` if we initiate) |
| Invoice | **Not implemented** — do not fake button |
| List filters / search / thumbs | **Weak / missing** |

**Do not rebuild orders.** Extend `CheckoutService` + thin new endpoints; keep one state machine.

---

## 1. Current order architecture

```
POST /orders → PENDING_PAYMENT | COD→CONFIRMED
GET /orders → list (status filter, pagination)
GET /orders/{id} → detail + shipment + history
POST /orders/{id}/cancel → CANCELLED + stock adjust
POST /orders/{id}/returns → after DELIVERED
Admin: POST /admin/orders/{id}/status → fulfillment + shipment
```

Key files: `CheckoutService`, `OrderStateMachine`, `OrderController`, web `OrdersClient` / `OrderDetailClient`, Flutter `orders_screen` / `order_detail_screen`.

---

## 2. Database tables

`orders`, `order_items`, `order_status_histories`, `payments`, `shipments`, `shipment_events` (unused), `return_*`, `coupon_redemptions`, `refunds` (admin).

**No new core tables required.** Optional: none for Phase G MVP. Use existing history + shipments.

---

## 3–4. Statuses

**Order (authoritative):** see `OrderStateMachine`.  
**Payment:** `pending` | `success` | `failed` (+ proposed `refund_pending` for paid cancel honesty).

### Transition matrix (customer cancel)

| Current | Customer cancel |
|---------|-----------------|
| PENDING_PAYMENT | YES |
| PAYMENT_FAILED | YES |
| CONFIRMED | YES |
| PROCESSING | YES |
| PACKED | YES |
| SHIPPED+ | NO |
| CANCELLED | NO |

### Reorder

| Current | Reorder |
|---------|---------|
| Most statuses including DELIVERED / CANCELLED | YES if owned |
| PENDING_PAYMENT (unpaid cart still open) | Prefer YES but items → cart at current price |

---

## 5. Current APIs

| Path | Exists |
|------|--------|
| GET /orders | Yes |
| GET /orders/{id} | Yes |
| POST /orders/{id}/cancel | Yes |
| GET /orders/{id}/tracking | **No** |
| POST /orders/{id}/reorder | **No** |

---

## 6–12. Clients

Web/Android: basic list + detail timeline. Web has cancel without reason modal. Android has **no cancel**. Neither has reorder/filters/search.

---

## 13–15. Gaps / security / inconsistencies

- No reorder; no action flags; Android cancel gap  
- Coupon redemption not restored on cancel of confirmed orders  
- Paid cancel has no refund initiation (must not claim “refunded”)  
- Web hardcodes cancel allowlist  
- Admin refund looks for payment `captured` vs runtime `success` (fix if touching admin)

Security: ownership OK; keep server as sole cancel/reorder authority.

---

## 16–19. Required changes

**DB:** no destructive changes; SQL notes + optional sample status orders.  
**API:** flags, tracking presenter, reorder, cancel reasons + coupon restore + refund_pending for paid online, search `q`.  
**Web/Android:** filters, cancel sheet/modal, reorder summary → cart, consume `can_*`.

---

## 20. Implementation sequence

1. Audit ✅  
2. Action helpers + enrich list/detail  
3. Tracking endpoint  
4. Cancel harden + coupon restore + refund_pending  
5. Reorder → CartService  
6. API smoke  
7. Website  
8. Android  
9. Docs + SQL + report  

---

## Final API contract (Phase G)

| Method | Path | Role |
|--------|------|------|
| GET | `/orders?status=&q=&page=&per_page=` | List |
| GET | `/orders/{id}` | Detail + `actions` |
| GET | `/orders/{id}/tracking` | Timeline + shipment |
| POST | `/orders/{id}/cancel` | `{ reason, reason_code? }` |
| POST | `/orders/{id}/reorder` | → cart + summary |

Guest checkout: **not** supported (auth required) — unchanged.
