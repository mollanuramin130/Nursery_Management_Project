# QA-10 Test Matrix — Cross-Platform Integration

**Date:** 2026-08-12 · **Phase:** QA-10

Legend: **PASS** · **FAIL** · **PARTIAL** · **UNVERIFIED** · **BLOCKED** · **N/A**

---

## A. Automated API (PHPUnit)

| Test | Result |
|------|--------|
| `test_same_cod_order_visible_to_customer_and_admin_with_matching_totals` | PASS |
| `test_admin_status_transition_propagates_to_customer_order` | PASS |
| `test_shipped_creates_lowercase_shipment_status_while_order_is_upper` | PASS |
| `test_idempotent_place_order_and_customer_cannot_hit_admin` | PASS |
| `test_cancel_propagates_and_invalid_transition_is_409` | PASS |
| Suite `Qa10CrossPlatformIntegrationTest` | **PASS** (5/5) |

---

## B. Regression (mandatory)

| Filter | Result |
|--------|--------|
| `Qa10\|Qa09\|Qa08\|Qa07\|Qa05\|Qa04\|Qa03\|Qa02` | **PASS** (49 tests, 238 assertions) |

---

## C. Live LAN golden journey (API dual sessions)

Environment: `http://192.168.1.3:8000/api/v1` · Customer `asha@example.com` · Admin `admin@nursery.test`

| Step | Result | Evidence |
|------|--------|----------|
| Health ready | PASS | `database/cache healthy` |
| Login cust web + android sessions | PASS | Dual JWTs |
| Login admin web + android sessions | PASS | Dual JWTs |
| Cart add (web) → read (mobile) | PASS | Shared cart |
| Qty update (mobile) → read (web) | PASS | qty=2 |
| Place COD | PASS | `ORD-20260812-00013` CONFIRMED |
| Idempotent `X-Request-Id` | PASS | Same order id |
| Order visible cust web/mobile | PASS | Matching number/totals |
| Order visible admin web/mobile | PASS | Matching number/totals |
| Status PROCESSING→PACKED→SHIPPED | PASS | All sessions agree after GET |
| Shipment casing | PASS | order `SHIPPED`, shipment `shipped` |
| Customer → admin API | PASS | 403 |
| Cancel + invalid 409 | PARTIAL | PHPUnit PASS; live cancel retry hit auth throttle after golden path |
| Returns list both cust sessions | PASS | `/customer/returns` |
| Reorder live | UNVERIFIED | — |
| Concurrent 4-UI device | UNVERIFIED | Device present; UI not driven |

---

## D. Auth / RBAC / Payment

| Item | Result |
|------|--------|
| Dual-session auth | PASS |
| RBAC 403 | PASS |
| QA-09 EnsurePermission OR | PASS (regression) |
| QA-SEC-001 | OPEN (not in scope to fix) |
| Razorpay live | UNVERIFIED |

---

## E. Clients

| Client | Code changed | Tests run this phase |
|--------|--------------|----------------------|
| Customer Web | No | N/A (no code change) |
| Customer Mobile | No | N/A |
| Admin Web | No | N/A |
| Admin Mobile | No | N/A |
| API | Test only | PASS |

---

## F. Database

| Item | Result |
|------|--------|
| Schema change | None |
| Proposal required | No |
