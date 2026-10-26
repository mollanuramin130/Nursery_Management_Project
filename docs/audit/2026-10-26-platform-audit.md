# Nuvikoz platform audit — 26 October 2026

## A. Scope and architecture

Baseline: `74156597492ce8e1d2d405b3be3aeb8f12dca0a8`, branch `development/v1.0.0`.
Audit branch: `audit/2026-10-26-platform-hardening`. No production deployment, live database changes, credential changes, or merges were performed.

This is a source audit, local regression run and targeted browser verification. It is **not** a certification that every screen, external integration or concurrency scenario works. The repository contains 1,481 tracked files at baseline; architecture was mapped across all five apps, with detailed review focused on authentication, configuration, payment integration, shared UI and deployment checks.

| Component | Stack | Connection / responsibility |
|---|---|---|
| `apps/nursery-web` | Next.js App Router, React, Tailwind 4, Zustand | Customer storefront; same-origin BFF holds JWTs in HttpOnly cookies and forwards to Laravel |
| `apps/nursery-admin` | Next.js App Router, React, Tailwind 4, Zustand | Staff portal; BFF and backend permission checks |
| `apps/nursery-api` | Laravel 13, JWT auth, PHP, Eloquent | `/api/v1`, catalog, inventory, checkout, orders, payment, marketing, delivery, notification modules |
| `apps/nursery_app` | Flutter/Dart, Dio, secure storage | Customer app calls Laravel directly with bearer tokens |
| `apps/nursery_admin_mobile` | Flutter/Dart, Dio, secure storage | Staff app calls the same API |
| Database / integrations | MySQL in production; SQLite in tests | Razorpay, mail, FCM, queues, scheduled work, filesystem assets |

Hostinger-specific constraints are preserved: Node 22 deployment target, JavaScript Next config, default `@next/env` import and `next build --webpack` for the WASM fallback. PHP must be **8.4.1+ with sodium**, matching the locked dependencies. Local Node verification used 24.22.1; Node 22 and Hostinger's old glibc still need staging verification.

## B–C. Findings, root causes and corrections

Severity is impact-based; dependency advisory severity does not establish that a particular exploit is reachable in this application.

| ID | Severity | Finding / root cause | Files / correction | Status and verification |
|---|---|---|---|---|
| AUD-01 | P1 | Unlayered anchor and font resets outranked Tailwind utilities; green links/buttons inherited dark text or lost font weight | Both `src/app/globals.css`: move resets into `@layer base` | Verified in Chromium against generated production CSS |
| AUD-02 | P2 | Admin muted text on muted surface measured 4.27:1 | Admin `globals.css`: muted token `#66756c` → `#5e6d64` | Verified 4.80:1 |
| AUD-03 | P1 | Generic BFF omitted OTP login token issuer (admin also omitted registration); decoded segments were joined without validation | Both `server/proxy-path.ts` and proxy route handlers: protect session issuers, reject ambiguous separators/traversal, encode valid segments | Regression tests pass; this closes a browser-token boundary gap, not a demonstrated account takeover |
| AUD-04 | P2 | Passing empty non-null response bodies for 204/205/304 throws | Both `server/bff-upstream.ts`: null body for bodyless statuses | Regression tests pass |
| AUD-05 | P2 | Refresh 429/5xx/malformed replies treated like rejected credentials; cookies cleared | BFF refresh/proxy/logout: preserve session on transient failures, return 503; local logout still succeeds | Helper tests pass; real concurrent refresh remains a staging test |
| AUD-06 | P1 | Runtime `env()` calls lose .env values after `config:cache`; affects payments, shipping, URLs, FCM and readiness | New `config/store.php`, `services.php`; consumers use `config()`; tests set configuration rather than process globals | Real config-cache subprocess regression test included |
| AUD-07 | P1 | Signed OTP proofs could be replayed until expiry | `OtpService`, `AuthController`, `VerificationCode`, additive `consumed_at` migration | Single-use conditional update; registration/reset consumption rolls back with business transaction; regression tests included |
| AUD-08 | P1 | Production SMS sender is a placeholder but previously reported successful sending | `OtpService`: return `503 SMS_UNAVAILABLE` before creating a code outside local/testing | False-success bug fixed; real SMS integration **Blocked** pending provider/account credentials |
| AUD-09 | P1 | npm audit: 1 critical + 7 high findings per portal; Composer: 8 advisories across 4 packages | Both npm lockfiles/manifests; PHP lockfile (Laravel 13.30.0, CommonMark 2.10.4, Flysystem 3.36.0, HttpFoundation 8.1.7; Carbon 3.15.1 after a downgrade-install incompatibility, plus required time polyfill); no `audit fix --force` | Dependency scans recorded; final validation below |
| AUD-10 | P1 | CI used PHP 8.3 despite lock requiring ≥8.4.1, omitted live branch, ignored typecheck failures | CI: PHP 8.4+sodium, Node 22, actual branch, blocking typecheck/unit/audit/build; `next typegen` before tsc | Local scripts checked; hosted workflow result must be checked on PR |
| AUD-11 | P2 | Mobile status substring `ACTIVE` matched `INACTIVE`; `DELIVER` matched unfinished delivery | Admin mobile `theme/app_theme.dart`: exact success states | Source fix and Flutter test added; execution Blocked by missing Flutter SDK |
| AUD-12 | P2 | User-facing outage messages exposed Laravel commands and environment setup instructions; timeout not cleared on rejection | Both `src/lib/api-health.ts`: safe message and finally cleanup | Regression tests; browser outage-state smoke check |
| AUD-13 | P2 | Registration tests omitted current mobile/OTP contract | Two API test fixtures now use actual OTP verification | Regression tests retained, no weakened assertions |
| AUD-14 | P2 | Admin render-time ref assignment, full-page segment navigation, unescaped JSX apostrophe | AnalyticsShell, segment list, SupportContactDialog | Typecheck/build; targeted lint improved, remaining lint debt disclosed |
| AUD-15 | P2 | Seeded catalog images are SVG; Flutter customer `CachedNetworkImage` is a raster decoder | `nursery_app/lib/widgets/resilient_image.dart` / starter assets | **Pending**: SVG rendering or raster derivatives need implementation and device validation; fallback currently masks failed imagery |
| AUD-16 | P2 | Broad synchronous effect-state patterns and render-prop/ref lint finding remain | Existing customer/admin page and component code | **Pending**; do not disable rules to claim green lint |

