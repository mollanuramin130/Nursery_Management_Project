# QA Feature Matrix

**Date:** 2026-08-13 · **Through QA-27** · **Mode:** TEST PAID verified; LIVE readiness NOT READY; GREEN NO; YELLOW

Legend: **WORKING** (implemented + contract-aligned) · **PARTIAL** · **BROKEN** (known defect) · **MISSING** · **N/A** · **UNVERIFIED** (needs live run)

---

## Customer commerce

| Feature | API | Customer Web | Customer Mobile | Admin Web | Admin Mobile | DB | Status | Severity | Notes |
|---------|-----|--------------|-----------------|-----------|--------------|-----|--------|----------|-------|
| Login | WORKING | WORKING* | WORKING* | WORKING | WORKING | users | PARTIAL* | HIGH* | *Contracts OK. QA-SEC-001 Web JWT localStorage remains OPEN (QA-11 Option C). Mobile secure storage + QA-12 Customer Mobile refresh single-flight. |
| Register | WORKING | WORKING | WORKING | N/A | N/A | users | PARTIAL | MEDIUM | UI password rules weaker than API → 422 |
| Forgot password | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | LOW | |
| Reset password UI | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | QA-02 FIXED |
| Home / catalog | WORKING | WORKING | WORKING | N/A | N/A | products | WORKING | — | |
| Search / filters | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | |
| PDP | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | |
| Wishlist | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | |
| Cart | WORKING | WORKING | WORKING | N/A | N/A | carts | WORKING | — | QA-03 totals; QA-12 sellableQty batch in present() |
| Coupon | WORKING | WORKING | WORKING | WORKING | MISSING | coupons | WORKING | — | Cart uses `code`; checkout `coupon_code`; QA-12 admin list paginated |
| Addresses | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | |
| Checkout preview | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | QA-04 preview mandatory; QA-13 PERF-011 stale-response generation guard |
| Place order | WORKING | WORKING | WORKING | N/A | N/A | orders | WORKING | — | POST `/orders`; idempotency via `X-Request-Id` |
| Payment Razorpay | WORKING | WORKING* | WORKING* | N/A | N/A | payments | WORKING* | — | *TEST PAID (QA-26). LIVE readiness QA-27 NOT READY / charge BLOCKED. |
| Payment UPI (QR + Intent) | WORKING | WORKING* | WORKING* | WORKING* | WORKING* | payments | WORKING* | — | *Checkout TEST PAID verified. Separate QR/Intent physical charges optional/UNVERIFIED. LIVE BLOCKED. |
| COD | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | QA-04 API + real-device PASS |
| Cross-platform order sync | WORKING | WORKING | WORKING | WORKING | WORKING* | orders | WORKING* | — | *QA-10 + QA-14 live fulfill→customer DELIVERED PASS; concurrent 4-UI device UNVERIFIED |
| Orders / tracking | WORKING | WORKING | WORKING | WORKING | PARTIAL | shipments | WORKING | — | QA-05: live Admin fulfill → customer DELIVERED; shipment status lowercase vs order UPPER |
| Inventory ops | WORKING | N/A | N/A | WORKING | WORKING | inventory_* | WORKING | — | QA-05: reserve/commit/release + expired-reservation fix |
| Fulfillment pick/pack/ship | WORKING | N/A | N/A | WORKING | WORKING | — | WORKING | — | QA-05 PHPUnit + live E2E PASS; Admin Mobile UI device UNVERIFIED |
| Delivery fail/retry | WORKING | N/A | N/A | WORKING | WORKING | — | WORKING | — | Phase7/20 + Qa05 |
| Cancel / reorder | WORKING | WORKING | WORKING | PARTIAL | PARTIAL | — | WORKING | — | |
| Returns | WORKING | WORKING | WORKING | WORKING | MISSING | return_* | WORKING* | — | *QA-07 Mobile account list/detail PASS; Admin Mobile still MISSING |
| Account reviews | WORKING | WORKING | WORKING | WORKING | MISSING | reviews | WORKING* | — | *QA-07 My Reviews PASS; create remains PDP; no customer edit/delete API |
| Rewards / subs | WORKING | WORKING | WORKING | WORKING | MISSING | — | PARTIAL | LOW | Admin mobile N/A by design |
| Offers | WORKING | WORKING | PARTIAL | — | — | — | WORKING* | — | *QA-06: Web consumes `/offers`; “Coming soon” = upcoming campaigns only |
| Find Your Plant | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | QA-06 page + `/plant-finder/match` |
| Notifications inbox | WORKING | WORKING | WORKING | WORKING | WORKING | notifications | WORKING* | — | *Phase 11 + QA-22 dispatcher; LIVE FCM push BLOCKED without credentials |
| Push / FCM | WORKING* | PARTIAL* | PARTIAL* | PARTIAL* | PARTIAL* | user_devices | PARTIAL* | — | *QA-23: HTTP v1 gateway ready. Server/client Firebase credentials EMPTY/MISSING → REAL FCM **BLOCKED**. Stub/automated PASS. See `docs/QA-23-FCM-SETUP.md` |
| Profile | WORKING | WORKING | WORKING | N/A | N/A | — | WORKING | — | |

