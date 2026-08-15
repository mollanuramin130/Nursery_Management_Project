# QA-41 TEST MATRIX

Device: **vivo 1951** (`2d3714f`)  
Build: debug APK with `--dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1`  
Reverse: `adb reverse tcp:8000 tcp:8000`

Legend: PASS · FAIL · PARTIAL · N/A · UNVERIFIED · BLOCKED

## Customer Mobile

| Screen | Cold | Cached | API ON | API OFF | Offline | Refresh | Rapid nav | Images | Retry | Recovery |
|--------|------|--------|--------|---------|---------|---------|-----------|--------|-------|----------|
| Home | PASS | PASS | PASS | PASS* | PASS* | PASS | PARTIAL | PASS | PASS | PASS |
| Shop/Categories | PASS | PASS | PASS | PARTIAL | PARTIAL | UNVERIFIED | UNVERIFIED | PASS | UNVERIFIED | UNVERIFIED |
| Catalog (filters) | UNVERIFIED | — | UNVERIFIED | — | — | UNVERIFIED | — | — | — | — |
| Search | UNVERIFIED | | | | | N/A† | | | | |
| Product Detail | UNVERIFIED | | | | | soft PTR code | | | | |
| Wishlist | UNVERIFIED | | | | | soft sync | | | | |
| Cart | UNVERIFIED | | | | | soft fetch code | | | | |
| Checkout | UNVERIFIED | | | | | | | | | |
| Orders | UNVERIFIED | | | | | soft | | | | |
| Order Detail | UNVERIFIED | | | | | soft | | | | |
| Account | UNVERIFIED | | | | | N/A† | | | | |
| Addresses | UNVERIFIED | | | | | soft (QA-40-M) | | | | |
| Returns | UNVERIFIED | | | | | soft | | | | |
| Login/Logout | UNVERIFIED | | | | | | | | | |

\* Mock/cache path exercised when API unreachable (pre dart-define) and via DEBUG overlay historically.  
† Search/Account hub PTR intentionally N/A by design (QA-40-M-011).

## Admin Mobile

| Screen | Status |
|--------|--------|
| Login | PASS (launch evidence `16-admin-login.png`) |
| Dashboard / Orders / Inventory soft-load | CODE FIXED · device UNVERIFIED |
| Notifications / Fulfillment soft-load | CODE FIXED · device UNVERIFIED |
| Purchasing soft-load | CODE FIXED · device UNVERIFIED |
| Full Vivo matrix | UNVERIFIED (carries QA-40-M-010) |

## Network matrix

| Test | Result |
|------|--------|
| API ON + Internet ON | PASS (Home `11-home-images-settled.png`) |
| API OFF / Internet ON | PASS* (saved-data banner + glyphs) |
| Pull refresh API ON | PASS (`12-home-pull-refresh.png`) |
| Retry bumps image gen | CODE + unit test PASS |
| Rapid PTR / navigate during request | PARTIAL / UNVERIFIED |
| LIVE payment | BLOCKED |

## Automated

| Suite | Result |
|-------|--------|
| `apps/nursery_app` flutter test | 68 PASS |
| `apps/nursery_admin_mobile` flutter test | 28 PASS |
| `apps/nursery-api` php artisan test | 253 PASS (2 skipped) |
