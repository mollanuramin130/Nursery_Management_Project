# QA Remediation Baseline

**Date:** 2026-08-12  
**Purpose:** Final pre-remediation inventory before QA-01.  
**Rule:** No fixes applied in this document. Findings re-verified against current repository code.

---

## 1. Repository state

| Item | Value |
|------|--------|
| Git branch | `development/v1.0.0` (tracks `origin/development/v1.0.0`) |
| Worktree | **Dirty** — Phase 20/21 + QA-00 docs uncommitted / modified |
| Apps | `nursery-api`, `nursery-web`, `nursery_app`, `nursery-admin`, `nursery_admin_mobile` |
| Local env files present | `nursery-api/.env`, `nursery-web/.env.local`, `nursery-admin/.env.local` (**yes**) |
| CI | `.github/workflows/ci.yml` present (Phase 21) |
| Sample SQL | `database/nursery_sample_data.sql` (+ phase SQL helpers) |
| docs/qa/ | Created for phase reports |

### Notable uncommitted / new work already in tree (do not re-implement)

- Phase 20 fulfillment (driver assign, pick scan, POD, Admin Mobile fulfillment)  
- Phase 21 hardening (webhook binding, CORS, cleartext, security headers, CI)  
- QA-00 audit docs  

Remediation must **build on** these, not revert them.

---

## 2. Applications discovered

| App | Path | Role |
|-----|------|------|
| Laravel API | `apps/nursery-api` | Source of truth `/api/v1` |
| Customer Web | `apps/nursery-web` | Next.js storefront |
| Customer Mobile | `apps/nursery_app` | Flutter shopper |
| Admin Web | `apps/nursery-admin` | Next.js ops portal `:3001` |
| Admin Mobile | `apps/nursery_admin_mobile` | Flutter GreenLeaf Ops |
| MySQL seeds | `database/*.sql` | Local/demo data |

---

## 3. API / health / auth / payment (discovered)

| Capability | Location | Status |
|------------|----------|--------|
| Health | `GET /api/v1/health`, `/health/live`, `/health/ready` | **Live verified** → HTTP 200, `database:healthy` |
| Laravel `/up` | bootstrap health | Present |
| Auth | `POST /auth/login\|register\|refresh\|forgot-password\|reset-password`, `GET /auth/me` | Present |
| Cart / checkout / orders / payments | Cart, Order, Payment modules | Present |
| Razorpay stub | `RazorpayGateway` refuses stub when `app()->environment('production')` | Backend guard **exists** |
| Webhook | Requires `provider_order_id` (Phase 21) | Hardened |
| CORS example | Includes `:3000` and `:3001` in `.env.example` | Present |

### Tests inventory

| Suite | Count (approx) |
|-------|----------------|
| API Feature/Unit PHPUnit files | **18** |
| Customer Flutter tests | **1** |
| Admin Mobile Flutter tests | **2** |
| Playwright / Next E2E | **Not found** as first-class suite |

---

## 4. QA document set (inputs)

| Doc | Present |
|-----|---------|
| `QA-00-COMPLETE-SYSTEM-AUDIT.md` | Yes |
| `QA_FEATURE_MATRIX.md` | Yes |
| `QA_MASTER_BUG_REGISTER.md` | Yes |
| `QA_REMEDIATION_ROADMAP.md` | Yes |
| `QA_DATABASE_CHANGE_PROPOSAL.md` | Yes — **NO DB CHANGE REQUIRED** |
| `PROJECT_AUDIT.md` / `DATABASE_DESIGN.md` | Yes |

---

## 5. QA-00 finding re-verification