\*Login marked CRITICAL* as operator-reported failure class; root cause is usually environment, not field-name contract mismatch.

---

## Admin / ops

| Feature | API | Admin Web | Admin Mobile | Status | Severity | Notes |
|---------|-----|-----------|--------------|--------|----------|-------|
| Dashboard | WORKING | WORKING | WORKING | WORKING | — | QA-12: low-stock KPI SQL count |
| Orders | WORKING | WORKING | WORKING | WORKING | — | Mobile uses `/admin/orders` not always fulfillment; QA-12 list canReorder cheaper |
| Products CRUD | WORKING | WORKING | MISSING | PARTIAL | MEDIUM | Parity gap (Web primary) |
| Categories | WORKING | WORKING | MISSING | PARTIAL | LOW | |
| Inventory / adjust | WORKING | WORKING | WORKING | WORKING | — | QA-12: SQL pagination + indexes |
| Transfers | WORKING | WORKING | MISSING | PARTIAL | LOW | Documented Phase 19 |
| Warehouses / suppliers / PO | WORKING | WORKING | WORKING | WORKING | — | |
| Fulfillment pick/pack/ship | WORKING | WORKING | WORKING | WORKING | — | QA-05 API + QA-09 device hub/queue/detail PASS |
| Returns / refunds | WORKING | WORKING | MISSING | PARTIAL | MEDIUM | Admin Mobile intentional MISSING |
| Marketing / CRM | WORKING | WORKING | MISSING | PARTIAL | LOW | Web primary; QA-09 intentional |
| Analytics / reports | WORKING | WORKING | MISSING | PARTIAL | LOW | Web primary; QA-09 intentional; QA-PERF-010 inventory full-scan OPEN (assessed) |
| Users & roles UI | WORKING | WORKING | MISSING | WORKING* | — | *QA-08 Admin Web; Admin Mobile intentional MISSING (QA-09) |
| Settings / audit | WORKING | WORKING | MISSING | PARTIAL | LOW | |
| Notifications inbox | WORKING | WORKING | WORKING | WORKING | — | |

---

## Canonical order status map (API / DB)

`PENDING_PAYMENT` · `PAYMENT_FAILED` · `CONFIRMED` · `PROCESSING` · `PACKED` · `SHIPPED` · `OUT_FOR_DELIVERY` · `DELIVERED` · `DELIVERY_FAILED` · `CANCELLED` · `RETURN_REQUESTED` · `RETURNED` · `REFUNDED`

Shipment status strings often **lowercase** in DB (`shipped`, `out_for_delivery`) while order statuses are **UPPER_SNAKE**. Clients must not assume one casing for both.
