# QA-02 Report — Authentication + Session + Password Reset

**Date:** 2026-08-12  
**Status:** COMPLETE (tests + live API verification PASS)

---

## 1. Objective

Fix authentication error clarity, password-reset client flows, register password rule parity, and Admin Mobile stale session after refresh failure.

## 2. Bugs addressed

| ID | Result |
|----|--------|
| QA-AUTH-001 | **FIXED / VERIFIED** — login returns `Invalid email or password` / `Account is blocked` with distinct `meta.error_code` |
| QA-AUTH-002 | **FIXED / VERIFIED** — Customer Web + Mobile reset-password UI; email links → Customer Web |
| QA-AUTH-003 | **FIXED / VERIFIED** — Web/Mobile client validation matches API mixed-case + number rules |
| QA-AUTH-004 | **FIXED / VERIFIED** — Admin Mobile clears tokens + in-memory user on refresh failure → login redirect |

## 3. Root causes

1. `bootstrap/app.php` AuthenticationException renderer discarded safe messages.  
2. Reset API existed; Customer Web/Mobile had no `/reset-password` UI; reset emails used API `APP_URL` (no HTML route).  
3. Clients only enforced min length 8.  
4. Admin Mobile `_refreshTokens` returned `false` without `clearTokens` / clearing `AuthProvider.user`.

## 4. Files changed

### API
- `apps/nursery-api/bootstrap/app.php`
- `apps/nursery-api/app/Providers/AppServiceProvider.php` (`ResetPassword::createUrlUsing` → `CUSTOMER_WEB_URL`)
- `apps/nursery-api/app/Modules/Auth/Http/Requests/ResetPasswordRequest.php`
- `apps/nursery-api/app/Modules/Auth/Services/AuthService.php`
- `apps/nursery-api/.env.example` (+ `CUSTOMER_WEB_URL`)
- `apps/nursery-api/tests/Feature/Qa02AuthMessagesTest.php`

### Customer Web
- `apps/nursery-web/src/lib/password-rules.ts`
- `apps/nursery-web/src/app/reset-password/page.tsx`
- `apps/nursery-web/src/app/register/page.tsx`
- `apps/nursery-web/src/app/forgot-password/page.tsx`
- `apps/nursery-web/src/store/auth.ts`

### Customer Mobile
- `apps/nursery_app/lib/core/password_rules.dart`
- `apps/nursery_app/lib/core/auth_messages.dart`
- `apps/nursery_app/lib/core/auth_navigation.dart`
- `apps/nursery_app/lib/providers/auth_provider.dart`
- `apps/nursery_app/lib/screens/reset_password_screen.dart`
- `apps/nursery_app/lib/screens/register_screen.dart`
- `apps/nursery_app/lib/screens/forgot_password_screen.dart`
- `apps/nursery_app/lib/app.dart`
- `apps/nursery_app/test/widget_test.dart`

### Admin Mobile
- `apps/nursery_admin_mobile/lib/core/api_client.dart`
- `apps/nursery_admin_mobile/lib/providers/auth_provider.dart`
- `apps/nursery_admin_mobile/lib/main.dart`

## 5. API changes

- 401 login/refresh messages preserved when safe; codes: `AUTH_INVALID_CREDENTIALS`, `AUTH_ACCOUNT_BLOCKED`, `AUTH_REFRESH_INVALID`
- Password reset email URL: `{CUSTOMER_WEB_URL}/reset-password?token=&email=`
- `password_confirmation` included in reset validation payload

## 6. Database changes

None.

## 7–8. Frontend / Mobile

- Web/Mobile: reset password screens + register rule UX  
- Admin Mobile: session invalidation callback → login

## 9–11. Tests executed / results

| Test | Result |
|------|--------|
| `php artisan test --filter=Qa02AuthMessagesTest` | **PASS** (3) |
| Live wrong password → `AUTH_INVALID_CREDENTIALS` | **PASS** |
| Live blocked account → `AUTH_ACCOUNT_BLOCKED` | **PASS** (user restored after) |
| `flutter test test/widget_test.dart` (customer) | **PASS** |
| Dart analyze (auth files) | **PASS** |

## 12. Remaining issues

- Mail delivery still depends on `MAIL_*` config (reset link email).  
- Mobile deep-link of email URL into the app is not required (web URL is primary; app has manual token entry).  
- QA-SEC-001 (localStorage JWT) deferred to QA-11.

## 13. Risks

Low. Message whitelist prevents leaking arbitrary exception text.

## 14. Next phase

**QA-03 — Cart + Coupon + Total Consistency** (QA-CART-001, QA-CART-002)
