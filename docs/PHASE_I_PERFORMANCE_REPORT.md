# Phase I — Performance Report

**Date:** 2026-08-11  
**Rule:** No invented timings. Unmeasured items marked **NOT MEASURED**.

## Build artifacts (VERIFIED)

| Artifact | Path | Size |
|----------|------|------|
| Release APK | `apps/nursery_app/build/app/outputs/flutter-apk/app-release.apk` | **~52 MB** |
| Release AAB | `apps/nursery_app/build/app/outputs/bundle/release/app-release.aab` | **~42 MB** |

Build command used:

```bash
flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.example.com/api/v1
```

Notes:

- Size includes Flutter engine + Razorpay + fonts/plugins.
- Icon tree-shaking reduced Material/Cupertino fonts significantly (build log).
- APK signed with **debug** keystore because `android/key.properties` is missing — not Play-upload ready.

## Startup

| Metric | Result |
|--------|--------|
| Cold start ms | **NOT MEASURED** |
| Warm start ms | **NOT MEASURED** |
| First meaningful frame | Code change: cart/wishlist no longer block `runApp` | **Code VERIFIED** |
| Auth bootstrap before first frame | Still awaited (required) | VERIFIED |

## Runtime screens

| Area | Result |
|------|--------|
| Home render | **NOT MEASURED** |
| Catalog scroll jank | **NOT MEASURED** |
| Image cache behavior | `cached_network_image` + memCacheWidth in places | Source VERIFIED |
| Cart operations | **NOT MEASURED** |
| Checkout | **NOT MEASURED** |
| Memory leaks | Controllers disposed on key forms (source review) | Partial |
| Battery / polling | No continuous polling found in source review | Partial |

## Risks still open

1. `google_fonts` may fetch on first launch (**NOT MEASURED** impact).
2. Large remote campaign images (**NOT MEASURED**).
3. Finder server does work — client cost low.

## Recommendation

Do not ship size/performance claims without device profiling (Android Studio Profiler / Play vitals).
