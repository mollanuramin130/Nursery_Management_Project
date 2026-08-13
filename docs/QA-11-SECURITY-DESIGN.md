# QA-11 Security Design — Web Session Protection & Auth Architecture

**Date:** 2026-08-12  
**Phase:** QA-11  
**Authors:** Security architecture review (code-first)  
**Status:** Design locked in QA-11 · **Implemented in QA-33** (see `docs/QA-33-SECURITY-REPORT.md`)

---

## 1. Current authentication architecture

```
┌─────────────────┐  ┌──────────────────┐
│ Customer Web    │  │ Admin Web        │
│ (Next.js)       │  │ (Next.js)        │
│ localStorage    │  │ localStorage     │
│ Bearer header   │  │ Bearer header    │
└────────┬────────┘  └────────┬─────────┘
         │                    │
         │   Authorization: Bearer <JWT>
         │   POST body: { refresh_token }
         ▼                    ▼
┌─────────────────────────────────────────┐
│ Laravel API /api/v1                     │
│ tymon/jwt-auth access JWT (HS256)       │
│ Opaque DB refresh tokens (sha256 hash)  │
│ auth:api + active.user + permission:*   │
└─────────────────────────────────────────┘
         ▲                    ▲
         │                    │
┌────────┴────────┐  ┌────────┴─────────┐
│ Customer Mobile │  │ Admin Mobile     │
│ flutter_secure_ │  │ flutter_secure_  │
│ storage + Bearer│  │ storage + Bearer │
└─────────────────┘  └──────────────────┘
```

- **Access token:** JWT via `tymon/jwt-auth`, default TTL **60 minutes** (`JWT_TTL`).
- **Refresh token:** Opaque random string; only **SHA-256 hash** stored in `refresh_tokens`; default TTL **90 days** (`JWT_REFRESH_TTL` minutes).
- **Rotation:** Refresh revokes old row and sets `replaced_by_token_id`.
- **Logout:** Blacklists current access JWT (if present) + revokes refresh row(s).
- **Password reset:** Revokes all refresh tokens for the user.
- **RBAC:** `EnsurePermission` reads DB role→permissions; `super_admin` bypasses.

---

## 2. Current token storage

| Client | Access key | Refresh key | Store |
|--------|------------|-------------|-------|
| Customer Web | `gl_access_token` | `gl_refresh_token` | **localStorage** |
| Admin Web | `gl_admin_access` | `gl_admin_refresh` | **localStorage** |
| Customer Mobile | `gl_access_token` | `gl_refresh_token` | **flutter_secure_storage** |
| Admin Mobile | `gl_ops_access_token` | `gl_ops_refresh_token` | **flutter_secure_storage** |

API returns tokens in **JSON body** on login/register/refresh. No auth cookies today.  
CORS: `supports_credentials: false` (`config/cors.php`).

---

## 3. Current attack surface

| Surface | Risk |
|---------|------|
| XSS → localStorage JWT theft | **HIGH** (QA-SEC-001) |
| Stolen refresh token reuse until rotation/expiry | MEDIUM |
| Cross-origin Bearer (API ≠ Web origin) | By design; no cookie CSRF today |
| `users.manage` assigning `super_admin` | **HIGH** privilege escalation (new) |
| Admin login `?next=` open redirect | MEDIUM |
| Customer Web refresh-fail leaves Zustand `user` | LOW/MEDIUM UX session drift |
| Compromised access JWT until TTL/blacklist | MEDIUM |
| Payment stub in production | Mitigated (QA-04/08) |

---

## 4. Why localStorage is a security risk

Any XSS in Customer/Admin Web can call `localStorage.getItem('gl_*')` and exfiltrate **access + refresh** tokens. Unlike HttpOnly cookies, JS can always read localStorage. Mobile apps using OS-backed secure storage are **not** in the same XSS class.

---

## 5. Proposed secure architecture (future — not QA-11)

**Recommended long-term (QA-SEC-001 FIX project):**

**BFF / same-site cookie bridge** or **API-set HttpOnly cookies with credentialed CORS**, keeping **Bearer for Flutter**.

Preferred path for this monorepo (cross-origin ports today: Web `:3000`, Admin `:3001`, API `:8000`):

### Option preferred: Same-site reverse proxy + HttpOnly cookies (Web only)

1. Deploy Web + API under one site origin in production (e.g. `www` + `/api` proxy) **or** dedicated auth BFF on Web origin.
2. Login/refresh set cookies:
   - `gl_access` (HttpOnly, Secure, SameSite=Lax|Strict, Path=/api)
   - `gl_refresh` (HttpOnly, Secure, SameSite=Strict, Path=/api/v1/auth)
3. Web axios uses `credentials: 'include'`; **stop** storing JWTs in localStorage.
4. Mobile **unchanged** (Bearer + secure storage).
5. CSRF: SameSite + custom header requirement (`X-Requested-With` / double-submit) for cookie-authenticated mutating requests.
6. Dual auth middleware: accept **either** Bearer **or** cookie JWT.

### Rejected for QA-11: Blind cookie migration on current split origins

Setting `SameSite=None; Secure` cookies from `:8000` to be sent from `:3000` requires credentialed CORS, CSRF redesign, dual clients, and high regression risk. **Unsafe to half-ship.**

---

## 6. Cookie attributes (when implemented)

