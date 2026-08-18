# QA-43 REPORT

**Date:** 2026-08-18  
**Status:** **PARTIAL**  
**GREEN:** NO · **LIVE:** OUT OF SCOPE  

QA-43 covers three tracks on the same date:

1. **Branding / splash / icons** — this document’s summary; details in `QA-43-SPLASH-REPORT.md` and `QA-43-BRANDING-REPORT.md`.
2. **Global error handling + helpline** — `QA-43-ERROR-HANDLING-REPORT.md`.
3. **Auth & action-flow friction** (429 / login lockout) — Track 3 below.

---

## Branding pass (this implementation)

### What changed

- Customer Flutter splash: 2.4s botanical sequence (field → seed → sprout → leaves → **GreenLeaf** → **Grow Better. Live Greener.**).
- Admin Flutter splash: related dark identity with leaf + shield + **2×2 inventory grid** + **Manage. Monitor. Grow.**
- Native first frame no longer shows a finished plant (customer soil+seed only; admin shield ghost).
- Adaptive icons: customer botanical leaf; admin shield + leaf + grid. Same `#0C2417` family, different silhouette.
- Startup overlay architecture **unchanged** (router already mounted; splash does not wait on API).

### What did not change

- GoRouter, auth bootstrap, cache → mock → API, BFF/web, payments, Razorpay, COD.
- No Lottie/GIF/video dependency.
- Web clients were not restyled.

### Evidence

| Check | Result |
|-------|--------|
| Customer `qa43_back_navigation_test` (includes splash + icons) | **PASS** |
| Admin `qa43_navigation_test` (includes splash + icons) | **PASS** |
| `flutter analyze` splash + app host (both apps) | **No issues** |
| Android APK build | **UNVERIFIED** this pass |
| Vivo 1951 `2d3714f` | **UNVERIFIED** (`adb devices` empty) |

### Remaining

- Reinstall both APKs on Vivo to confirm native→Flutter handoff and launcher icons.
- Density PNG mipmaps were not regenerated (API 30 uses adaptive XML).
- Android 12+ system splash **UNVERIFIED**.

---

## Track 3 — Auth & action-flow friction (2026-08-18)

**QA-43 STATUS: PARTIAL** (PHPUnit + web unit + Flutter auth unit; device rapid-flow **UNVERIFIED**)

**GREEN:** NO · **LIVE:** OUT OF SCOPE

### Proven source (not “brute force on valid passwords”)

Laravel stacked **two** IP throttles. Laptop + `adb reverse` phones all appear as `127.0.0.1`.

1. **Global** `$middleware->throttleApi('120,1')` — 120 req/min for **every** `/api/*` call (home, shop, health, cart, login).
2. **Route** `POST auth/login` `throttle:10,1`.

Browsing Home → Shop → PDP filled the global bucket. The next **valid** login then returned **HTTP 429**. `ApiResponse::friendlyRateLimitMessage()` labeled any 429 on `auth/login` as “Too many sign-in attempts.” Customer Web then locked the form for **60s** when `Retry-After` was missing.

There is **no** failed-login counter / account lockout in `AuthService`. `config/auth.php` `'throttle' => 60` is the password-reset broker only.

### Classification (inventory)

