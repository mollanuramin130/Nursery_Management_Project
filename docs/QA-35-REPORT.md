# QA-35 REPORT — Comprehensive TEST Bug / Parity / UI Uniformity / Stability Audit

**Date:** 2026-08-13  
**Verdict:** **COMPLETE**  
**LIVE payment / LIVE FCM / GREEN:** **OUT OF SCOPE / NO**

---

## Phase 1 — Baseline (no code changes)

| Suite | Result |
|-------|--------|
| PHPUnit QA filter | **242 tests / 1184 assertions** (240 pass + 2 skipped) — matches QA-34 |
| Customer/Admin Web unit | **PASS** |
| Flutter Customer UPI + Admin suite | **PASS** |
| Web/Admin production build | **PASS** |
| Flutter analyze | **PASS** (info-only pre-existing) |
| BFF readiness / smoke | **PASS** |

---

## Audit summary

Fresh audit across API + four clients found **six genuine defects** (deep link, pending-payment copy, admin transition labels, paid-query banner, payment status labels, returns error empty-state). Fixed with regression coverage. No BFF/payment architecture redesign. QA-SEC-001 remains **CLOSED**.

---

## Commerce / payment / security (TEST)

| Area | Result |
|------|--------|
| COD / order lifecycle | **PASS** (suite + prior 9069) |
| Razorpay TEST automated | **PASS** (`rzp_test_*`; Qa18–Qa31) |
| Payment amount authority / idempotency / webhook | **PASS** |
| Dynamic QR generation | **PASS** (prior) / settlement **UNVERIFIED** |
| UPI Intent handoff | **PASS** (prior) / settle **BLOCKED** |
| IDOR / CSRF / HttpOnly BFF | **PASS** |
| Concurrency suites | **PASS** (existing payment/order tests) |

---

## Cross-client parity & UI

| Item | Result |
|------|--------|
| Order status badges | **PASS** (canonical labels) |
| PENDING_PAYMENT customer copy | **PASS** after QA-35-002 |
| Payment status display | **PASS** after QA-35-005 |
| Admin transition UI labels | **PASS** after QA-35-003 |
| Admin Mobile returns deep link | **PASS** after QA-35-001 |
| Intentional Admin Mobile scope | **QA-ADM-002** unchanged |

---

## Final regression (after fixes)

| | Tests | Assertions | Skipped |
|--|------:|-----------:|--------:|
| QA-34 / baseline | 242 | 1184 | 2 |
| QA-35 final | **243** | **1190** | 2 |
| Delta | **+1** | **+6** | 0 |

New PHPUnit: `Qa35ParityAndTimelineTest`. Admin Flutter **27** tests (+2). Builds/BFF smoke **PASS**.

---

## Database / API

- **DB schema:** none  
- **API:** timeline title string only (`Order placed` for PENDING_PAYMENT) — non-breaking customer-facing copy  

---

## Device

- `2d3714f` connected; `adb reverse`; Customer + Admin APK launch **PASS**  
- Full interactive UI re-walk of every screen: **PARTIAL** (targeted fixes + prior QA-29–34 device evidence)
