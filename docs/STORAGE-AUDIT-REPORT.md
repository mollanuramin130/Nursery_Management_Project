# GreenLeaf Storage Audit

**Date:** 2026-08-17  
**Mode:** AUDIT ONLY — nothing was deleted.  
**Scope:** Git repository + developer-home/tooling paths created while bringing the stack up on this Mac.

---

## Repository

| Field | Value |
| --- | --- |
| Path | `/Users/nuraminmolla/Desktop/Nursery_Management_Project` |
| Git root | Same (this is the only clone on Desktop) |
| Branch | `development/v1.0.0` (tracks `origin/development/v1.0.0`) |
| Tracked files | 1,382 |
| Git status | Clean except `database/nursery_sample_data.sql` (one-line local MySQL-8 fix). No untracked source. |
| Ignored (present) | `.env` / `.env.local`, `vendor/`, `node_modules/`, `.next/`, Flutter `build/` + `.dart_tool/`, Android `.gradle/`, Laravel `storage/` cache/logs |
| Root `.gitignore` | Minimal (secrets/`.DS_Store`). Per-app gitignores cover build/cache. |

**Note:** The working tree is named `Nursery_Management_Project`, not `Nursery_Platform`. No second clone of either name was found.

---

## Current Size

| Location | Size | Notes |
| --- | --- | --- |
| Git repository (working tree) | **5.0 GB** | ~4.7 GB of that is ignored build/deps |
| `~/opt` (SDKs/runtime we installed) | **13 GB** | Flutter×2, Android SDK, MySQL, JDK, Node |
| `/tmp/sdk-dl` (installer archives) | **4.4 GB** | Leftover zip/tar after extract |
| Cursor sandbox Gradle/npm cache | **4.5 GB** | Accidental Gradle home under `/var/folders/.../T/` |
| `~/.pub-cache` | **509 MB** | Dart packages |
| `~/.config/herd-lite` | **181 MB** | PHP/Composer (plus leftover PHP 8.3 binary) |
| **Related total (approx)** | **~23 GB** | Overlaps the “40 GB” spike: zip **plus** extracted copies existed at the same time |

Mac APFS report at audit time: **113 GB** volume, **~52 GB** free. System-volume “Used” numbers on APFS snapshots are not a reliable total of user files.

Peak during install was higher than today’s residual: Flutter **3.47 zip (2.1 GB) + extracted (4.1 GB)** plus Flutter **3.38.10 zip (2.0 GB) + extracted (3.6 GB)** plus Android NDK/SDK plus Flutter `build/` plus Gradle sandbox were all on disk together.

---

## Top 50 Largest Directories

Sizes are `du` of that directory (children included).

