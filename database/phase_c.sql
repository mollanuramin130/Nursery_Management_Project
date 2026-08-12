-- =============================================================================
-- GreenLeaf Nursery — PHASE C database changes
-- Home + Categories + Search + Filters + Product Listing
-- =============================================================================
--
-- FINDING (audit)
--   Existing catalog schema already supports Phase C discovery:
--   categories (hierarchy via parent_id), products, product_images,
--   plant_profiles (sunlight/water/difficulty/indoor_outdoor),
--   product_tags, campaigns, campaign_products, banners, inventory_items.
--
--   NO new tables are required for Phase C.
--
-- This file:
--   1) Documents existing discovery indexes (already created by migrations)
--   2) Applies safe stock/availability diversity for filter testing
--   3) Does NOT truncate or wipe catalog data
--
-- PREREQUISITE
--   1) Laravel migrations applied
--   2) Load database/nursery_sample_data.sql for full catalog seed
--
-- HOW TO LOAD
--   mysql -u root nursery_local < database/phase_c.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- 1) Existing indexes used by Phase C queries (reference only — already present)
-- -----------------------------------------------------------------------------
-- products: slug (unique), status, product_type, price, stock_status,
--           is_featured, is_new, published_at, rating_avg/rating_count
-- categories: slug (unique), parent_id, status, sort_order
-- plant_profiles: product_id, indoor_outdoor, sunlight, water_requirement,
--                 difficulty_level
-- banners: placement, status, sort_order
-- campaigns: slug, status, priority, season_code
--
-- Optional search helper index (create only if missing on your MySQL version):
-- CREATE INDEX products_name_idx ON products (name);
-- CREATE INDEX products_sku_idx ON products (sku);

-- -----------------------------------------------------------------------------
-- 2) Availability diversity for filter / listing QA
--    Safe UPDATEs — only touch known sample product IDs when present.
-- -----------------------------------------------------------------------------

-- Low stock samples
UPDATE products
SET stock_status = 'low_stock', updated_at = NOW()
WHERE id IN (115, 118)
  AND status = 'active';

-- Out of stock sample (listing must disable Add to Cart)
UPDATE products
SET stock_status = 'out_of_stock', updated_at = NOW()
WHERE id IN (128)
  AND status = 'active';

-- Keep inventory rows consistent with out-of-stock product when present
UPDATE inventory_items
SET qty_on_hand = 0, qty_reserved = 0, updated_at = NOW()
WHERE product_id = 128;

UPDATE inventory_items
SET qty_on_hand = 3, low_stock_threshold = 5, updated_at = NOW()
WHERE product_id IN (115, 118);

-- -----------------------------------------------------------------------------
-- 3) Ensure home banners use campaign / category / product link types
--    (idempotent — only corrects known Phase C demo banners)
-- -----------------------------------------------------------------------------
UPDATE banners
SET link_type = 'campaign',
    link_value = 'monsoon-plants-2026',
    updated_at = NOW()
WHERE id = 1 AND title = 'Monsoon Special';

UPDATE banners
SET link_type = 'category',
    link_value = 'indoor-plants',
    updated_at = NOW()
WHERE id = 2 AND title = 'Indoor Jungle';

-- -----------------------------------------------------------------------------
-- Done. Full product/category sample catalog remains in nursery_sample_data.sql
-- =============================================================================
