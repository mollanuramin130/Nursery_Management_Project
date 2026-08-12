# Phase E — Implementation Report

**Date:** 2026-08-11  
**Status:** Complete (cart / coupon / move parity; checkout not redesigned)

---

## 1. Existing cart architecture

One `CartService` resolves guest (`X-Cart-Token`) or authenticated (`Bearer`) cart, presents server totals, merges guest cart on login/register.

## 2. Database tables

Reused: `carts`, `cart_items`, `coupons`, `wishlists`, `products`, `inventory_items`.  
No new tables. See `database/phase_e_cart.sql`.

## 3. API audit → changes

| Gap | Fix |
|-----|-----|
| Non-atomic web move to wishlist | `POST /cart/items/{id}/move-to-wishlist` (transaction) |
| No wishlist → cart atomic API | `POST /wishlist/{productId}/move-to-cart` |
| No clear cart | `DELETE /cart` |
| Free delivery client-hardcoded | `free_delivery` on cart + `commerce.free_delivery_threshold` in app config |

## 4. Problems found

- Free delivery ₹999 hardcoded on web/Flutter
- Web move = two API calls (race)
- Android had no move-to-wishlist / move-to-cart
- Mini-cart showed grand total as “Subtotal”
- Coupon usage limits schema-only (unchanged limitation)

## 5–6. Database / SQL changes

None destructive. Documentation + verification queries in `database/phase_e_cart.sql`.  
Config: `FREE_DELIVERY_THRESHOLD` (default 999).

## 7–9. Final APIs

Documented in `docs/PHASE_E_CART_API_DOCUMENTATION.md`.

Cart: GET/POST/PUT/DELETE items, DELETE cart, apply/remove coupon, move-to-wishlist.  
Wishlist: list/add/remove/contains + move-to-cart.  
Coupon apply recalculates cart; client `discount` ignored.

## 10. JSON contract

Same envelope + cart payload for Website and Android, including `free_delivery`.

## 11–13. Guest / auth / merge

Guest via `X-Cart-Token`; auth via Bearer. Merge on login/register with stock-capped qty. Wishlist remains auth-only (guest move → login).

## 14–16. Pricing / stock / coupon

Server calculates subtotal, discount, grand total. Stock checked on add/update/move-to-cart. Coupon validated against current cart; soft-cleared when below min on present.

## 17–18. Move implementations

Both directions use DB transactions. Duplicate wishlist → ensure exists + remove cart line. Stock fail on move-to-cart keeps wishlist item.

## 19. Website changes

- Cart page: API free delivery, clear cart, atomic move-to-wishlist  
- MiniCartDrawer: API free delivery  
- Wishlist: Move to cart via atomic API  
- TopAnnouncement: threshold from `/app/config`  
- Cart store: `clearCart`, `moveToWishlist`

## 20. Android changes

- `Cart.freeDelivery` model  
- `CartProvider`: clear, moveToWishlist, replaceCart  
- `WishlistProvider.moveToCart`  
- Cart screen: free delivery from API, move/remove UX, clear, empty state  
- Mini-cart: real subtotal + free-delivery hint  
- Wishlist screen: Move to cart / Remove list UX

## 21. Checkout integration

Unchanged Phase F surface. Checkout continues to consume server cart / checkout preview. Cart totals remain authoritative inputs.

## 22. Security

- Client price/discount ignored (smoke-tested)  
- Mutations scoped to resolved cart / authenticated user  
- Move-to-wishlist requires auth and cart ownership

## 23–26. Tests

API smoke: `php apps/nursery-api/scripts/phase_e_cart_smoke.php` — all PASS.  
Cross-platform: same endpoints → same DB cart for web/Android sessions of the same user. Manual UI verification recommended after local servers start.

## 27. Remaining limitations

- Coupon global/per-user usage limits not fully enforced  
- Cart `shipping_total` / `tax_total` stay 0 until checkout  
- No guest wishlist  
- Marketing CSS variable `--free-delivery-threshold` may still say 999 locally (banner uses API)

---

## Definition of Done (checklist)

- [x] One cart / coupon / wishlist API for Web + Android  
- [x] Same DB + JSON contract  
- [x] Server totals, stock, coupon, ownership  
- [x] Guest + auth cart + documented merge  
- [x] Clear, coupon apply/remove, atomic moves  
- [x] Free delivery from API  
- [x] Docs + SQL notes + smoke script  
- [x] Phase F checkout not redesigned  
