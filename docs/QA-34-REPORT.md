# QA-34 REPORT — Production HTTPS + BFF Deployment Readiness & Cross-Client Regression

**Date:** 2026-08-13  
**Verdict:** **COMPLETE** (TEST / local / staging-readiness)  
**LIVE payment / LIVE FCM / GREEN:** **OUT OF SCOPE / NO**

---

## Objectives met

1. Re-validated Customer + Admin Web BFF auth end-to-end (login, me, refresh, logout).  
2. Documented and enforced production config contract: `API_PROXY_TARGET`, `COOKIE_SECURE`, same-origin BFF, CSRF, CORS.  
3. Confirmed browser never receives JWT in JSON; HttpOnly access/refresh; no JWT in localStorage/sessionStorage.  
4. Confirmed CSRF reject (missing + invalid), session lifecycle, isolation/IDOR, payment suite regression.  
5. Did **not** redesign BFF; did **not** add hosting infrastructure; did **not** claim GREEN.

---

## Deployment contract (staging / production)

| Setting | Required | Notes |
|---------|----------|-------|
| `API_PROXY_TARGET` | **Yes** (server-only) | Laravel `/api/v1` base for BFF upstream |
| `COOKIE_SECURE` | **Yes** on HTTPS | `true` on staging/production; local HTTP leaves unset/`false` |
| `NODE_ENV=production` | Build/runtime | Also defaults Secure cookies on |
| Browser API base | `/api/bff/proxy` | Hardcoded — not Laravel origin |
| Laravel `supports_credentials` | `false` | Correct — browser auth is same-origin BFF |
| CORS origins | Explicit allow-list | Never `*` with credentials |

Env examples updated:

- `apps/nursery-web/.env.{example,staging.example,production.example}`
- `apps/nursery-admin/.env.{example,staging.example,production.example}`

Readiness script:

```bash
bash apps/nursery-api/scripts/qa34_bff_readiness.sh
```

---

## HTTPS Secure cookie

| Check | Result |
|-------|--------|
| Local HTTP → `Secure` **absent** | **PASS** (header evidence) |
| Policy: `NODE_ENV=production` → Secure true | **PASS** (unit) |
| Policy: `COOKIE_SECURE=true` override | **PASS** (unit) |
| Live HTTPS staging host cookie jar | **UNVERIFIED** (no staging HTTPS host in this phase) |

---

## Security evidence (no token values)

| Check | Result |
|-------|--------|
| Login/refresh JSON omits tokens | **PASS** |
| HttpOnly access + refresh | **PASS** |
| SameSite=Lax | **PASS** |
| Missing/invalid CSRF → 403 | **PASS** |
| Logout → `/auth/me` 401 | **PASS** (Customer + Admin) |
| Invalid access cookie → 401 | **PASS** |
| Customer BFF → admin orders → 403 | **PASS** |
| IDOR (API) | **PASS** (carry-forward Qa11/Qa33) |
| Browser client no Bearer/localStorage JWT write | **PASS** (source audit) |
| Razorpay mode | **TEST** (`rzp_test_*`, unsigned webhooks false) |

---

## Functional regression

| Area | Result |
|------|--------|
| Cart via BFF | **PASS** |
| COD / order lifecycle | **PASS** (prior QA-32 **9069** + suite) |
| Razorpay TEST automated | **PASS** (Qa18–Qa31 in filter; no new LIVE charge) |
| Notifications (in-app/stub) | **PASS** (suite; LIVE FCM N/A) |
| Customer/Admin Mobile Bearer | **PASS** |
| Device launch + `adb reverse` | **PASS** (`2d3714f`) |
| QA-ADM-002 | **Intentional** (unchanged) |
| Dynamic QR settle | **UNVERIFIED** (carry-forward) |
| UPI-app settle | **BLOCKED** (carry-forward) |
| Mobile-data w/o reverse | **UNVERIFIED** (carry-forward) |

---

## Build / analyze

| Suite | Result |
|-------|--------|
| PHPUnit QA filter | **242 / 1184** (240 pass + 2 skipped) — same as QA-33 |
| Customer/Admin `test:unit` | **PASS** (+ QA-34 Secure/BFF asserts) |
| Web + Admin `npm run build` | **PASS** |
| Flutter tests | **PASS** |
| Flutter analyze | **PASS** (info-only) |
| composer / npm audit | **PASS** |

---

## Changes in this phase

1. **QA-34-001** — Staging/production env examples lacked BFF deploy vars → fixed.  
2. `resolveCookieSecureFlag` shared by server cookies + unit tests (no logic drift).  
3. `scripts/qa34_bff_readiness.sh` — config + smoke + local cookie attrs.

**Database changes:** none  
**API contract changes:** none  

---

## Remaining risks

1. Staging/production must set `API_PROXY_TARGET` + HTTPS `COOKIE_SECURE=true` (or rely on `NODE_ENV=production`).  
2. Live HTTPS cookie jar not exercised on a real staging host this phase.  
3. Same-origin XSS can still *use* session cookies (HttpOnly prevents theft) — CSP remains.  
4. LIVE Razorpay / LIVE FCM / prod `--strict` still block GREEN.
