# Phase E — Cart + Coupon + Move to Wishlist Audit

**Date:** 2026-08-11  
**Status:** Audit complete → **implemented** (see `PHASE_E_CART_API_DOCUMENTATION.md`, `PHASE_E_IMPLEMENTATION_REPORT.md`)

---

## Executive verdict

| Area | Status |
|------|--------|
| Cart CRUD + coupon | **Implemented** under `/api/v1/cart*` — server totals authoritative |
| Guest `X-Cart-Token` + login merge | **Implemented** |
| Wishlist list/add/remove | **Implemented** (auth-only) |
| Atomic cart → wishlist | **Implemented** `POST /cart/items/{id}/move-to-wishlist` |
| Atomic wishlist → cart | **Implemented** `POST /wishlist/{productId}/move-to-cart` |
| Clear cart API | **Implemented** `DELETE /cart` |
| Free delivery in cart API | **Implemented** `free_delivery` + app config threshold |
| Coupon usage limits / redemptions | Designed but **not enforced** (known limitation) |

**Do not create** `mobile_cart` / separate coupon engines. Extended `CartService` + wishlist routes.

---

## 1. Current cart architecture

```
Optional JWT + X-Cart-Token
        ↓
   CartService.resolveFromRequest
        ↓
   carts + cart_items
        ↓
   present() → totals JSON
```

One cart per user (`user_id` unique) or guest (`cart_token`).

---

## 2. Database schema

| Table | Role |
|-------|------|
| `carts` | user_id / cart_token, coupon_code, status |
| `cart_items` | product, variant, qty, unit_price_snapshot |
| `coupons` | percent/fixed, min, max, dates, limits (limits unused) |
| `wishlists` | user_id + product_id unique |
| `inventory_items` | stock for assertAvailable |

No `coupon_usages` table in migrations.

---

## 3–5. Current APIs

### Cart
- `GET /cart`
- `POST /cart/items` `{ product_id, quantity, variant_id? }` — increases qty if exists
- `PUT /cart/items/{id}` `{ quantity }`
- `DELETE /cart/items/{id}`
- `POST /cart/apply-coupon` `{ code }`
- `DELETE /cart/coupon`

### Wishlist
- `GET|POST /wishlist`, `DELETE /wishlist/{productId}`, `GET /wishlist/contains`

### Coupons public
- `GET /coupons`

---

## 6–8. Website / Android / Checkout

Both: API-authoritative totals; coupon on cart page; guest token merge on login.  
Web: move to wishlist = wishlist POST + cart DELETE (non-atomic).  
Android: **no** move to wishlist.  
Checkout: uses `/checkout/preview` (Flutter requires it; web falls back to cart).

---

## 9–10. Guest / authenticated

Guest: `X-Cart-Token`. User: JWT cart. Merge on login/register only. Wishlist auth-only (keep).

---

## 11–14. Pricing / stock / coupon / free delivery

| Concern | Backend |
|---------|---------|
| Prices | Snapshot + live product/variant; client price ignored |
| Stock | `InventoryService` on add/update |
| Coupon | Active + date + min_order; discount recalculated in `present()` |
| Shipping on cart | Always 0 until checkout |
| Free delivery | **Not in API** — clients hardcode 999 |

---

## 15–17. Problems / duplicates / missing

1. Non-atomic move to wishlist  
2. No move wishlist → cart  
3. No clear cart  
4. Free delivery not server-driven  
5. Coupon usage limits unused  
6. Merge may skip stock re-check  
7. Flutter mini-cart “Subtotal” shows grandTotal  

---

## 18–19. Required changes

### API (do now)
1. `POST /cart/items/{id}/move-to-wishlist` — transaction  
2. `POST /wishlist/{productId}/move-to-cart` — transaction + stock  
3. `DELETE /cart` — clear items + coupon  
4. Cart `present()` include `free_delivery: { threshold, remaining, qualifies }` from config  
5. Normalize coupon code case in discount calc  

### Database
No new tables. Optional: document `app_settings` / env `FREE_DELIVERY_THRESHOLD` default 999.  
SQL file: `database/phase_e_cart.sql` (notes + verify).

### Clients
- Consume free_delivery from cart API  
- Use atomic move endpoints  
- Android: move to wishlist UI  
- Wishlist → cart move button  

---

## 20. Implementation order

1. Audit (this doc)  
2. CartService + routes  
3. API tests  
4. Website  
5. Android  
6. Docs + report  
