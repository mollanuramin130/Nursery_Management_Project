-- =============================================================================
-- GreenLeaf Nursery — PHASE D database notes
-- Product Details + Wishlist + Reviews + Recommendations
-- =============================================================================
--
-- AUDIT FINDING
--   No new tables required. Phase D uses existing schema from migrations:
--     products, product_images, plant_profiles, product_relations,
--     recommendation_rules, wishlists, reviews, review_images
--
--   Full seed data is in database/nursery_sample_data.sql
--   (wishlists, approved+pending reviews, related/FBT links, reco rules).
--
-- This file:
--   1) Documents authoritative table names
--   2) Optional verification SELECTs
--   3) Does NOT DROP/CREATE tables
--
-- HOW TO LOAD (optional)
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < database/phase_d.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------

SELECT COUNT(*) AS wishlist_rows FROM wishlists WHERE deleted_at IS NULL;
SELECT status, COUNT(*) AS n FROM reviews WHERE deleted_at IS NULL GROUP BY status;
SELECT relation_type, COUNT(*) AS n FROM product_relations GROUP BY relation_type;
SELECT code, type, status FROM recommendation_rules WHERE deleted_at IS NULL OR deleted_at IS NOT NULL LIMIT 20;

-- Sample product with relations / reviews
SELECT p.id, p.slug, p.rating_avg, p.rating_count,
       (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved' AND r.deleted_at IS NULL) AS approved_reviews,
       (SELECT COUNT(*) FROM product_relations pr WHERE pr.product_id = p.id) AS relation_links
FROM products p
WHERE p.slug IN ('money-plant', 'snake-plant', 'areca-palm')
  AND p.deleted_at IS NULL;

-- =============================================================================
-- Done
-- =============================================================================
