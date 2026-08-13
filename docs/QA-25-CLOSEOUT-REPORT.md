# QA-25 CLOSEOUT REPORT

**Date:** 2026-08-12  
**Verdict:** **PARTIAL** — credentials SET + Web UI integrated · full browser TEST charge **UNVERIFIED**  
**GREEN:** **NO** · **LIVE:** **NO**

---

## Why not COMPLETE

Credentials are **SET**. Customer Web UPI/Razorpay UI is integrated. A provider TEST `createOrder` smoke **PASS**. A human still must complete Razorpay TEST Checkout in the browser and confirm webhook → PAID (see `docs/QA-25-UI-CHECKOUT-REPORT.md`).

---

## Delivered

1. Credentials probe (SET/EMPTY only; no secret values)  
2. Confirmed `.env` gitignored; webhook route registered  
3. `Qa25RealTestPaymentGateTest` — 5 tests **PASS** (presence, live-key policy, webhook route, empty gate, COD+notify)  
4. Full QA-02→25 filter regression — **229 tests / 1114 assertions PASS**  
5. Customer Web + Admin Web unit — **PASS**  
6. Flutter customer + admin tests — see evidence below  
7. Documentation + register updates  
8. **No** payment/notification architecture redesign  

Local `--strict` exit **1** (correct for `APP_ENV=local`).

---

## Evidence (executed)

| Suite | Result |
|-------|--------|
| `Qa25RealTestPaymentGateTest` | **5 / 5 PASS** |
| PHPUnit filter Qa02–Qa25 + Phase | **229 / 1114 PASS** |
| nursery-web `test:unit` | **PASS** |
| nursery-admin `test:unit` | **PASS** |
| Customer Flutter `flutter test` | **PASS** (All tests passed) |
| Admin Flutter `flutter test` | **PASS** (All tests passed) |
| `composer audit` | **PASS** — no advisories |
| `nursery:production-readiness --strict` | exit **1** expected (local) |

| REAL Razorpay TEST | **BLOCKED** |
| LIVE payment | **BLOCKED** (out of scope) |

---

## Operator checklist (do locally — never paste secrets into chat)

1. Razorpay Dashboard → **Test Mode** → create API keys (`rzp_test_*` only).  
2. Create Test webhook secret; set events `payment.captured`, `order.paid` (+ failure if used).  
3. Put values **only** in `apps/nursery-api/.env` as `RAZORPAY_KEY` / `RAZORPAY_SECRET` / `RAZORPAY_WEBHOOK_SECRET`.  
4. Expose API with HTTPS tunnel or staging: `POST /api/v1/payments/webhooks/razorpay`.  
5. Point Razorpay Test webhook URL at that endpoint; keep `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`.  
6. `cd apps/nursery-api && php artisan config:clear` then `php artisan queue:work`.  
7. Verify presence with the probe in `docs/RAZORPAY-TEST-SETUP.md` (SET/EMPTY only).  
8. Re-run QA-25 real matrix: Customer Web + physical device (`API_BASE_URL=http://192.168.1.3:8000/api/v1`) UPI QR/Intent TEST charge → verify → webhook → admin visibility.  
9. Record only order id / Razorpay order id / payment id — never secrets.  

Until step 3 shows all three **SET**, QA-25 stays **BLOCKED**.

---

## QA-26 readiness

Ready to start when TEST credentials + reachable webhook exist and real TEST evidence is collected. TEST PASS ≠ LIVE PASS ≠ GREEN.
