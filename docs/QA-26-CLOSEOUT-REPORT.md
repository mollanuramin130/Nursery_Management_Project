# QA-26 CLOSEOUT REPORT

**Date:** 2026-08-13  
**Verdict:** **COMPLETE** (Razorpay **TEST** only)  
**GREEN:** **NO** · **LIVE:** **NO**

---

## Why COMPLETE

The real TEST chain is verified end-to-end from DB + logs + API:

Customer checkout (UPI/Razorpay checkout mode)  
→ Razorpay TEST payment `pay_TPBCfrCIzFjCKz`  
→ webhook `order.paid` / `payment.captured` (signed; secret SET)  
→ `finalizeSuccess` (`payment.verify.succeeded` for payment **5526** / order **9062**)  
→ order **CONFIRMED**, payment **success**  
→ inventory **sale** once per line  
→ customer `payment_confirmed` + `order_confirmed`  
→ admin order API shows success + Razorpay payment id  

---

## Exact ids

| Item | Value |
|------|--------|
| GreenLeaf Order | **9062** / `ORD-20260813-00006` |
| GreenLeaf Payment | **5526** |
| Razorpay Order | **order_TPBCV5HS098qBW** |
| Razorpay Payment | **pay_TPBCfrCIzFjCKz** |
| Amount | **₹1697.00** |
| Paid at | **2026-08-13 13:28:01** |

---

## Regression (executed)

| Suite | Result |
|-------|--------|
| Payment filter Qa04/18/21/24/25/26 | **34 pass + 2 skipped** |
| Full Qa02–Qa26 + Phase | **233 / 1120** (231 pass + 2 skipped) |
| Customer Web `test:unit` | **PASS** |

---

## Remaining outside QA-26

- LIVE Razorpay credentials / LIVE charge  
- Production `--strict` GREEN host  
- LIVE FCM  
- QA-SEC-001  

---

## QA-27 readiness

**YES.**
