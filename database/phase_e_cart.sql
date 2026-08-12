-- =============================================================================
-- GreenLeaf Nursery — PHASE E database notes
-- Cart + Coupon + Cart ↔ Wishlist
-- =============================================================================
--
-- AUDIT FINDING
--   No new tables required. Phase E reuses existing schema:
--     carts, cart_items, coupons, wishlists, products, inventory_items
--
--   Free-delivery threshold is configuration (env FREE_DELIVERY_THRESHOLD),
--   not a database column. Default: 999.
--
--   Full coupon seed data is in database/nursery_sample_data.sql
--   (WELCOME10, MONSOON10, FLAT50, GREEN15, …). There is no GREEN10 seed;
--   use WELCOME10 (10% off, min ₹299) for Phase E testing.
--
-- This file:
--   1) Documents authoritative table / index expectations
--   2) Optional verification SELECTs
--   3) Does NOT DROP/CREATE tables
--
-- HOW TO LOAD (optional)
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < database/phase_e_cart.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- Schema expectations (informational — already applied by Laravel migrations)
-- -----------------------------------------------------------------------------
-- carts:
--   id, user_id NULL, cart_token NULL, coupon_code NULL, currency, status, timestamps
--   UNIQUE-ish: one active cart per user_id; guest carts keyed by cart_token
--
-- cart_items:
--   id, cart_id, product_id, product_variant_id NULL, quantity, unit_price_snapshot
--
-- wishlists:
--   id, user_id, product_id — unique (user_id, product_id)
--
-- coupons:
--   code, type (percent|fixed), value, min_order_amount, max_discount,
--   starts_at, ends_at, usage_limit, per_user_limit, status
--   NOTE: usage / per-user limits are schema-ready but not fully enforced in API yet.
--
-- inventory_items:
--   sellable quantity used by CartService stock checks

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------

SELECT COUNT(*) AS cart_rows FROM carts;
SELECT COUNT(*) AS cart_item_rows FROM cart_items;
SELECT COUNT(*) AS wishlist_rows FROM wishlists WHERE deleted_at IS NULL OR deleted_at IS NOT NULL;
SELECT code, type, value, min_order_amount, max_discount, status
FROM coupons
WHERE deleted_at IS NULL OR deleted_at IS NOT NULL
ORDER BY id
LIMIT 20;

-- Guest vs user carts
SELECT
  SUM(user_id IS NULL) AS guest_carts,
  SUM(user_id IS NOT NULL) AS user_carts,
  SUM(coupon_code IS NOT NULL) AS carts_with_coupon
FROM carts
WHERE status = 'active';

-- Sample purchasable products for cart tests
SELECT id, slug, price, stock_status, status
FROM products
WHERE status = 'active' AND deleted_at IS NULL
ORDER BY id DESC
LIMIT 10;
