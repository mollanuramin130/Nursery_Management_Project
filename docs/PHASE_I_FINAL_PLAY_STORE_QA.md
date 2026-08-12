# Phase I — Final Play Store QA Audit

**Date:** 2026-08-11  
**Scope:** Android Flutter app `apps/nursery_app` (stabilize only — no redesign / no new features)  
**Status:** Audit complete — prioritized findings below. Broad code changes deferred until owner review of blockers.

Legend for verification:

| Tag | Meaning |
|-----|---------|
| **VERIFIED** | Inspected/run in this session |
| **NOT VERIFIED** | Requires device/manual/Play Console/owner |
| **REQUIRES OWNER INPUT** | Cannot invent (secrets, legal, store listing) |

---

## 1. Toolchain (VERIFIED)

| Item | Actual value |
|------|----------------|
| Flutter | 3.38.10 (stable), revision `c6f67dede3` |
| Dart | 3.10.9 |
| DevTools | 2.51.1 |
| pubspec SDK constraint | `^3.10.9` |

---

## 2. Android Gradle / SDK (VERIFIED)

| Item | Actual value | Source |
|------|----------------|--------|
| Gradle DSL | Kotlin (`android/app/build.gradle.kts`) | project |
| `compileSdk` | **37** | `android/app/build.gradle.kts` |
| `targetSdk` | **36** (`flutter.targetSdkVersion`) | FlutterExtension default |
| `minSdk` | **24** (`flutter.minSdkVersion`) | FlutterExtension default |
| Java/Kotlin | 17 | `build.gradle.kts` |
| `applicationId` / namespace | `com.greenleaf.nursery_app` | `build.gradle.kts` |
| `versionName` | `1.0.0` | `pubspec.yaml` → `1.0.0+1` |
| `versionCode` | `1` | pubspec build number |
| App label | `GreenLeaf Nursery` | `AndroidManifest.xml` |
| MainActivity | `FlutterActivity` only | `MainActivity.kt` |

**Play Console note:** Target SDK / policy thresholds change over time. Current `targetSdk=36` / `compileSdk=37` look modern for Flutter 3.38 — **REQUIRES MANUAL verification against current Play Console requirements before upload** (NOT VERIFIED against live Console).

---

## 3. Dependencies (VERIFIED — `pubspec.yaml`)

| Package | Role | Production assessment |
|---------|------|------------------------|
| `dio` | HTTP | Required |
| `flutter_secure_storage` | Auth/cart tokens | Required |
| `provider` | State | Required — do not replace |
| `go_router` | Navigation | Required |
| `cached_network_image` | Images | Required |
| `google_fonts` | Typography | Required (network font fetch risk on first launch — note) |
| `razorpay_flutter` | Payments | Required |
| `cupertino_icons` | Icons | Low risk |
| `flutter_lints` (dev) | Analysis | Required |

No Riverpod/Bloc. No analytics SDK. No unused heavy SDKs found in pubspec.

---

## 4. Permissions (VERIFIED)

**App manifest (`main`):**

- `INTERNET` only

**Debug overlay:** cleartext override for local API (`usesCleartextTraffic=true` in `debug` manifest only).

**Merged plugins may add permissions** (e.g. Razorpay) — **NOT VERIFIED** on release merged manifest in this session; must inspect release APK/AAB merge before store upload.

No camera/location/contacts/microphone requested by app source.

---

## 5. ProGuard / R8 (VERIFIED)

Release:

- `isMinifyEnabled = true`
- `isShrinkResources = true`
- `proguard-android-optimize.txt` + `proguard-rules.pro` (Flutter keep rules)

Razorpay-specific keep rules: **not explicitly present** — risk of release minify breaking payment (**P1 candidate**, needs release payment smoke).

---

## 6. Release signing (VERIFIED)

| Item | Status |
|------|--------|
| `android/key.properties` | **MISSING** |
| Release signingConfig | Falls back to **debug** keystore when key.properties absent |
| Comment in Gradle | Documents intentional local fallback |

**P0 for Play upload:** cannot ship a Play-ready AAB without a real upload keystore. Local `--release` can still build with debug signing.

---

## 7. API / environment (VERIFIED)

`lib/core/config.dart`:

```dart
API_BASE_URL default = 'http://10.0.2.2:8000/api/v1'
STORE_NAME default = 'GreenLeaf Nursery'
ONLINE_PAYMENTS_ENABLED default = true
```

Production intended override:

`--dart-define=API_BASE_URL=https://…/api/v1`

