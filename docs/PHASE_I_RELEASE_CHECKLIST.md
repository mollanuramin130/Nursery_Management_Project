# Phase I — Release Checklist

## Engineering

- [x] `flutter analyze` passes
- [x] `flutter test` passes
- [x] Release APK builds (with HTTPS dart-define)
- [x] Release AAB builds (with HTTPS dart-define)
- [ ] No P0 issues remaining
- [ ] No P1 issues remaining
- [ ] Payment verified on release build (sandbox/live)
- [ ] Authentication verified on device
- [ ] Cart / checkout / orders verified on device
- [ ] Campaigns / Find Your Plant verified on device
- [x] Accessibility improvements started (finder semantics)
- [ ] Accessibility TalkBack pass
- [x] Performance artifact sizes recorded
- [ ] Performance profiled on device
- [x] Security source review completed
- [ ] Production API HTTPS configured for real host
- [x] Cleartext blocked in release
- [x] Permissions reviewed (app source)
- [ ] Merged release manifest permissions reviewed
- [ ] Final branded adaptive icon + store screenshots
- [x] Splash theme colors restored / present
- [x] Version `1.0.0+1` verified
- [ ] Privacy policy URL ready
- [ ] Play Data Safety form filled
- [ ] Store listing assets ready
- [ ] Upload keystore (`android/key.properties`) configured

## Required production build command

```bash
cd apps/nursery_app
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_API/api/v1 \
  --dart-define=STORE_NAME="GreenLeaf Nursery"
```

Place real signing in `android/key.properties` (see `key.properties.example`). **Do not commit secrets.**

## Artifacts produced this session

| File | Notes |
|------|-------|
| `build/app/outputs/flutter-apk/app-release.apk` | ~52–54 MB; debug-signed |
| `build/app/outputs/bundle/release/app-release.aab` | ~42–44 MB; debug-signed |

**Do not upload debug-signed AAB to Play.**