| Rank | Size | Path | Class |
| --- | --- | --- | --- |
| 1 | 13 GB | `~/opt` | mixed A/D/H (see breakdown) |
| 2 | 4.5 GB | Cursor TMP sandbox cache | D / F |
| 3 | 4.4 GB | `/tmp/sdk-dl` | F / H |
| 4 | 5.0 GB | repo root | mixed |
| 5 | 4.9 GB | `apps/` | mixed |
| 6 | 4.2 GB | `~/opt/android-sdk` | A (local Android toolchain) |
| 7 | 4.1 GB | `~/opt/flutter` (3.47) | **H — unusable on macOS 12** |
| 8 | 3.6 GB | `~/opt/flutter-3.38.10` | A (working Flutter) |
| 9 | 2.8 GB | `~/opt/android-sdk/ndk/28.2.13676358` | A |
| 10 | 2.4 GB | `apps/nursery_admin_mobile` | mixed |
| 11 | 2.3 GB | `apps/nursery_admin_mobile/build` | C |
| 12 | 1.3 GB | `apps/nursery_app` | mixed |
| 13 | 1.2 GB | `apps/nursery_app/build` | C |
| 14 | 770 MB | `~/opt/mysql-8.0.31-macos12-x86_64` | A |
| 15 | 599 MB | `apps/nursery-admin` | mixed |
| 16 | 555 MB | `apps/nursery-web` | mixed |
| 17 | 509 MB | `~/.pub-cache` | D |
| 18 | 460 MB | `apps/nursery-admin/node_modules` | D |
| 19 | 459 MB | `apps/nursery-web/node_modules` | D |
| 20 | 290 MB | `~/opt/jdk-17` | A |
| 21 | 208 MB | `~/opt/node` | A |
| 22 | 207 MB | `~/opt/mysql-data` | A |
| 23 | 181 MB | `~/.config/herd-lite` | A (+ leftover 8.3 binary) |
| 24 | 138 MB | `apps/nursery-admin/.next` | D |
| 25 | 124 MB | `apps/nursery_admin_mobile/.dart_tool` | D |
| 26 | 95 MB | `apps/nursery-web/.next` | D |
| 27 | 91 MB | `apps/nursery-api` | mixed |
| 28 | 90 MB | `apps/nursery_app/.dart_tool` | D |
| 29 | 88 MB | `apps/nursery-api/vendor` | D |
| 30 | 75 MB | `~/.config/herd-lite/bin/php8.3` | F (backup binary) |
| 31 | 49 MB | `.git` | A |
| 32 | 39 MB | `docs/` | B (QA evidence — keep) |
| 33 | 38 MB | `.git/objects` | A |
| 34 | 6.8 MB | `docs/qa40-mobile` | B |
| 35 | 6.8 MB | `docs/qa39` | B |
| 36 | 6.7 MB | `docs/qa41-mobile` | B |
| 37 | 6.0 MB | `docs/qa42a-mobile` | B |
| 38 | 5.7 MB | `docs/qa42-mobile` | B |
| 39 | 3.4 MB | `docs/qa40` | B |
| 40 | 3.0 MB | `apps/nursery_app/android` | B |
| 41 | 2.9 MB | `apps/nursery_admin_mobile/android` | B |
| 42 | 2.8 MB | `apps/nursery_app/android/.gradle` | D |
| 43 | 2.7 MB | `apps/nursery_admin_mobile/android/.gradle` | D |
| 44 | 1.4 MB | `apps/nursery-api/app` | B |
| 45 | 1.0 MB | `apps/nursery-admin/src` | B |
| 46 | 856 KB | `apps/nursery_app/lib` | B |
| 47 | 752 KB | `apps/nursery-web/src` | B |
| 48 | 492 KB | `apps/nursery-api/tests` | B |
| 49 | 224 KB | `apps/nursery-api/storage` | D |
| 50 | 152 KB | `database/` | B |

---

## Top 100 Largest Files

Almost every file **>5 MB** inside the repo is **ignored build/native/debug APK output** or **`node_modules` binaries**. None of these are Git-tracked source.

| Size | Path | Class |
| --- | --- | --- |
| 342 MB | `apps/nursery_app/build/.../arm64-v8a/libflutter.so` | C |
| 342 MB | `apps/nursery_admin_mobile/build/.../arm64-v8a/libflutter.so` | C |
| 337 MB | `apps/nursery_admin_mobile/build/.../x86_64/libflutter.so` | C |
| 301 MB | `apps/nursery_admin_mobile/build/.../armeabi-v7a/libflutter.so` | C |
| 222 MB | `apps/nursery_app/build/.../libVkLayer_khronos_validation.so` | C |
| 222 MB | `apps/nursery_admin_mobile/build/.../libVkLayer_khronos_validation.so` | C |
| 165 MB | `apps/nursery_admin_mobile/build/app/outputs/flutter-apk/app-debug.apk` | C / G |
| 165 MB | `apps/nursery_admin_mobile/build/app/outputs/apk/debug/app-debug.apk` | C / G (copy, different inode) |
| 161 MB | `apps/nursery_app/build/.../zip-cache/...` | C |
| 161 MB | `apps/nursery_admin_mobile/build/.../zip-cache/...` | C |
| 105 MB | `apps/nursery_admin_mobile/build/.../zip-cache/...` | C |
| 101 MB | `apps/nursery_admin_mobile/build/.../zip-cache/...` | C |
| 84 MB | `apps/nursery_app/build/app/outputs/flutter-apk/app-debug.apk` | C / G |
| 84 MB | `apps/nursery_app/build/app/outputs/apk/debug/app-debug.apk` | C / G (copy) |
| 84 MB | `apps/nursery-web/node_modules/@next/swc-darwin-x64/next-swc.darwin-x64.node` | D |
| 84 MB | `apps/nursery-admin/node_modules/@next/swc-darwin-x64/next-swc.darwin-x64.node` | D |
| 79 MB ×3 | Flutter `kernel_blob.bin` / `app.dill` under customer app | C / D |
| 76 MB ×3 | Same under admin mobile | C / D |
| 36–15 MB | Stripped `libflutter.so` / validation layers | C |
| 23 MB | `apps/nursery-api/vendor/laravel/pint/builds/pint` | D |
| 22–8 MB | `.next/dev/cache/turbopack/*.sst` | D |
| 19 MB ×2 | `sharp-libvips` in both Next apps | D |
| **Outside repo** | | |
| 2.1 GB | `/tmp/sdk-dl/flutter.zip` (Flutter **3.47**, cannot run here) | F / H |
| 2.0 GB | `/tmp/sdk-dl/flutter-3.38.10.zip` | F (already extracted) |
| 172 MB | `/tmp/sdk-dl/jdk17.tar.gz` | F (already extracted) |
| 137 MB | `/tmp/sdk-dl/cmdline-tools.zip` | F |
| 15 MB | `/tmp/sdk-dl/platform-tools.zip` | F |
| 96 MB | `~/.config/herd-lite/bin/php` (8.4 — in use) | A |
| 75 MB | `~/.config/herd-lite/bin/php8.3` (backup; not used) | F |

