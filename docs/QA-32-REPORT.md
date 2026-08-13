# QA-32 REPORT — Final TEST Hardening, Bug Fix, Parity & Regression

**Date:** 2026-08-13  
**Verdict:** **PARTIAL**  
**Device:** CONNECTED — `2d3714f` / vivo 1951 (`adb reverse tcp:8000`)  
**LIVE:** **OUT OF SCOPE** · **GREEN:** **NO**

---

## Objective

Stabilize the existing TEST system across Customer/Admin Web + Mobile + Laravel API without redesigning Razorpay, fabricating UPI/QR settlement, or claiming LIVE/GREEN.

---

## Preflight (verified)

| Check | Result |
|-------|--------|
| API `/health/ready` | **PASS** — database/cache healthy |
| Customer Web `:3000` | **PASS** — HTTP 200 + CSP headers live |
| Admin Web `:3001` | **PASS** — HTTP 200 + CSP headers live |
| Razorpay key mode | **PASS** — `rzp_test_*` only |
| Unsigned webhooks | **PASS** — disabled |
| Device packages | **PASS** — `com.greenleaf.nursery_app` + `com.greenleaf.nursery_admin_mobile` installed/launchable |
| Device → API via reverse | **PASS** — health JSON via `127.0.0.1:8000` |

---

## Master bug audit

| ID | Still real? | Severity | Action |
|----|-------------|----------|--------|
| **QA-SEC-001** | YES — JWTs still in `localStorage` | HIGH | Temporary CSP mitigation shipped; **remains OPEN** (no HttpOnly/BFF redesign) |
| **QA-ADM-002** | Intentional ops scope | MEDIUM (by design) | **Documented intentional** — not “fixed away” |
| QA-31-001 / 002 | Fixed previously | — | Re-verified via PHPUnit + code path |
| QA-30-001 / 002 | Fixed previously | — | Re-verified via PHPUnit |
| True UPI settle | Provider/device account limit | — | **BLOCKED** (honest) |
| Dynamic QR settle | No completed TEST QR charge | — | **UNVERIFIED** (honest) |
| Mobile-data w/o reverse | LAN path not re-proven | — | **UNVERIFIED** |

---

## QA-SEC-001 (Phase 3)

**Current risk:** XSS on Customer/Admin Web can exfiltrate `localStorage` JWTs.

**Attack scenario:** Injected script reads `gl_*` / `gl_admin_*` tokens → caller API as victim.

**Safe change implemented (TEST mitigation only):**
- CSP + existing security headers on Customer Web (`nursery-web/next.config.ts`) and Admin Web (`nursery-admin/next.config.ts`)
- Verified live: `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`

**Not done (risky redesign):** HttpOnly cookie / same-site BFF (QA-11 Option C).

**Blocks TEST release?** **NO** — known accepted risk for local/UAT.  
**Blocks GREEN / public Web?** **YES** until dedicated SEC project.

Regression: Admin `qa-unit-checks` still pass; web builds succeed with CSP.

---

## QA-ADM-002 (Phase 4)

**Classification:** **INTENTIONAL** (ops subset), not a defect.

Admin Mobile remains Dashboard / Orders / Inventory / Scan / Fulfillment / PO / Suppliers / Warehouses / Notifications. Catalog, marketing, full returns module, and analytics stay Admin Web–only by design. Return notification deep links continue to fall back to orders.

No code change to invent false Admin Web parity.

---

## Cross-client parity & UI uniformity

| Workflow | Result | Notes |
|----------|--------|-------|
| Auth login / bad password / logout+refresh revoke | **PASS** | API: logout invalidates access + refresh |
| Order status terminology | **PASS** | Canonical statuses; Admin now human labels |
| PENDING_PAYMENT customer copy | **PASS** (documented) | Customer Web/Mobile: **Order placed**; Admin: **Pending payment** (role-appropriate) |
| Payment amount authority | **PASS** | Client never marks PAID; amount check before pending reuse |
| COD lifecycle | **PASS** | Order **9069** end-to-end |
| Notifications (in-app/stub) | **PASS** | Lifecycle events for customer; no LIVE FCM claim |

### Fixes this phase

