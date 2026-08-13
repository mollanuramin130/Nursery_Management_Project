# QA-28 BUG REGISTER

**Date:** 2026-08-13  
**Phase:** TEST-only full system QA  
**Scope:** Local + Razorpay TEST only · LIVE / FCM LIVE / prod `--strict` out of scope

Severity: CRITICAL / HIGH / MEDIUM / LOW  
Status: FIXED / OPEN / ACCEPTED / INTENTIONAL

---

## NEW — FIXED in QA-28

### QA-28-001 — Customer Web return notification deep link 404
- **Severity:** HIGH · **Component:** Customer Web
- **Description:** `notificationHref` / server routes used `/account/returns/{id}` but only `/account/returns` existed → notification taps 404.
- **Steps:** Open customer notification with `return_id` → follow link.
- **Expected:** Return detail page.
- **Actual:** Next.js 404.
- **Root cause:** Missing route after QA-PAR-001 Mobile detail parity.
- **Fix:** Added `apps/nursery-web/src/app/account/returns/[id]/page.tsx` (`GET /returns/{id}`); list rows link to detail.
- **Regression:** Customer Web build includes `/account/returns/[id]`; deep-link unit check unchanged (`/account/returns/3`).
- **Status:** **FIXED**

### QA-28-002 — Customer Mobile refresh failure left in-memory user signed in
- **Severity:** HIGH · **Component:** Customer Mobile
- **Description:** Failed `/auth/refresh` cleared tokens but did not clear `AuthProvider.user`.
- **Steps:** Expire refresh → trigger authenticated API → observe UI still shows profile/account until next bootstrap.
- **Expected:** Local session cleared with tokens.
- **Actual:** Stale `user` remained.
- **Root cause:** `ApiClient._refreshTokens` had no session-expiry callback into AuthProvider.
- **Fix:** `ApiClient.onSessionExpired` + `AuthProvider.clearLocalSession()` wired in `main.dart`.
- **Regression:** Existing token-refresh coordinator tests still PASS; wiring is app bootstrap.
- **Status:** **FIXED**

### QA-28-003 — Order status label mismatch (list vs detail / Web vs Mobile)
- **Severity:** MEDIUM · **Component:** Customer Web + Customer Mobile
- **Description:** List showed raw `PENDING PAYMENT` / Mobile list “Payment pending” while detail used “Order placed”.
- **Expected:** Shared customer-facing labels.
- **Actual:** Inconsistent copy.
- **Root cause:** Duplicated label maps.
- **Fix:** Shared `order-status.ts` on Web; Mobile orders list aligned to detail labels; COD cancel copy “Not required (COD)” on Web.
- **Regression:** `qa-unit-checks` order-status asserts.
- **Status:** **FIXED**

### QA-28-004 — Admin Mobile offered all status buttons (not gated by transitions)
- **Severity:** HIGH · **Component:** Admin Mobile
- **Description:** Order detail showed CONFIRMED…CANCELLED buttons for any status; invalid clicks relied only on API 409.
- **Expected:** UX gates match Admin Web / `OrderStateMachine` (backend still authoritative).
- **Actual:** Ungated buttons.
- **Root cause:** Hardcoded `_commonNext` list.
- **Fix:** `order_transitions.dart` + filter via `allowedOrderTransitions`.
- **Regression:** `test/order_transitions_test.dart` PASS.
- **Status:** **FIXED**

### QA-28-005 — Admin Mobile return deep link to missing `/returns/{id}`
- **Severity:** MEDIUM · **Component:** Admin Mobile
- **Description:** Return notifications resolved to `/returns/$id` with no route (QA-ADM-002 intentional gap).
- **Expected:** Non-404 landing (order when available).
- **Actual:** Broken path.
- **Fix:** Deep link → `/orders/{order_id}` or `/orders`.
- **Regression:** `notification_deep_link_test.dart` PASS.
- **Status:** **FIXED**

### QA-28-006 — Admin Mobile “Transfers” tile misleading
- **Severity:** LOW · **Component:** Admin Mobile
- **Description:** Tile labeled Transfers navigated to inventory root (no transfer UI).
- **Fix:** Renamed to “Stock movements”; navigates `/inventory/movements` with Admin Web note for complex transfers.
- **Status:** **FIXED**

### QA-28-007 — Admin Mobile order status filter incomplete
- **Severity:** LOW · **Component:** Admin Mobile
- **Description:** Filter omitted PAYMENT_FAILED / DELIVERY_FAILED / return/refund statuses.
- **Fix:** Expanded dropdown to full lifecycle set used by Admin Web.
- **Status:** **FIXED**

### QA-28-008 — Admin Mobile order payment block type error
- **Severity:** MEDIUM · **Component:** Admin Mobile
- **Description:** `Text(() { return Column(...); }())` — analyze error `Column` not assignable to `String` (broke payment visibility compile-time).
- **Fix:** Replaced with `Builder` returning `Column`.
- **Regression:** `flutter analyze` clean on touched files; admin mobile tests PASS.
- **Status:** **FIXED**

---

## OPEN (carry-forward)

### QA-SEC-001 — Web JWTs in `localStorage`
- **Severity:** HIGH · **Status:** **OPEN** (unchanged; not HttpOnly/BFF)

### QA-ADM-002 — Admin Mobile intentional ops subset
- **Severity:** MEDIUM (scope) · **Status:** **INTENTIONAL / PARTIAL** (unchanged)

### QA-PERF-010 — production performance / N+1 at scale
- **Status:** **OPEN** (local lightweight latency only in QA-28)

---

## NOT bugs / accepted differences

| Item | Classification |
|------|----------------|
| Customer Mobile UPI mode hardcodes `upi_intent`; Web offers Checkout / QR / Intent | ACCEPTED DIFFERENCE (TEST) |
| Admin Mobile missing catalog/marketing/returns/analytics | QA-ADM-002 INTENTIONAL |
| FCM LIVE push delivery | OUT OF SCOPE |
| LIVE Razorpay / prod `--strict` | OUT OF SCOPE (QA-27) |
