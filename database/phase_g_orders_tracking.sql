-- =============================================================================
-- GreenLeaf Nursery — PHASE G database notes
-- Orders + Tracking + Cancel + Reorder
-- =============================================================================
--
-- AUDIT FINDING
--   No new core tables required for Phase G.
--   Existing (Laravel migrations / prior phases):
--     orders
--     order_items                 -- historical name/price/qty snapshots
--     order_status_histories      -- immutable transitions (server-written)
--     payments
--     shipments                   -- carrier / tracking / eta
--     coupon_redemptions
--     inventory_* / carts / products
--
-- Phase G application changes are API + clients only:
--   - list filters/search + can_cancel / can_reorder
--   - GET /orders/{id}/tracking
--   - cancel reason codes + refund_pending / coupon restore / restock
--   - POST /orders/{id}/reorder → cart
--
-- This SQL file:
--   1) Documents expected indexes / columns
--   2) Optional read-only verification
--   3) Optional DEV-ONLY sample status history helpers (commented)
--   4) Does NOT DROP tables / DELETE orders / fake paid production payments
--
-- HOW TO APPLY
--   Prefer: no migration required if schema already present.
--   Verify:
--     /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < database/phase_g_orders_tracking.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- Safe index helpers (no-op if already present on your MySQL version —
-- run ALTER only after checking INFORMATION_SCHEMA if needed)
-- -----------------------------------------------------------------------------
-- Recommended (usually already created by Laravel):
--   INDEX orders (user_id, status, created_at)
--   INDEX order_status_histories (order_id, created_at)
--   INDEX shipments (order_id)

-- Example safe add (uncomment if missing):
-- ALTER TABLE orders ADD INDEX orders_user_status_created_idx (user_id, status, created_at);

-- -----------------------------------------------------------------------------
-- Verification (read-only)
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS orders_n FROM orders;
SELECT status, COUNT(*) AS n FROM orders GROUP BY status ORDER BY n DESC;
SELECT status, COUNT(*) AS n FROM payments GROUP BY status;
SELECT COUNT(*) AS histories_n FROM order_status_histories;
SELECT COUNT(*) AS shipments_n FROM shipments;

SELECT o.id, o.order_number, o.status, o.payment_method, o.grand_total, o.cancel_reason, o.cancelled_at
FROM orders o
ORDER BY o.id DESC
LIMIT 15;

SELECT h.order_id, h.from_status, h.to_status, h.note, h.created_at
FROM order_status_histories h
ORDER BY h.id DESC
LIMIT 20;

SELECT s.order_id, s.status, s.carrier, s.tracking_number, s.eta_date
FROM shipments s
ORDER BY s.id DESC
LIMIT 10;

-- -----------------------------------------------------------------------------
-- DEV SAMPLE DATA (OPTIONAL — DO NOT RUN IN PRODUCTION)
-- Creates illustrative multi-status orders only when you explicitly uncomment
-- and substitute real @user_id / @product_id / @warehouse_id values.
-- Prefer placing real COD/online orders via API smoke scripts instead.
-- -----------------------------------------------------------------------------
/*
-- SET @user_id = 1;
-- SET @product_id = 1;
-- SET @warehouse_id = 1;

-- Example: leave as documentation only.
-- INSERT INTO orders (...);
-- INSERT INTO order_items (...);
-- INSERT INTO order_status_histories (...);
*/

SELECT 'phase_g_orders_tracking.sql verification complete' AS phase_g_note;
