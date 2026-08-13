# QA-30 REPORT — TEST-Only Payment Completion + Remaining Device Bugs

**Date:** 2026-08-13  
**Project:** GreenLeaf Nursery Platform  
**Verdict:** **COMPLETE** (TEST scope) · **GREEN:** **NO** · **LIVE:** **OUT OF SCOPE**

**Device:** vivo 1951 / `2d3714f` · Working API path: `adb reverse tcp:8000` + `API_BASE_URL=http://127.0.0.1:8000/api/v1`

---

## Executive summary

QA-30 completed **one real Razorpay TEST payment on the physical Vivo device**, fixed wishlist / image / payment-recovery defects with regression tests, and closed previously UNVERIFIED logout + search smokes. LIVE Razorpay, LIVE FCM, and production `--strict` remain out of scope. No GREEN claim.

Primary charge evidence: **Order 9066** / `ORD-20260813-00010` / **₹49** / payment **5530** / `pay_TPDwnubVyTYAiG` → **CONFIRMED** (see `docs/QA-30-RAZORPAY-TEST-EVIDENCE.md`).

---

## Scorecard

| Area | Result |
|------|--------|
| QA-30 STATUS | **COMPLETE** |
| DEVICE | Vivo 1951 / `2d3714f` |
| RAZORPAY TEST | **PASS** |
| REAL DEVICE TEST PAYMENT | **PASS** |
| PAYMENT VERIFICATION | **PASS** |
| WEBHOOK | **PASS** |
| INVENTORY SINGLE-COMMIT | **PASS** |
| CUSTOMER MOBILE | **PASS** |
| ADMIN MOBILE | **PASS** |
| CUSTOMER WEB | **PASS** (API parity + prior) |
| ADMIN WEB | **PASS** (API parity + prior) |
| DYNAMIC QR | **UNVERIFIED** (not exposed on Mobile) |
| UPI INTENT | **PARTIAL** (chooser PASS; full UPI-app settle UNVERIFIED — paid via Checkout Netbanking) |
| WISHLIST | **PASS** |
| IMAGE CONSISTENCY | **PASS** |
| LOGOUT | **PASS** (Customer + Admin Mobile) |
| SEARCH | **PASS** |
| ORDER LIFECYCLE | **PASS** (device COD QA-29 + this TEST online confirm) |
| NOTIFICATIONS | **PARTIAL** (in-app / local stub FCM; LIVE FCM BLOCKED) |
| CROSS-CLIENT PARITY | **PASS** for order 9066 |
| SECURITY | **PARTIAL** — QA-SEC-001 OPEN |
| BUILD | **PASS** (info-level Flutter analyze only) |
| REGRESSION | **238 tests / 1153 assertions** (236 pass + 2 skipped) |

---

## Fixes

1. **QA-29-003 Wishlist** — soft-delete unique conflict → restore path (`WishlistService`).
2. **QA-29-004 Images** — distinct Tulsi asset URL + repair script/sample SQL.
3. **QA-30-001 Payment recovery** — `payment.failed` then `payment.captured` must CONFIRM order + single inventory commit.
4. **QA-30-002 Checkout fallback** — poll UPI only when intent launch succeeds.

---

## UNVERIFIED / BLOCKED

| Item | Why |
|------|-----|
| Dynamic QR charge | Mobile UI does not expose QR mode |
| UPI Intent settle inside UPI app | Chooser opened; charge completed via Checkout Netbanking TEST Success |
| Pure mobile-data (no adb reverse) | Host LAN often AP-isolated |
| LIVE Razorpay / LIVE FCM / prod `--strict` | Explicitly out of scope |

---

## Security

- Webhook signature validation exercised (invalid unsigned probe → 401; signed capture accepted).
- No secrets printed in docs or logs excerpts.
- **QA-SEC-001** remains **OPEN**.

---

## Next phase recommendation

**QA-31** — Optional: UPI Intent settle end-to-end on a device with TEST-friendly UPI; Dynamic QR on Web only; LIVE readiness remains blocked until QA-27 gates + QA-SEC-001 addressed. Do not claim GREEN.
