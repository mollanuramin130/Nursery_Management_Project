-- =============================================================================
-- GreenLeaf Nursery — PHASE H database notes
-- Offers + Seasonal Campaigns + Find Your Plant
-- =============================================================================
--
-- AUDIT FINDING
--   No new core tables required.
--   Reuse:
--     campaigns, campaign_products, banners
--     products.price / compare_at_price (sale offers)
--     coupons (distinct from offers)
--     promotions (schema exists; runtime engine still future)
--     plant_profiles + tags / product_tags
--
-- Phase H is API + client work on top of existing schema.
-- Sample campaigns / plant traits already in nursery_sample_data.sql.
--
-- This file:
--   1) Optional index helpers
--   2) Verification SELECTs
--   3) Does NOT DROP / DELETE / invent fake paid data
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- Optional index (uncomment if missing):
-- ALTER TABLE plant_profiles ADD INDEX plant_profiles_pet_safety_index (pet_safety);

SELECT COUNT(*) AS campaigns_n FROM campaigns WHERE deleted_at IS NULL;
SELECT status, COUNT(*) AS n FROM campaigns WHERE deleted_at IS NULL GROUP BY status;
SELECT COUNT(*) AS campaign_products_n FROM campaign_products;
SELECT COUNT(*) AS sale_products_n
FROM products
WHERE deleted_at IS NULL
  AND status = 'active'
  AND compare_at_price IS NOT NULL
  AND compare_at_price > price;
SELECT COUNT(*) AS plant_profiles_n FROM plant_profiles;
SELECT indoor_outdoor, COUNT(*) AS n FROM plant_profiles GROUP BY indoor_outdoor;
SELECT sunlight, COUNT(*) AS n FROM plant_profiles GROUP BY sunlight;
SELECT difficulty_level, COUNT(*) AS n FROM plant_profiles GROUP BY difficulty_level;

SELECT id, slug, title, status, starts_at, ends_at, priority
FROM campaigns
WHERE deleted_at IS NULL
ORDER BY priority DESC
LIMIT 20;

SELECT 'phase_h_offers_campaigns_find_plant.sql verification complete' AS phase_h_note;