| ID | QA-00 claim | Code re-check | Classification |
|----|-------------|---------------|----------------|
| **QA-CFG-001** | Login/checkout fail when API/config wrong | Health live OK on this machine; clients still depend on correct base URL / CORS / seed / LAN IP. No field-name mismatch. | **CONFIRMED** (operational P0) |
| **QA-PAY-001** | Web accepts `local_stub` without prod guard | `checkout/page.tsx`, `OrderDetailClient.tsx`, `razorpay.ts` still branch on `mode === "local_stub"`. Flutter release blocks stub. Backend also blocks stub in `production` env. | **CONFIRMED** (client guard still missing) |
| **QA-AUTH-001** | Generic `"Unauthenticated"` | `bootstrap/app.php` AuthenticationException → fixed message; AuthService messages discarded | **CONFIRMED** |
| **QA-AUTH-002** | Reset password UI missing | API `POST /auth/reset-password` exists; **no** Web/Mobile reset route/screen (forgot only) | **CONFIRMED** |
| **QA-AUTH-003** | Register password UI weaker than API | API `Password::min(8)->mixedCase()->numbers()`; clients mostly length-focused | **CONFIRMED** |
| **QA-AUTH-004** | Admin Mobile refresh fail does not clear tokens | `api_client.dart` `_refreshTokens` catch → `return false` **without** `clearTokens()` | **CONFIRMED** |
| **QA-CHK-001** | Web checkout falls back to cart total | `checkout/page.tsx`: `preview?.grand_total ?? cart?.grand_total` | **CONFIRMED** |
| **QA-CART-001** | Free delivery cart vs checkout differ | Cart: `freeDeliveryMeta($subtotal)` raw; Checkout: threshold vs `$subtotal - $discount` | **CONFIRMED** |
| **QA-CART-002** | `variant_id` vs `product_variant_id` | API cart present uses `variant_id`; Web `types.ts` still has `product_variant_id` | **CONFIRMED** |
| **QA-PAR-001** | Mobile returns missing | **Nuance:** Order detail can POST `/orders/{id}/returns` and show return rows; **no** dedicated `/account/returns` list route | **CONFIRMED PARTIAL** (list/inbox gap) |
| **QA-PAR-002** | Mobile My Reviews missing | PDP `product_reviews_section` only; no account reviews route | **CONFIRMED** |
| **QA-ADM-001** | Users & Roles Coming Soon | `navigation.ts` → `/coming-soon?feature=Users` | **CONFIRMED** |
| **QA-ADM-002** | Admin Mobile thinner than Web | By design for ops; fulfillment/inventory/PO present | **CONFIRMED — INTENTIONAL_SCOPE** (unless product reverses) |
| **QA-PAY-002** | local_stub in non-prod | Expected for local; backend already blocks in production | **CONFIRMED** (ops/env) |
| **QA-MSG-001** | Offers Coming Soon | Still present in offers UI | **CONFIRMED** |
| **QA-SEC-001** | Web JWT localStorage | Unchanged | **CONFIRMED** (defer large cookie migration unless launch tier requires) |
| **QA-DOC-001** | Phase COMPLETE vs gaps | Still true | **CONFIRMED** |
| **QA-UX-001** | Dev demo credentials | Dev-gated | **CONFIRMED** (acceptable) |
| **QA-TYPE-001** | ShippingMethod ETA typing | Web type incomplete | **CONFIRMED** |
| **QA-OPS-001** | Backup/monitoring unverified on host | Docs exist; host evidence still missing | **CONFIRMED** (ops) |

### False leads (still false)

| Claim | Status |
|-------|--------|
| Login uses wrong body field `username` | **DOCUMENTATION ERROR / rumor** — clients use `email`/`password` |
| Clients expect `data.token` | **NOT REPRODUCIBLE** — use `access_token` |
| Place order hits `/checkout` API | **NOT REPRODUCIBLE** — `POST /orders` |

### Already fixed (do not re-fix)

| Area | Evidence |
|------|----------|
| Webhook unbound payment match | Phase 21 + `Phase21WebhookHardeningTest` |
| Admin Mobile release cleartext | Phase 21 network security |
| CORS production fail-closed + `:3001` in example | Phase 21 |
| Security headers Next apps | Phase 21 |
| Fulfillment driver/scan/POD APIs | Phase 20 |

### New information (baseline)