| Concern | Severity |
|---------|----------|
| Default API is emulator cleartext localhost | **P0** if release built without dart-define |
| No build flavors / staging vs prod schemes | P2 process gap |
| No secrets in Flutter source (good) | — |
| Razorpay key comes from server `client_payload` (good) | — |
| `local_stub` payment path in client when API returns `mode=local_stub` | **P1** — must not be reachable in production API |

Network security:

- Main: `usesCleartextTraffic=false` + `network_security_config` base cleartext **false**
- Debug: cleartext **true** (OK for local)

---

## 8. Image / network client (VERIFIED)

- Dio timeouts: connect 20s / receive 30s
- 401 → refresh once → retry
- Cart token via secure storage + header
- Images: `cached_network_image` with `memCacheWidth` in several places
- Error sanitization: `ErrorStateView.sanitize` used widely
- Skeletons exist for major screens

---

## 9. State management & navigation (VERIFIED)

- Provider: Auth / Cart / Wishlist + ApiClient / SessionStorage
- go_router shell: Home / Categories / Cart / Orders / Account + detail routes
- Offers + Find Your Plant routes registered (Phase H)
- `debugShowCheckedModeBanner: false`

Startup (`main.dart`):

```dart
await auth.bootstrap();
await cart.fetch();
await wishlist.bootstrap(...);
```

**Risk:** cold start blocked on network cart/wishlist — performance/P2 if API slow/offline.

---

## 10. Crash / error handling (PARTIAL)

**VERIFIED present:** ApiException mapping, ErrorStateView sanitize, auth redirect helpers, payment cancel/fail types.

**NOT VERIFIED:** full HTTP matrix 400–503 on device; payment minify; offline during checkout.

---

## 11. Accessibility status (PARTIAL)

| Area | Status |
|------|--------|
| Semantics usage | Sparse (~7 call sites: home banner, product card, search, ui_kit) |
| Touch targets | Theme tokens `AppTouch` exist — NOT VERIFIED systematically |
| Text scaling | NOT VERIFIED |
| Contrast | NOT VERIFIED (manual) |
| Finder selected state | Visual border/color — semantic selected state **weak** |
| Color-only status | Partially mitigated by text badges |

---

## 12. Performance risks (AUDIT)

| Risk | Severity | Evidence |
|------|----------|----------|
| Startup awaits cart + wishlist | P2 | `main.dart` |
| `google_fonts` first-load network | P3 | dependency |
| Finder loads scoring server-side (good) | — | API |
| Large Unsplash campaign images | P3 | CDN URLs from API |
| List pagination | Present on catalog — NOT VERIFIED for dupes |

AAB/APK size: **NOT MEASURED** yet (release build pending after signing/config decisions).

---

## 13. Security risks (SOURCE REVIEW — not a full pentest)

| Finding | Severity | Notes |
|---------|----------|-------|
| Default cleartext emulator API | P0 (misconfig risk) | Must force HTTPS dart-define for store builds |
| Tokens in `flutter_secure_storage` | Good | VERIFIED |
| No hardcoded Razorpay secret | Good | Client uses public key from server |
| `local_stub` payment acceptance client-side | P1 | Server must refuse in production |
| Debug cleartext overlay | OK if debug-only | VERIFIED |
| Logging of Bearer tokens | Not found in lib grep | Good |
| Sample passwords in docs/scripts | Outside app | Docs only — not shipped in APK |

This is a **source review**, not a complete security audit.

---

## 14. Prototype leftovers classification

| Occurrence | Class | Action |
|------------|-------|--------|
| `10.0.2.2` default API | B/D — dev default + release risk | Require prod dart-define; fail-fast in release if cleartext |
| `local_stub` in `razorpay_checkout.dart` | B — mirrors API stub | Keep for non-prod; ensure prod API never returns stub |
| “not fake AI” copy in Find Plant | A — user-facing honesty | Keep |
| Forgot password UX | A | Keep |
| No `Secret@123` in `lib/` | — | Good (sample creds are docs/scripts only) |
| `TODO`/`FIXME` in lib | None found in grep | — |

---

## 15. App icon / splash (VERIFIED inspection)

| Item | Status |
|------|--------|
| Launcher mipmaps | Present (mdpi→xxxhdpi) |
| Icon file size | Extremely small (~0.5–1.4 KB each) — looks like **default Flutter placeholder** |
| Adaptive icon (`mipmap-anydpi-v26`) | **MISSING** |
| Splash | Flutter `LaunchTheme` / Android 12 behavior — NOT visually audited |
| Debug banner | Disabled |

**P1/P2:** Store listing quality requires a real branded adaptive icon — **REQUIRES OWNER INPUT** (brand asset).

---

## 16. Build validation (VERIFIED this session)