Remaining files under 5 MB are overwhelmingly `node_modules` / Flutter intermediates. Full list of **all files ≥5 MB in-repo is 70 rows** (shown above / grouped). There is no 100th independent “mystery” file — rank 71+ are smaller Next/TypeScript maps (~5 MB each).

**Largest Git-tracked content** is documentation and source (docs ~39 MB total; app `lib/`/`src/` well under 2 MB each). No multi-hundred-MB files are tracked.

---

## Build/Cache Usage

| Path | Size | Class | Why safe / not | Regenerated by |
| --- | --- | --- | --- | --- |
| `apps/nursery_admin_mobile/build` | 2.3 GB | C | Regeneratable. Deleting while you still want a quick rebuild slows the next `flutter run`. Phone already has the APK. | `flutter build` / `flutter run` |
| `apps/nursery_app/build` | 1.2 GB | C | Same | same |
| `apps/nursery-admin/node_modules` | 460 MB | D | Regeneratable. Sites **stop** until `npm install`. | `cd apps/nursery-admin && npm install` |
| `apps/nursery-web/node_modules` | 459 MB | D | Same | `cd apps/nursery-web && npm install` |
| `apps/nursery-admin/.next` | 138 MB | D | Regeneratable. Next recreates on `npm run dev`. | `next dev` / `next build` |
| `apps/nursery-web/.next` | 95 MB | D | Same | same |
| `apps/nursery_admin_mobile/.dart_tool` | 124 MB | D | Regeneratable | `flutter pub get` + build |
| `apps/nursery_app/.dart_tool` | 90 MB | D | Same | same |
| `apps/nursery-api/vendor` | 88 MB | D | Regeneratable. API **stops** until `composer install`. | `composer install` |
| `apps/*/android/.gradle` | ~6 MB | D | Tiny; regeneratable | Gradle |
| Cursor TMP `.../gradle` | 4.4 GB | D / F | **Not** `~/.gradle`. Accidental sandbox Gradle home. | Next Flutter/Gradle build (will re-download into a new cache) |
| Cursor TMP `.../npm` | 173 MB | D / F | Sandbox npm cache | `npm install` |
| `~/.pub-cache` | 509 MB | D | Needed for Flutter deps | `flutter pub get` |
| Laravel `storage/logs/laravel.log` | 19 KB | D | Tiny | grows at runtime |

---

## Duplicate Projects

**None found.**

Searched Desktop, Documents, Downloads, `/tmp` (depth 3) for `Nursery_Platform`, `GreenLeaf`, `nursery-api`, `*_old`, `*_backup`, `*_copy`, Clone.

| PATH | SIZE | LAST MODIFIED | GIT? | SAME CONTENT? | RECOMMENDATION |
| --- | --- | --- | --- | --- | --- |
| `/Users/nuraminmolla/Desktop/Nursery_Management_Project` | 5.0 GB | live | Yes | Canonical | Keep |

Duplicate **APKs** (not project copies): each app writes `outputs/apk/debug/app-debug.apk` **and** `outputs/flutter-apk/app-debug.apk` as **separate files** (different inodes, same size). Extra **~249 MB**. Class **G**. Both live under `build/` so `flutter clean` removes them together.

---

## Temporary Files

| Path | Size | Class | Notes |
| --- | --- | --- | --- |
| `/tmp/sdk-dl/*` | 4.4 GB | F / H | Installer archives after successful extract |
| `/tmp/mysqld.log`, mysql sockets | negligible | F | Live MySQL |
| `~/.config/herd-lite/bin/php8.3` | 75 MB | F | Replaced by PHP 8.4; macOS 12 cannot use Herd 8.4, we kept 8.3 then switched to static 8.4 |

