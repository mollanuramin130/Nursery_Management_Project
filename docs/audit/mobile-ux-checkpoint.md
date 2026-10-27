# Customer mobile reliability delivery

Baseline cd7fd4a; PR #4. Scope: startup zone initialization, SVG images, product shell navigation, search response invalidation and Flutter analyzer fixes. Full app visual redesign remains separate work.

Verified on GitHub Actions with Flutter 3.47.8, JDK 17: flutter pub get PASS, flutter analyze PASS (zero issues), flutter test PASS, debug Android APK PASS. API, Admin Web and Customer Web CI PASS. Workflow run 40489062196. Debug APK artifact 12728350782 uses API_BASE_URL=https://api.nuvikoz.com/api/v1. No device/emulator visual or payment testing claimed.

Resolved lockfile committed from the passing CI environment; exact Flutter version pinned and lock enforced. This source merge does not install an Android app on users' phones. Download the debug APK from the workflow artifact to install for testing; release signing/Google Play publication were not performed. No website UI or API/database changes were required by this mobile patch.

Follow-up: catalog request races, default mock fallback audit, responsive layouts and screen-by-screen device/visual checks. Rollback: revert the PR merge commit and rebuild/install the preceding app version. No production data mutations performed.
