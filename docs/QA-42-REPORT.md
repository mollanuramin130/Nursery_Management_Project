# QA-42 REPORT — Full Vivo Matrix + TEST Payment Smoke

**Date:** 2026-08-15  
**Status:** **PARTIAL**  
**GREEN:** NO · **LIVE READY:** NO

---

## Scope delivered

1. Closed residual soft-load / flicker gaps from QA-40-M-008 / QA-41.
2. Expanded Vivo customer shell matrix evidence.
3. COD TEST API smoke (PASS).
4. Razorpay TEST initiate (PASS) — verify requires real TEST checkout signature (not fabricated).
5. Regression suites.

## Key fixes (QA-42)

| ID | Fix |
|----|-----|
| QA-42-001 | Admin list/detail: failed soft refresh no longer replaces usable rows with full-screen `OpsError` |
| QA-42-002 | `OpsStaleBanner` for non-blocking refresh failure when data exists |
| QA-42-003 | Checkout soft `_bootstrap` — no CartSkeleton flash when addresses/methods already loaded |
| QA-42-004 | Product reviews soft `_load` — keep list while reloading after submit |
| QA-42-005 | Document: Admin Mobile has **no** `/products` route — Inventory is product/stock stand-in (closes M-008 product gap as INTENTIONAL architecture) |

## Architecture note — “Admin Products”

Ops Mobile routes: Dashboard · Orders · Inventory (stock) · More (fulfillment, POs, suppliers, warehouses, movements, notifications, profile).  
Product catalog CRUD remains **Admin Web**. Inventory soft-error + soft-load covers the mobile “products residual” intent.

## Payments (TEST / local)

| Path | Result |
|------|--------|
| COD place → CONFIRMED + cart clear | **PASS** (`payment-smoke.log`) |
| Razorpay place + initiate (rzp_test key) | **PASS** |
| Verify with `local_*` stub signature | **FAIL (expected)** — env has real TEST keys; stub signature correctly rejected |
| Device Razorpay Checkout UI | **UNVERIFIED** |
| LIVE / UPI settlement | **BLOCKED / OUT OF SCOPE** |

## Device (vivo 1951)

Evidence: `docs/qa42-mobile/`  
Customer Home/Shop/Cart/Orders/Account/Search/Categories captured.  
Admin login UI captured; interactive multi-field ADB login unreliable (field concatenation) → post-login tabs **UNVERIFIED** this pass.

## Regression

| Suite | Result |
|-------|--------|
| Customer Flutter | **68** PASS |
| Admin Flutter | **29** PASS (+ OpsStaleBanner) |
| PHPUnit | **253** / **1209** (2 skipped) |
| Customer Web unit | PASS |
| Admin Web unit | PASS |