No leftover `/tmp/mysql-*.tar.gz` (already gone). No `/tmp/php84.tar.gz`.

---

## QA Artifacts

**IMPORTANT QA EVIDENCE (keep — Class B)**  
`docs/QA-*.md` closeouts, reports, matrices, bug registers, Razorpay/UPI evidence, architecture docs, plus screenshot folders:

- `docs/qa39`, `docs/qa40`, `docs/qa40-mobile`, `docs/qa41-mobile`, `docs/qa42-mobile`, `docs/qa42a-mobile` (~35 MB combined)
- `docs/qa03_*.png`

**SAFE-TO-DELETE QA ARTIFACTS**  
None identified as *duplicate junk*. `docs/qa42-mobile/payment-smoke.log` is small and evidence-related — **I UNKNOWN — do not delete**.

PHPUnit coverage dirs: not present. No Playwright/Cypress artifacts. No extra IPA/AAB.

---

## External Caches

### OUTSIDE REPOSITORY — POTENTIAL DISK CONSUMERS

| PATH | SIZE | PURPOSE | SAFE TO DELETE? | REGENERATABLE? | RECOMMENDED ACTION |
| --- | --- | --- | --- | --- | --- |
| `/tmp/sdk-dl` | 4.4 GB | Flutter/JDK/Android **installer zips** | Yes, **high** | Yes (re-download) | Delete after you confirm apps still run |
| `~/opt/flutter` | 4.1 GB | Flutter **3.47** — **does not start on macOS 12** | Yes, **high** | Yes, but do **not** reinstall 3.47 | Delete; keep `flutter-3.38.10` |
| Cursor sandbox cache | 4.5 GB | Gradle+npm used during `flutter run` | Yes, **high** | Yes | Delete; next build uses a new cache |
| `~/opt/flutter-3.38.10` | 3.6 GB | Working Flutter (Dart 3.10.9) | **No** if you still build mobile | N/A | Keep |
| `~/opt/android-sdk` | 4.2 GB | Android SDK + NDK | **No** for Flutter Android | Partial | Keep |
| `~/opt/mysql-8.0.31-macos12-x86_64` | 770 MB | Local MySQL 8 | **No** while API uses it | Reinstall tarball | Keep |
| `~/opt/mysql-data` | 207 MB | `nursery_local` data | **No** | Re-migrate + sample SQL | Keep |
| `~/opt/jdk-17` | 290 MB | Java 17 for Gradle | **No** for Android builds | Re-extract JDK | Keep |
| `~/opt/node` | 208 MB | Node 24 for Next.js | **No** for websites | Re-extract Node | Keep |
| `~/.pub-cache` | 509 MB | Pub packages | Optional later | `flutter pub get` | Keep while iterating |
| `~/.config/herd-lite` | 181 MB | PHP 8.4 + Composer | **No** for API (`php` is this) | php.new / static-php | Keep; optional delete `php8.3` only |
| `~/Library/Caches` | 174 MB | Chrome/Apple system | **No** (not our junk) | OS | Leave |
| `~/Library/Developer` | missing/empty | Xcode | N/A | N/A | None |
| `~/.gradle` | missing | Normal Gradle home unused | N/A | N/A | Gradle lived in sandbox TMP |
| Docker | not installed | — | — | — | None |
| `~/Library/Caches/Google` | 94 MB | Chrome | Don’t touch | — | Leave |

---

## SAFE TO DELETE

Nothing below has been deleted. Confirm before running commands in § recommended cleanup.

| Path | Size | Reason | Regeneratable | Confidence |
| --- | --- | --- | --- | --- |
| `/tmp/sdk-dl` | 4.4 GB | Installer archives already extracted into `~/opt` | Yes (re-download) | **HIGH** |
| `~/opt/flutter` | 4.1 GB | Flutter 3.47 **cannot run** on macOS 12; apps use `~/opt/flutter-3.38.10` | Yes, but do not restore 3.47 | **HIGH** |
| Cursor TMP sandbox cache (`.../T/cursor-sandbox-cache`) | 4.5 GB | Accidental Gradle/npm cache; not required at runtime | Yes | **HIGH** |
| `~/.config/herd-lite/bin/php8.3` | 75 MB | Unused PHP 8.3 backup | Yes | **MEDIUM** |
| `apps/nursery_* /build` (both Flutter apps) | 3.5 GB | Debug intermediates; APKs already on Vivo | `flutter run` / `build apk` | **MEDIUM** (rebuild time) |
| Duplicate `apk/debug` vs `flutter-apk` copies | ~249 MB | Same APK written twice | Flutter rebuild | **MEDIUM** (comes back on next build) |

