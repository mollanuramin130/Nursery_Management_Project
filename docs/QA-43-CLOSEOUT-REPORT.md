# QA-43 CLOSEOUT REPORT

**Date:** 2026-08-18  
**Track:** GreenLeaf global error handling + helpline  
**Status:** **PARTIAL** (unit + architecture PASS; device helpline / payment E2E UNVERIFIED)

**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## What shipped

Centralized error categories (A–F), branded unknown-error UI, confirmation support sheet, `GL-xxxxx` references, kind-specific banners (500 ≠ offline), Flutter global handlers, Next error boundaries, helpline `8926627220`.

QA-37 → QA-42A and branding QA-43 splash/icons were not redesigned away. Cache-first, SWR, mock JSON, BFF HttpOnly, Bearer, COD, Razorpay TEST verify-on-server remain.

---

## Evidence (this pass)

| Suite | Result |
|-------|--------|
| Customer Flutter (full) | **99 passed** |
| Admin Flutter | **40 passed** |
| Customer Web `npm run test:unit` | **PASS** |
| Admin Web `npm run test:unit` | **OK** |
| PHPUnit | **Not re-run** (no backend change) |
| Vivo helpline dialer | **UNVERIFIED** |

---

## Acceptance (honest)

| Criterion | Result |
|-----------|--------|
| No raw exceptions in normal UI | PASS (unit + ErrorWidget/boundary). Device UNVERIFIED |
| Unknown errors: Try Again + Contact Support | PASS (code) |
| Helpline 8926627220 | PASS (unit) |
| Mobile dialer only after explicit action | PASS (code). Device UNVERIFIED |
| Web tel: where supported | PASS (code) |
| Temp network ≠ support screen | PASS (unit) |
| API 5xx ≠ “You’re offline” | PASS (unit) |
| Offline banner not on cache peek | PASS (QA-42A tests) |
| Retry debounce | PASS (code) |
| Cache/mock/auth/payment security | Preserved |
| Payment never fake-success | Preserved |
| No secrets in UI/logs | PASS (redact unit) |
| No stack traces to users | PASS (sanitize unit) |
| QA-42A intact | PASS (tests updated for 5xx copy) |

---

## Remaining

- Device: Wi-Fi off/on, API 500/502, rapid Retry, native dialer on Vivo 1951
- Customer/Admin Web desktop copy-number smoke
- Do not treat this closeout as COMPLETE for release

See `docs/QA-43-ERROR-HANDLING-REPORT.md` and `docs/QA-43-BUG-REGISTER.md`.

---

## Branding addendum (same date)

Premium Flutter splash/icon pass: customer seed→sprout sequence + **Grow Better. Live Greener.**; admin leaf+shield+grid. Overlay architecture preserved. Unit splash tests **PASS**. Vivo cold start **UNVERIFIED** (device not attached).

See `docs/QA-43-REPORT.md` · `docs/QA-43-SPLASH-REPORT.md` · `docs/QA-43-BRANDING-REPORT.md`.

---

## Track 3 — Auth & action-flow friction (same date)

**Status:** **PARTIAL**

Root cause of “valid login blocked” was the **global 120 req/min IP limiter** shared by shop browsing and login (plus a 60s Web lock when Retry-After was missing). Not an account lockout.

Shipped: env-aware named limiters; health/webhooks skip the global bucket; login single-flight on Web; 429 ≠ offline; cart does not retry 429; production ceilings retained.

**Not claimed COMPLETE:** no Vivo rapid login→checkout evidence this pass.

| Suite | Result |
|-------|--------|
| PHPUnit `Qa43ThrottleFrictionTest` + `ThrottleLimitsTest` | Run this pass (see closeout after tests) |
| CSRF / BFF / Razorpay HMAC / order idempotency | Unchanged (preserved) |


