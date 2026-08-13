# QA-33 REPORT — QA-SEC-001 Security / Authentication Hardening

**Date:** 2026-08-13  
**Verdict:** **COMPLETE**  
**QA-SEC-001:** **CLOSED**  
**LIVE:** OUT OF SCOPE · **GREEN:** **NO**

---

## Objective

Replace Web JWT persistence in `localStorage` with same-origin Next.js BFF + HttpOnly cookies without breaking Laravel Bearer auth for Mobile or payment/order security.

---

## What changed

### Customer Web
- Browser API base → `/api/bff/proxy`
- Auth → `/api/bff/auth/{login,register,logout,refresh,csrf}`
- HttpOnly cookies: `gl_web_access`, `gl_web_refresh`
- CSRF double-submit: `gl_web_csrf` + `X-CSRF-Token`
- Legacy `gl_access_token` / `gl_refresh_token` cleared; never written again
- Server-side silent refresh on 401 inside BFF proxy

### Admin Web
- Same BFF pattern with `gl_admin_web_*` cookies
- Analytics CSV export no longer injects Bearer from JS storage
- Login health probe via BFF proxy

### Laravel / Mobile
- **No auth redesign** — Bearer login/refresh/logout unchanged
- New tests: `Qa33BearerMobileCompatTest`

---

## Verification highlights

| Check | Result |
|-------|--------|
| Login body omits tokens | **PASS** |
| Access/refresh HttpOnly | **PASS** |
| `/auth/me` via BFF | **PASS** |
| Logout → 401 | **PASS** |
| CSRF mismatch → 403 | **PASS** |
| Admin BFF login + me | **PASS** |
| Mobile Bearer login still returns tokens | **PASS** |
| Customer → admin API 403 | **PASS** |
| IDOR order/payment → 404 | **PASS** |
| Payment/idempotency suite (Qa18–Qa31) | **PASS** (in filter) |
| Device APK launch + `adb reverse` | **PASS** |
| Builds / audits | **PASS** |

Smoke: `bash apps/nursery-api/scripts/qa33_bff_smoke.sh`

---

## Regression

| | Tests | Assertions | Skipped |
|--|------:|-----------:|--------:|
| QA-32 baseline | 240 | 1170 | 2 |
| QA-33 current | **242** | **1184** | 2 |
| Delta | **+2** | **+14** | 0 |

New: `Qa33BearerMobileCompatTest` (2 tests). No failures.

---

## Out of scope (unchanged)

- Razorpay LIVE / LIVE FCM / production `--strict` GREEN  
- UPI TEST settlement without TEST UPI account  
- Forcing Mobile onto cookie/BFF  

---

## Files (primary)

- `apps/nursery-web/src/app/api/bff/**`
- `apps/nursery-web/src/lib/{api,storage,csrf,session-cookie-policy,server/*}.ts`
- `apps/nursery-admin/src/app/api/bff/**`
- `apps/nursery-admin/src/lib/{api/client,storage,csrf,session-cookie-policy,server/*}.ts`
- `apps/nursery-api/tests/Feature/Qa33BearerMobileCompatTest.php`
- `apps/nursery-api/scripts/qa33_bff_smoke.sh`
