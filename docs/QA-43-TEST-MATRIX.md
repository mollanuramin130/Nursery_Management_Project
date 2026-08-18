# QA-43 TEST MATRIX

**Date:** 2026-08-18  
**GREEN:** NO · **LIVE:** OUT OF SCOPE  

Branding cases (QA-43-009…037) remain from the splash/icon pass. This table adds the error-handling + helpline cases.

| ID | Client | Case | Expected | Result |
|----|--------|------|----------|--------|
| QA-43-009 | Customer | Splash finishes locally | `onFinished` without API | PASS (unit) |
| QA-43-010 | Customer | Offline / no network | Splash still completes | PASS (unit) |
| QA-43-011 | Customer | Splash is not an API gate | No `ApiClient` in splash | PASS (unit) |
| QA-43-012 | Customer | Brand copy | GreenLeaf + Grow Better. Live Greener. | PASS (unit) |
| QA-43-013 | Customer | Adaptive icon | Vector foreground, mipmaps present | PASS (unit) |
| QA-43-015 | Customer | `maxDisplay` cap | Completes even if `minDisplay` is 30s | PASS (unit) |
| QA-43-016 | Customer | Reduced motion | Wordmark still readable | PASS (unit) |
| QA-43-017 | Customer | Seedling ≠ shield | Foreground XML is seedling | PASS (unit) |
| QA-43-020 | Admin | Splash completes locally | GreenLeaf Admin + tagline | PASS (unit) |
| QA-43-021 | Admin | Not an API gate | No `ApiClient` in splash | PASS (unit) |
| QA-43-022 | Admin | `maxDisplay` cap | Completes | PASS (unit) |
| QA-43-023 | Admin | Shield mark | Foreground XML is shield | PASS (unit) |
| QA-43-030 | Customer | Cold start on Vivo 1951 | Native cream + soil/seed then home | **PARTIAL** — native soil/seed PASS; Flutter sprout/wordmark skipped under debug first-frame delay; home after splash PASS (`docs/qa43-vivo/`) |
| QA-43-033 | Customer | Launcher icon | Seedling / leaf, not Android default | **PASS** on Vivo FunTouch (white tile + dark leaf). See `docs/qa43-vivo/launcher-both-icons.png` |
| QA-43-034 | Admin | Cold start on Vivo | Dark splash + shield + grid | **PARTIAL** — dark field + leaf/shield outline PASS; wordmark not captured on debug; dashboard after splash PASS |
| QA-43-035 | Admin | Launcher icon distinct | Shield / ops mark ≠ customer | **PASS** distinct; label truncated “GreenLeaf…” (**QA-43-032**) |
| QA-43-037 | Customer | False offline on healthy cache peek | No saved-data banner | PASS (`qa42a_refresh_banner_test`) |
| QA-43-100 | All | Classify 500 ≠ offline | Server unavailable copy | PASS (unit) |
| QA-43-101 | All | Classify 503 ≠ offline | Unable to connect copy | PASS (unit) |
| QA-43-102 | All | Timeout ≠ support | Category A, no helpline | PASS (unit) |
| QA-43-103 | All | Unexpected offers support | Try Again + Contact Support + `GL-xxxxx` | PASS (unit) |
| QA-43-104 | All | Helpline URI | `tel:8926627220` | PASS (unit) |
| QA-43-105 | All | Secrets redacted | JWT / rzp / password / SQL not in UI copy | PASS (unit) |
| QA-43-106 | Customer Mobile | Second connection fail → offline | First fail is “Connection temporarily unavailable” | PASS (code + unit streak) |
| QA-43-107 | Customer Web | Browser `offline` event | Confirmed offline banner | PASS (code) E2E UNVERIFIED |
| QA-43-108 | Mobile | Contact Support sheet | Dialer after explicit Call Support | UNVERIFIED (device) |
| QA-43-109 | Web desktop | Call Support | Number + copy; no forced tel: | PASS (code) E2E UNVERIFIED |
| QA-43-110 | Payment | Failed/unconfirmed | “Payment could not be completed”; no fake success | PASS (code) device UNVERIFIED |
| QA-43-111 | Auth | 401 / session | Existing recovery; no auto helpline | PASS (classifier unit) |
| QA-43-112 | Images | Failed image | Resilient placeholder; no global support | PASS (existing resilient image, unchanged) |
| QA-43-113 | Retry tap flood | Coalesce / debounce | RetryButton 450ms busy | PASS (code) device UNVERIFIED |
| QA-43-114 | Admin catch `toString()` | Sanitized ops copy | PASS (admin unit) |
| QA-43-115 | Web crash | Error boundary + `error.tsx` | Branded view | PASS (code) browser UNVERIFIED |
| QA-43-116 | Cache + API down | Keep content + kind-specific banner | PASS (unit suffix) device UNVERIFIED |

## Auth / throttle friction

| ID | Client | Case | Expected | Result |
|----|--------|------|----------|--------|
| QA-43-200 | API | Valid login after 80 catalog GETs | 200, not 429 | PASS (PHPUnit, testing limits) |
| QA-43-201 | API | Testing env relaxed api/login ceilings | api ≥ 1000, login ≥ 60 | PASS (unit + feature) |
| QA-43-202 | API | Login limiter still enforces when set to 2/min | 3rd bad login 429 | PASS (PHPUnit) |
| QA-43-203 | API | Two valid logins in a row | Both 200 | PASS (PHPUnit) |
| QA-43-204 | API | Health + webhook skip global limiter | `skipsGlobalApiLimit` true | PASS (PHPUnit) |
| QA-43-205 | Customer Web | Invalid credentials | Normal auth error, not throttle copy | PASS (existing Qa02 + unit) |
| QA-43-206 | Customer Web | Concurrent login taps | One request; duplicate ignored | PASS (code) E2E UNVERIFIED |
| QA-43-207 | All | HTTP 429 ≠ offline | `rateLimited` kind | PASS (web + Flutter unit) |
| QA-43-208 | Customer Mobile | Cart 429 | Keep cart; do not retry; not local-only | PASS (code) device UNVERIFIED |
| QA-43-209 | Customer | Rapid Home→Shop→Cart→Checkout | No brute-force popup in local | UNVERIFIED (device/browser) |
| QA-43-210 | Customer | Double-tap Place Order | One order (busy + server idempotency) | PASS (existing code) E2E UNVERIFIED |
| QA-43-211 | Payment | Webhook HMAC + replay | Signature required; idempotent | PRESERVED (no change) |
| QA-43-212 | Admin Web | Login single-flight | Duplicate tap ignored | PASS (code) E2E UNVERIFIED |
| QA-43-213 | Production | Login ~12/min, api ~180/min | Protection remains | PASS (config defaults) |

Device tests require the physical Vivo (`2d3714f`), `adb reverse tcp:8000 tcp:8000`, and `API_BASE_URL=http://127.0.0.1:8000/api/v1`.
