# QA-41 BUG REGISTER

| ID | Severity | Screen | Platform | Root cause | Before | After | Files | Test | Device | Status |
|----|----------|--------|----------|------------|--------|-------|-------|------|--------|--------|
| QA-41-001 | High | Home categories | Customer | DEBUG FAB over category icons | Green circle over Flowering Plants | FAB bottom-left above nav | `debug_network_panel.dart` | visual | `15-home-fab-bottom-left.png` | FIXED |
| QA-41-002 | High | Home / Categories | Customer | null `image_url` → confusing green tile | Solid green square (Admin Test) | Letter glyph on soft green | `resilient_image.dart`, `home_screen.dart`, `categories_screen.dart` | `qa41_image_stability_test` | `11-…`, `13-…` | FIXED |
| QA-41-003 | High | All images | Customer | Retry did not bust image cache keys | Failed images stayed broken after Retry | `bumpSyncGeneration` + `cacheKey #gN` | `offline_controller.dart`, `network_status_banner.dart`, `resilient_image.dart` | `qa41_image_stability_test` | PARTIAL | FIXED |
| QA-41-004 | High | Offers/Returns/… | Customer | `onRetry: () => setState(_future=_load())` | Skeleton flash on Retry | `softReplaceFuture` | multiple screens | soft_future_refresh | code | FIXED |
| QA-41-005 | Med | Catalog | Customer | `ValueKey` remount emptied items | Filter change → skeleton | Keep provider; `didUpdateWidget` | `catalog_screen.dart` | — | UNVERIFIED | FIXED |
| QA-41-006 | Med | Admin lists | Admin | `_loading=true` wiped lists | Blank spinner on refresh | Soft when data exists | `ops_providers.dart`, `more_screens.dart`, `fulfillment_screens.dart` | `qa41_soft_load_test` | UNVERIFIED | FIXED |
| QA-41-007 | High | Device | Both | Default API `10.0.2.2` on physical phone | Always mock/saved data on Vivo | Document dart-define 127.0.0.1 | `RUN.txt`, build flags | — | PASS | FIXED (ops) |
| QA-41-008 | Low | Banner | Customer | Unsplash slow → long spinner | Spinner on Monsoon banner | Settles with image (acceptable) | — | — | INTENTIONAL | INTENTIONAL |
| QA-40-M-008 | Med | Admin products | Admin | Hard-load remnants | — | Partially covered via ops soft | — | — | OPEN | OPEN |
| QA-40-M-010 | Med | Admin | Admin | Full Vivo matrix | — | Login only this phase | — | — | UNVERIFIED | UNVERIFIED |
| QA-40-M-011 | Low | Search/Account | Customer | PTR N/A by design | — | — | — | — | INTENTIONAL | INTENTIONAL |
| QA-40-M-012 | Med | Checkout | Customer | COD/Razorpay TEST device | — | — | — | — | UNVERIFIED | UNVERIFIED |
| LIVE / UPI | Blocker | Payments | All | LIVE out of scope | — | — | — | — | BLOCKED | BLOCKED |

## Classification summary

- **FIXED:** QA-41-001…007  
- **OPEN:** QA-40-M-008 (residual admin product CRUD)  
- **UNVERIFIED:** QA-40-M-010, QA-40-M-012, full screen matrix rows  
- **INTENTIONAL:** QA-41-008, QA-40-M-011  
- **BLOCKED:** LIVE, UPI settlement  
- **PRE-EXISTING:** Admin Test Category `image_url: null` in API (glyph is correct UX; seed image optional later)