1. **QA-32-001** — Admin Web + Admin Mobile displayed raw `ORDER_STATUS` codes → canonical labels (`orderStatusLabel` / `opsStatusLabel`).
2. **QA-32-002** — Customer Mobile order detail missing `RETURN_*` / `REFUNDED` / `DELIVERY_FAILED` labels → aligned with list + Web.

---

## Payment TEST hardening (no architecture redesign)

Reconfirmed via prior evidence + PHPUnit filter (not re-fabricated device UPI settle):

| Item | Result |
|------|--------|
| COD | **PASS** |
| Razorpay Checkout TEST | **PASS** (QA-30 order **9066** / `pay_TPDwnubVyTYAiG`) |
| Dynamic QR generation | **PASS** (QA-31 **9067**) |
| Dynamic QR settlement | **UNVERIFIED** |
| UPI Intent handoff | **PASS** (QA-31 **9068** → GPay) |
| True UPI-app settlement | **BLOCKED** |
| Payment verification | **PASS** |
| Webhook signature + duplicates | **PASS** |
| Inventory single-commit / idempotency | **PASS** |
| Amount tamper (incl. pending reuse) | **PASS** |

---

## Order lifecycle evidence (QA-32 live API)

**Order 9069** (COD, ₹49):

`CONFIRMED → PROCESSING → PACKED → SHIPPED → OUT_FOR_DELIVERY → DELIVERED → RETURN_REQUESTED → RETURNED → REFUNDED`

- Invalid jump `CONFIRMED → DELIVERED` rejected earlier in audit pattern (state machine).
- Customer timeline matched admin transitions.
- In-app notifications included packed/shipped/OFD/delivered/return_requested (stub/queue — **not** LIVE FCM).

---

## Auth / session

| Case | Result |
|------|--------|
| Customer login | **PASS** |
| Wrong password → `AUTH_INVALID_CREDENTIALS` | **PASS** |
| Logout with refresh_token → access 401 | **PASS** |
| Refresh after logout fails | **PASS** |
| Admin login + order access | **PASS** |

---

## Device (Vivo)

| Check | Result |
|-------|--------|
| `adb devices` | **PASS** — `2d3714f` |
| `adb reverse` + health | **PASS** |
| Launch Customer + Admin APKs | **PASS** |
| Full interactive UI re-smoke every screen | **PARTIAL** — launch + prior QA-29–31 device evidence; not a full manual re-walk of every screen in this phase |
| Mobile-data without reverse | **UNVERIFIED** |

---

## Build / regression

| Suite | Result |
|-------|--------|
| PHPUnit QA filter | **240 tests / 1170 assertions** (238 pass + 2 skipped) — **same as QA-31** |
| Customer Web `test:unit` | **PASS** |
| Admin Web `test:unit` | **PASS** (+ QA-32 label asserts) |
| Customer Flutter `upi_payment_test` | **PASS** (4) |
| Admin Flutter suite | **PASS** (25; +1 `opsStatusLabel` test) |
| Customer Web build | **PASS** |
| Admin Web build | **PASS** |
| `composer audit` | **PASS** — no advisories |
| `npm audit --omit=dev` (web/admin) | **PASS** — 0 vulnerabilities |
| Flutter analyze | **PASS** (info-only pre-existing style notes) |

**Difference vs QA-31:** 0 test / 0 assertion delta on PHPUnit filter. Admin Flutter +1 test.

---

## Classification summary

See `docs/QA-32-CLOSEOUT-REPORT.md` scorecard and `docs/QA-32-TEST-MATRIX.md`.

---

## Remaining blockers / risks

1. QA-SEC-001 (HttpOnly/BFF) — OPEN  
2. True UPI-app TEST settle — BLOCKED (GPay payment account)  
3. Dynamic QR settle — UNVERIFIED  
4. Mobile-data without `adb reverse` — UNVERIFIED  
5. LIVE FCM / LIVE Razorpay / production `--strict` — OUT OF SCOPE / NOT READY  
6. QA-ADM-002 intentional Admin Mobile scope  

## Next phase (justified)

**QA-33 candidates (only if needed):**
1. Dedicated **SEC-001** HttpOnly/BFF project (required before public Web GREEN)  
2. Re-attempt UPI/QR settle **only** with a TEST-capable UPI account  
3. LIVE readiness / production ops — **separate** from TEST hardening; do **not** treat TEST green as LIVE green  

**Do not** claim GREEN from QA-32 alone.
