# QA-31 Bug Register

**Date:** 2026-08-13 · **Scope:** TEST-only UPI completion + payment UX consistency  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-31-001 — Client amount override accepted on pending payment reuse
- **Classification:** BUG (PAYMENT / BACKEND) · **Severity:** HIGH
- **Root cause:** `PaymentService::initiate` checked `amount` only when creating a new gateway order; pending reuse returned early and ignored a mismatched client `amount`.
- **Fix:** Move amount-authority check before pending reuse.
- **Tests:** `Qa31AmountAuthorityAndIdempotencyTest::test_client_amount_override_rejected_even_when_pending_payment_exists` (+ existing Qa21 amount test)
- **Live API:** order 9067 initiate with `amount:1` → **409 CONFLICT**
- **Status:** **FIXED**

### QA-31-002 — Mobile UPI option terminology lagged Web
- **Classification:** UX / PARITY · **Severity:** LOW
- **Root cause:** Customer Mobile labeled online pay as “UPI” while Web uses “UPI / Razorpay” + Checkout / Dynamic QR / UPI Intent submodes.
- **Fix:** Mobile title → **UPI / Razorpay**; subtitle clarifies Intent then Checkout fallback (no fake QR mode on Mobile).
- **Device:** Payment step shows updated copy after APK rebuild.
- **Status:** **FIXED** (accepted platform difference: Mobile still does not expose Dynamic QR radio)

---

## Verified still PASS (prior)

| ID | Notes |
|----|--------|
| QA-30-001 | failed→captured recovery — re-tested via `Qa31…failed_then_retry…` + `Qa30PaymentFailedThenCapturedRecoveryTest` |
| QA-30-002 | UPI→Checkout fallback unit tests PASS |

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-SEC-001 | Web JWT in localStorage | **OPEN** |
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** (intentional) |

---

## Not defects

| Item | Classification |
|------|----------------|
| True UPI-app settle on Vivo | **BLOCKED** — GPay has no registered payment account for TEST QR/intent |
| Dynamic QR scan settlement | **UNVERIFIED** — generation works; no completed QR charge |
| Mobile-data without `adb reverse` | **UNVERIFIED** — device cannot reach Mac LAN `10.165.198.44:8000` |
