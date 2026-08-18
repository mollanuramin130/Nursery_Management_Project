# QA-43 ERROR HANDLING REPORT

**Date:** 2026-08-18  
**GREEN:** NO · **LIVE:** OUT OF SCOPE  
**Helpline:** `8926627220` (`tel:8926627220` — dialer only, never auto-call)

---

## 1. Files analyzed

Existing classification and presentation (reused, not replaced):

- Customer Mobile: `network_errors.dart`, `network_status_provider.dart`, `offline_controller.dart`, `network_status_banner.dart`, `ErrorStateView`, `resilient_image.dart`, checkout / order-detail payment paths, `main.dart` (no prior global Flutter handlers)
- Admin Mobile: `ApiException.userMessage`, ops providers (`e.toString()` leaks), `OpsError`, `OpsStaleBanner`, `main.dart`
- Customer Web: `network-errors.ts`, `api.ts` interceptors, `NetworkStatusBanner`, `ErrorState.tsx` (retry only), no `error.tsx` / boundary
- Admin Web: `api/client.ts` unwrap (could leak BFF/PHP copy), `States.ErrorState`, no boundary

QA-37 → QA-42A cache-first / SWR / mock / BFF / HttpOnly / Bearer / Razorpay TEST / COD left intact.

---

## 2. Files modified / added

**Customer Mobile**

- Added: `lib/core/support.dart`, `app_error.dart`, `app_error_handler.dart`, `widgets/greenleaf_error_view.dart`, `support_contact_sheet.dart`, `retry_button.dart`, `test/qa43_error_handling_test.dart`
- Updated: `network_errors.dart`, `network_status_banner.dart`, `offline_controller.dart`, `network_status_provider.dart`, `ui_kit.dart` (`ErrorStateView`), `main.dart`, checkout payment copy, order-detail payment support, `AndroidManifest.xml` (`DIAL` + `tel` query)

**Admin Mobile**

- Added: support / app_error / handler / GreenLeafErrorView / support sheet, `test/qa43_error_handling_test.dart`, `url_launcher`
- Updated: `api_client.dart` (5xx never pass raw body), ops/auth/fulfillment/more/inventory catch → `sanitizeCaughtError`, `main.dart`, manifest `tel` query

**Customer Web**

- Added: `src/lib/support.ts`, `error-category.ts`, error components, `app/error.tsx`, `app/global-error.tsx`
- Updated: `network-errors.ts`, `network-status.ts`, `ErrorState.tsx`, `Providers.tsx` (boundary), `api.ts` sanitize, footer Help `tel:`, payment-failed Contact Support

**Admin Web**

- Added: support + error UI + `error.tsx` / `global-error.tsx` / boundary
- Updated: `api/client.ts` 5xx / transport copy (no `API_PROXY_TARGET` leak), sidebar Support link

---

## 3. Error categories

| ID | Category | Support |
|----|----------|---------|
| A | Temporary network (timeout, DNS, first connection fail) | No |
| B | API unavailable (502/503/504) / server 500 | After 2 retries or Need Help |
| C | Authentication / session | Never auto |
| D | Validation / 404 / 403 / 422 / 409 | Never |
| E | Payment (failed / unconfirmed — never fake success) | Yes, without implying paid |
| F | Unexpected runtime / widget crash | Yes + `GL-xxxxx` |

---

## 4. Global architecture

```
Exception / HTTP / transport
        ↓
AppErrorClassifier (maps NetworkKind → ErrorCategory)
        ↓
AppErrorHandler (redacted debug log + reference)
        ↓
Presenter:
  keep cache/mock on screen
  subtle banner (kind-specific copy)
  full GreenLeafErrorView only when no usable content or widget crash
```

Flutter: `FlutterError.onError` + `PlatformDispatcher.onError` + `ErrorWidget.builder` + `runZonedGuarded`.  
Web: React `AppErrorBoundary` + Next `error.tsx` / `global-error.tsx`.

---

## 5. Helpline

- Number displayed: **8926627220**
- Mobile: confirmation sheet → Call Support → native dialer (`tel:`) → user must press Call
- If no dialer: “Unable to open the phone app.” + Copy Number
- Web: `tel:` on mobile UA; desktop shows number + Copy Number (does not force a phone app)

---

## 6–13. Behavior notes

- **Retry:** `RetryButton` / coalescer already used by screens; retry does not reset nav, cache, or auth.
- **Offline banner:** confirmed offline only after a second consecutive connection failure **or** explicit browser `offline`. HTTP 500/502/503/timeout never say “You’re offline.”
- **API failure:** keep cached/mock; “Unable to connect to GreenLeaf right now.” + Retry.
- **Payment:** “Payment could not be completed.” Server verify remains source of truth. Support sheet states calling does not mean paid.
- **Cache/mock:** unchanged (QA-37/42A). Cache peek still does not raise the banner.
- **Screen stability:** banners use `AnimatedSize`; no full-screen swap for refresh failure when data exists.
- **Security:** redaction of JWT / cookies / passwords / `rzp_*`; no stack traces in UI; admin 5xx ignores Laravel body.

---

## Intentionally unhandled / unverified

- Physical Vivo dialer open (needs device)
- Desktop browser `tel:` (by design, copy-only)
- Every one of the 34 runtime scenarios on-device (classified + unit-covered; E2E UNVERIFIED)
- PHPUnit not re-run this pass (no API changes)
- Pre-existing `Qa13RegressionTest::test_free_delivery_threshold_boundaries` untouched

**Do not claim GREEN or LIVE.**
