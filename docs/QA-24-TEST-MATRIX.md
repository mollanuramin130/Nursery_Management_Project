# QA-24 TEST MATRIX — Real Razorpay TEST

| # | Case | Result |
|---|------|--------|
| 1 | Credentials presence (no values logged) | PASS (EMPTY) |
| 2 | Reject `rzp_live_` in QA-24 policy | PASS (N/A until SET) |
| 3 | Empty keys → stub initiate, no secret leak | PASS |
| 4 | Amount mismatch initiate → 409 | PASS |
| 5 | COD + customer/staff notification | PASS |
| 6 | Explicit BLOCKED gate when empty | PASS |
| 7 | Qa18–21 payment/idempotency/webhook simulation | PASS |
| 8 | Qa11 security | PASS (in filter) |
| 9 | Real TEST UPI success | **BLOCKED** |
| 10 | Real TEST failure/cancel | **BLOCKED** |
| 11 | Real TEST webhook | **BLOCKED** |
| 12 | Device TEST payment | **BLOCKED** |
| 13 | Customer Web real TEST | **BLOCKED** |
| 14 | Admin real payment visibility | **BLOCKED** |

Webhook route (existing): `POST /api/v1/payments/webhooks/razorpay`  
Setup: `docs/RAZORPAY-TEST-SETUP.md`
