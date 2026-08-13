# QA-01 Report

**Date:** 2026-08-12  
**Phase:** Environment + API Health Integrity + Cross-Client Connectivity  
**Status:** **PARTIAL** (API + Web verified; Flutter device/emulator interactive runs NOT AVAILABLE this session)

---

## Scope

QA-01 only: environment, API reachability, CORS, health, seed smoke, client base URLs, connectivity diagnostics.

**Out of scope (deferred):** auth message redesign, password reset UI, cart business rules, checkout payment, JWT cookie migration.

---

## Environment Tested

| Component | Value |
|-----------|--------|
| Host | Local macOS developer machine |
| API | `http://127.0.0.1:8000/api/v1` (`php artisan serve`) |
| Customer Web | `http://localhost:3000` (dev server running) |
| Admin Web | `http://127.0.0.1:3001` (reachable) |
| MySQL | Reachable via health ready (`database:healthy`) |
| Android emulator / physical devices | **NOT AVAILABLE** this session |
| iOS simulator / device | **NOT AVAILABLE** this session |

### Client API configuration (code source of truth)

| Client | Mechanism | Default / example |
|--------|-----------|-------------------|
| Customer Web | `NEXT_PUBLIC_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` |
| Admin Web | `NEXT_PUBLIC_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` |
| Customer Mobile | `--dart-define=API_BASE_URL` | debug default `http://10.0.2.2:8000/api/v1` |
| Admin Mobile | `--dart-define=API_BASE_URL` | same emulator default |

---

## Problems Found

1. **QA-CFG-001 class:** Login/checkout appear broken when API URL, CORS, host binding, or seed data are wrong — not because `email`/`password`/`access_token` field names mismatch.
2. Rapid smoke scripts can hit **HTTP 429** auth throttle (ops noise, not connectivity breakage).
3. Flutter interactive connectivity not exercised on emulator/device in this session.

---

## Root Causes

**QA-CFG-001 root cause:** configuration / networking / environment class.

- Wrong or missing `NEXT_PUBLIC_API_BASE_URL` / `API_BASE_URL`
- Laravel not listening on `0.0.0.0` for physical devices
- Using Android emulator host `10.0.2.2` on a physical phone
- CORS origins missing for Web ports 3000/3001
- API or MySQL down
- Missing sample/seed users

**Not the root cause (confirmed again):** login JSON field contract mismatch.

---

## Files Changed

| File | Why |
|------|-----|
| `apps/nursery-web/src/lib/api-health.ts` + login banner | Clear “API unreachable” vs credential confusion |
| `apps/nursery-admin/src/lib/api-health.ts` + LoginClient | Same for Admin Web |
| `apps/nursery_app/lib/core/api_health.dart` + login screen | Same for Customer Mobile |
| `apps/nursery_admin_mobile/lib/core/api_health.dart` + login screen | Same for Admin Mobile |
| `apps/nursery-api/scripts/qa01_smoke.sh` | Health + login smoke; 429 retry |
| `apps/nursery-api/scripts/qa01_connectivity_smoke.sh` | Extended cart/preview/admin path |
| `RUN.txt` | Short connectivity checklist |
| `docs/ENVIRONMENT_SETUP.md` | Setup guide |
| `docs/QA-01-ENVIRONMENT-AND-API-HEALTH.md` | This phase developer guide |
| `docs/qa/QA-01-ENVIRONMENT-MATRIX.md` | Evidence matrix |
| `docs/QA-01-REPORT.md` | This report |
| `docs/qa/QA-01-REPORT.md` | Mirror / prior location |
| `docs/QA_MASTER_BUG_REGISTER.md` | QA-CFG-001 status |

Why safe: probes are read-only GETs; smoke scripts never print tokens; no business-rule changes; no schema changes.

---

## Configuration Changes

- Documented `CORS_ALLOWED_ORIGINS` for `:3000` / `:3001` (existing config verified correct — **not weakened**).
- Documented `php artisan serve --host=0.0.0.0 --port=8000` for device testing.
- Example env files already present; placeholders only.

---

## API Changes

**No API business logic changes.**

Uses existing:

- `GET /api/v1/health/ready`
- `POST /api/v1/auth/login`
- catalog / cart / checkout preview / admin routes for smoke only

---

## Database Changes

**No database schema changes.**

---

## Tests Executed

