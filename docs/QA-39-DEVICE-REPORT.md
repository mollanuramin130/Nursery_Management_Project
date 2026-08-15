# QA-39 DEVICE REPORT — Vivo 1951

**Date:** 2026-08-15 · **ADB:** `2d3714f` · **Android 11**

## Connection

```
adb devices → 2d3714f device
adb reverse tcp:8000 tcp:8000
API_BASE_URL=http://127.0.0.1:8000/api/v1
curl health/ready → ok / healthy
```

## Screenshots captured

| File | Screen |
|------|--------|
| `docs/qa39/vivo_home.png` | Home + bottom nav + Sale + wishlist heart |
| `docs/qa39/vivo_shop.png` | Categories / Shop tab active |
| `docs/qa39/vivo_cart.png` | Empty cart |
| `docs/qa39/vivo_account.png` | Guest account gate |
| `docs/qa39/vivo_cart_with_item.png` | PDP Potting Mix (fullscreen sticky) |
| `docs/qa39/vivo_pdp_after_add.png` | Snackbar truncated "View Ca" (pre-fix evidence) |
| `docs/qa39/vivo_cart_filled.png` | Cart+item+Checkout+offline banner+truncated snackbar |
| `docs/qa39/vivo_snack_fixed.png` | Snackbar action "Open" visible (post-fix) |

## Device observations

1. Bottom nav consistent on shell screens.
2. PDP correctly hides shell tabs.
3. Snackbar action truncation reproduced and shortened.
4. Offline/saved-data banner appeared during cart session.
5. Debug network FAB remains DEBUG-only overlap risk.

## Not captured this session

- Admin Mobile full walkthrough screenshots
- Wishlist remove frame-by-frame after latest bootstrap removal
- COD / Razorpay TEST sheet on device