**Do not** put `node_modules`, `vendor`, `.next`, `android-sdk`, `flutter-3.38.10`, MySQL, JDK, Node, or `.env*` in this table while you need the stack running today.

---

## DO NOT DELETE

| Path | Reason |
| --- | --- |
| `.git/` | History |
| All app source (`lib/`, `src/`, `app/`, migrations, tests, scripts) | Project source |
| `docs/**` QA reports, matrices, evidence screenshots | Required evidence |
| `database/nursery_sample_data.sql` | Seed data (tracked; currently modified locally) |
| `RUN.txt`, README, lockfiles, `pubspec.yaml`, `composer.json`, `package.json` | Project contract |
| `apps/*/.env` and `.env.local` | Local config (secrets — never commit, never paste) |
| `~/opt/flutter-3.38.10` | Only Flutter that runs on this Mac |
| `~/opt/android-sdk` | Android builds |
| `~/opt/mysql-*` + `mysql-data` | Live local DB |
| `~/opt/jdk-17`, `~/opt/node` | Build/runtime |
| `~/.config/herd-lite/bin/php` (8.4) + `composer` | Laravel |
| `apps/nursery-web/node_modules` + `apps/nursery-admin/node_modules` | Running websites |
| `apps/nursery-api/vendor` | Running API |
| Mock JSON / Flutter assets under `apps/nursery_app` | Offline/runtime |

---

## UNKNOWN — MANUAL REVIEW

| Path | Size | Why uncertain |
| --- | --- | --- |
| `docs/qa42-mobile/payment-smoke.log` | small | QA evidence vs disposable log |
| `android-37` symlink vs `android-37.0` under Android SDK | tiny | Gradle workaround; deleting breaks `compileSdk 37` |
| Cursor IDE caches under `~/Library/Caches/com.todesktop.*` | <1 MB | IDE, not GreenLeaf |

---

## Estimated Reclaimable Space

| Band | Approx | What |
| --- | --- | --- |
| **HIGH CONFIDENCE** | **~13.0 GB** | `/tmp/sdk-dl` (4.4) + unused Flutter 3.47 (4.1) + sandbox Gradle/npm (4.5) |
| **MEDIUM CONFIDENCE** | **~3.6 GB** | Flutter `build/` folders (3.5) + leftover `php8.3` (0.075) — rebuild cost |
| **LOW CONFIDENCE** | **~1.2 GB** | `node_modules`+`.next`+`vendor` if you accept reinstall **and** downtime |
| **If you also uninstall the whole local toolchain** | ~13 GB more (`~/opt` minus already-counted 4.1) | Only if you no longer develop on this Mac |

---

## Classification key

- **A REQUIRED** — needed to run/build GreenLeaf on this machine  
- **B PROJECT SOURCE** — Git-tracked (or should-keep) product/docs  
- **C BUILD OUTPUT** — `build/`, APKs, native `.so`  
- **D DEVELOPMENT CACHE** — `node_modules`, `.next`, `vendor`, pub/Gradle  
- **E TEST ARTIFACT** — none large  
- **F TEMPORARY FILE** — `/tmp` archives, PHP 8.3 backup  
- **G DUPLICATE** — APK copies; unused second Flutter SDK  
- **H UNRELATED** — Flutter 3.47 (wrong OS); installer zips after extract  
- **I UNKNOWN** — listed above  

---

## How this disk spike happened

Session installs (not Git):

1. PHP/Composer via php.new + static PHP 8.4  
2. MySQL 8.0.31 tarball → `~/opt`  
3. Node 24 → `~/opt/node`  
4. Flutter **3.47** zip **and** extract — **fails on Monterey**  
5. Flutter **3.38.10** zip **and** extract — **this is the one in use**  
6. JDK 17 + Android cmdline-tools / platforms / **NDK 2.8 GB**  
7. First `flutter run` pulled Gradle into Cursor’s **sandbox `/var/folders` cache (4.4 GB)**  
8. Two Flutter debug `build/` trees (3.5 GB) plus two Next `node_modules` (~0.9 GB)

That stack easily looks like “40 GB” at peak (archives **plus** extracted SDKs **plus** builds). Residual today is ~23 GB related files.
