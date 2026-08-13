# QA-03 Report — Cart + Coupon + Total Consistency

**Date:** 2026-08-12  
**Status:** COMPLETE (PHPUnit PASS)

---

## 1. Objective

Unify free-delivery qualification between cart and checkout; normalize variant field naming toward API canonical `variant_id`.

## 2. Bugs addressed

| ID | Result |
|----|--------|
| QA-CART-001 | **FIXED / VERIFIED** — Cart free-delivery uses merchandise after discount (same as checkout) |
| QA-CART-002 | **FIXED / VERIFIED** — Clients send/read `variant_id`; API still accepts legacy `product_variant_id` |

## 3. Root causes

1. `CartService::present` called `freeDeliveryMeta($subtotal)` while `CheckoutService::buildTotals` used `subtotal − discount`.  
2. Web `cartService.addItem` posted `product_variant_id` while cart JSON returned `variant_id`.

## 4. Files changed

- `apps/nursery-api/app/Modules/Cart/Services/CartService.php`
- `apps/nursery-api/app/Modules/Cart/Http/Controllers/CartController.php` (comment)
- `apps/nursery-api/tests/Feature/Qa03CartTotalsTest.php`
- `apps/nursery-web/src/lib/services.ts`
- `apps/nursery-web/src/lib/types.ts`

## 5. API changes

- Cart `free_delivery` basis = merchandise after discount (no schema change)
- Add-item: `variant_id` canonical; `product_variant_id` retained as alias

## 6. Database changes

None.

## 7–8. Frontend / Mobile

- Customer Web uses `variant_id` on add-to-cart  
- Mobile already used product id only (no variant field mismatch)

## 9–11. Tests / results

| Test | Result |
|------|--------|
| `php artisan test --filter=Qa03CartTotalsTest` | **PASS** (3) — meta unit, cart↔checkout with FLAT100, variant alias |

## 12. Remaining issues

- Cart `shipping_total` remains `0` until checkout preview (by design; shipping resolved at checkout)  
- QA-CHK-001 (preview fallback on place-order) is QA-04

## 13. Risks

Low. Customers near the free-delivery threshold with coupons may see “remaining” increase after applying a discount — correct per business rule.

## 14. Next phase

**QA-04 — Checkout + Payment + Order Creation** (QA-CHK-001, QA-PAY-001, QA-PAY-002)
