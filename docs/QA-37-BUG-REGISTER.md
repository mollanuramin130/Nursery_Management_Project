# QA-37 Bug Register

**Date:** 2026-08-13 · **Scope:** Parity + Network resilience + Offline-first mock  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED — Offline-first (Customer Mobile)

### QA-37-010 — No mock/cache catalog when API down
- **Severity:** HIGH · **Fix:** `CatalogRepository` + `assets/mock_data/*` · **Status:** **FIXED**

### QA-37-011 — Cart/wishlist unusable offline
- **Severity:** HIGH · **Fix:** Local persist + local mutations; “Saved on this device” · **Status:** **FIXED**

### QA-37-012 — Risk of fake order/payment offline
- **Severity:** CRITICAL (prevented) · **Fix:** Checkout blocks `localOnly`; no fake Razorpay · **Status:** **FIXED**

### QA-37-013 — Broken remote images offline
- **Severity:** MEDIUM · **Fix:** `ResilientNetworkImage` + placeholder asset · **Status:** **FIXED**

---

## FIXED — Network resilience

### QA-37-006 — Token refresh network blip logged users out
- **Severity:** CRITICAL · **Component:** Customer/Admin Mobile + Web BFF clients  
- **Repro:** Signed-in → briefly lose API → next 401 triggers refresh → refresh hits connection error → session cleared  
- **Expected:** Keep session; show reconnect / offline  
- **Actual:** `catch` cleared tokens + `onSessionExpired` / `onUnauthorized`  
- **Fix:** Invalidate session only on auth rejection (401/403 or unsuccessful refresh body); transport failures return false without clearing  
- **Status:** **FIXED**

### QA-37-007 — No offline / API-unavailable global UX
- **Severity:** HIGH · **Component:** Customer Web + Mobile  
- **Fix:** NetworkKind model + non-blocking banner + health retry; offline copy ≠ API unavailable copy  
- **Status:** **FIXED**

### QA-37-008 — Customer Web network errors were ops-facing
- **Severity:** MEDIUM · **Component:** `nursery-web` `unwrapError`  
- **Fix:** Shopper-safe map via `network-errors.ts` (hide artisan / proxy hints)  
- **Status:** **FIXED**

### QA-37-009 — BFF upstream fetch had no timeout
- **Severity:** HIGH · **Component:** Customer/Admin Web `bff-upstream.ts`  
- **Fix:** AbortSignal 30s on `laravelFetch`  
- **Status:** **FIXED**

---

## FIXED — Prior parity (still valid)

| ID | Summary | Status |
|----|---------|--------|
| QA-37-001 | Session expiry wishlist hearts | **FIXED** |
| QA-37-002 | Cart mutation race | **FIXED** |
| QA-37-003 | PDP wishlist double-tap | **FIXED** |
| QA-37-004 | Web cart error ≠ empty | **FIXED** |
| QA-37-005 | Web wishlist busy/error toast | **FIXED** |
| QA-36-007/008 | Bottom nav shell / wishlist flicker | **FIXED** reconfirmed |

---

## OPEN / PARTIAL / BLOCKED / UNVERIFIED

| Item | Classification |
|------|----------------|
| QA-ADM-002 Admin Mobile ops subset | **OPEN** intentional |
| Variant PDP picker (both customers) | **PARTIAL** |
| Mobile stock-alert cancel parity | **PARTIAL** LOW |
| Full interactive Wi-Fi/API-stop matrix on Vivo | **PARTIAL** (hooks shipped; exhaustive device matrix **UNVERIFIED** this session) |
| Mobile-data without adb reverse | **UNVERIFIED** / **ENVIRONMENT** |
| UPI-app settle / QR settle | **BLOCKED** / **UNVERIFIED** |
| Live HTTPS Secure cookie jar | **UNVERIFIED** |
| Payment overlay hang if Razorpay SDK never returns | **PRE-EXISTING** / MEDIUM UX (HTTP timeouts exist; gateway UI separate) |
| Coupon apply `mutating` guard | **LOW** deferred |
| QA-SEC-001 | **CLOSED** |
