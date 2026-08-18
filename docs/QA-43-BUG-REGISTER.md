# QA-43 BUG REGISTER

| ID | Severity | Client | Root cause | Fix | Status |
|----|----------|--------|------------|-----|--------|
| QA-43-001 | P1 | Both | Default/short splash looked like a generic Flutter overlay | Sequenced CustomPaint splash + fade | FIXED |
| QA-43-002 | P1 | Both | Customer and admin shared the same two-leaf launcher mark | Distinct seedling vs shield adaptive icons | FIXED |
| QA-43-003 | P1 | Admin | Native launch color was cream while Flutter splash was dark | Native `launch_background` `#0A2418` | FIXED |
| QA-43-004 | P2 | Both | Density PNG foregrounds overrode vector adaptive icons | Removed `drawable-*/ic_launcher_foreground.png` | FIXED |
| QA-43-005 | P2 | Both | Native splash drew a full plant, then Flutter restarted growth | Native first frame is soil+seed (customer) / shield ghost (admin); Flutter grows the rest | FIXED (code) / device UNVERIFIED |
| QA-43-006 | P2 | Both | Splash could theoretically hang if a later gate were added | `maxDisplay` 5s + cancelled timers | FIXED |
| QA-43-050 | P2 | Customer | Tagline / timeline drifted from brand brief | 2.4s sequence; **Grow Better. Live Greener.**; no bounce curve | FIXED (unit) |
| QA-43-051 | P2 | Admin | Admin mark was only a leaf-in-shield | Added 2×2 inventory grid on splash + adaptive icon | FIXED (unit) |
| QA-43-030 | P2 | Both | Physical Vivo 1951 visual pass | Requires reinstall after this pass | UNVERIFIED |
| QA-43-031 | P3 | Both | Android 12+ system splash (device is Android 11) | values-v31 styles added | UNVERIFIED on API 31+ |
| QA-43-032 | P3 | Both | Launcher labels truncated as “GreenLeaf…” | Manifest labels shortened to GreenLeaf / GreenLeaf Admin (needs reinstall to confirm) | UNVERIFIED |

## Classification

- **FIXED:** QA-43-001…006  
- **UNVERIFIED:** QA-43-030, QA-43-031  
- **REGRESSIONS:** none intended; QA-37…QA-42A and SEC-001 were not modified  

---

## QA-43 error-handling (2026-08-18)

| ID | Severity | Client | Root cause | Fix | Status |
|----|----------|--------|------------|-----|--------|
| QA-43-040 | P1 | Customer Mobile | Banner always said “You’re offline · Showing saved data” | Kind-specific `statusBannerText`; 500/503 ≠ offline | FIXED (unit) |
| QA-43-041 | P1 | All | No branded unknown-error UI / helpline | `GreenLeafErrorView` + support sheet `8926627220` | FIXED (unit); device dialer UNVERIFIED |
| QA-43-042 | P1 | Flutter | No global FlutterError / zone / ErrorWidget | `installAppErrorHandling` + `runZonedGuarded` | FIXED (code) |
| QA-43-043 | P1 | Web | No error boundary / `error.tsx` | Boundary + Next error routes | FIXED (code); browser UNVERIFIED |
| QA-43-044 | P1 | Admin Mobile | `e.toString()` on provider errors | `sanitizeCaughtError` | FIXED (unit) |
| QA-43-045 | P1 | Admin Web | Transport error leaked `API_PROXY_TARGET` | Shopper/ops-safe unwrap | FIXED (code) |
| QA-43-046 | P2 | All | Support too easy to show on blips | `shouldOfferSupport` gating | FIXED (unit) |
| QA-43-047 | P2 | Payment | Gateway raw messages on failure | Safe “Payment could not be completed” + support without implying paid | FIXED (code) |
| QA-43-108 | P2 | Mobile | Native dialer on Vivo | Explicit Call Support → `tel:` | UNVERIFIED |

## Non-goals (this pass)

- No architecture, API, DB, auth, payment, or BFF redesign.
- No production Razorpay credential changes.
- No claim of GREEN or LIVE.

---

## QA-43 auth / throttle friction (2026-08-18)

| ID | Severity | Client | Root cause | Fix | Status |
|----|----------|--------|------------|-----|--------|
| QA-43-200 | P1 | All | Global API `120,1` per IP exhausted by shop/health then labeled as login brute-force | Named env-aware `api` limiter; local/testing relaxed; health/webhooks skip global | FIXED (PHPUnit) |
| QA-43-201 | P1 | Customer Web | Login form locked 60s when Retry-After missing | Default 10s, cap 30s; calm copy | FIXED (unit) |
| QA-43-202 | P2 | Customer/Admin Web | Double submit could POST login twice | Single-flight `loginInFlight`; caller ignores duplicate | FIXED (code) |
| QA-43-203 | P2 | Customer Mobile | Cart treated 429 as transport and retried / marked local-only | 429 keep-cart, no retry, not offline | FIXED (code) |
| QA-43-204 | P2 | All | 429 copy said “Too many sign-in attempts” / “wait a minute” | Calm “wait a moment”; login-path wording only when that route is limited | FIXED (code) |
| QA-43-209 | P1 | All clients | Rapid legitimate QA flow on device | Needs Vivo / browser evidence | UNVERIFIED |

**Intentionally retained:** production login/api/order/payment throttles; order/payment/inventory idempotency; Razorpay HMAC; CSRF; BFF HttpOnly.