| ID | Finding | Severity |
|----|---------|----------|
| **QA-PAR-001b** | Mobile returns is **partial** (create on order detail), not total absence | Adjusts fix scope for QA-07 |
| **QA-PAY-001b** | Backend already refuses stub in `production`; Web guard still required for mis-set `APP_ENV` / staging stub leakage | Defense in depth |
| **QA-BASE-001** | Dirty git tree with Phase 20/21 — remediation should not conflict; prefer commit/stabilize before large QA diffs | Process |

---

## 6. Recommended phase order (unchanged, confirmed)

```
QA-01 ENV/HEALTH
 → QA-02 AUTH
 → QA-03 CART TOTALS
 → QA-04 CHECKOUT/PAYMENT
 → QA-05 ORDER/INVENTORY/FULFILLMENT
 → QA-06 CUSTOMER WEB
 → QA-07 CUSTOMER MOBILE
 → QA-08 ADMIN WEB
 → QA-09 ADMIN MOBILE
 → QA-10 CROSS-PLATFORM
 → QA-11 SECURITY
 → QA-12 PERFORMANCE
 → QA-13 AUTOMATION
 → QA-14 E2E
 → QA-15 ACCEPTANCE
```

Do **not** skip QA-01. Do **not** start with cookie JWT migration (QA-SEC-001) before commerce P0/P1.

---

## 7. Modules / files likely affected by early phases

### QA-01
- `apps/nursery-api` health (read-only verify)  
- `docs/RUN_DEPLOY_AND_CUSTOMIZE.md`, `RUN.txt`  
- Optional: lightweight connectivity banner in Web/Mobile (no secrets)  
- CORS `.env` verification  

### QA-02
- `bootstrap/app.php` (auth exception mapping)  
- `AuthService` / requests  
- `nursery-web` + `nursery_app`: reset-password screens, register validation copy  
- `nursery_admin_mobile/lib/core/api_client.dart` refresh clear  

### QA-03
- `CartService::freeDeliveryMeta` / `CheckoutService::buildTotals`  
- `nursery-web/src/lib/types.ts` CartItem  

### QA-04
- `nursery-web/.../checkout/page.tsx`, `razorpay.ts`, order detail  
- `RazorpayGateway` / `PaymentService` (confirm prod guards)  
- Tests: checkout preview required, stub refused  

### Later
- Mobile returns list (QA-07)  
- Admin users UI or WONT_FIX decision (QA-08)  

**Database:** No migration planned unless investigation overturns QA-00 proposal.

---

## 8. Test strategy

| Layer | Approach |
|-------|----------|
| API | Extend PHPUnit for auth messages, free-delivery consistency, checkout preview authority |
| Existing | Keep Phase 3/7/12/20/21 suites green |
| Web | Manual + add targeted unit/e2e when tooling present; at minimum build/lint |
| Mobile | `flutter test` + device smoke with LAN URL |
| Cross | Documented golden journeys in QA-10/14 |

Every FIXED P0/P1 bug requires: reproduce → fix → test → verify clients → update bug register.

---

## 9. Risks

| Risk | Mitigation |
|------|------------|
| Dirty worktree mixes Phase 20/21 with QA fixes | Baseline recorded; prefer sequential commits per QA phase |
| Fixing free-delivery changes cart UX numbers | API-first single rule; update both meta payloads |
| Auth message change may affect clients parsing only `message` | Keep 401; improve `message` / optional `meta.error_code` safely |
| Declaring INTENTIONAL_SCOPE for Admin Mobile | Product confirmation in QA-09 report |

---

## 10. Baseline verdict

| Question | Answer |
|----------|--------|
| Is QA-00 still accurate? | **Yes**, with PAR-001 nuance |
| Any stale “already fixed” bugs among P0/P1? | **No** among listed P0/P1 client issues |
| Ready to start QA-01? | **Yes** |
| DB change required to start? | **No** |

---

## Next action

**Begin QA-01 — Environment + API Health Integrity**  
Produce `docs/qa/QA-01-REPORT.md` after acceptance criteria met.