| Test | Result |
|------|--------|
| `GET /health/ready` | **PASS** |
| CORS OPTIONS Origin `:3000` / `:3001` | **PASS** |
| Customer login + CORS ACAO | **PASS** |
| Customer `GET /auth/me` | **PASS** |
| `GET /products` | **PASS** |
| `GET /cart` + `POST /cart/items` | **PASS** |
| `POST /checkout/preview` | **PASS** (connectivity only) |
| Admin login + `/auth/me` + dashboard/orders/products | **PASS** (after throttle clear) |
| Customer Web HTTP 200 | **PASS** |
| Admin Web HTTP 200 | **PASS** |
| `Phase12HardeningTest` health | **PASS** |
| Flutter emulator/device interactive | **NOT AVAILABLE** |
| `qa01_smoke.sh` | **PASS** when not throttled / with retry |

---

## Test Results — matrix

| Client | Environment | API Reachable | Login | Auth Request | Result |
|--------|-------------|---------------|-------|--------------|--------|
| Customer Web | Local | PASS | PASS (API+CORS; UI probe code present) | PASS (`/auth/me`) | **PASS** |
| Customer Mobile | Emulator | NOT AVAILABLE | NOT AVAILABLE | NOT AVAILABLE | **NOT AVAILABLE** |
| Customer Mobile | Physical | NOT AVAILABLE | NOT AVAILABLE | NOT AVAILABLE | **NOT AVAILABLE** |
| Admin Web | Local | PASS | PASS | PASS (dashboard/orders/products) | **PASS** |
| Admin Mobile | Emulator/Device | NOT AVAILABLE | NOT AVAILABLE | NOT AVAILABLE | **NOT AVAILABLE** |

Cart connectivity smoke: **PASS** (API).  
Checkout preview connectivity smoke: **PASS** (API).  
No payment/order placement in QA-01.

---

## Remaining Bugs

- QA-CFG-001 residual: operator misconfig / device URL still possible → mitigated with docs + probes → marked **PARTIALLY VERIFIED** until Flutter device runs exist.
- Auth throttle 429 during aggressive smoke (ops).

---

## Deferred Bugs

| ID | Phase |
|----|-------|
| QA-AUTH-001 | QA-02 |
| QA-AUTH-002 | QA-02 |
| QA-AUTH-003 | QA-02 |
| QA-AUTH-004 | QA-02 |
| QA-CART-001 | QA-03 |
| QA-CART-002 | QA-03 |
| QA-CHK-001 | QA-04 |
| QA-PAY-001 | QA-04 |
| QA-PAR-001 | QA-07 |
| QA-SEC-001 | QA-11 |

---

## Acceptance criteria

| Criterion | Result |
|-----------|--------|
| Laravel API starts | **PASS** |
| MySQL connectivity | **PASS** |
| Health endpoint | **PASS** |
| Env documented | **PASS** |
| Base URLs verified (config) | **PASS** |
| CORS verified | **PASS** |
| Customer Web reaches API | **PASS** |
| Customer Mobile reaches API | **NOT AVAILABLE** |
| Admin Web reaches API | **PASS** |
| Admin Mobile reaches API | **NOT AVAILABLE** |
| Customer login smoke | **PASS** |
| Admin login smoke | **PASS** |
| Customer authenticated request | **PASS** |
| Admin authenticated request | **PASS** |
| Cart connectivity smoke | **PASS** |
| Checkout preview connectivity | **PASS** |
| No secrets committed | **PASS** |
| Health automated test | **PASS** |
| QA-CFG-001 | **PARTIALLY VERIFIED** |
| QA-01 documentation | **PASS** |

---

## Final output summary

1. **QA-01 status:** **PARTIAL**  
2. **Root cause of QA-CFG-001:** environment/connectivity/config (not login field mismatch)  
3. **Files changed:** listed above  
4. **Configuration changes:** documentation + verified CORS (no production weaken)  
5. **API changes:** none (business logic)  
6. **Database changes:** none  
7–8. **Tests:** see matrices  
9. **Applications verified:** API, Customer Web, Admin Web; Mobile **NOT AVAILABLE**  
10. **Remaining:** Flutter runtime verification; operator residual  
11. **Deferred:** AUTH/CART/CHK/PAY/PAR/SEC list above  
12. **Run commands:** see `RUN.txt` / `docs/QA-01-ENVIRONMENT-AND-API-HEALTH.md`  
13. **QA-02 safe to start?** **YES** for auth-message/reset work — after acknowledging mobile rows remain NOT AVAILABLE until a device/emulator session is run  

**Stopped here. Did not implement QA-02.**
