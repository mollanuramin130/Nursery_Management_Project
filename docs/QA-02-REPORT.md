# QA-02 Authentication Remediation Report

**Date:** 2026-08-12  
**Status:** PARTIAL (API + Web + unit tests verified; Customer/Admin Mobile interactive UI smoke NOT AVAILABLE on this host)

**QA-01 gate:** PARTIALLY VERIFIED — allowed to start QA-02.

---

## Scope

Authentication, session integrity, password recovery, and auth UX across:

- Laravel REST API
- Customer Web
- Customer Mobile
- Admin Web
- Admin Mobile

Out of scope (deferred): cart, checkout, payments, QA-SEC-001 httpOnly cookies.

---

## Bugs Addressed

| ID | Result |
|----|--------|
| QA-AUTH-001 | **FIXED** — safe login messages + `meta.error_code`; client-friendly mapping |
| QA-AUTH-002 | **FIXED** — Customer Web + Mobile forgot/reset UI; email links via `CUSTOMER_WEB_URL` |
| QA-AUTH-003 | **FIXED** — client password rules match API `min(8)+letters+mixedCase+numbers` |
| QA-AUTH-004 | **FIXED** — Admin Mobile refresh failure clears tokens + session → login |

---

## Root Causes

1. `AuthenticationException` renderer discarded safe AuthService messages → generic `Unauthenticated`.
2. Reset API existed; Customer Web/Mobile lacked complete reset UI; reset emails pointed at API URL.
3. Clients enforced weaker password rules than API.
4. Admin Mobile `_refreshTokens` returned `false` without clearing secure storage / in-memory user.

---

## Backend Changes

- `bootstrap/app.php` — whitelist: Invalid credentials / Account blocked / Invalid refresh token
- `AppServiceProvider` — `ResetPassword::createUrlUsing` → `{CUSTOMER_WEB_URL}/reset-password?token=&email=`
- `tests/Feature/Qa02AuthMessagesTest.php` — expanded coverage (login, blocked, register weak/dup, refresh/logout, forgot/reset)
- Friendly 429 messages via `ApiResponse` (shared with prior work)

---

## Customer Web Changes

- `/forgot-password`, `/reset-password`
- `src/lib/password-rules.ts`
- `src/lib/auth-messages.ts` — safe login/register/forgot/reset toasts
- Login uses health probe + `authUserMessage`

---

## Customer Mobile Changes

- Forgot + reset screens + routes
- `password_rules.dart`, `auth_messages.dart`
- Secure storage retained; refresh clears tokens on failure
- Unit tests: `test/auth_messages_test.dart`

---

## Admin Web Changes

- `src/lib/auth-messages.ts` on login
- Existing refresh interceptor + staff gate unchanged (RBAC backend authoritative)

---

## Admin Mobile Changes

- `_invalidateSession()` on refresh failure (clear tokens + `onSessionInvalid`)
- Friendlier `ApiException.userMessage` for credentials / blocked / 429
- Unit tests: `test/auth_messages_test.dart`

---

## API Contract Changes

No breaking API contract changes.

Login still accepts:

```json
{ "email": "...", "password": "...", "device": { "platform": "web" } }
```

Response still uses `access_token`, `refresh_token`, `user`.

Auth failure envelope now includes distinct `message` + `meta.error_code` for known auth cases (same envelope shape).

---

## Database Changes

No database schema changes.

---

## Tests Executed

| Suite | Result |
|-------|--------|
| `php artisan test --filter=Qa02AuthMessagesTest` | **PASS** (10 tests) |
| Customer Mobile `flutter test test/auth_messages_test.dart` | **PASS** |
| Admin Mobile `flutter test test/auth_messages_test.dart` | **PASS** |
| `flutter analyze` (auth files) | **PASS** |
| Live curl: invalid login | **PASS** (`Invalid email or password` / `AUTH_INVALID_CREDENTIALS`) |
| Live curl: valid customer + admin login | **PASS** |
| Live curl: refresh success / invalid refresh | **PASS** |
| Live curl: forgot-password | **PASS** after cache clear (earlier **429** during burst — expected throttle) |
| Customer/Admin Mobile interactive login UI | **NOT AVAILABLE** on this host |
| Customer Web interactive click-through | **NOT RUN** (pages present; API contract verified) |

---

## Manual Smoke Results

See `docs/QA-02-AUTH-SMOKE-MATRIX.md`.

---

## Security Considerations

- No user enumeration on forgot-password (generic success copy).
- Invalid email and wrong password share the same credentials message.
- Tokens/passwords not logged in client auth helpers.
- **QA-SEC-001** (Web `localStorage` JWT) deferred to QA-11 — documented, not fixed here.

---

## Remaining Issues

- Mobile interactive UI smoke not run here → overall QA-02 **PARTIAL** until device/emulator smoke is marked PASS.
- Forgot/reset email delivery depends on `MAIL_*` and `CUSTOMER_WEB_URL` in the environment.
- Mobile deep-link into reset screen: email opens Customer Web by design; app supports manual token entry / query prefill.

---

## Deferred bugs

- QA-CART-001, QA-CART-002 → QA-03
- QA-CHK-001, QA-PAY-001 → QA-04
- QA-PAR-001 → later parity
- QA-SEC-001 → QA-11

---

## Can QA-03 start?

**YES** — authentication contract and QA-AUTH-001–004 code fixes are in place; remaining PARTIAL is mobile UI smoke / mail env ops, not a blocker for cart/checkout remediation.
