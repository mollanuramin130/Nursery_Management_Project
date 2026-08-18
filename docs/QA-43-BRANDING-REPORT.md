# QA-43 BRANDING REPORT

**Date:** 2026-08-18  
**Status:** **PARTIAL**  
**GREEN:** NO · **LIVE:** OUT OF SCOPE  

This phase is UI/UX/startup/branding only. It does not claim production or live readiness.

---

## Brand family

The platform remains **GreenLeaf** on device labels, in-app copy, and APK names. The splash wordmark stays **GreenLeaf** so it matches the established product name.

Tokens reused from `apps/nursery_app/lib/theme/tokens.dart` (`primary`, `primaryDeep`, `primarySoft`, `cream`, `secondary`). No new random palette.

## Customer identity

| Item | Treatment |
|------|-----------|
| Concept | Growth, plants, shopping |
| Palette | Cream field `#F7F4EE`, leaf `#1A5C3A` / `#2D6A4F` |
| Symbol | Soil → seed → sprout → two leaves |
| Tagline | Grow Better. Live Greener. |
| Tone | Softer, brighter, welcoming |

Launcher adaptive background `#0C2417`. Foreground is a botanical leaf/seedling (not a shield).

## Admin identity

| Item | Treatment |
|------|-----------|
| Concept | Management, operations, inventory |
| Palette | Deep field `#0A2418`, line `#D4E8DB` |
| Symbol | Leaf + shield + 2×2 dashboard/inventory grid |
| Tagline | Manage. Monitor. Grow. |
| Tone | Darker, geometric, operational |

Launcher adaptive background `#0C2417`. Foreground is **shield + leaf + grid** — a different silhouette from customer, not a recolor.

## Icon generation

Vector adaptive icons (`drawable/ic_launcher_foreground.xml` + `ic_launcher_background.xml`). Density PNG mipmaps remain as pre-26 / launcher fallbacks and were not regenerated this pass (Android 11 / API 30 uses the adaptive XML).

## Assets

No Lottie/GIF/video added. Animation is CustomPaint only. Native first-frame marks live in `drawable/splash_logo.xml`. Obsolete full-plant native artwork was replaced with soil+seed / shield ghost.
