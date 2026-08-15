# QA-41 REPORT — Mobile Stability + Refresh + Cache/API/Mock + UI Flicker

**Date:** 2026-08-15  
**Status:** **PARTIAL** (not GREEN · not LIVE READY)  
**Device:** vivo 1951 · ADB `2d3714f`  
**API:** `http://127.0.0.1:8000/api/v1` via `adb reverse tcp:8000 tcp:8000`

---

## Objective

Stabilize Customer + Admin Flutter apps under slow/unavailable API, offline, reconnect, pull-to-refresh, Retry, image failure, and soft revalidation — without skeleton flash when usable data exists.

## Root causes addressed (this phase)

| ID | Issue | Fix |
|----|--------|-----|
| QA-41-001 | DEBUG FAB over category row looked like broken image (Flowering Plants green circle) | Move FAB bottom-left above shell nav |
| QA-41-002 | Null `image_url` (Admin Test Category) rendered as solid green tile | `CategoryGlyphAvatar` / `CategoryCircleImage` letter fallback |
| QA-41-003 | Retry did not revalidate failed network images | `bumpSyncGeneration` + `cacheKey` includes generation |
| QA-41-004 | FutureBuilder `onRetry` / mutations assigned incomplete Future | Soft `softReplaceFuture` + `completedFuture` |
| QA-41-005 | Catalog filter remount flashed skeleton (QA-40-M-009) | Remove `ValueKey` remount; `didUpdateWidget` → `applyFilters` |
| QA-41-006 | Admin notifications/fulfillment/purchasing hard-cleared UI | Soft load when rows/detail already present |
| QA-41-007 | Physical device used emulator host `10.0.2.2` | Document/build with `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1` |

## Architecture preserved

```
cache → mock JSON (read fallback) → API → validate → update cache → smooth UI
```

Stale-while-revalidate: existing UI stays visible during soft refresh.

## Evidence

See `docs/qa41-mobile/`:

- `10-home-api-on.png` / `11-home-images-settled.png` — API ON, glyph **A**, real category photos, no FAB on categories
- `13-shop-catalog.png` — Admin Test Category glyph; other categories imaged
- `15-home-fab-bottom-left.png` — FAB clear of category row
- `16-admin-login.png` — Admin install with device API define

## Regression

| Suite | Result |
|-------|--------|
| Customer Flutter | **68** tests passed (+2 QA-41 image tests) |
| Admin Flutter | **28** tests passed (+1 soft-load smoke) |
| PHPUnit | **253** tests · **1209** assertions · 2 skipped |
| LIVE / UPI settlement | **OUT OF SCOPE / BLOCKED** |

## Still open / unverified

See `QA-41-BUG-REGISTER.md`.