| Attribute | Production | Local |
|-----------|------------|-------|
| HttpOnly | true | true |
| Secure | **true** | false (http) |
| SameSite | `Lax` or `Strict` if same-site; `None` only if forced cross-site | Lax |
| Path | narrow (`/api`) | same |
| Domain | env-driven, never hard-coded | host-only |
| Max-Age | access ≈ TTL; refresh ≈ refresh TTL | same |

Frontend JS must **never** read these cookies.

---

## 7. CSRF strategy (when cookies land)

Mandatory once cookies authenticate browsers:

1. Prefer **same-site** deployment so `SameSite=Lax` blocks most cross-site POSTs.
2. Require non-simple header on state-changing API calls (e.g. `X-XSRF-TOKEN` or `X-Requested-With`).
3. Optionally Laravel-style CSRF cookie + header for Web BFF.
4. Mobile Bearer requests remain CSRF-irrelevant.

**Today:** CSRF **N/A** for API auth (no session cookies; `supports_credentials: false`).

---

## 8. CORS strategy

**Current (correct for Bearer):**

- Explicit `CORS_ALLOWED_ORIGINS`
- Production empty → fail closed
- `supports_credentials: false`
- No `Access-Control-Allow-Origin: *` with credentials

**Future cookie mode:**

- Keep explicit origins
- `supports_credentials: true`
- Never combine `*` with credentials
- Test valid/invalid origin + OPTIONS preflight

---

## 9. Token refresh strategy

**Current:** Client POSTs `{ refresh_token }` → new pair; old refresh revoked (rotation).

**Future Web cookies:** Refresh endpoint sets new cookies; body may omit tokens for Web clients; Mobile still receives body tokens.

**Do not break:** Admin Mobile `TokenRefreshCoordinator` single-flight (QA-09).

---

## 10. Logout / revocation strategy

**Current:**

- Blacklist access JWT (cache; Redis required multi-node)
- Revoke refresh hash (or all devices)
- Clients clear local storage / secure storage

**Future:** Clear cookies (`Set-Cookie` max-age=0) + same revocation.

---

## 11–14. Client impact

| Client | QA-11 impact | Cookie migration impact |
|--------|--------------|-------------------------|
| Customer Web | Session-clear on refresh fail; keep localStorage until SEC-001 project | Major: remove token storage, credentials, CSRF |
| Admin Web | Sanitize `next`; keep localStorage | Major (same) |
| Customer Mobile | **No auth migration** | None if dual-mode Bearer retained |
| Admin Mobile | **No auth migration**; preserve QA-09 refresh | None |

---

## 15. API impact (cookie project)

- Cookie issue/clear helpers
- Dual auth (Bearer \| cookie)
- Optional CSRF middleware for cookie path
- CORS credentials flip
- Contract docs + PHPUnit matrix
- **Mobile must keep working with Bearer**

---

## 16. Database impact

**None for QA-11.** Refresh token table already supports revocation/rotation. Cookie auth needs no new tables unless adding server sessions (not required if JWT-in-cookie).

If a future design adds server sessions → update `QA_DATABASE_CHANGE_PROPOSAL.md` first.

---

## 17. Backward compatibility

Cookie project must ship as:

1. API accepts Bearer (all clients) **and** cookies (Web)
2. Web cutover
3. Deprecate token-in-localStorage
4. Never force Flutter onto cookies

---

## 18. Migration / rollback strategy (future SEC-001 project)

1. Feature flag `AUTH_WEB_COOKIE_MODE`
2. Dual-write period (tokens in body + cookies)
3. Web switches to credentials-only
4. Flag off → rollback to Bearer/localStorage within one deploy
5. Monitor 401 rates + CORS failures

---

## 19. Testing strategy

**QA-11 (this phase):**

- `Qa11SecurityTest` — RBAC, IDOR, escalation, logout, sensitive fields, payment stub
- Web unit checks — redirect sanitize, unauthorized handler wiring
- Mobile flutter test regression (no auth rewrite)
- Qa02–Qa10 regression

**Future cookie project:** CSRF matrix, credentialed CORS, cookie Secure in prod, Web E2E login/refresh/logout, mobile Bearer unchanged.

---

## 20. Deployment requirements (future)

- HTTPS everywhere (Secure cookies)
- Shared cache/Redis for JWT blacklist
- Explicit CORS origins for Web + Admin
- Same-site proxy **strongly preferred** over cross-site `SameSite=None`

---

## QA-SEC-001 DECISION (locked)

### **OPTION C — QA-SEC-001 remains OPEN**

**Rationale:**

1. Web and API are **cross-origin** in local and typical deploy layouts.
2. Full HttpOnly cookie auth requires API contract, CORS credentials, CSRF, dual auth, and dual Web cutovers.
3. A partial migration (cookies without CSRF, or localStorage removed without cookies) would **weaken** or break auth.
4. Mobile already uses secure storage; forcing cookies would regress Flutter for no gain.
5. Command explicitly allows documenting remaining work instead of unsafe partial migration.

**QA-11 still delivers:** architecture design, adversarial RBAC/IDOR tests, privilege-escalation fix, open-redirect fix, Customer Web session-clear hardening, secrets/headers/dependency review.

**Follow-up:** Schedule dedicated **SEC-001 cookie/BFF project** after QA-12 performance or as parallel security epic — not a drive-by in QA-11.
