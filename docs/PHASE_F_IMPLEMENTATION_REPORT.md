# Phase F — Implementation Report

**Date:** 2026-08-11  
**Status:** Implemented (local stub + sandbox-ready Razorpay; production requires live keys)

---

## 1–3. Existing architectures

Checkout/order/payment modules already existed (`CheckoutService`, `PaymentService`, `RazorpayGateway`). Flow: preview → place (`PENDING_PAYMENT` or COD `CONFIRMED`) → initiate → verify/webhook → confirm. Address snapshots and inventory reserve/commit/release were already in place.

## 4–5. Database audit / changes

**Reused:** `orders`, `order_items`, `payments`, `shipping_methods`, `coupons`, carts, inventory.  
**Added:** `coupon_redemptions`; unique `payments.provider_payment_id`.  
**Files:** Laravel migration `2026_08_11_180000_phase_f_checkout_payment.php`, `database/phase_f_checkout_payment.sql`.

## 6–10. API changes

| Change | Detail |
|--------|--------|
| Cart clear timing | Cleared only on COD confirm or payment success |
| Unpaid place | Cart retained; block second `PENDING_PAYMENT` |
| Order idempotency | Same `X-Request-Id` returns existing order |
| Free delivery | Checkout shipping `0` when threshold met |
| Coupon usage | Enforced + recorded on confirm |
| Initiate | Top-level `provider_order_id`; COD initiate rejected |
| Verify | Order-id match, amount check, returns `order` object |
| Retry | `POST /orders/{id}/retry-payment` |
| Status | `GET /payments/{id}` |
| Webhook | Secret required in production |
| Stub | Forbidden in production |

## 11–12. Gateway + verification

Razorpay only. Server creates order amount from `order.grand_total`. HMAC verify with `RAZORPAY_SECRET`. Local stub for empty keys in non-production. Webhook HMAC with `RAZORPAY_WEBHOOK_SECRET`.

## 13. Idempotency

- Order: `X-Request-Id`  
- Payment verify: already-success short-circuit  
- Unique `provider_payment_id`  
- Webhook: skip if payment already success  

## 14–16. Stock / coupon / cart

Reserve on place → commit on success → release on fail/cancel unpaid.  
Coupon redemption only on confirm.  
Cart not cleared on fail/cancel unpaid.

## 17–19. Website / Android / success

- Website: Razorpay Checkout.js; removed fake blind success; order detail pending/fail/retry  
- Android: `razorpay_flutter` + local stub path; pending/paid/retry on order detail  
- Success only after verify returns `CONFIRMED`

## 20–21. Failure / retry

Cancel/fail keeps cart. Retry-payment reopens gateway. Cancel unpaid releases stock.

## 22. Security fixes

Prod stub/webhook gates; client amount ignored; secrets server-side; ownership checks; unique gateway payment id.

## 23–28. Tests

API smoke `phase_f_checkout_smoke.php` — all PASS (preview, cart retain, initiate amount, verify, idempotent verify, COD, cart clear).  
`tsc` clean; `flutter analyze` clean.  
Sandbox with real Razorpay test keys: configure per `PAYMENT_CONFIGURATION.md` (not run here — keys empty).

## 29. Remaining limitations

- No automatic timeout job for abandoned `PENDING_PAYMENT` (cancel manually)  
- Tax still `0` (by design until tax rules added)  
- Real sandbox E2E requires dashboard test keys + webhook URL  
- Order number sequence still app-level (low concurrency risk)

## 30. Production deployment requirements

1. Set live/test Razorpay env vars on API host only  
2. Configure webhook URL + secret  
3. `APP_ENV=production`  
4. HTTPS clients  
5. Run `php artisan migrate`  
6. Smoke COD + one sandbox online payment before cutover  

---

## Definition of Done (summary)

- [x] Server cart / address / shipping / coupon / stock / totals  
- [x] Razorpay integrate (stub + real path)  
- [x] Server verify + webhook hardening  
- [x] Idempotency + cart clear rules  
- [x] Website + Android unified API  
- [x] Docs + SQL + config guide  
- [ ] Live sandbox payment with real test keys (ops step when keys available)
