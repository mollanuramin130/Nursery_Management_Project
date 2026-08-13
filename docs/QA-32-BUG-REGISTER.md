# QA-32 Bug Register

**Date:** 2026-08-13 · **Scope:** Final TEST hardening / parity / security mitigation  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-32-001 — Admin clients showed raw order status codes
- **Classification:** UX / PARITY · **Severity:** LOW–MEDIUM (P2)
- **Affected:** Admin Web, Admin Mobile
- **Root cause:** Badges/chips rendered API enums (`OUT_FOR_DELIVERY`) instead of canonical human labels.
- **Fix:**
  - Admin Web: `orderStatusLabel` + `ORDER_STATUS_LABELS` in `order-transitions.ts`; wired into orders list/detail + dashboard.
  - Admin Mobile: `opsStatusLabel` + `OpsStatusChip` in `shared/widgets.dart`.
- **Tests:** Admin `qa-unit-checks`; Admin Flutter `opsStatusLabel` test.
- **Status:** **FIXED**

### QA-32-002 — Customer Mobile order detail incomplete status labels
- **Classification:** UX / PARITY · **Severity:** LOW (P3)
- **Affected:** Customer Mobile order detail
- **Root cause:** Detail `_labels` map lagged list/Web (missing return/refund/delivery_failed).
- **Fix:** Align `_labels` in `order_detail_screen.dart` with list + Customer Web.
- **Status:** **FIXED**

### QA-SEC-001 — Temporary XSS blast-radius mitigation (CSP)
- **Classification:** SECURITY mitigation · **Severity:** N/A (parent issue remains HIGH)
- **Change:** CSP (+ existing headers) on Customer Web + Admin Web Next configs.
- **Verified:** Response headers present on `:3000` / `:3001`.
- **Does not close** localStorage JWT architecture risk.
- **Status:** **MITIGATED / PARENT OPEN**

---

## Verified still PASS (prior)

| ID | Notes |
|----|--------|
| QA-31-001 | Amount authority before pending reuse — PHPUnit |
| QA-31-002 | Mobile “UPI / Razorpay” terminology |
| QA-30-001 | failed→captured recovery |
| QA-30-002 | UPI→Checkout poll gate |

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-SEC-001 | Web JWT in localStorage | **OPEN** (CSP only; need HttpOnly/BFF) |
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** (intentional — not a defect) |

---

## Intentional / documented (not bugs)

| Item | Notes |
|------|--------|
| Customer `PENDING_PAYMENT` → “Order placed” | Customer-facing; Admin uses “Pending payment” |
| Admin Mobile missing catalog/marketing/returns UI | QA-ADM-002 ops scope |
| Mobile no Dynamic QR radio | Platform difference; Web has QR mode |

---

## Not defects / environment limits

| Item | Classification |
|------|----------------|
| True UPI-app settle on Vivo | **BLOCKED** — no TEST-capable UPI payment account |
| Dynamic QR scan settlement | **UNVERIFIED** — generation OK; no completed charge |
| Mobile-data without `adb reverse` | **UNVERIFIED** |
| LIVE FCM / LIVE Razorpay | **OUT OF SCOPE** |
