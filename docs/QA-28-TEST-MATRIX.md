# QA-28 TEST MATRIX

**Date:** 2026-08-13  
**Environment:** Local TEST only · Razorpay TEST keys · No LIVE charge · No LIVE FCM

Legend: **PASS** / **FAIL** / **PARTIAL** / **BLOCKED** / **UNVERIFIED** / **ACCEPTED** / **N/A**

---

## A. Application inventory smoke

| App | Reachability / build | Notes |
|-----|----------------------|-------|
| Laravel API | PASS | health ~60ms; products list ~47ms |
| Customer Web | PASS | HTTP 200; `npm run build` PASS (includes returns `[id]`) |
| Admin Web | PASS | HTTP 200; `npm run build` PASS |
| Customer Mobile | PARTIAL | `flutter test` PASS; `flutter analyze` info-only; device UI smoke UNVERIFIED this session |
| Admin Mobile | PARTIAL | unit tests PASS; analyze clean on fixed files; device UI smoke UNVERIFIED |
| MySQL / migrations | PASS | No schema change required; QA-26 order/payment rows remain baseline |

---

## B. Customer Web ↔ Customer Mobile

| Flow | Classification | Notes |
|------|----------------|-------|
| Login / Register / Forgot-Reset | MATCH | API shared; UI platform-specific |
| Home / Categories / Product list-detail / Search | MATCH | Same catalog APIs |
| Wishlist / Cart / Coupon | MATCH | |
| Address / Shipping / Checkout preview | MATCH | |
| COD | PASS (API/Web) · Mobile device UNVERIFIED | Architecture unchanged |
| UPI / Razorpay Checkout | ACCEPTED DIFFERENCE | Web modes; Mobile `upi_intent` default |
| Dynamic QR / UPI Intent | ACCEPTED DIFFERENCE | Web selectable; Mobile intent-first |
| Payment verify / confirmation | MATCH (API) · UI PARTIAL device | QA-26 baseline |
| Order history / detail / cancel / return | MATCH after QA-28-001/003 | Web return detail added |
| Notifications deep links | PASS (Web route) · Mobile unit PASS | FCM LIVE N/A |
| Profile / Logout / Session refresh | FIXED QA-28-002 | Mobile stale user cleared |

---

## C. Admin Web ↔ Admin Mobile

| Area | Classification | Notes |
|------|----------------|-------|
| Login / session | MATCH | |
| Dashboard / Orders / Inventory / Fulfillment | MATCH / ops subset | |
| Order status transitions UX | FIXED QA-28-004 | Now gated like Web |
| Products / Categories / Campaigns / Reports / Returns UI | INTENTIONAL (QA-ADM-002) | Admin Web only |
| Notifications deep links | FIXED QA-28-005 | Returns → order |
| Transfers tile | FIXED QA-28-006 | Stock movements |
| Status filters | FIXED QA-28-007 | Full lifecycle |

---

## D. Payment TEST (Razorpay TEST only)

| Case | Result | Evidence |
|------|--------|----------|
| Real TEST success (Checkout) | PASS | QA-26: pay_TPBCfrCIzFjCKz / payment 5526 / order 9062 |
| COD | PASS | Prior QA + architecture intact |
| Webhook signed | PASS | QA-26 |
| Duplicate webhook / verify | PASS (automated gates prior) | No new LIVE claim |
| Dynamic QR LIVE | N/A | TEST-only; not LIVE evidence |
| UPI Intent LIVE | N/A | |
| LIVE credentials | BLOCKED / OUT OF SCOPE | Local remains `rzp_test_*` |

---

## E. Order lifecycle

| Transition family | Result | Notes |
|-------------------|--------|-------|
| PLACED→…→DELIVERED edges | PASS (API machine) | `Qa28ConsistencyGateTest` + prior feature tests |
| CANCELLED / RETURN / REFUND paths | PASS (API) · UI PARTIAL device | |
| Inventory single sale on pay | PASS | QA-26 |
| Admin Mobile invalid transition UX | FIXED | Buttons gated |

---

## F. Notifications

| Direction | Result | Notes |
|-----------|--------|-------|
| In-app list / unread | PASS (prior + architecture) | |
| Deep links customer return | FIXED | Web detail page |
| Deep links admin return | FIXED | Orders fallback |
| FCM LIVE delivery | OUT OF SCOPE / UNVERIFIED | |

---

## G–H. Uniformity & API contracts

| Check | Result |
|-------|--------|
| Order status labels | PASS after QA-28-003 |
| COD cancel payment copy | PASS after Web align |
| payment_method / status enums | PASS (clients share API) |
| Web UPI mode picker vs Mobile | ACCEPTED DIFFERENCE |

---

## I–K. Errors / security / idempotency

| Check | Result |
|-------|--------|
| Client safe error mapping | PASS (prior QA-02+) |
| QA-SEC-001 | OPEN |
| Stub payment production refuse | PASS (prior) |
| Duplicate payment/webhook | PASS (prior automated) |
| IDOR / ownership | PASS (prior security suites) · not re-claimed 100% |

---

## L. Real UI smoke (this session)

| Surface | Result |
|---------|--------|
| Customer Web HTTP + build | PASS |
| Admin Web HTTP + build | PASS |
| Customer Mobile interactive device | UNVERIFIED |
| Admin Mobile interactive device | UNVERIFIED |
| Checkout payment interactive re-run | UNVERIFIED (QA-26 baseline retained) |

---

## M. Performance (local lightweight)

| Endpoint | HTTP | Latency |
|----------|------|---------|
| `/api/v1/health` | 200 | ~0.06s |
| `/api/v1/products?per_page=12` | 200 | ~0.05s |

No production load test.

---

## N. Build / quality

| Suite | Result | Classification |
|-------|--------|----------------|
| PHPUnit full | **244 tests / 1143 assertions** (242 pass + 2 skipped) | NEW +2 tests vs QA-27 240/1133 |
| Customer Web qa-unit-checks | PASS | |
| Customer Web build | PASS | |
| Admin Web build | PASS | |
| Customer Flutter tests | PASS | |
| Customer Flutter analyze | PARTIAL | 10 info PRE-EXISTING / NON-BLOCKING |
| Admin Flutter tests | PASS | |
| Admin Flutter analyze (fixed files) | PASS | |
| composer audit | PASS (clean) | |
| npm audit (web/admin prod) | PASS (0 vulns) | |

---

## O. Database consistency

| Check | Result |
|-------|--------|
| Order/payment/inventory from QA-26 | PASS (baseline retained; no schema change) |
| Duplicate business records on re-verify | PASS (prior idempotency) |
