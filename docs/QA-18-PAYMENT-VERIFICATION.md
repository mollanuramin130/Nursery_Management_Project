# QA-18 PAYMENT VERIFICATION

**Date:** 2026-08-12  
**Provider:** Razorpay (unchanged — no provider swap)

---

## 1. Credential probe (secrets not printed)

| Variable | Present? |
|----------|----------|
| RAZORPAY_KEY | **NO** |
| RAZORPAY_SECRET | **NO** |
| RAZORPAY_WEBHOOK_SECRET | **NO** |

---

## 2. LIVE / real gateway

| Check | Result |
|-------|--------|
| Real Razorpay order create | **BLOCKED** |
| Real payment success/fail/cancel | **BLOCKED** |
| Live webhook delivery | **BLOCKED** |
| Claim “Razorpay LIVE PASS” | **FORBIDDEN** — credentials unavailable |

Test-mode keys were also absent. Even if test keys appear later, **LIVE ≠ TEST**.

---

## 3. Automated payment path (local stub / signed webhook) — executed

Suite: `tests/Feature/Qa18ProductionPaymentTest.php` — **11 tests PASS** (part of 179 regression).

| Scenario | Result |
|----------|--------|
| Initiate → verify success → order CONFIRMED | **PASS** (local_stub) |
| Invalid signature → not paid | **PASS** |
| Duplicate webhook → idempotent | **PASS** |
| Invalid webhook signature → 401 | **PASS** |
| Amount mismatch → 409, not paid | **PASS** |
| payment.failed webhook → not CONFIRMED | **PASS** |
| COD regression + fulfill → DELIVERED | **PASS** |
| Production stub create refused | **PASS** |
| Cross-user initiate IDOR → 404 | **PASS** |
| Unauthenticated payment endpoints → 401 | **PASS** |
| Paid Razorpay path → fulfill → DELIVERED | **PASS** (stub) |
| Empty webhook secret + unsigned disallowed → 503 | **PASS** |

---

## 4. COD

**PASS** — unaffected; initiate refused on CONFIRMED COD (409).

---

## 5. Classification

| Layer | Status |
|-------|--------|
| Server payment safety automation | **PASS** |
| Razorpay LIVE | **BLOCKED / UNVERIFIED** |
| Paid public checkout go-live | **BLOCKED** |
