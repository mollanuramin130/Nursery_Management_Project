# QA-37 Test Matrix (Offline-first + resilience)

**Date:** 2026-08-13 · Device `2d3714f`

| # | Case | Result | Evidence |
|---|------|--------|----------|
| 1 | Audit before code | **PASS** | OFFLINE-ARCHITECTURE |
| 2 | Mock JSON API envelope | **PASS** | `assets/mock_data/*` |
| 3 | Home/catalog/PDP mockOnly | **PASS** | catalog_repository_offline_test |
| 4 | Search “money” offline | **PASS** | unit |
| 5 | Cart local add mockOnly | **PASS** | offline_local_cart_test |
| 6 | Wishlist local toggle | **PASS** | offline_local_cart_test |
| 7 | Wishlist 500 restores | **PASS** | wishlist_optimistic_test |
| 8 | Place order blocked localOnly | **PASS** | checkout_screen guard |
| 9 | Payment never faked | **PASS** | architecture |
| 10 | DEBUG network sim | **PASS** | DebugNetworkOverlay |
| 11 | Banner “Showing saved data” | **PASS** | NetworkStatusBanner |
| 12 | Bottom nav shell | **PASS** | shell_nav_routes_test |
| 13 | PHPUnit regression | **PASS** | 253 / 1209 |
| 14 | Customer Flutter suite | **PASS** | **52** |
| 15 | Vivo Wi‑Fi OFF physical | **UNVERIFIED** | use DEBUG sim |
| 16 | API process stop physical | **UNVERIFIED** | AUTO fallback coded |
| 17 | LIVE / GREEN | **NO** | |

Legend: PASS · PARTIAL · UNVERIFIED · BLOCKED · NO
