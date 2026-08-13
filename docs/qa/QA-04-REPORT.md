# QA-04 Report — Checkout + Payment + Order Creation

**Date:** 2026-08-12  
**Status:** COMPLETE (PHPUnit PASS; Web TypeScript check PASS)

---

## 1. Objective

Ensure checkout never places an order on incomplete totals; ensure `local_stub` payment cannot succeed in production (API + Customer Web).

## 2. Bugs addressed

| ID | Result |
|----|--------|
| QA-CHK-001 | **FIXED / VERIFIED** — Web blocks place-order until preview succeeds; no cart-total fallback |
| QA-PAY-001 | **FIXED / VERIFIED** — `localStubPayment` throws in production site builds |
| QA-PAY-002 | **MITIGATED / VERIFIED** — API already refused stub in production; pending-reuse path hardened; local still allowed |

## 3. Root causes

1. Checkout UI used `preview?.grand_total ?? cart?.grand_total` and allowed submit without successful preview.  
2. Web accepted `mode: local_stub` without a production guard (Flutter already blocked in release).  
3. Backend gateway already blocked stub in production; pending payment reuse could still advertise `local_stub` if keys empty.

## 4. Files changed

- `apps/nursery-web/src/app/checkout/page.tsx`
- `apps/nursery-web/src/lib/razorpay.ts`
- `apps/nursery-api/app/Modules/Payment/Services/PaymentService.php`
- `apps/nursery-api/tests/Feature/Qa04PaymentStubGuardTest.php`

## 5. API changes

- Payment initiate: refuse pending stub reuse when `production` and keys empty

## 6. Database changes

None.

## 7–8. Frontend / Mobile

- Customer Web: preview error + retry; disable CTA until `preview.grand_total` present  
- `localStubPayment` / OrderDetail retry uses same production throw  
- Customer Mobile already gated stub with `kReleaseMode`

## 9–11. Tests / results

| Test | Result |
|------|--------|
| `php artisan test --filter=Qa04PaymentStubGuardTest` | **PASS** (3) |
| `npx tsc --noEmit` (nursery-web) | **PASS** |

## 12. Remaining issues

- Full live Razorpay E2E still needs real/test keys (ops).  
- COD / Razorpay happy-path smoke remains for QA-14.  
- Refund `local_stub` in admin stays non-prod operational (documented).

## 13. Risks

Low. Preview failure now blocks checkout by design (correct commerce rule).

## 14. Next phase

**QA-05 — Order + Inventory + Fulfillment Regression**
