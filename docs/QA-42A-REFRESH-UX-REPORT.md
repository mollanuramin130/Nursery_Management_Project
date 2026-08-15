# QA-42A — Refresh UX / Offline Banner / Screen Stability

**Date:** 2026-08-15  
**Status:** **PARTIAL**  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## Root cause

During stale-while-revalidate, `CatalogRepository.peek*()` called `OfflineController.markSource(cache)`, which set `servingLocal = true`.

`NetworkStatusBanner` treated `servingLocal` (and quiet `syncing`) as offline, so **every pull-to-refresh** briefly showed:

> Showing your saved GreenLeaf data

…even while the API was healthy and about to succeed. That made normal refresh look like a failure.

## Fix (QA-42A)

1. **`markSource(source, {asDegraded: false})`** — cache peeks are quiet; they do not flip the offline banner.
2. **`markDegraded(source)`** — only after genuine API failure / offline skip / mock-only fallback.
3. **Banner show rules** — show only for `net.showBanner`, `servingLocal`, recovery (`showBackOnline` / syncing-while-degraded). Quiet sync alone does **not** show a banner.
4. Copy: `"You're offline · Showing saved data"` (+ brief `"Back online"` after recovery).
5. Soft `AnimatedSize` on banner insert/remove.

Architecture preserved: cache → mock → API, SWR, RefreshIndicator, soft FutureBuilder.

## Files changed

- `apps/nursery_app/lib/providers/offline_controller.dart`
- `apps/nursery_app/lib/widgets/network_status_banner.dart`
- `apps/nursery_app/lib/data/catalog_repository.dart`
- `apps/nursery_app/lib/providers/cart_provider.dart`
- `apps/nursery_app/test/qa42a_refresh_banner_test.dart`

## Regression

| Suite | Result |
|-------|--------|
| Customer Flutter | **73** PASS (+5 QA-42A) |
| Admin Flutter | **29** PASS |
| PHPUnit | **253 / 1209** |
| Customer Web unit | PASS |

## Vivo evidence (`docs/qa42a-mobile/`)

| Shot | Result |
|------|--------|
| `01-home-online-idle.png` | No offline banner |
| `02-home-ptr-in-progress.png` | RefreshIndicator only; content kept; **no** saved-data banner |
| `03-home-ptr-settled.png` | Settled online |
| `06-home-ptr-api-down.png` | True offline banner + saved content |

## Remaining / unverified

- Full multi-screen PTR matrix (Shop/Cart/Orders/…) UNVERIFIED on device this pass
- Admin Mobile has no global offline banner (OpsStaleBanner only) — N/A parity
- PDP image flash during API blip still UNVERIFIED as separate polish item
