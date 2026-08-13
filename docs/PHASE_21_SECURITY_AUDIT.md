# PHASE 21 — Security Audit

**Date:** 2026-08-12  
**Scope:** Laravel API + four clients

## Summary

Platform security baseline is strong (JWT, RBAC middleware, order IDOR scoping, webhook HMAC, request IDs, health probes). Phase 21 fixed the highest remaining API risk (unbound webhook payment match) and client cleartext/headers gaps.

---

## Findings

| ID | Severity | Component | Finding | Fix / Status |
|----|----------|-----------|---------|--------------|
| S1 | **High** | Payment webhook | Missing `provider_order_id` could bind latest payment | **FIXED** — require provider order id; refuse unbound match |
| S2 | **High** | CI | No automated test gate | **FIXED** — `.github/workflows/ci.yml` |
| S3 | **High** | Admin Mobile | Release manifest allowed cleartext | **FIXED** — cleartext false + network security config; debug overlay only |
| S4 | **High** | Customer/Admin Web | No security headers | **FIXED** — X-CTO, Referrer-Policy, XFO, Permissions-Policy |
| S5 | **High** | Web JWT | Tokens in `localStorage` (XSS risk) | **OPEN** — httpOnly cookie session is a larger architecture change; document as known risk |
| S6 | **Medium** | CORS | Empty env could fall back to localhost | **FIXED** — production fail-closed; expose `X-Request-Id` |
| S7 | **Medium** | Order fillable | Broad `$fillable` including status/totals | **OPEN** — create paths use explicit arrays today; narrow in future |
| S8 | **Medium** | Audit | Login/checkout/payment verify not in AuditLogger | **OPEN** — non-blocking; app logs exist |
| S9 | **Medium** | Admin Web | No localhost API prod warning | **FIXED** — console CRITICAL guard |
| S10 | **Low** | Demo credentials | Dev-gated prefills | **PASS** — empty when `NODE_ENV=production` |
| S11 | **Info** | Secrets scan | No live keys in apps source | **PASS** |
| S12 | **Pass** | IDOR orders | Scoped by `user_id` | Verified |
| S13 | **Pass** | Admin authz | `permission:` middleware | Verified + existing tests |
| S14 | **Pass** | Error responses | No stack traces when debug off | Verified |
| S15 | **Pass** | Mobile tokens | `FlutterSecureStorage` | Verified |
| S16 | **Pass** | Rate limits | Login 10/min, register 5, API 120 | Verified |
| S17 | **Info** | MFA | Not implemented for Admin | Future enhancement |

---

## Verification

```bash
cd apps/nursery-api && php artisan test --filter=Phase21WebhookHardeningTest
cd apps/nursery-api && php artisan test --filter='Phase3HardeningTest|Phase7FulfillmentTest'
```

## Credential rotation

If any historical secret was ever committed outside this audit scope, rotate JWT, DB, Razorpay, and webhook secrets before production cutover. **Do not paste secrets into docs.**
