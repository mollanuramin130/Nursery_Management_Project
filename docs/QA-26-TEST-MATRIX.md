# QA-26 TEST MATRIX

**Date:** 2026-08-13 · Evidence payment **5526** / order **9062**

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Razorpay TEST payment success recorded | **PASS** | `payments.status=success`, paid_at 13:28:01 |
| 2 | Payment linked to correct order | **PASS** | `5526.order_id=9062` |
| 3 | Razorpay ids match | **PASS** | `pay_TPBCfrCIzFjCKz` / `order_TPBCV5HS098qBW` |
| 4 | Server verification succeeded | **PASS** | log `payment.verify.succeeded`; `provider_signature` present |
| 5 | Webhook received | **PASS** | logs `order.paid` + `payment.captured` |
| 6 | Webhook signature verified | **PASS** | secret SET; unsigned false; INFO logged only after HMAC check |
| 7 | Order paid/confirmed state | **PASS** | `orders.status=CONFIRMED` |
| 8 | Inventory committed once | **PASS** | one `sale` per line at 13:28:01; no duplicates |
| 9 | Customer sees paid order | **PASS** | `GET /orders/9062` → CONFIRMED + payment success |
| 10 | Admin sees payment/order | **PASS** | `GET /admin/orders/9062` → success + provider payment id |
| 11 | Payment-success notification | **PASS** | DB `payment_confirmed` + `order_confirmed` |
| 12 | Idempotency | **PASS** | single payment row; second webhook after success; single sales |
| 13 | COD regression | **PASS** | Qa26 COD test in suite |
| 14 | Payment/full regression | **PASS** | 233 / 1120 (231+2 skip) |
| 15 | LIVE / GREEN | **BLOCKED** | out of scope |
| 16 | LIVE FCM push | **UNVERIFIED** | Firebase EMPTY |
| 17 | Interactive Web UI re-smoke | **UNVERIFIED** | API verified |
| 18 | Dynamic QR / Intent physical charge | **UNVERIFIED** | this success was checkout mode |
