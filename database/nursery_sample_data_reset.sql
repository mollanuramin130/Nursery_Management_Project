-- =============================================================================
-- OPTIONAL DESTRUCTIVE RESET — local / staging ONLY
-- =============================================================================
-- WARNING: This DELETES sample-related table data (TRUNCATE).
-- Do NOT run on production.
--
-- Use only when you want a clean re-seed, then run:
--   mysql -u root -p nursery_local < nursery_sample_data.sql
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `support_messages`;
TRUNCATE TABLE `support_tickets`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `notification_templates`;
TRUNCATE TABLE `review_images`;
TRUNCATE TABLE `reviews`;
TRUNCATE TABLE `shipment_events`;
TRUNCATE TABLE `shipments`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `order_status_histories`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `cart_items`;
TRUNCATE TABLE `carts`;
TRUNCATE TABLE `wishlists`;
TRUNCATE TABLE `coupon_redemptions`;
TRUNCATE TABLE `campaign_products`;
TRUNCATE TABLE `promotion_products`;
TRUNCATE TABLE `product_bundle_items`;
TRUNCATE TABLE `product_bundles`;
TRUNCATE TABLE `product_relations`;
TRUNCATE TABLE `product_tags`;
TRUNCATE TABLE `product_categories`;
TRUNCATE TABLE `product_videos`;
TRUNCATE TABLE `product_images`;
TRUNCATE TABLE `attribute_values`;
TRUNCATE TABLE `stock_movements`;
TRUNCATE TABLE `inventory_items`;
TRUNCATE TABLE `purchase_order_items`;
TRUNCATE TABLE `purchase_orders`;
TRUNCATE TABLE `plant_profiles`;
TRUNCATE TABLE `fertilizer_profiles`;
TRUNCATE TABLE `pot_profiles`;
TRUNCATE TABLE `tool_profiles`;
TRUNCATE TABLE `soil_profiles`;
TRUNCATE TABLE `care_product_profiles`;
TRUNCATE TABLE `accessory_profiles`;
TRUNCATE TABLE `product_variants`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `tags`;
TRUNCATE TABLE `brands`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `banners`;
TRUNCATE TABLE `campaigns`;
TRUNCATE TABLE `promotions`;
TRUNCATE TABLE `coupons`;
TRUNCATE TABLE `shipping_methods`;
TRUNCATE TABLE `shipping_zones`;
TRUNCATE TABLE `tax_rates`;
TRUNCATE TABLE `warehouses`;
TRUNCATE TABLE `suppliers`;
TRUNCATE TABLE `recommendation_rules`;
TRUNCATE TABLE `attribute_definitions`;
TRUNCATE TABLE `schema_field_maps`;
TRUNCATE TABLE `settings`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `user_devices`;
TRUNCATE TABLE `customer_profiles`;
TRUNCATE TABLE `user_role`;
TRUNCATE TABLE `role_permission`;
TRUNCATE TABLE `refresh_tokens`;
TRUNCATE TABLE `api_outbound_logs`;
TRUNCATE TABLE `api_request_logs`;
TRUNCATE TABLE `webhook_inbox`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `permissions`;
TRUNCATE TABLE `roles`;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Reset complete. Now run nursery_sample_data.sql' AS status;
