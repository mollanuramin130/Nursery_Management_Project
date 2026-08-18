# QA-43 SPLASH REPORT

**Date:** 2026-08-18  
**Status:** **PARTIAL**  
**GREEN:** NO · **LIVE:** OUT OF SCOPE  

---

## Architecture (unchanged)

```
Android LaunchTheme (windowBackground = brand field + quiet first-frame mark)
        ↓  first Flutter frame
Branded Flutter overlay (home/login already mounted underneath)
        ↓  local fade
App shell (cache → mock → API as before)
```

Splash **must not wait for API**. Auth bootstrap is still local secure-storage in `main()` before `runApp`. Cart/wishlist/catalog stay fire-and-forget.

Implementation: Flutter-native `AnimationController` + `CustomPaint` (no Lottie, no GIF, no video, no network).

## Customer sequence (~2400ms + ~380ms fade)

| Time | Frame |
|------|--------|
| 0.0–0.3s | Cream botanical field |
| 0.3–0.8s | Seed on soil |
| 0.8–1.3s | Sprout / stem |
| 1.3–1.7s | Two leaves unfold (easeOutCubic, no bounce) |
| 1.7–2.1s | GreenLeaf wordmark |
| 2.1–2.4s | Tagline **Grow Better. Live Greener.** |
| then | Fade onto the already-painted home shell |

Default `minDisplay` = 2400ms. Hard `maxDisplay` = 5s. Reduced motion jumps to the final frame and exits sooner.

Native splash on Android 11 is cream **with soil + seed only** so the first paint is never a finished plant (avoids grow-twice flicker). Flutter then grows the shoot/leaves. Android 12+ uses the same soil+seed system splash icon.

## Admin sequence (~2200ms + ~360ms fade)

| Time | Frame |
|------|--------|
| 0.0s | Dark `#0A2418` field |
| 0.3s | Leaf mark |
| 0.7s | Shield outline |
| 1.1s | Inventory 2×2 grid |
| 1.6–2.2s | GreenLeaf Admin + **Manage. Monitor. Grow.** |

Native admin splash is a quiet shield ghost (no filled leaf) so Flutter is the reveal.

## Offline / cache / mock

Unchanged from QA-37 / QA-42A:

- Cache peeks are quiet (`markSource` does not mean offline).
- Mock JSON still fills empty local stores.
- API failure does not hold the splash.
- No fake payment/order success.
- False “You are offline” banner is not reintroduced.

## Flicker controls

- Splash is a `Stack` overlay; the router is not remounted when it dismisses.
- Overlay fades out, then `onFinished` unmounts it.
- Native and Flutter backgrounds match per app (cream vs dark).
- Native mark matches Flutter t≈0 (soil/seed vs shield ghost).
- No `ApiClient` / `CatalogRepository` imports in splash files.

## Icons

| App | Adaptive foreground | Background |
|-----|---------------------|------------|
| Customer | Botanical leaf / seedling (white, safe-zone) | `#0C2417` |
| Admin | Shield + leaf + 2×2 inventory grid | `#0C2417` |

Same brand family, different silhouette. Labels: **GreenLeaf** / **GreenLeaf Admin**.
