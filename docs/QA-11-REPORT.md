# QA-11 REPORT — Security Hardening + RBAC Audit + Web Session Protection

**Date:** 2026-08-12  
**Phase:** QA-11  
**Status:** **COMPLETE**

---

## 1. Status

**COMPLETE**

Security architecture reviewed. **QA-SEC-001 remains OPEN** (Option C — full HttpOnly cookie migration deferred as a dedicated project). Safe hardening delivered and tested.

---

## 2. Security architecture

See `docs/QA-11-SECURITY-DESIGN.md`.

Summary: Laravel JWT access + opaque DB refresh tokens; Web stores tokens in localStorage; Mobile uses `flutter_secure_storage`; CORS `supports_credentials: false`; CSRF N/A for current Bearer model.

---

## 3. QA-SEC-001 status

### **OPTION C — OPEN**

| Criterion | Result |
|-----------|--------|
| Web no longer stores JWTs in localStorage | **No** (intentionally deferred) |
| Secure cookie architecture designed | **Yes** (design doc) |
| Unsafe half-migration avoided | **Yes** |

Follow-up: dedicated same-site / BFF cookie project after deploy topology is fixed.

---

## 4. Bugs discovered

| ID | Severity | Tags | Finding |
|----|----------|------|---------|
| QA-SEC-002 | HIGH | SECURITY · ADMIN · RBAC | `users.manage` could assign `super_admin` |
| QA-SEC-003 | MEDIUM | SECURITY · ADMIN · UX | Admin Web login `?next=` open redirect |
| QA-SEC-004 | MEDIUM | SECURITY · FRONTEND | Customer Web refresh-fail cleared tokens but left Zustand `user` |

---

## 5. Bugs fixed

| ID | Fix |
|----|-----|
| QA-SEC-002 | `AdminUserController::assertActorMayAssignRoles` — only `super_admin` may grant `super_admin` |
| QA-SEC-003 | `sanitizeAdminNext` + LoginClient |
| QA-SEC-004 | `setUnauthorizedHandler` + `clearSession` on Customer Web (parity with Admin Web) |

Stale Flutter `widget_test` auth message expectations aligned with QA-02 `AuthMessages` (test-only).

---

## 6. Customer Web security

| Check | Result |
|-------|--------|
| Token storage still localStorage | **OPEN** (SEC-001) |
| Refresh-fail clears user session | **PASS** |
| Open-redirect sanitize (existing) | **PASS** |
| Unit checks | **PASS** |

---

## 7. Admin Web security

| Check | Result |
|-------|--------|
| Token storage still localStorage | **OPEN** (SEC-001) |
| Login `next` sanitize | **PASS** |
| Client RBAC UX-only; API enforces | **PASS** (documented) |
| Unit checks | **PASS** |

---

## 8. Customer Mobile security regression

| Check | Result |
|-------|--------|
| Still flutter_secure_storage / Bearer | **PASS** (unchanged) |
| `flutter test` widget + auth_messages | **PASS** |
| Device interactive | **UNVERIFIED** |

---

## 9. Admin Mobile security regression

| Check | Result |
|-------|--------|
| TokenRefreshCoordinator | **PASS** (`session_refresh_test.dart`) |
| Cookie migration | **N/A** (not applied) |
| Device interactive | **UNVERIFIED** |

---

## 10. API authentication

| Check | Result |
|-------|--------|
| Login failure 401 | **PASS** |
| Logout invalidates refresh | **PASS** |
| Me omits password | **PASS** |
| Forgot-password no enumeration | **PASS** |

---

## 11. RBAC

| Check | Result |
|-------|--------|
| Customer → admin 403 | **PASS** |
| products.read vs write | **PASS** |
| inventory.view vs adjust | **PASS** |
| orders.view vs update_status | **PASS** |
| purchase_orders.view | **PASS** |
| super_admin bypass | **PASS** |
| super_admin assignment guard | **PASS** |

---

## 12. IDOR / object authorization

| Resource | Result |
|----------|--------|
| Order show cross-customer | **PASS** (404) |
| Address update cross-customer | **PASS** (404) |
| Return show cross-customer | **PASS** (404) |
| Profile mass-assignment | **PASS** (roles/password ignored) |

---

## 13. Password / reset security

| Check | Result |
|-------|--------|
| Hashed passwords / hidden | **PASS** |
| Forgot non-enumerating | **PASS** |
| Reset reuse deep matrix | **UNVERIFIED** this phase (prior QA-02) |

---

## 14. Payment security

| Check | Result |
|-------|--------|
| Production stub blocking (prior Qa04/Qa08) | **PASS** (regression suite) |
| Razorpay live | **UNVERIFIED** |

---

## 15. CORS / CSRF

