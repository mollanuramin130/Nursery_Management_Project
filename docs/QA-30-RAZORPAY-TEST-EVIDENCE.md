# QA-30 — Razorpay TEST Evidence (Device)

**Date:** 2026-08-13  
**Mode:** TEST only (`rzp_test_*`) · **LIVE:** OUT OF SCOPE  
**Device:** vivo 1951 / `2d3714f` · API via `adb reverse tcp:8000 tcp:8000`

Secrets are **not** recorded in this file.

---

## Transaction (authoritative)

| Field | Value |
|-------|--------|
| GreenLeaf Order ID | **9066** |
| Order number | **ORD-20260813-00010** |
| Amount | **₹49.00** |
| Product | Coriander Seeds Pack × 1 |
| Customer | Asha Kumar (`asha@example.com`) |
| Payment DB ID (success) | **5530** |
| Razorpay Order ID | **order_TPDlYGn0iAIT1e** |
| Razorpay Payment ID | **pay_TPDwnubVyTYAiG** |
| Final payment status | **success** |
| Final order status | **CONFIRMED** |
| Inventory sales (`stock_movements.type=sale`) | **1** |

Superseded / failed attempt on same order (expected retry, not duplicate success):

| Payment ID | Status | Notes |
|------------|--------|--------|
| 5529 | failed | Initial UPI Intent initiate (`order_TPDjp1dT4KR3I1`) — abandoned |
| 5530 | success | Checkout retry → Netbanking TEST Success |

---

## Device path executed

1. Customer Mobile checkout → **UPI** → UPI app chooser (PhonePe / PNB ONE / GPay) opened (**UPI Intent launch PASS**).
2. Chooser dismissed / poll abandoned; order left `PENDING_PAYMENT`.
3. Order detail **Pay ₹49** → Razorpay Checkout (TEST).
4. Contact details entered → **Cards** rejected (`International cards are not supported` — TEST Visa `4111…`).
5. **Netbanking** → Bank of Baroda → Razorpay demo bank → **Success**.
6. Webhooks received (signed TEST tunnel): `payment.failed` (earlier card attempt), then `payment.captured` + `order.paid` for `pay_TPDwnubVyTYAiG`.

---

## Webhook / verify evidence (non-secret log lines)

```
[2026-08-13 16:07:57] payment.webhook event=payment.failed provider_order_id=order_TPDlYGn0iAIT1e
[2026-08-13 16:09:23] payment.webhook event=payment.captured provider_order_id=order_TPDlYGn0iAIT1e provider_payment_id=pay_TPDwnubVyTYAiG
[2026-08-13 16:09:23] payment.verify.succeeded payment_id=5530 order_id=9066 order_number=ORD-20260813-00010
[2026-08-13 16:09:23] payment.webhook event=order.paid provider_order_id=order_TPDlYGn0iAIT1e provider_payment_id=pay_TPDwnubVyTYAiG
```

---

## QA-30-001 interaction (honest)

Immediately after capture, payment **5530=success** while order remained **PAYMENT_FAILED** because an earlier `payment.failed` webhook had already flipped the order, and `finalizeSuccess` only confirmed from `PENDING_PAYMENT`.

**Fix:** recover `PAYMENT_FAILED` → re-reserve → commit → `CONFIRMED` (see `PaymentService` + state machine). Regression: `Qa30PaymentFailedThenCapturedRecoveryTest`.

After fix, order **9066** confirmed; Customer Mobile shows **Order confirmed**; Admin Mobile lists **ORD-20260813-00010 · CONFIRMED**.

---

## Dynamic QR

Customer Mobile does **not** expose a separate Dynamic QR checkout mode (Web does). Marked **UNVERIFIED / ACCEPTED DIFFERENCE** — no fabricated QR charge.
