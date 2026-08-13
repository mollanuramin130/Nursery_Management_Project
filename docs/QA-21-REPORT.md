# QA-21 REPORT — UPI Dynamic QR + Intent + Server Verification

**Date:** 2026-08-12  
**Phase:** QA-21  
**Status:** **PARTIAL** (code complete; LIVE payment BLOCKED)  
**Release recommendation:** **YELLOW — CONDITIONAL RELEASE**  
**GREEN RELEASE:** **NO**

---

## 1. Status

**PARTIAL**

Implemented provider-backed UPI (`dynamic_qr` / `upi_intent`) on the existing Razorpay + PaymentService architecture. Server remains authoritative for PAID. Credentials on this host remain **EMPTY** → LIVE UPI / webhook **BLOCKED**. Simulation ≠ LIVE.

---

## 2. What was built

### API
- `method: upi` + `mode: dynamic_qr|upi_intent|checkout` on initiate
- Amount authority: reject mismatched client `amount`
- `RazorpayGateway::withUpiChannel` / `presentPendingUpiPayload` (QR deep-link + optional QR API)
- Admin order payment payload: provider ids + `upi_mode` / `channel`
- Tests: `Qa21UpiPaymentTest` (5)

### Customer Web
- Checkout selector COD | UPI
- Dynamic QR UI + bounded status polling + stub confirm (dev only)
- Helpers + unit asserts in `upi-payment.ts` / `qa-unit-checks`

### Customer Mobile
- COD | UPI; initiate `upi_intent`; `url_launcher`; poll API; return ≠ paid
- Helpers + `upi_payment_test.dart`

### Admin Web / Mobile
- Payment method / UPI mode / txn visibility (additive API fields)

### Docs
- `docs/UPI-PAYMENT-DESIGN.md`
- `docs/UPI-PAYMENT-API-CONTRACT.md`
- `docs/UPI-PAYMENT-TEST-MATRIX.md`
- This report + closeout

---

## 3. Bugs fixed

None classified as new production commerce defects beyond UPI feature delivery. Polling stale-closure on Web fixed during implementation. Flutter intent path no longer falls through to Checkout after a real UPI return.

---

## 4. New bugs / open

| ID | Status |
|----|--------|
| QA-SEC-001 | **OPEN** (unchanged; UPI does not fix) |
| Razorpay credentials EMPTY | **BLOCKED** for LIVE |
| Prod `--strict` on this host | **UNVERIFIED** (local correctly fails) |

---

## 5. Payment verification

| Item | Result |
|------|--------|
| Credentials | **EMPTY** |
| Dynamic QR (stub/automated) | **PASS** |
| UPI Intent (stub/automated) | **PASS** |
| Server verification | **PASS** (automated) |
| Webhook signature / idempotency | **PASS** (Qa18–20; not re-LIVE) |
| LIVE UPI / webhook | **BLOCKED** |
| COD | **PASS** (Qa21 + regression) |

---

## 6–9. Clients

| App | Result |
|-----|--------|
| Customer Web unit | **PASS** |
| Customer Web LIVE UPI | **BLOCKED** |
| Customer Mobile unit / analyze | **PASS** |
| Customer Mobile LIVE UPI | **BLOCKED** / device **UNVERIFIED** |
| Admin Web unit + payment fields | **PASS** / LIVE **UNVERIFIED** |
| Admin Mobile payment display | Code **PASS**; device **UNVERIFIED** |

---

## 10. Database

**NONE** — reused `payments` + `meta` JSON. No new tables.

---

## 11. API contract

**Additive** — see `docs/UPI-PAYMENT-API-CONTRACT.md`. No breaking changes.

---

## 12. Production readiness

| Item | Result |
|------|--------|
| This host | `APP_ENV=local`, `APP_DEBUG=true` |
| `--strict` | exit **1** (expected) |
| Real staging/prod `--strict` | **UNVERIFIED** |

---

## 13. Security

- QA-11 regression included in full suite
- QA-SEC-001 remains **OPEN**
- Amount / IDOR / signature covered by Qa18–21

---

## 14. Evidence anchors

- `Qa21UpiPaymentTest`
- UPI docs in `docs/UPI-PAYMENT-*.md`
- Regression counts in closeout report

---

## 15. GREEN criteria

Not met: LIVE credentials, HTTPS prod host, SEC-001 or signed acceptance, real webhook, backup/rollback.
