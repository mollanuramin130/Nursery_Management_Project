# Phase E — Cart + Coupon + Wishlist API Documentation

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`  
**Guest cart:** `X-Cart-Token: <token>`  
**Authenticated:** `Authorization: Bearer <access_token>` (+ `active.user` where noted)

Website and Android **must** use these endpoints. Totals, stock, coupon discount, and free-delivery progress are **server-authoritative**. Clients must not send trusted `price` / `discount` / `total` values.

---

## Cart payload (shared)

Returned by get/add/update/remove/clear/coupon/move-to-wishlist (top-level `data`), and nested under `data.cart` for wishlist → cart.

```json
{
  "id": 12,
  "cart_token": "abc…",
  "currency": "INR",
  "items": [
    {
      "id": 501,
      "product_id": 703,
      "variant_id": null,
      "name": "Succulent Desk Kit",
      "slug": "succulent-desk-kit",
      "thumbnail_url": "https://…",
      "unit_price": 549,
      "quantity": 1,
      "line_total": 549,
      "stock_status": "in_stock",
      "max_qty": 20
    }
  ],
  "item_count": 1,
  "subtotal": 549,
  "discount_total": 0,
  "coupon_code": null,
  "tax_total": 0,
  "shipping_total": 0,
  "grand_total": 549,
  "free_delivery": {
    "enabled": true,
    "threshold": 999,
    "remaining": 450,
    "qualifies": false
  }
}
```

**Notes**

| Field | Rule |
|-------|------|
| `unit_price` / line totals | From product (or variant) / snapshot — client price ignored |
| `tax_total` / `shipping_total` | `0` on cart; shipping resolved at checkout |
| `free_delivery` | From `FREE_DELIVERY_THRESHOLD` env (default `999`) |
| Duplicate product add | **Increases** quantity (Option A) |

---

## CART-01 Get cart

`GET /cart` — optional JWT + optional `X-Cart-Token`

**Success 200** — cart payload (empty cart if none).  
May set response header `X-Cart-Token`.

---

## CART-02 Add item

`POST /cart/items` — optional JWT + optional `X-Cart-Token`

**Request**

```json
{
  "product_id": 703,
  "quantity": 1,
  "variant_id": null
}
```

Also accepts `product_variant_id` as alias of `variant_id`.  
Extra fields such as `price` are **ignored**.

**Success 201** — full cart.  
**Errors:** `404` product, `409` inventory, `422` validation.

---

## CART-03 Update quantity

`PUT /cart/items/{id}` — optional JWT + cart token

**Request:** `{ "quantity": 3 }`  

**Success 200** — full cart.  
**Errors:** `404` item not in this cart, `409` stock, `422` validation.

Ownership: item must belong to the resolved cart (guest token or authenticated user).

---

## CART-04 Remove item

`DELETE /cart/items/{id}` — optional JWT + cart token  

**Success 200** — full cart.

---

## CART-05 Clear cart

`DELETE /cart` — optional JWT + cart token  

Clears all lines and coupon.  
**Success 200** — empty cart payload.

---

## CART-06 Apply coupon

`POST /cart/apply-coupon` — optional JWT + cart token  

**Request**

```json
{ "code": "WELCOME10" }
```

Client-supplied `discount` is **ignored**. Backend validates code, dates, status, min order, max discount, and recalculates cart.

**Success 200** — full cart with `coupon_code` + `discount_total`.  
**Errors:** invalid/expired/ineligible coupon (typically `400`).

Seed codes: `WELCOME10`, `MONSOON10`, `FLAT50`, `GREEN15` (no `GREEN10` in sample data).

---

## CART-07 Remove coupon

`DELETE /cart/coupon` — optional JWT + cart token  

**Success 200** — full cart, `coupon_code: null`, recalculated totals.

**Mutation rule:** After qty/remove changes, `present()` soft-clears coupon if cart falls below `min_order_amount`.

---

## CART-08 Move cart item → wishlist

`POST /cart/items/{id}/move-to-wishlist` — **Bearer + `active.user` required**

Atomic transaction:

1. Ensure wishlist row for product (no duplicate)
2. Delete cart line
3. Return updated cart

Guests must sign in first.  
**Success 200** — full cart.  
**Errors:** `401` guest, `404` item/product.

---

## WISH-01 List / add / remove / contains

| Method | Path | Auth |
|--------|------|------|
| GET | `/wishlist` | Bearer |
| POST | `/wishlist` `{ product_id }` | Bearer |
| DELETE | `/wishlist/{productId}` | Bearer |
| GET | `/wishlist/contains?product_ids[]=` | Bearer |

Wishlist is **authenticated-only** (no guest wishlist).

---

## WISH-02 Move wishlist → cart

`POST /wishlist/{productId}/move-to-cart` — Bearer + `active.user`

**Request (optional):** `{ "quantity": 1 }`

Atomic transaction:

1. Lock wishlist row
2. `CartService::addItem` (stock validated)
3. Delete wishlist row
4. Return `{ cart, product_id }`

If stock fails → rollback → wishlist item **kept**.

**Success 200**

```json
{
  "success": true,
  "message": "Moved to cart successfully",
  "data": {
    "cart": { "...": "full cart payload" },
    "product_id": 703
  }
}
```

---

## CONFIG — Free delivery threshold

`GET /app/config`

```json
{
  "commerce": {
    "free_delivery_threshold": 999,
    "currency": "INR"
  }
}
```

Cart responses already include computed `free_delivery`; config is for marketing banners.

---

## Guest → authenticated merge

On `POST /auth/login` or `POST /auth/register` with `X-Cart-Token`:

1. Guest cart lines merge into the user cart
2. Quantities capped by sellable stock
3. Guest cart retired

Never silently drop guest lines that can merge.

---

## Traceability

| Feature | Database | API | Website | Android |
|---------|----------|-----|---------|---------|
| Cart CRUD | `carts`, `cart_items` | `/cart*` | cart store + page | `CartProvider` + `CartScreen` |
| Coupon | `coupons` | apply/remove coupon | cart coupon form | cart coupon UI |
| Free delivery | env threshold | `free_delivery` on cart | cart + mini-cart + banner | cart + mini-cart |
| Cart → wishlist | `cart_items` + `wishlists` | `POST …/move-to-wishlist` | cart page | cart line action |
| Wishlist → cart | same | `POST /wishlist/{id}/move-to-cart` | wishlist page | wishlist screen |
| Checkout | orders module | `/checkout/preview` | checkout uses cart | checkout uses cart |

---

## Smoke test

```bash
php apps/nursery-api/scripts/phase_e_cart_smoke.php
```

Covers guest add, login merge, coupon (client discount ignored), client price ignored, move both ways, stock reject, clear.
