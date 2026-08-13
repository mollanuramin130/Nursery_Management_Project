# QA-34 Test Matrix

**Environment:** Local TEST · BFF `:3000`/`:3001` · API `:8000` · Device `2d3714f`  
**Date:** 2026-08-13  
**Baseline:** QA-33 COMPLETE · QA-SEC-001 CLOSED

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Customer Web login | **PASS** | readiness + smoke |
| 2 | Customer Web logout | **PASS** | me → 401 |
| 3 | Customer Web session refresh | **PASS** | refresh 200, no token in body |
| 4 | Customer Web protected routes | **PASS** | `/auth/me`, `/cart` via BFF |
| 5 | Admin Web login | **PASS** | smoke |
| 6 | Admin Web logout | **PASS** | me → 401 |
| 7 | Admin Web session refresh | **PASS** | refresh 200 |
| 8 | Admin Web protected routes | **PASS** | `/admin/orders` 200 |
| 9 | HttpOnly cookie | **PASS** | Set-Cookie attrs |
| 10 | Secure cookie under HTTPS | **PARTIAL** | unit policy PASS; live host UNVERIFIED |
| 11 | SameSite behavior | **PASS** | Lax |
| 12 | CSRF valid request | **PASS** | login/refresh |
| 13 | CSRF invalid/missing | **PASS** | 403 |
| 14 | JWT browser leak audit | **PASS** | body + source grep |
| 15 | localStorage token audit | **PASS** | getAccess/Refresh null; legacy cleared |
| 16 | sessionStorage token audit | **PASS** | announce dismiss only |
| 17 | Customer/Admin isolation | **PASS** | customer→admin 403 |
| 18 | IDOR | **PASS** | Qa11/Qa33 + prior |
| 19 | Customer order access | **PASS** | suite |
| 20 | Admin order access | **PASS** | BFF admin orders |
| 21 | Cart | **PASS** | BFF `/cart` 200 |
| 22 | Checkout | **PASS** | suite / prior |
| 23 | Razorpay TEST regression | **PASS** | Qa18–Qa31 filter; `rzp_test_*` |
| 24 | COD | **PASS** | prior + suite |
| 25 | Notifications | **PASS** | stub/in-app |
| 26 | Returns/refunds | **PASS** | suite |
| 27 | Customer Mobile | **PASS** | Bearer login + APK launch |
| 28 | Admin Mobile | **PASS** | tests + APK launch |
| 29 | Cross-client parity | **PASS** | |
| 30 | UI uniformity | **PASS** | |
| 31 | Build | **PASS** | web + admin |
| 32 | Analyze/lint | **PASS** | flutter info-only |
| 33 | Full regression | **PASS** | **242 / 1184** |
| 34 | QA-ADM-002 | **OPEN** intentional | unchanged |
| 35 | Dynamic QR settlement | **UNVERIFIED** | carry-forward |
| 36 | UPI-app settlement | **BLOCKED** | carry-forward |
| 37 | Mobile-data without adb reverse | **UNVERIFIED** | carry-forward |
| 38 | Env deploy contract (`API_PROXY_TARGET` / `COOKIE_SECURE`) | **PASS** | examples + readiness script |
| 39 | CORS credentials false | **PASS** | `config/cors.php` |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A · NO