## D. UI / accessibility verification

Measured with Chromium and the actual CSS linked from each current production build (not obsolete CSS files left in `.next`). Script: `scripts/audit/contrast.cjs`.

| Pair | Before | After | Target |
|---|---:|---:|---:|
| Customer primary link foreground/background | 2.01:1 | 7.97:1 | 4.5:1 |
| Admin primary link with inherited ink | approximately 2.10:1 | 7.97:1 | 4.5:1 |
| Customer muted text / muted surface | — | 5.54:1 | 4.5:1 |
| Admin muted text / muted surface | 4.27:1 | 4.80:1 | 4.5:1 |

Button text utilities now produce intended font weights. Checked at widths 390 and 1440. Disabled controls are not counted as WCAG text-contrast failures. This sampled component check does not cover all background images, focus states, chart colors, keyboard workflows or screen-reader semantics.

Local browser smoke scope: customer `/login`, `/register`, `/shop`, admin `/login`, each at 390/1440 pixels; HTTP 200, no horizontal document overflow and no browser page errors in those runs. External requests were blocked and the Laravel backend was unavailable in this browser exercise, so these are rendering/outage checks, **not successful login, purchase or authenticated-admin E2E claims**. Screenshots were visually inspected.

## E–F. Integration and security

Browser JWT responses are limited to dedicated cookie-setting authentication handlers. The mobile API contract remains bearer-token based. CORS settings and business permissions were not loosened. Payment signature verification, order ownership and amount-authority tests remain in the API suite. No real payment, email, SMS, FCM or live account operation was performed.

The OTP migration adds one nullable column; it does not delete existing records. New code consumes proof only within the registration/reset transaction. A conditional update prevents two requests from consuming the same proof successfully; MySQL concurrency verification is still required because local tests use SQLite.

Configuration-cache testing builds an isolated temporary configuration cache, then launches a second PHP process without the relevant runtime environment variables and verifies settings survive. Fake test values only; secrets are not returned by public app config.

## G. Performance

No production load test or representative latency/throughput benchmark was run. No performance percentage improvement is claimed. Fixes prevent avoidable refresh logout/retry cycles and CSS overrides; health probes now clean up timeout handles. Existing DB index migrations and pagination tests were inspected/exercised by the suite; production query plans and large datasets remain unverified.

## H. Reproducible verification

From each Next app:

```sh
npm ci
npm run test:unit
npm run test:audit
npm run typecheck
npm run build
npm audit
```

From API (PHP 8.4.1+ and required extensions including sodium):

```sh
composer install --no-interaction --prefer-dist
php vendor/bin/phpunit
composer validate --no-check-publish
composer audit
```

