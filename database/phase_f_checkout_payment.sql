-- =============================================================================
-- GreenLeaf Nursery — PHASE F database notes
-- Checkout + Razorpay Payment + Order Success
-- =============================================================================
--
-- AUDIT FINDING
--   Core tables already exist from Laravel migrations:
--     orders, order_items, order_status_histories, payments,
--     shipping_methods, coupons, carts, cart_items, addresses, inventory_*
--
-- Phase F additive migration (Laravel):
--   apps/nursery-api/database/migrations/2026_08_11_180000_phase_f_checkout_payment.php
--     + coupon_redemptions
--     + UNIQUE payments.provider_payment_id
--
-- This SQL file:
--   1) Documents schema expectations
--   2) Optional verification SELECTs
--   3) Does NOT DROP tables / does NOT invent fake "paid" production payments
--
-- HOW TO APPLY
--   Prefer: cd apps/nursery-api && php artisan migrate
--   Optional verify:
--     /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < database/phase_f_checkout_payment.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- Ensure coupon_redemptions exists (safe if migration already ran)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  coupon_code VARCHAR(40) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY coupon_redemptions_order_id_unique (order_id),
  KEY coupon_redemptions_coupon_id_user_id_index (coupon_id, user_id),
  CONSTRAINT coupon_redemptions_coupon_id_foreign FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
  CONSTRAINT coupon_redemptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT coupon_redemptions_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS orders_n FROM orders;
SELECT status, COUNT(*) AS n FROM orders GROUP BY status;
SELECT status, COUNT(*) AS n FROM payments GROUP BY status;
SELECT COUNT(*) AS coupon_redemptions_n FROM coupon_redemptions;
SELECT id, code, name, price, status FROM shipping_methods WHERE deleted_at IS NULL OR deleted_at IS NOT NULL LIMIT 20;

-- Recent orders (read-only check — no fake payment inserts)
SELECT id, order_number, status, payment_method, grand_total, coupon_code, placed_at
FROM orders
ORDER BY id DESC
LIMIT 10;