| Command | Result |
|---------|--------|
| `flutter pub get` | OK |
| `flutter analyze` | **No issues found** |
| `flutter test` | **9/9 passed** |
| `flutter build apk --release` | **PASS** (~52 MB) with HTTPS dart-define |
| `flutter build appbundle --release` | **PASS** (~42 MB) with HTTPS dart-define; **debug-signed** (no key.properties) |

---

## 17. Functional / device QA status

End-to-end journeys (auth→cart→pay→orders→campaigns→finder): **NOT VERIFIED** on physical device this session.

Unit/widget tests cover money formatting, auth redirect sanitize, error sanitize, a few UI widgets only — **not** commerce E2E.

---

## 18. Play Store readiness (summary)

| Gate | Status |
|------|--------|
| Analyze/tests | PASS |
| Real release signing | **FAIL** (no keystore) |
| Production API default | **FAIL** (emulator HTTP default) |
| Adaptive / branded icon | **FAIL / weak** |
| Privacy policy / Data Safety | **REQUIRES OWNER INPUT** |
| Screenshots / store listing | **REQUIRES OWNER INPUT** |
| Payment live/sandbox E2E | **NOT VERIFIED** |
| R8 + Razorpay release smoke | **NOT VERIFIED** |
| Website cross-platform regression | **NOT VERIFIED** this session |

---

## 19. Prioritized findings (do not fix randomly)

### P0 — Release blockers

1. **Production API URL** — Release builds must use HTTPS via `--dart-define=API_BASE_URL=…`. Shipping with default `http://10.0.2.2:8000` makes the app unusable/insecure.  
2. **Release signing keystore** — `android/key.properties` missing; Play upload needs a real keystore.  
3. **Payment authority** — Confirm production API never returns `mode=local_stub` and always verifies payment server-side (backend already designed this way — needs prod config check).

### P1 — Critical before store

4. **Branded adaptive launcher icon** (current icons appear placeholder-grade).  
5. **Release minify + Razorpay** keep rules / smoke test.  
6. **Startup resilience** — `main.dart` hard-awaits cart/wishlist; offline/cold start may hang UX.  
7. **Full payment E2E** on release build (success/fail/cancel/double-tap) — NOT VERIFIED.  
8. **Accessibility gaps** on Finder / primary CTAs (semantics incomplete).

### P2 — Major quality

9. No product flavors (dev/staging/prod).  
10. Thin automated test matrix (no integration tests for checkout).  
11. google_fonts network dependency on first paint.  
12. Campaign/Unsplash image weight.  
13. Play Console Data Safety / privacy copy — REQUIRES OWNER INPUT.

### P3 / P4

14. Dependency minor updates available (do not mass-upgrade).  
15. Cosmetic empty-state copy consistency pass.  
16. Text-scaling polish — NOT VERIFIED.

---

## 20. Recommended fix sequence (next)

1. Add **release assert**: refuse cleartext/emulator default when `kReleaseMode`.  
2. Document exact production build command with dart-defines.  
3. Add `key.properties.example` + owner keystore steps (no secrets committed).  
4. Soften startup: don’t block first frame on cart/wishlist network.  
5. Add Razorpay ProGuard keeps; run release payment smoke.  
6. Icon: adaptive XML + owner assets.  
7. Expand Semantics on Finder options / primary buttons.  
8. Build release APK/AAB; measure size; fill performance/accessibility/security reports with real results.  
9. Manual E2E + fill `PHASE_I_TEST_MATRIX.md`.  
10. Final recommendation: READY / READY WITH NON-BLOCKING / NOT READY.

---

## 21. Documents to produce after fixes

| Doc | Status |
|-----|--------|
| `PHASE_I_FINAL_PLAY_STORE_QA.md` | **This file — done** |
| `PHASE_I_TEST_MATRIX.md` | Pending (scenarios + results) |
| `PHASE_I_PERFORMANCE_REPORT.md` | Pending (no invented numbers) |
| `PHASE_I_ACCESSIBILITY_REPORT.md` | Pending |
| `PHASE_I_SECURITY_REPORT.md` | Pending |
| `PHASE_I_RELEASE_CHECKLIST.md` | Pending |
| `PHASE_I_FINAL_REPORT.md` | Pending |

---

## 22. Interim release recommendation

**NOT READY FOR RELEASE**

Reasons (factual):

- No Play upload keystore configured  
- Default API points at emulator cleartext  
- Icon quality / adaptive icon insufficient for store  
- Payment/minify/device E2E not verified this session  
- Store privacy/listing assets require owner input  

Engineering quality signals that are healthy:

- `flutter analyze` clean  
- unit/widget tests passing  
- secure token storage  
- cleartext disabled in release manifest  
- minify enabled for release  
- payment SDK present with server-verify design  
