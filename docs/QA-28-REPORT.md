# QA-28 REPORT — TEST-ONLY Full System QA

**Date:** 2026-08-13  
**Project:** GreenLeaf Nursery Platform  
**Phase status:** **COMPLETE** (TEST environment quality audit)  
**GREEN / LIVE / LIVE FCM:** **NO / OUT OF SCOPE / OUT OF SCOPE**

---

## 1. Scope decision (honored)

| In scope | Out of scope |
|----------|--------------|
| Local DEV | Razorpay LIVE credentials / LIVE charge |
| Razorpay TEST + TEST webhook | Production `--strict` PASS requirement |
| COD + existing TEST payment architecture | Production HTTPS / hosting / backup / monitoring evidence |
| Bugs, parity, contracts, uniformity | Redesign of payment/notification architecture |
| Minimal fixes + regression | Claiming GREEN production release |

QA-26 real TEST payment remains the **only** real PSP success baseline.  
QA-27 LIVE readiness remains **NOT READY** — not re-opened as a blocker for QA-28.

---

## 2. Preflight

| Check | Result |
|-------|--------|
| API / Customer Web / Admin Web HTTP | 200 |
| `RAZORPAY_KEY` | SET · `rzp_test_*` |
| `RAZORPAY_SECRET` / webhook secret | SET (values not printed) |
| LIVE key present locally | NO (correct for this phase) |

---

## 3. Inventory (six components)

Audited: Laravel API, Customer Web, Customer Mobile, Admin Web, Admin Mobile, MySQL.  
Unit-test PASS was **not** treated as UI parity proof. Device interactive smoke for both Flutter apps is **UNVERIFIED** this session.

---

## 4. Parity findings

### Customer Web ↔ Mobile
Core commerce APIs **MATCH**. Platform UX differences for UPI mode selection are **ACCEPTED**.  
Genuine defects fixed: return deep-link 404 (Web), session refresh stale user (Mobile), status label inconsistency, COD cancel payment wording.

### Admin Web ↔ Mobile
**QA-ADM-002** remains intentional PARTIAL (ops subset).  
Genuine defects fixed: ungated status buttons, incomplete filters, broken return deep link, misleading Transfers tile, payment `Text(Column)` type error.

---

## 5. Payment / order / notifications

| Area | Verdict |
|------|---------|
| Razorpay TEST | **PASS** (QA-26 baseline; not re-charged this phase) |
| COD | **PASS** (architecture + prior evidence) |
| Order lifecycle machine | **PASS** (API + `Qa28ConsistencyGateTest`) |
| Notifications architecture | **PARTIAL** (deep links fixed; FCM LIVE out of scope) |
| Security | **PARTIAL** (QA-SEC-001 OPEN) |

---

## 6. Fixes shipped (minimal)

1. Customer Web return detail page + list links  
2. Shared order status labels (Web) + Mobile list align + COD copy  
3. Customer Mobile `onSessionExpired` → clear local user  
4. Admin Mobile order transition gates + filters + deep links + stock movements label  
5. Admin Mobile payment block compile/type fix  
6. `Qa28ConsistencyGateTest` (2 tests)

See `docs/QA-28-BUG-REGISTER.md`.

---

## 7. Regression

| Metric | QA-27 | QA-28 |
|--------|-------|-------|
| PHPUnit tests | 240 | **244** |
| Assertions | 1133 | **1143** |
| Passed / skipped | 238 / 2 | **242 / 2** |

Additional: Customer + Admin Web builds PASS; Flutter unit tests PASS; composer/npm audits clean.

---

## 8. Honest gaps (UNVERIFIED)

- Interactive Customer/Admin Mobile device smoke this session  
- Fresh interactive Checkout + Razorpay TEST charge re-run (QA-26 evidence retained)  
- Full manual order-lifecycle click-through on all four UIs  
- LIVE FCM delivery  
- Production load / N+1 at scale  

---

## 9. Next phase recommendation

**QA-29** should focus on **device UI smoke + optional second TEST payment path coverage** (Dynamic QR / Intent on Web) and/or **QA-SEC-001 design execution** — **not** LIVE Razorpay until a production host passes `--strict` (QA-27 gates unchanged).
