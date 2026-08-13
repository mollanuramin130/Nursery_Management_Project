# UPI Payment Test Matrix (QA-21)

**Date:** 2026-08-12  
**Legend:** PASS = executed evidence · UNVERIFIED = no evidence · BLOCKED = environment prevents run · N/A

---

## Automated (this host)

| # | Case | Layer | Result | Evidence |
|---|------|-------|--------|----------|
| 1 | UPI dynamic_qr initiate returns QR + server amount | API | PASS | `Qa21UpiPaymentTest` |
| 2 | Client amount ≠ order grand_total rejected | API | PASS | Qa21 |
| 3 | Verify confirms + inventory commit once (+ duplicate verify) | API | PASS | Qa21 |
| 4 | upi_intent returns intent URL | API | PASS | Qa21 |
| 5 | COD unaffected | API | PASS | Qa21 |
| 6 | Signed webhook / idempotency / amount / auth gates | API | PASS | Qa18–20 |
| 7 | Duplicate initiate / already-paid / wrong order / cancelled | API | PASS | Qa19 |
| 8 | Secret not in initiate; IDOR verify; invalid payment id | API | PASS | Qa20 |
| 9 | Web UPI helpers (map/poll/label/QR) | Web unit | PASS | `qa-unit-checks` |
| 10 | Flutter UPI helpers | Mobile unit | PASS | `upi_payment_test.dart` |
| 11 | Admin unit (RBAC / refund safety) | Admin unit | PASS | `qa-unit-checks` |
| 12 | Qa11 security regression | API | *(run in full QA-21 suite)* | `Qa11SecurityTest` |

---

## Failure / security matrix

| # | Case | Result | Notes |
|---|------|--------|-------|
| 1 | Successful UPI (stub path) | PASS | Simulation ≠ LIVE |
| 2 | Failed UPI | PARTIAL | Existing failed payment tests; UI maps failed |
| 3 | Cancelled UPI | PARTIAL | Client cancel → pending order; API cancel covered historically |
| 4 | Expired UPI (UI poll timeout) | PASS (unit) | Client marks expired after bound; server expiry meta present |
| 5 | Duplicate initiate | PASS | Qa19 |
| 6 | Duplicate verify | PASS | Qa21 / Qa18 |
| 7 | Duplicate webhook | PASS | Qa18 / Qa20 |
| 8 | Already-paid | PASS | Qa19 |
| 9 | Wrong order | PASS | Qa19 / Qa20 |
| 10 | Wrong user (IDOR) | PASS | Qa20 |
| 11 | Wrong amount | PASS | Qa21 |
| 12 | Invalid signature | PASS | Qa18 |
| 13 | Invalid payment ID | PASS | Qa20 |
| 14 | Cancelled order | PASS | Qa19 |
| 15 | Non-payable order | PASS | Existing payment gates |
| 16 | Concurrent attempts | PARTIAL | Locking in PaymentService; dedicated race UNVERIFIED |
| 17 | Network failure | UNVERIFIED | Client error paths exist |
| 18 | Webhook before client | PASS | Qa20 webhook-after-verify / order |
| 19 | Client before webhook | PASS | Verify path |
| 20 | App restart while pending | UNVERIFIED | Device |
| 21 | Browser refresh while pending | UNVERIFIED | Manual |
| 22 | Delayed status | PASS (poll design) | Bound poll |
| 23 | Provider timeout | UNVERIFIED | LIVE |
| 24 | Provider API failure | PARTIAL | Stub/local path; LIVE BLOCKED |

---

## Integration chain

| Step | Result |
|------|--------|
| Checkout → initiate → verify/webhook → CONFIRMED → inventory once → customer order → admin payment fields | PASS (automated stub) |
| LIVE PSP TEST transaction | **BLOCKED** (KEY/SECRET/WEBHOOK EMPTY) |
| LIVE production payment | **BLOCKED** |

---

## Real device (vivo / Android)

| Item | Result |
|------|--------|
| Device attached historically (`2d3714f`) | Prior QA |
| UPI Intent / QR LIVE pay | **BLOCKED** (no credentials) |
| Return-to-app → API refresh only | Code present; LIVE **UNVERIFIED** |

---

## COD regression

| Item | Result |
|------|--------|
| Qa21 COD case | PASS |
| Broader QA-02→20 COD / checkout | Included in full regression run |

---

## Builds

Recorded in `docs/QA-21-REPORT.md`.
