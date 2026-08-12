# PHASE 16 — Android UX

**App:** `apps/nursery_app`

---

## Home

- Easy care picks (`recommendedForYou`)
- Indoor plants horizontal rail when feed includes data
- Native section headers / grids (not a web clone)

## Product detail

- Best-effort `POST /product-views` after local recently viewed track
- **Notify me** card when OOS (login redirect if needed)
- Existing care sections / reviews / subscribe retained

## Wishlist

- Move to cart disabled when `product.isOutOfStock`
- Label shows “Out of stock”

## Cart

- Parses `warnings` + `checkoutBlocked`
- Warning banners above free-delivery progress
- Checkout disabled when blocked

## API client

- Sends `X-Guest-Token` from `SessionStorage.ensureDeviceId()` for guest view tracking

## Deferred

- Full-screen pinch-zoom gallery polish beyond existing swipe
- Search assist screen parity (API ready; can wire empty-state next)
- Price-drop push (no price history)
- Offline transactional cache as source of truth (still refresh from API)

## Feature parity note

Parity is **API-driven**, not identical layouts. Bottom nav, sheets, and sticky commerce bars remain Android-native patterns.
