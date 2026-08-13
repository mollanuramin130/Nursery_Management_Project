# QA-23 TEST MATRIX — Razorpay TEST Configuration

| # | Case | Result |
|---|------|--------|
| 1 | Env names `RAZORPAY_KEY/SECRET/WEBHOOK_SECRET` | PASS |
| 2 | Empty credentials → local_stub create | PASS |
| 3 | Initiate payload has no secret | PASS |
| 4 | Webhook route; empty secret + unsigned false → 503 | PASS |
| 5 | Production rejects `rzp_test_` key | PASS |
| 6 | Qa18–21 payment/idempotency/amount/IDOR | PASS (prior + filter) |
| 7 | Real TEST Dynamic QR payment | **BLOCKED** (keys EMPTY) |
| 8 | Real TEST UPI Intent payment | **BLOCKED** |
| 9 | Real TEST webhook receive | **BLOCKED** |
| 10 | Device TEST payment | **BLOCKED** |
| 11 | LIVE payment | **BLOCKED** |
| 12 | Production `--strict` on prod host | **UNVERIFIED** |

Webhook URL: `POST /api/v1/payments/webhooks/razorpay`
