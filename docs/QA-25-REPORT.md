# QA-25 REPORT — Real Razorpay TEST Payment + UPI End-to-End Verification

**Date:** 2026-08-12  
**Status:** **PARTIAL** — credentials SET + Customer Web UPI/Razorpay UI integrated; full browser TEST charge still **UNVERIFIED**  
**GREEN:** **NO**  
**LIVE payment:** **NOT CLAIMED**  
**UI detail:** `docs/QA-25-UI-CHECKOUT-REPORT.md`

---

## 1. Status

**PARTIAL** — TEST credentials configured; Customer Web UPI/Razorpay UI integrated; end-to-end browser charge still needs operator confirmation.

| Variable | State |
|----------|--------|
| `RAZORPAY_KEY` | **SET** (`rzp_test_*`) |
| `RAZORPAY_SECRET` | **SET** |
| `RAZORPAY_WEBHOOK_SECRET` | **SET** |
| `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS` | **false** |
| `.env` gitignored | **YES** |

Provider `createOrder` smoke **PASS** (see UI report). Full Checkout → webhook → PAID remains **UNVERIFIED** until a human completes Razorpay TEST UPI in the browser.

Payment architecture (QA-21) and notification architecture (QA-22) were **not redesigned**.

---

## 2. Objective

Convert EMPTY → real TEST lifecycle (initiate → PSP → verify → webhook → finalizeSuccess → inventory once → notifications → admin visibility) without claiming LIVE/GREEN.

**Outcome this host:** environment gate failed; real matrix **not executed**.

---

## 3. Environment / config gate

| Check | Result |
|-------|--------|
| Names present in `.env` template | **PASS** |
| Values SET | **FAIL** (EMPTY) |
| `rzp_test_` only policy | **N/A** until SET |
| `config:clear` for payment | **NOT RUN** (no keys to load) |
| Queue worker for real flow | **NOT STARTED** (no real payment) |

---

## 4. Razorpay TEST configuration

**BLOCKED** — all three secrets EMPTY. No secret values logged or documented.

---

## 5–8. Dynamic QR / UPI Intent / Verification / Webhook

| Item | Result |
|------|--------|
| Dynamic QR (real PSP) | **BLOCKED** |
| UPI Intent (real PSP) | **BLOCKED** |
| Server verification (real) | **BLOCKED** |
| Webhook (reachable + signed) | **BLOCKED** |
| Webhook route registered | **PASS** (automated: `POST /api/v1/payments/webhooks/razorpay` ≠ 404) |
| Idempotency / amount / inventory (automated prior suites) | **PASS** (Qa18–24 + Qa25 gate) |

---

## 9–12. Clients

| Client | Result |
|--------|--------|
| Customer Web real TEST | **BLOCKED** · unit tests see closeout |
| Customer Mobile real TEST | **BLOCKED** (device available; PSP keys not) |
| Admin Web real payment visibility | **BLOCKED** · unit tests see closeout |
| Admin Mobile payment visibility | **UNVERIFIED** |

---

## 13. Notifications

| Path | Result |
|------|--------|
| QA-22 COD new_order / order_confirmed | **PASS** (`Qa25RealTestPaymentGateTest`) |
| Payment-success notifications (real) | **BLOCKED** |
| LIVE FCM | **BLOCKED** (Firebase EMPTY — separate gate) |

---

## 14. Inventory / payment safety

Automated single-commit / failure / duplicate webhook coverage remains from Qa18–21. **Real** success path **BLOCKED**.

---

## 15. Security

| Item | Result |
|------|--------|
| Qa11 / payment ownership suites | included in regression (see closeout) |
| Webhook signature (automated) | **PASS** historically (Qa18) — real Dashboard webhook **BLOCKED** |
| Secret leakage in responses | **PASS** (stub initiate gate) |
| QA-SEC-001 | **OPEN** (unchanged) |

---

## 16–19. Tests / regression / build

See `docs/QA-25-CLOSEOUT-REPORT.md` for exact executed counts.

Added: `apps/nursery-api/tests/Feature/Qa25RealTestPaymentGateTest.php` — **executed PASS**.

---

## 20–21. Database / API

**NONE** — no schema or contract changes.

---

## 22–23. Bugs

| Fixed | Open |
|-------|------|
| None (environment blocker) | **QA-SEC-001**; Razorpay EMPTY; FCM EMPTY; prod `--strict` on local |

---

## 24–26. UNVERIFIED / BLOCKED / risks

**UNVERIFIED:** Admin Mobile interactive payment visibility; real UPI Intent on device when PSP supports it.

**BLOCKED:** Real TEST payment, QR, Intent, webhook, LIVE, GREEN.

**Risks:** Shipping without TEST evidence; operator may confuse stub `local_stub` with real TEST; LIVE keys must never be used on local for QA-25.

---

## 27. Files changed

- `apps/nursery-api/tests/Feature/Qa25RealTestPaymentGateTest.php` (new)
- `docs/QA-25-REPORT.md` / `QA-25-CLOSEOUT-REPORT.md` / `QA-25-TEST-MATRIX.md`
- Register / matrix / roadmap / `RUN.txt` / setup status touch-ups

No production payment code changes.

---

## 28. QA-26 readiness

**YES** once operator configures TEST-only credentials + reachable webhook (see checklist in closeout). QA-26 should not claim LIVE/GREEN from TEST alone.
