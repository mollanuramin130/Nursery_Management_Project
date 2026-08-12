# PHASE 12 — Security Audit

GreenLeaf Nursery — post-hardening security audit.

**Date:** 2026-08-12  
**Scope:** Laravel API, MySQL, Website, Android, Admin  
**Method:** Code review + targeted feature tests (`Phase12HardeningTest`, regression Phases 3–11)

Severity: CRITICAL | HIGH | MEDIUM | LOW | INFO

---

## 1. Authentication

| Item | Status |
|------|--------|
| Password hashing (`hashed` cast / bcrypt) | OK |
| JWT access + refresh tokens | OK (existing Phase 3) |
| Refresh rotation / logout revocation | OK (blacklist + refresh rows) |
| Inactive/blocked users | OK (`active.user` middleware) |
| Stronger password rules (letters + mixed case + numbers) | **Fixed** Phase 12 |

**Residual:** Multi-node JWT blacklist requires shared cache (`CACHE_STORE=redis`). File cache is unsafe across nodes (CRITICAL ops).

---

## 2. Authorization / RBAC

| Item | Status |
|------|--------|
| Admin routes `permission:*` middleware | OK |
| Frontend-only hiding is not relied on | OK |
| Customer blocked from admin APIs | Verified (Phase 3) |

---

## 3. IDOR

| Resource | Result |
|----------|--------|
| Orders / addresses / wishlist / loyalty / subscriptions / notifications | Ownership scoped (prior phases) |
| Returns | Ownership via `user_id` (Phase 8) |

---

## 4. Mass assignment

| Finding | Severity | Fix |
|---------|----------|-----|
| `User` fillable included `status`, verification timestamps | MEDIUM | Narrowed fillable; status set only via `forceFill` / explicit assign in Auth/Admin services |
| `Order` privileged fields in fillable | LOW | Residual — creates go through services, not `$request->all()` |

---

## 5. SQL injection

Eloquent / query builder used for commerce paths. No new raw concatenated user input introduced. Dynamic sort columns remain allow-listed where present (prior work).

---

## 6. XSS

API returns JSON. Product/review HTML not rendered as trusted HTML in API. Clients must not `dangerouslySetInnerHTML` untrusted fields. Admin notes not exposed on customer return serialize (Phase 8).

---

## 7. File upload

Image URLs validated with `SecureImageUrl` (http/https only; rejects `javascript:` / non-URL schemes). MIME/content verification for binary uploads remains limited because catalog often uses remote URLs — document as residual if moving to local disk uploads.

---

## 8. Payment security

Phase 3 remains authoritative: backend totals, Razorpay HMAC, webhook secret required in production, unsigned webhooks gated. Frontend amount not trusted.

---

## 9. Webhooks

Signature + idempotency from Phase 3 retained. No unsigned path in production.

---

## 10. Rate limiting

| Route class | Limit |
|-------------|-------|
| Global API | **120/min** (Phase 12) |
| Auth register/login | Existing stricter throttles |
| Payments / checkout / webhooks | Existing Phase 3 throttles |
| Health | 60–120/min |

---

## 11. CORS

Configured via `CORS_ALLOWED_ORIGINS`. Production must list HTTPS shop + admin origins only (documented in `.env.example`). Wildcard `*` not used with credentials.

---

## 12. Secrets

`.env` gitignored; `.env.example` has empty secrets. Clients must not embed Razorpay secret / JWT / DB passwords. Website warns if production build still points at localhost API.

---

## 13. Headers

API middleware `SecurityHeaders`: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS when secure/production. CSP not applied globally (would break admin/web assets).

---

## 14. HTTPS

Production checklist requires HTTPS for API, website, admin, Android `API_BASE_URL`. Android release cleartext disabled.

---

## 15. Logging

Request ID sanitized (no CR/LF injection). Do not log passwords/tokens/card data. Production: `APP_DEBUG=false`, `LOG_LEVEL=warning|error`.

---

## 16. Privacy

Customer serializers hide admin-only return meta. Admin APIs permission-gated.

---

## 17–20. Findings summary

| ID | Severity | Finding | Fix / verification |
|----|----------|---------|-------------------|
| C1 | CRITICAL (ops) | `APP_DEBUG=true` in live | Checklist / runbook — operator must set false |
| C2 | CRITICAL (ops) | Missing live Razorpay + webhook secret | Checklist — refuse stubs in prod |
| C3 | CRITICAL (ops) | JWT blacklist on file cache (multi-node) | Use Redis; documented |
| H1 | HIGH | PlantFinder loaded entire catalog | Cap 300 candidates |
| H2 | HIGH | Reviews missing list indexes | Migration indexes |
| H3 | HIGH | Next.js localhost API default | Prod console error + `.env.example` |
| H4 | HIGH | CORS localhost in prod env | Docs / example |
| M1 | MEDIUM | No global API throttle | `throttleApi(120,1)` |
| M2 | MEDIUM | Weak passwords | `Password::min(8)->letters()->mixedCase()->numbers()` |
| M3 | MEDIUM | User privileged fillable | Narrowed |
| M4 | MEDIUM | Loose image URLs | `SecureImageUrl` |
| M5 | MEDIUM | Queue `after_commit=false` | Enabled `true` |
| M6 | MEDIUM | No home cache | 60s cache + invalidation |
| M7 | MEDIUM | Unbounded wishlist | Soft limit 100 |

Verification: `php artisan test` — **68 passed** including `Phase12HardeningTest`.
