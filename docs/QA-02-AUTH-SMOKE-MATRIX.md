# QA-02 Auth Smoke Matrix

**Date:** 2026-08-12  
**Environment:** local API `http://127.0.0.1:8000/api/v1`, Customer Web `:3000`, Admin Web `:3001`

Legend: **PASS** = verified this session · **CODE PASS** = implementation + unit/API verified, interactive UI not clicked · **N/A** · **NOT AVAILABLE** = no device/emulator run here · **BLOCKED** = env/throttle

| Client | Login | Invalid Login | Register | Refresh | Logout | Forgot | Reset |
|--------|-------|---------------|----------|---------|--------|--------|-------|
| Customer Web | CODE PASS | CODE PASS (API + message mapper) | CODE PASS (rules + UI) | CODE PASS (interceptor) | CODE PASS | CODE PASS (API + page) | CODE PASS (page + API test) |
| Customer Mobile | NOT AVAILABLE | CODE PASS (AuthMessages unit) | CODE PASS (PasswordRules unit) | CODE PASS (client clears tokens) | CODE PASS (provider) | CODE PASS (screen present) | CODE PASS (screen + API test) |
| Admin Web | CODE PASS | CODE PASS (`authUserMessage`) | N/A | CODE PASS | CODE PASS | N/A | N/A |
| Admin Mobile | NOT AVAILABLE | CODE PASS (userMessage unit) | N/A | CODE PASS (invalidate on fail) | CODE PASS | N/A | N/A |

## API checks executed (this session)

| Check | Result |
|-------|--------|
| `POST /auth/login` wrong password | PASS — `Invalid email or password` / `AUTH_INVALID_CREDENTIALS` |
| `POST /auth/login` asha@example.com | PASS — tokens returned |
| `POST /auth/login` admin@nursery.test | PASS |
| `POST /auth/refresh` valid | PASS |
| `POST /auth/refresh` invalid | PASS — `AUTH_REFRESH_INVALID` |
| `POST /auth/forgot-password` | PASS after `cache:clear` (429 if throttled) |
| PHPUnit `Qa02AuthMessagesTest` | PASS (10) |

## Notes

1. Reset email HTML link targets Customer Web (`CUSTOMER_WEB_URL`), not the mobile app.
2. Do not mark mobile Login cells PASS until a device/emulator run is recorded.
3. Auth throttle (`login` 10/min, `forgot` 5/min, `orders` 20/min) can produce friendly 429 copy during aggressive smoke.