| Check | Result |
|-------|--------|
| Explicit origins; credentials false | **PASS** (code review) |
| CSRF for cookie auth | **N/A** (no cookies yet) |
| Credentialed CORS live matrix | **UNVERIFIED** |

---

## 16. Security headers

| Header | API |
|--------|-----|
| X-Content-Type-Options | **PASS** (`nosniff`) |
| X-Frame-Options | **PASS** (`DENY`) |
| Referrer-Policy | **PASS** |
| Permissions-Policy | **PASS** |
| HSTS | **PASS** when secure/production |
| CSP | **NOT APPLICABLE** on API (documented) |

---

## 17. Secrets / environment audit

| Check | Result |
|-------|--------|
| `.env` gitignored | **PASS** |
| `.env.example` placeholders | **PASS** |
| Live secret literals in source | **PASS** (docs use `rzp_live_xxx` placeholder only) |

---

## 18. Dependency audit

| Suite | Result |
|-------|--------|
| `composer audit` | **PASS** (0 advisories) |
| Customer Web `npm audit` | **PASS** (0 vulns) |
| Admin Web `npm audit` | **PASS** (0 vulns) |

No blind upgrades performed.

---

## 19. Logging / data exposure

| Check | Result |
|-------|--------|
| Auth responses omit password | **PASS** |
| API request logger writing headers | **N/A** (schema/env only; not implemented) |
| Risk if logger enabled without redaction | Documented remaining risk |

---

## 20. Unit tests

| Suite | Result |
|-------|--------|
| Customer Web `npm run test:unit` | **PASS** |
| Admin Web `npm run test:unit` | **PASS** |
| Customer Mobile flutter tests | **PASS** |
| Admin Mobile session_refresh | **PASS** |

---

## 21. Integration tests

| Suite | Result |
|-------|--------|
| `Qa11SecurityTest` | **PASS** 11/11 |

---

## 22. Regression tests

```
php artisan test --filter='Qa11|Qa10|Qa09|Qa08|Qa07|Qa05|Qa04|Qa03|Qa02'
→ passed tests=60 assertions=279
```

**PASS**

---

## 23. Real-device tests

| Item | Result |
|------|--------|
| Device available | Yes (`2d3714f`) |
| Interactive login/refresh/logout UI | **UNVERIFIED** |
| API health LAN | **PASS** |

---

## 24. Build/lint/analyze

| Item | Result |
|------|--------|
| PHPUnit QA filter | **PASS** |
| Web unit | **PASS** |
| Flutter targeted | **PASS** |
| Full lint suites | **UNVERIFIED** this phase |

---

## 25. Database changes

**None.**

---

## 26. API contract changes

**None** for clients. Behavioral hardening: assigning `super_admin` without being `super_admin` → **403**.

---

## 27. UNVERIFIED

- Full HttpOnly cookie migration  
- Razorpay live  
- Device interactive auth  
- Reset-token reuse deep matrix  
- Credentialed CORS (future)  
- Full npm/composer upgrade campaign  

---

## 28. BLOCKED

**None.**

---

## 29. Remaining risks

1. **QA-SEC-001** OPEN — XSS can still steal Web JWTs  
2. Cross-origin cookie project still required for FIXED claim  
3. Refresh reuse family wipe not implemented  
4. `api_request_logs` schema could store headers if logger is later enabled without redaction  
5. Razorpay live UNVERIFIED  
6. QA-ADM-002 intentional PARTIAL  

---

## 30. Files changed

- `docs/QA-11-SECURITY-DESIGN.md`
- `docs/QA-11-REPORT.md` (this file)
- `docs/QA-11-CLOSEOUT-REPORT.md`
- `docs/QA-11-TEST-MATRIX.md`
- `docs/QA_MASTER_BUG_REGISTER.md`
- `docs/QA_FEATURE_MATRIX.md`
- `docs/QA_REMEDIATION_ROADMAP.md`
- `apps/nursery-api/app/Modules/Admin/Http/Controllers/AdminUserController.php`
- `apps/nursery-api/tests/Feature/Qa11SecurityTest.php`
- `apps/nursery-web/src/lib/api.ts`
- `apps/nursery-web/src/store/auth.ts`
- `apps/nursery-web/src/lib/qa-unit-checks.ts`
- `apps/nursery-admin/src/lib/auth-redirect.ts`
- `apps/nursery-admin/src/app/login/LoginClient.tsx`
- `apps/nursery-admin/src/lib/qa-unit-checks.ts`
- `apps/nursery_app/test/widget_test.dart`

---

## 31. QA-12 readiness

**YES** — proceed to Performance + reliability. Track SEC-001 cookie/BFF as a parallel security epic; do not block QA-12 on it.