| Location | Mechanism | Trigger | HTTP | User impact | Class | Action |
|----------|-----------|---------|------|-------------|-------|--------|
| `bootstrap/app.php` `throttleApi('120,1')` | Global 120/min/IP | Any `/api/*` | 429 | Login after browsing | **E** false-positive | Named `api` limiter; local 2000/min; testing 10000; prod 180 |
| Auth `throttle:10,1` login | Login IP throttle | POST login | 429 | Real brute-force + shared IP | **A** | Named `login`; local 60; testing 120; prod 12; key IP+email |
| Register/forgot/reset `5,1` | Auth write | POST | 429 | QA register loops | **A** | Named; relaxed local |
| Orders `20,1` / payments `20,1` | Abuse | Place/pay | 429 | Rapid QA checkout | **A/B** | Named; relaxed local; **idempotency kept** |
| Webhook `120,1` + global 120 | Burst | Razorpay retries | 429 | Missed webhooks | **B** | Skip global; keep `webhook` + HMAC |
| Health `60,1` + global | Polling | Health probes | 429 | Steals shopping budget | **C/E** | Skip global; named `health` |
| `ApiResponse` login-path 429 copy | Wording | Any 429 on login URL | 429 | “Brute force” popup | **E** | Calm “wait a moment” |
| Web login `?? 60` lock | UX lockout | Missing Retry-After | — | 60s form lock | **E** | Cap 30s; default 10s |
| Cart Flutter retry on 429 | Amplification | Cart GET/POST 429 | — | Extra 429s / offline-like | **E** | Do not retry 429; keep cart; 429 ≠ offline |
| Web Zustand `login()` no single-flight | Duplicate POST | Double Enter | 429 | Extra login hits | **C** | In-flight guard; button already disabled |
| CSRF / JWT / BFF HttpOnly | Auth | Mutations | 401/419 | Real auth | **A KEEP** | Unchanged |
| Razorpay verify + webhook HMAC | Payment | Gateway | 4xx | Fraud | **A KEEP** | Unchanged |
| Order/payment/inventory idempotency | Finance | Double tap / replay | 200 replay | Dup orders | **B KEEP** | Unchanged |

A = security-critical · B = business-critical · C = UX · D = dev-only · E = false-positive source

### What changed

- `config/throttling.php` + `ThrottleLimits` + named `RateLimiterConfigurator`.
- `throttleApi()` uses the named `api` limiter (no hardcoded `120,1`).
- Health + payment webhooks **excluded from the global bucket** (still have their own limiter).
- Customer/Admin Web login: single-flight; do not navigate on duplicate tap.
- Flutter login already single-flight; extra `if (loading) return` on customer login screen.
- 429 copy is calm; lock uses Retry-After (default 10s, cap 30s).
- Cart does **not** treat 429 as transport/offline and does **not** retry it.
- Local-only `[AUTH] login request started/completed` logs (no password/email/JWT).

### Environment ceilings (defaults; override with `THROTTLE_*`)

| Limiter | local | testing | staging | production |
|---------|-------|---------|---------|------------|
| api | 2000 | 10000 | 400 | 180 |
| login | 60 | 120 | 30 | 12 |
| register | 30 | 120 | 15 | 5 |
| password | 20 | 120 | 15 | 5 |
| order-write / payment-write | 60 | 200 | 40 | 20 |
| webhook | 300 | 1000 | 180 | 120 |

### Preserved (not removed)

Authentication, authorization, IDOR/ownership, CSRF, HttpOnly BFF cookies, Razorpay signature + webhook HMAC, amount authority, server-side totals, order/payment/inventory idempotency, admin auth.

### Duplicate-request findings

- Customer/Admin Web `login()` could fire twice in the same tick (Enter + click). **Fixed** with a module-level in-flight flag; caller ignores `false`.
- Customer Flutter `AuthProvider.login` already returned early when `loading`.
- Place order already uses `busy` / `_submissionLocked`. **Not** relaxed.
- Cart single-flight mutations (QA-37) preserved.

### Status lines

- **SECURITY STATUS:** Preserved (production throttles remain; local/testing relaxed via env)
- **UX STATUS:** Calmer 429; no 60s default lock
- **LOGIN STATUS:** Valid credentials no longer share a 120/min shopping bucket in local/test
- **ORDER STATUS:** Idempotency unchanged; write throttle relaxed only in local/test
- **PAYMENT STATUS:** HMAC / verify / webhook skip-global only; limiter kept
- **CUSTOMER WEB:** Code + unit PASS; browser rapid-flow UNVERIFIED
- **CUSTOMER MOBILE:** Code + auth unit; device UNVERIFIED
- **ADMIN WEB:** Code; unit PASS (existing)
- **ADMIN MOBILE:** Code + auth unit; device UNVERIFIED

