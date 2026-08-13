# QA-33 Security Report — Web HttpOnly / BFF Authentication

**Date:** 2026-08-13  
**Issue:** QA-SEC-001  
**Decision:** **CLOSED** (Web JWT no longer JS-accessible)

---

## 1. Threat model (before)

```
XSS on Customer/Admin Web
    → localStorage.getItem(gl_* / gl_admin_*)
    → steal access + refresh JWT
    → call Laravel API as victim from any origin
```

Both access and refresh tokens were readable by arbitrary page JavaScript.

---

## 2. Target architecture (implemented)

```
Browser (no JWT in JS)
   ↓ same-origin fetch + CSRF header
Next.js BFF  (/api/bff/auth/*, /api/bff/proxy/*)
   ↓ HttpOnly cookies → Authorization: Bearer (server-only)
Laravel API /api/v1
   ↓ unchanged Bearer auth for Mobile
Flutter secure storage (unchanged)
```

| Client | Auth mechanism |
|--------|----------------|
| Customer Web | HttpOnly cookies via BFF |
| Admin Web | HttpOnly cookies via BFF |
| Customer Mobile | Bearer + flutter_secure_storage |
| Admin Mobile | Bearer + flutter_secure_storage |

Laravel API contract for Mobile **unchanged** (tokens still in login JSON body).

---

## 3. Cookie attributes

| Cookie | HttpOnly | Secure (prod) | Secure (local http) | SameSite | Path |
|--------|----------|---------------|---------------------|----------|------|
| `gl_web_access` / `gl_admin_web_access` | yes | yes | no | Lax | `/` |
| `gl_web_refresh` / `gl_admin_web_refresh` | yes | yes | no | Lax | `/api/bff` |
| `gl_web_csrf` / `gl_admin_web_csrf` | **no** (double-submit) | yes | no | Lax | `/` |

Verified on Customer login response: access/refresh `HttpOnly; SameSite=Lax`; body **does not** contain tokens.

---

## 4. CSRF

Mutating BFF routes require `X-CSRF-Token` matching the readable CSRF cookie.  
Mismatch → **403** `CSRF_MISMATCH` (smoke verified).

Same-origin BFF + `SameSite=Lax` further reduces cross-site cookie posting.

---

## 5. CORS

Laravel remains `supports_credentials: false` with explicit origins — **correct**.  
Browsers no longer send credentialed cross-origin API auth; the BFF uses server-side `fetch` to Laravel (no browser CORS for JWT).

Never use `Access-Control-Allow-Origin: *` with credentials.

---

## 6. Token leak audit

| Surface | Result |
|---------|--------|
| localStorage JWT keys | Cleared on bootstrap; `getAccess`/`getRefresh` always `null` |
| sessionStorage JWT | None |
| Login/register JSON body | Tokens stripped by BFF |
| Zustand persistence | No token persistence |
| URL/query | No tokens |
| console logs | No token logging found |
| Guest cart / device id localStorage | Non-auth; retained |

---

## 7. Residual risks (accepted / document)

1. **Same-origin XSS** can still *use* the session (cookie sent automatically) but cannot *exfiltrate* JWTs for offline reuse — standard HttpOnly model. CSP from QA-32 remains.
2. **Local HTTP** uses `Secure=false` cookies; production Next (`NODE_ENV=production`) sets `Secure=true`. Override via `COOKIE_SECURE`.
3. **Production deploy** should keep Web+BFF same-site (already true) and HTTPS.
4. **QA-ADM-002** intentional Admin Mobile scope unchanged.

---

## 8. Evidence commands (no secrets)

```bash
bash apps/nursery-api/scripts/qa33_bff_smoke.sh
cd apps/nursery-web && npm run test:unit
cd apps/nursery-admin && npm run test:unit
cd apps/nursery-api && ./vendor/bin/phpunit --filter Qa33
```
