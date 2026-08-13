# QA-25 UI — Razorpay TEST Checkout Integration (Customer Web)

**Date:** 2026-08-13  
**Status:** **PARTIAL** — UI + provider order create **PASS**; full browser TEST charge **UNVERIFIED** (needs operator in Checkout)  
**GREEN / LIVE:** **NO**

---

## Root cause

UPI was already in `PaymentBlock`, but:

1. Label said only “UPI” (not “UPI / Razorpay”) — easy to miss.
2. Initiate always used `mode=dynamic_qr`, so Razorpay Checkout.js rarely opened.
3. Mobile review step did not restate the selected payment method.

Credentials are now **SET** (`rzp_test_*`); unsigned webhooks **false**.

---

## Changes

- Customer Web checkout: COD + **UPI / Razorpay**, with Checkout / Dynamic QR / UPI Intent options
- Default initiate mode: `checkout` (existing API)
- Wait UI: Pay with Razorpay / Open UPI app / poll status
- Helpers + unit checks in `upi-payment.ts` / `qa-unit-checks.ts`
- PHPUnit isolation: `ClearsRazorpayEnv` (clear `$_SERVER` so stub tests stay stub)

**No** payment architecture redesign. **No** secrets in frontend.

---

## Evidence

| Item | Result |
|------|--------|
| Env KEY/SECRET/WEBHOOK | SET (test) |
| `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS` | false |
| Web unit | PASS |
| Web build | PASS |
| Provider createOrder smoke | PASS (`order_TPAGn4N7RMRVkD`) |
| Full browser TEST payment + webhook finalize | **UNVERIFIED** |
| Secrets in NEXT_PUBLIC / bundles | none found |

---

## Operator: complete real TEST charge

1. Ensure API + queue running; webhook URL still the Cloudflare tunnel → `/api/v1/payments/webhooks/razorpay`
2. Customer Web → Checkout → **UPI / Razorpay** → Razorpay Checkout → complete Razorpay **TEST** UPI
3. Confirm order PAID in account + admin; record Order ID / Razorpay order id / payment id only
