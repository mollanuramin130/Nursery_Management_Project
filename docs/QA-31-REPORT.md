# QA-31 REPORT — TEST-Only UPI Completion + Payment UX Consistency

**Date:** 2026-08-13  
**Verdict:** **PARTIAL** (TEST scope complete for what the environment allows)  
**GREEN:** **NO** · **LIVE Razorpay / LIVE FCM / production:** **OUT OF SCOPE**

**Baseline:** QA-30 COMPLETE — device Razorpay TEST PAID **9066** / `pay_TPDwnubVyTYAiG`.

---

## Executive summary

QA-31 verified Web **Dynamic QR generation**, Mobile **UPI Intent launch** with correct amount/order into GPay, payment hardening (amount authority on pending reuse, failed→retry, idempotency), and UPI terminology consistency. **True UPI-app settlement** and **QR scan payment** remain **BLOCKED / UNVERIFIED** due to device/account and TEST scan constraints — not fabricated as PASS.

---

## Scorecard

| Area | Result |
|------|--------|
| QA-31 STATUS | **PARTIAL** |
| RAZORPAY TEST | **PASS** (config + prior charge; no new LIVE) |
| WEB CHECKOUT | **PASS** |
| WEB DYNAMIC QR | **PARTIAL** (generate PASS · settle UNVERIFIED) |
| MOBILE UPI INTENT | **PARTIAL** (launch PASS · settle BLOCKED) |
| TRUE UPI-APP SETTLEMENT | **BLOCKED** |
| PAYMENT VERIFICATION | **PASS** |
| WEBHOOK | **PASS** (architecture + prior/idempotency tests) |
| INVENTORY SINGLE-COMMIT | **PASS** |
| FAILED → RETRY | **PASS** |
| IDEMPOTENCY | **PASS** |
| AMOUNT SECURITY | **PASS** (after QA-31-001) |
| CUSTOMER WEB | **PASS** |
| CUSTOMER MOBILE | **PASS** |
| ADMIN WEB | **PASS** |
| ADMIN MOBILE | **PASS** |
| CROSS-CLIENT PARITY | **PASS** (9066) |
| UPI UI UNIFORMITY | **PASS** |
| MOBILE-DATA | **UNVERIFIED** |
| NOTIFICATIONS | **PASS** (in-app/stub) · LIVE FCM out of scope |
| SECURITY | **PARTIAL** — QA-SEC-001 OPEN |
| BUILD | **PASS** |
| REGRESSION | **240 tests / 1170 assertions** (238 pass + 2 skipped) |

---

## Fixes

1. **QA-31-001** — Reject client amount override before pending payment reuse.  
2. **QA-31-002** — Customer Mobile “UPI / Razorpay” label aligned with Web terminology.

---

## UNVERIFIED / BLOCKED

| Item | Status | Why |
|------|--------|-----|
| Dynamic QR scan settle | UNVERIFIED | QR generated; no completed TEST scan charge |
| True UPI-app settle | BLOCKED | GPay: no payment account for TEST intent |
| Mobile-data w/o reverse | UNVERIFIED | Device cannot reach Mac LAN `:8000` |

---

## Next phase

**QA-32** — Only if a TEST-capable UPI account/device becomes available for Intent/QR settle; otherwise pivot to LIVE readiness gates (still no GREEN without QA-27 + QA-SEC-001). Do not claim GREEN.
