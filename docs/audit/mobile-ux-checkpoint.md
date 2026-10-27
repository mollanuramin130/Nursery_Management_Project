# Customer mobile reliability checkpoint

Baseline: cd7fd4a, development/v1.0.0. Feature branch: audit/mobile-ux-2026-10-27.

Implemented: same-zone Flutter initialization; SVG image renderer and Android-compatible raster Accept header; product details in existing Shop shell; product deep-link Back to Categories; purchase-bar inset and snackbar clearance; search invalidation on typing/clear and visible failure message. Navigation regression expectations updated.

Scope is the first reliability patch, not completion of the full UI redesign. No backend data or infrastructure changes.

Local diff whitespace check passed. Local Flutter bootstrap was blocked by network policy at pub.dev. Analyzer, tests, APK build and dependency lock resolution have not passed locally. New GitHub workflow resolves dependencies, analyzes, runs tests and uploads a debug APK. Do not merge unless checks pass; review the resolved pubspec.lock before integration. No release-signing credentials or Play publishing workflow used.

Pending: complete catalog request race fixes, live-data fallback audit, address shell policy, responsive components and screen-by-screen visual/device testing. Debug APK is a test artifact, not an installed app update. Existing Android installations require installing a newly built APK; source merge does not update phones.
