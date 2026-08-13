# QA-33 Test Matrix

**Environment:** Local TEST · BFF on `:3000`/`:3001` · Laravel `:8000` · Device `2d3714f`  
**Date:** 2026-08-13  
**Baseline:** QA-32 PARTIAL

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Threat model documented | **PASS** | SECURITY-REPORT |
| 2 | Customer login sets HttpOnly cookies | **PASS** | smoke + Set-Cookie attrs |
| 3 | Login JSON has no access/refresh | **PASS** | smoke |
| 4 | JS `storage.getAccess/Refresh` null | **PASS** | unit checks |
| 5 | Legacy localStorage JWT keys cleared | **PASS** | bootstrap `clearLegacyAuthTokens` |
| 6 | Customer `/auth/me` via BFF | **PASS** | smoke |
| 7 | Customer logout invalidates session | **PASS** | me → 401 |
| 8 | CSRF mismatch on mutation | **PASS** | 403 |
| 9 | CSRF happy path login | **PASS** | smoke |
| 10 | Admin BFF login + me | **PASS** | smoke |
| 11 | Cookie SameSite=Lax | **PASS** | headers |
| 12 | Cookie Secure=false local / true prod policy | **PASS** | unit + local header |
| 13 | Refresh path narrowed to `/api/bff` | **PASS** | unit policy |
| 14 | Mobile Bearer login still returns tokens | **PASS** | `Qa33BearerMobileCompatTest` |
| 15 | Customer cannot call admin APIs | **PASS** | 403 + Qa33 test |
| 16 | IDOR order (other customer) | **PASS** | 404 Order not found |
| 17 | IDOR payment (other customer) | **PASS** | 404 Payment not found |
| 18 | Unauthenticated order | **PASS** | 401 |
| 19 | Payment security suite (filter) | **PASS** | Qa18–Qa31 in regression |
| 20 | Token leak grep (web/admin) | **PASS** | no JWT localStorage writes |
| 21 | Customer/Admin unit checks | **PASS** | npm test:unit |
| 22 | Web + Admin production build | **PASS** | npm run build |
| 23 | Flutter tests + analyze | **PASS** | info-only notes |
| 24 | composer / npm audit | **PASS** | clean |
| 25 | Device launch + adb reverse | **PASS** | both packages |
| 26 | Full interactive mobile UI re-login | **PARTIAL** | API Bearer + APK launch; not full manual UI |
| 27 | LIVE Razorpay / FCM | **N/A** | OUT OF SCOPE |
| 28 | GREEN claim | **NO** | Explicit |

---

## Legend

PASS · PARTIAL · UNVERIFIED · BLOCKED · OPEN · N/A · NO