Use a dedicated test environment, generated test APP_KEY/JWT_SECRET and SQLite `:memory:` settings from phpunit.xml. Never point the test suite at production. Local static PHP lacked sodium; only local installation bypassed that extension requirement, so sodium-specific behavior is not verified here. Do not copy that bypass into production.

Contrast script, after both portal builds, with Playwright/Chromium installed in the test environment:

```sh
PLAYWRIGHT_MODULE=/absolute/path/to/node_modules/playwright node scripts/audit/contrast.cjs
```

**Final results:** API 275 tests: 274 passed, 1 skipped, 0 failures, 1,384 assertions. Each portal: existing unit checks passed, 7 new audit tests passed, typecheck and production build passed. npm audit: 0 findings each. Composer validate passed; Composer audit: 0 advisories. Remaining lint: customer 20 errors/10 warnings, admin 61 errors/7 warnings (baseline admin 64/8). Browser: 8 sampled route/viewport combinations passed rendering checks. Contrast: 12 sampled pairs passed.

Final outcome details are recorded in `2026-10-26-verification.json`. API baseline had stale registration fixtures and a missing local example .env; those failures were diagnosed rather than ignored. One real-payment test is skipped without gateway credentials. Flutter tests and Android/iOS builds were not executed.

## I. Changes and review

Review the audit branch diff and its logical commits. All changed/new paths are listed in `2026-10-26-changed-files.txt`. Application structure and existing data are preserved. No seed command was run against production and no fabricated customer ratings/reviews were added. The source remains on a review branch; merge and deployment require the owner's approval.

## J. Remaining blockers and release conditions

- Choose and integrate a real SMS provider before advertising registration/OTP login/mobile password reset as working. Production now returns an honest unavailable response.
- Run Flutter analyzer/tests/device builds; validate catalog SVG handling and staff status-chip changes. No mobile release is certified.
- Resolve or consciously triage remaining lint errors; the PR does not claim a clean full-repository lint result.
- Run staging purchase, duplicate-submit, refund, delivery, permissions, queue, scheduler, upload/storage, email and FCM journeys with test credentials. Local API tests mock external providers.
- Verify PHP web/CLI extensions, Node 22, Hostinger SWC WASM/webpack compatibility, actual DNS/TLS, secure cookies and asset paths on staging.
- Confirm CI on the draft PR before approval. No automated production deployment should be triggered by this audit branch.

## K. Hostinger deployment and rollback — only after approval

1. Review/merge the approved audit changes; record currently deployed SHA and take a database backup. Use a staging copy first. Never run `migrate:fresh`, reset the DB, regenerate production APP_KEY/JWT_SECRET or overwrite `.env`.
2. PHP must be 8.4.1+ in both web and CLI with sodium and project-required extensions. Confirm `composer check-platform-reqs --no-dev` after install.
3. In `/home/u317570912/domains/api.nuvikoz.com/source/apps/nursery-api`, update the approved branch using the existing deploy key. Install locked dependencies:

   ```sh
   composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
   php artisan package:discover --ansi
   php artisan migrate --force
   php artisan config:cache
   ```

   The no-scripts step accommodates Hostinger's disabled proc_open; run package discovery directly. Migration adds `verification_codes.consumed_at`. Deploy migration before serving new OTP code, preferably during a short controlled maintenance window. Keep production SMS unavailable until a provider is implemented.
4. Keep existing custom `public_html/index.php` pointing at source autoload/bootstrap. Do not copy all of `public/` over it. Confirm `public_html/storage` links to `source/apps/nursery-api/storage/app/public` if public uploads are used; Laravel's default `storage:link` under the source checkout alone does not make files visible through the separate document root. Preserve existing upload files and links.
5. Rebuild/deploy customer and admin portals from their own app roots on Node 22 with `npm ci`, `npm run build`, `.next` output. Keep the existing production API target/environment settings, use Webpack, and retain secure cookies over HTTPS. Confirm customer and staff login, refresh/logout, browsing, stock, cart and test-payment flow.
6. Check API `/up`, `/api/v1/health/ready`, `/api/v1/app/config`, CORS, uploads, queue/scheduler and runtime logs. Health responses alone do not prove commerce works.

Rollback: restore the previous application release/commit and its lockfiles, reinstall corresponding dependencies, regenerate the configuration cache, redeploy previous portal builds and restart workers as applicable. Leave the additive nullable `consumed_at` column in place during code rollback; old code ignores it. Do not run blanket migration rollback or restore a DB backup over new orders. Reverting security fixes reintroduces the documented risks; record that explicitly when choosing rollback.
