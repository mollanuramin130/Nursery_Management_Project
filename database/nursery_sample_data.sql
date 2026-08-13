-- =============================================================================
-- Nursery Platform — Professional Sample Data (SAFE INSERTS)
-- =============================================================================
-- Companion to: DATABASE_DESIGN.md + PROJECT_DEVELOPMENT_GUIDE.md
--
-- PURPOSE
--   Seed realistic data so website / Android / iOS / API look production-like.
--
-- SAFE BEHAVIOR
--   All inserts use: INSERT IGNORE INTO ...
--   → If the row (same PRIMARY KEY / UNIQUE key) already exists → SKIP
--   → If the row does not exist → INSERT
--   → No TRUNCATE / DELETE of your existing data
--
-- PREREQUISITE
--   1) Create MySQL database (e.g. nursery_local)
--   2) Run Laravel migrations so all tables exist
--   3) Load this file (safe to run multiple times)
--
-- HOW TO LOAD
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < /Users/nuramin/Desktop/Nursery_Platform/database/nursery_sample_data.sql
--
--   Or inside mysql:
--   USE nursery_local;
--   SOURCE /Users/nuramin/Desktop/Nursery_Platform/database/nursery_sample_data.sql;
--
-- =============================================================================
-- ALL SAMPLE USER LOGINS (DO NOT FORGET)
-- Login field = email (username). Password is the SAME for every sample user.
-- Password for ALL users below:  Secret@123
-- Also listed in: SAMPLE_LOGIN_CREDENTIALS.md
-- =============================================================================
--
-- | ID | Name                 | Email (username)           | Password     | Role               | Phone       |
-- |----|----------------------|----------------------------|--------------|--------------------|-------------|
-- |  1 | Nursery Super Admin  | superadmin@nursery.test    | Secret@123   | super_admin        | 9000000001  |
-- |  2 | Ops Admin            | admin@nursery.test         | Secret@123   | admin              | 9000000002  |
-- |  3 | Asha Kumar           | asha@example.com           | Secret@123   | customer           | 9876543210  |
-- |  4 | Ravi Sharma          | ravi@example.com           | Secret@123   | customer           | 9876543211  |
-- |  5 | Meera Patel          | meera@example.com          | Secret@123   | customer           | 9876543212  |
-- |  6 | Kabir Singh          | kabir@example.com          | Secret@123   | customer           | 9876543213  |
-- |  7 | Sneha Reddy          | sneha@example.com          | Secret@123   | customer           | 9876543214  |
-- |  8 | Arjun Mehta          | arjun@example.com          | Secret@123   | customer           | 9876543215  |
-- |  9 | Priya Nair           | priya@example.com          | Secret@123   | customer           | 9876543216  |
-- | 10 | Vikram Joshi         | vikram@example.com         | Secret@123   | customer           | 9876543217  |
-- | 11 | Inventory Manager    | inventory@nursery.test     | Secret@123   | inventory_manager  | 9000000003  |
-- | 12 | Order Manager        | orders@nursery.test        | Secret@123   | order_manager      | 9000000004  |
--
-- Quick copy (email / password):
--   superadmin@nursery.test / Secret@123
--   admin@nursery.test      / Secret@123
--   asha@example.com        / Secret@123
--   ravi@example.com        / Secret@123
--   meera@example.com       / Secret@123
--   kabir@example.com       / Secret@123
--   sneha@example.com       / Secret@123
--   arjun@example.com       / Secret@123
--   priya@example.com       / Secret@123
--   vikram@example.com      / Secret@123
--   inventory@nursery.test  / Secret@123
--   orders@nursery.test     / Secret@123
--
-- Image URLs use public placeholder CDN links (replace with your CDN later).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- SAFE MODE (default): insert only if row does not already exist
--   - INSERT IGNORE  → skip when PRIMARY/UNIQUE key already exists
--   - Re-run this file anytime without wiping your DB
--   - Does NOT delete or truncate existing data
--
-- OPTIONAL RESET (local/staging only): uncomment the block in
--   nursery_sample_data_reset.sql if you ever need a full wipe.
-- -----------------------------------------------------------------------------

-- =============================================================================
-- 1) ROLES & PERMISSIONS
-- =============================================================================
INSERT IGNORE INTO `roles` (`id`,`name`,`slug`,`description`,`created_at`,`updated_at`) VALUES
(1,'Super Admin','super_admin','Full system access',NOW(),NOW()),
(2,'Admin','admin','Business operations',NOW(),NOW()),
(3,'Nursery Manager','nursery_manager','Catalog & plant content',NOW(),NOW()),
(4,'Inventory Manager','inventory_manager','Stock & warehouses',NOW(),NOW()),
(5,'Order Manager','order_manager','Order lifecycle',NOW(),NOW()),
(6,'Customer Support','customer_support','Tickets & help',NOW(),NOW()),
(7,'Content Manager','content_manager','Banners & campaigns',NOW(),NOW()),
(8,'Customer','customer','Storefront customer',NOW(),NOW());

INSERT IGNORE INTO `permissions` (`id`,`name`,`slug`,`description`,`created_at`,`updated_at`) VALUES
(1,'Products Read','products.read','View products',NOW(),NOW()),
(2,'Products Write','products.write','Create/update products',NOW(),NOW()),
(3,'Products Publish','products.publish','Publish products',NOW(),NOW()),
(4,'Inventory View','inventory.view','View stock',NOW(),NOW()),
(5,'Inventory Adjust','inventory.adjust','Adjust stock',NOW(),NOW()),
(6,'Orders View','orders.view','View orders',NOW(),NOW()),
(7,'Orders Update Status','orders.update_status','Change order status',NOW(),NOW()),
(8,'Orders Cancel','orders.cancel','Cancel orders',NOW(),NOW()),
(9,'Payments Refund','payments.refund','Issue refunds',NOW(),NOW()),
(10,'Campaigns Manage','campaigns.manage','Campaigns/coupons/banners',NOW(),NOW()),
(11,'Users Manage','users.manage','Manage users/roles',NOW(),NOW()),
(12,'Reports View','reports.view','View reports/dashboard',NOW(),NOW());

-- super_admin → all permissions
INSERT IGNORE INTO `role_permission` (`id`,`role_id`,`permission_id`,`created_at`,`updated_at`)
SELECT NULL, 1, p.id, NOW(), NOW() FROM `permissions` p;

-- admin → most
INSERT IGNORE INTO `role_permission` (`role_id`,`permission_id`,`created_at`,`updated_at`) VALUES
(2,1,NOW(),NOW()),(2,2,NOW(),NOW()),(2,3,NOW(),NOW()),(2,4,NOW(),NOW()),
(2,5,NOW(),NOW()),(2,6,NOW(),NOW()),(2,7,NOW(),NOW()),(2,8,NOW(),NOW()),
(2,9,NOW(),NOW()),(2,10,NOW(),NOW()),(2,12,NOW(),NOW());

-- =============================================================================
-- 2) USERS (password for ALL = Secret@123)
-- =============================================================================
-- bcrypt: $2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2

INSERT IGNORE INTO `users` (`id`,`name`,`email`,`phone`,`password`,`status`,`email_verified_at`,`phone_verified_at`,`last_login_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,'Nursery Super Admin','superadmin@nursery.test','9000000001','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NOW(),JSON_OBJECT('title','Founder'),NOW(),NOW(),NULL),
(2,'Ops Admin','admin@nursery.test','9000000002','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NOW(),NULL,NOW(),NOW(),NULL),
(3,'Asha Kumar','asha@example.com','9876543210','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NOW(),JSON_OBJECT('preferred_language','en'),NOW(),NOW(),NULL),
(4,'Ravi Sharma','ravi@example.com','9876543211','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL),
(5,'Meera Patel','meera@example.com','9876543212','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `user_role` (`user_id`,`role_id`,`created_at`,`updated_at`) VALUES
(1,1,NOW(),NOW()),
(2,2,NOW(),NOW()),
(3,8,NOW(),NOW()),
(4,8,NOW(),NOW()),
(5,8,NOW(),NOW());

INSERT IGNORE INTO `customer_profiles` (`id`,`user_id`,`date_of_birth`,`gender`,`preferred_language`,`marketing_opt_in`,`default_address_id`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,'1994-05-12','female','en',1,NULL,NULL,NOW(),NOW(),NULL),
(2,4,'1990-11-02','male','en',1,NULL,NULL,NOW(),NOW(),NULL),
(3,5,'1998-01-20','female','hi',1,NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `addresses` (`id`,`user_id`,`label`,`name`,`phone`,`line1`,`line2`,`city`,`state`,`postal_code`,`country`,`is_default`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,'Home','Asha Kumar','9876543210','12 Green Street','Near City Park','Pune','Maharashtra','411001','IN',1,NULL,NOW(),NOW(),NULL),
(2,3,'Office','Asha Kumar','9876543210','Tech Park Tower B','Floor 4','Pune','Maharashtra','411057','IN',0,NULL,NOW(),NOW(),NULL),
(3,4,'Home','Ravi Sharma','9876543211','88 Lake View Road',NULL,'Bengaluru','Karnataka','560001','IN',1,NULL,NOW(),NOW(),NULL),
(4,5,'Home','Meera Patel','9876543212','15 Garden Lane','Satellite','Ahmedabad','Gujarat','380015','IN',1,NULL,NOW(),NOW(),NULL);

UPDATE `customer_profiles` SET `default_address_id` = 1 WHERE `id` = 1 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 1);
UPDATE `customer_profiles` SET `default_address_id` = 3 WHERE `id` = 2 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 3);
UPDATE `customer_profiles` SET `default_address_id` = 4 WHERE `id` = 3 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 4);

INSERT IGNORE INTO `user_devices` (`id`,`user_id`,`platform`,`device_id`,`push_token`,`app_version`,`is_active`,`last_seen_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,'android','asha-android-001','fcm_sample_token_asha_android','1.0.0',1,NOW(),NULL,NOW(),NOW(),NULL),
(2,3,'ios','asha-ios-001','apns_sample_token_asha_ios','1.0.0',1,NOW(),NULL,NOW(),NOW(),NULL),
(3,4,'android','ravi-android-001','fcm_sample_token_ravi','1.0.0',1,NOW(),NULL,NOW(),NOW(),NULL);

-- =============================================================================
-- 3) SETTINGS / TAX / SHIPPING / WAREHOUSE / SUPPLIER
-- =============================================================================
INSERT IGNORE INTO `settings` (`id`,`key`,`value`,`type`,`group_name`,`meta`,`created_at`,`updated_at`) VALUES
(1,'store.name','GreenLeaf Nursery','string','store',NULL,NOW(),NOW()),
(2,'store.currency','INR','string','store',NULL,NOW(),NOW()),
(3,'store.support_email','support@nursery.test','string','store',NULL,NOW(),NOW()),
(4,'store.support_phone','+919876543210','string','store',NULL,NOW(),NOW()),
(5,'api.request_log_enabled','true','bool','api',NULL,NOW(),NOW()),
(6,'feature.cod_enabled','true','bool','checkout',NULL,NOW(),NOW()),
(7,'feature.wishlist_enabled','true','bool','catalog',NULL,NOW(),NOW()),
(8,'app.min_android_version','1.0.0','string','app',NULL,NOW(),NOW()),
(9,'app.min_ios_version','1.0.0','string','app',NULL,NOW(),NOW()),
(10,'app.force_update','false','bool','app',NULL,NOW(),NOW());

INSERT IGNORE INTO `tax_rates` (`id`,`name`,`rate_percent`,`country`,`state`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,'India GST Standard',5.00,'IN',NULL,'active',NULL,NOW(),NOW(),NULL),
(2,'Maharashtra GST',5.00,'IN','Maharashtra','active',NULL,NOW(),NOW(),NULL);

-- If tax_rates columns differ slightly in your migration, adjust names to match.
-- Expected flexible shape used here: name, rate_percent, country, state, status, meta, timestamps, deleted_at

INSERT IGNORE INTO `shipping_zones` (`id`,`name`,`country`,`states_json`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`code`) VALUES
(1,'Pan India','IN',JSON_ARRAY('Maharashtra','Karnataka','Gujarat','Delhi','Tamil Nadu'),'active',NULL,NOW(),NOW(),NULL,'IN_ALL');

INSERT IGNORE INTO `shipping_methods` (`id`,`code`,`name`,`price`,`currency`,`eta_min_days`,`eta_max_days`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,'standard','Standard Delivery',49.00,'INR',3,5,'active',JSON_OBJECT('zone','IN_ALL'),NOW(),NOW(),NULL),
(2,'express','Express Delivery',99.00,'INR',1,2,'active',JSON_OBJECT('zone','IN_ALL'),NOW(),NOW(),NULL),
(3,'pickup','Store Pickup',0.00,'INR',0,1,'active',JSON_OBJECT('note','Pune nursery pickup'),NOW(),NOW(),NULL);

INSERT IGNORE INTO `warehouses` (`id`,`code`,`name`,`city`,`is_default`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,'WH-PUNE','Pune Main Nursery','Pune',1,'active',JSON_OBJECT('address','Nursery Road, Pune'),NOW(),NOW(),NULL),
(2,'WH-BLR','Bengaluru Hub','Bengaluru',0,'active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `suppliers` (`id`,`code`,`name`,`email`,`phone`,`city`,`state`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`contact_person`,`gstin`) VALUES
(1,'SUP-GREEN','Green Valley Farms','supply@greenvalley.test','9800000001','Nashik','Maharashtra','active',NULL,NOW(),NOW(),NULL,'Suresh Patil','27AAAAA0000A1Z5'),
(2,'SUP-POTS','ClayCraft India','orders@claycraft.test','9800000002','Morbi','Gujarat','active',NULL,NOW(),NOW(),NULL,'Anil Shah','24BBBBB0000B1Z5');

-- =============================================================================
-- 4) CATEGORIES / BRANDS / TAGS
-- =============================================================================
INSERT IGNORE INTO `categories` (`id`,`parent_id`,`name`,`slug`,`image_url`,`sort_order`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`description`) VALUES
(1,NULL,'Indoor Plants','indoor-plants','https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=800',1,'active',NULL,NOW(),NOW(),NULL,'Best plants for homes and offices'),
(2,NULL,'Outdoor Plants','outdoor-plants','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800',2,'active',NULL,NOW(),NOW(),NULL,'Garden and balcony plants'),
(3,NULL,'Flowering Plants','flowering-plants','https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800',3,'active',NULL,NOW(),NOW(),NULL,'Seasonal blooms'),
(4,NULL,'Fruit Plants','fruit-plants','https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=800',4,'active',NULL,NOW(),NOW(),NULL,'Fruit-bearing plants & trees'),
(5,NULL,'Vegetable Plants','vegetable-plants','https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=800',5,'active',NULL,NOW(),NOW(),NULL,'Kitchen garden starters'),
(6,NULL,'Seeds','seeds','https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=800',6,'active',NULL,NOW(),NOW(),NULL,'Vegetable and flower seeds'),
(7,NULL,'Pots & Planters','pots-planters','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=800',7,'active',NULL,NOW(),NOW(),NULL,'Ceramic, plastic and terracotta'),
(8,NULL,'Soil & Fertilizers','soil-fertilizers','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800',8,'active',NULL,NOW(),NOW(),NULL,'Potting mix and plant food'),
(9,NULL,'Gardening Tools','gardening-tools','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800',9,'active',NULL,NOW(),NOW(),NULL,'Essential tools'),
(10,NULL,'Plant Care','plant-care','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800',10,'active',NULL,NOW(),NOW(),NULL,'Care products'),
(11,1,'Low Maintenance','low-maintenance','https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=800',1,'active',NULL,NOW(),NOW(),NULL,'Beginner friendly indoor plants'),
(12,1,'Air Purifying','air-purifying','https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=800',2,'active',NULL,NOW(),NOW(),NULL,'Cleaner indoor air'),
(13,2,'Balcony Specials','balcony-specials','https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=800',1,'active',NULL,NOW(),NOW(),NULL,'Compact outdoor plants');

INSERT IGNORE INTO `brands` (`id`,`name`,`slug`,`logo_url`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`description`) VALUES
(1,'GreenLeaf','greenleaf','https://placehold.co/200x80/2d6a4f/ffffff?text=GreenLeaf','active',NULL,NOW(),NOW(),NULL,'House brand plants and care'),
(2,'UrbanJungle','urbanjungle','https://placehold.co/200x80/40916c/ffffff?text=UrbanJungle','active',NULL,NOW(),NOW(),NULL,'Modern indoor plant collection'),
(3,'ClayCraft','claycraft','https://placehold.co/200x80/b08968/ffffff?text=ClayCraft','active',NULL,NOW(),NOW(),NULL,'Premium pots and planters'),
(4,'SoilMate','soilmate','https://placehold.co/200x80/606c38/ffffff?text=SoilMate','active',NULL,NOW(),NOW(),NULL,'Organic soils and fertilizers');

INSERT IGNORE INTO `tags` (`id`,`name`,`slug`,`created_at`,`updated_at`,`deleted_at`,`status`) VALUES
(1,'Beginner','beginner',NOW(),NOW(),NULL,'active'),
(2,'Air Purifying','air-purifying',NOW(),NOW(),NULL,'active'),
(3,'Low Maintenance','low-maintenance',NOW(),NOW(),NULL,'active'),
(4,'Pet Caution','pet-caution',NOW(),NOW(),NULL,'active'),
(5,'Monsoon','monsoon',NOW(),NOW(),NULL,'active'),
(6,'Bestseller','bestseller',NOW(),NOW(),NULL,'active'),
(7,'New Arrival','new-arrival',NOW(),NOW(),NULL,'active'),
(8,'Balcony','balcony',NOW(),NOW(),NULL,'active');

-- =============================================================================
-- 5) PRODUCTS (plants + pot + fertilizer + soil + tool)
-- =============================================================================
INSERT IGNORE INTO `products`
(`id`,`brand_id`,`product_type`,`name`,`slug`,`sku`,`description`,`price`,`compare_at_price`,`currency`,`status`,`stock_status`,`is_featured`,`is_new`,`rating_avg`,`rating_count`,`published_at`,`meta`,`created_at`,`updated_at`,`deleted_at`,`tax_class`)
VALUES
(101,2,'plant','Money Plant','money-plant','PLT-MONEY-001','Popular trailing indoor plant. Easy to grow, great for beginners and offices.',299.00,349.00,'INR','active','in_stock',1,0,4.60,128,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','low_maintenance')),NOW(),NOW(),NULL,'gst5'),
(102,2,'plant','Snake Plant','snake-plant','PLT-SNAKE-001','Hardy indoor plant that thrives in low light and needs infrequent watering.',399.00,449.00,'INR','active','in_stock',1,0,4.70,210,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','air-purifying')),NOW(),NOW(),NULL,'gst5'),
(103,1,'plant','Peace Lily','peace-lily','PLT-PEACE-001','Elegant flowering indoor plant with glossy leaves and white blooms.',499.00,599.00,'INR','active','in_stock',1,1,4.50,86,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(104,1,'plant','Areca Palm','areca-palm','PLT-ARECA-001','Graceful indoor palm that adds tropical vibes and helps purify air.',799.00,899.00,'INR','active','in_stock',1,0,4.40,64,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(105,1,'plant','Jade Plant','jade-plant','PLT-JADE-001','Lucky succulent, perfect for desks and bright windowsills.',349.00,399.00,'INR','active','in_stock',0,0,4.55,92,NOW(),JSON_OBJECT('badges',JSON_ARRAY('beginner')),NOW(),NOW(),NULL,'gst5'),
(106,1,'plant','Rose Plant (Red)','rose-plant-red','PLT-ROSE-RED-001','Classic red rose for balconies and gardens. Seasonal flowering beauty.',449.00,499.00,'INR','active','in_stock',1,0,4.30,140,NOW(),JSON_OBJECT('badges',JSON_ARRAY('monsoon','balcony')),NOW(),NOW(),NULL,'gst5'),
(107,1,'plant','Tulsi (Holy Basil)','tulsi-holy-basil','PLT-TULSI-001','Sacred medicinal herb for home gardens. Aromatic and useful daily.',199.00,249.00,'INR','active','in_stock',1,0,4.80,320,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller')),NOW(),NOW(),NULL,'gst5'),
(108,1,'plant','Tomato Plant','tomato-plant','PLT-TOMATO-001','Kitchen-garden tomato starter plant for balconies and terraces.',149.00,179.00,'INR','active','in_stock',0,1,4.20,45,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','balcony')),NOW(),NOW(),NULL,'gst5'),
(109,1,'tree','Mango Sapling','mango-sapling','TRE-MANGO-001','Grafted mango sapling for home gardens. Fruiting in future seasons.',899.00,999.00,'INR','active','in_stock',1,0,4.10,38,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(110,1,'seed','Marigold Seeds Pack','marigold-seeds-pack','SED-MARI-001','Bright marigold seeds for borders and festive gardens.',79.00,99.00,'INR','active','in_stock',0,1,4.25,55,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','monsoon')),NOW(),NOW(),NULL,'gst5'),
(201,3,'pot','Ceramic Pot 6 inch','ceramic-pot-6-inch','POT-CER-6','Premium ceramic planter with drainage hole. Ideal for Money Plant & Snake Plant.',249.00,299.00,'INR','active','in_stock',1,0,4.45,77,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(202,3,'pot','Terracotta Pot 8 inch','terracotta-pot-8-inch','POT-TER-8','Breathable terracotta pot for outdoor and balcony plants.',199.00,229.00,'INR','active','in_stock',0,0,4.35,61,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(301,4,'fertilizer','Organic Vermicompost 1kg','organic-vermicompost-1kg','FER-VERMI-1','Nutrient-rich organic vermicompost for all plants.',149.00,179.00,'INR','active','in_stock',1,0,4.65,190,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller')),NOW(),NOW(),NULL,'gst5'),
(302,4,'fertilizer','NPK 20-20-20 Plant Food 500g','npk-20-20-20-500g','FER-NPK-202020','Balanced NPK fertilizer for leafy growth and overall plant health.',199.00,249.00,'INR','active','in_stock',0,0,4.40,88,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(401,4,'soil','Cocopeat Block 5kg','cocopeat-block-5kg','SOL-COCO-5','Expanded cocopeat for potting mixes. Excellent water retention.',179.00,199.00,'INR','active','in_stock',1,0,4.50,112,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(402,4,'soil','Potting Mix 10kg','potting-mix-10kg','SOL-MIX-10','Ready-to-use potting mix for indoor and outdoor plants.',299.00,349.00,'INR','active','in_stock',1,0,4.55,150,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller')),NOW(),NOW(),NULL,'gst5'),
(501,1,'tool','Garden Pruner','garden-pruner','TOL-PRUN-01','Sharp bypass pruner for clean cuts on stems and light branches.',349.00,399.00,'INR','active','in_stock',0,0,4.20,40,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(601,1,'accessory','Plant Support Stick Pack','plant-support-stick-pack','ACC-SUP-01','Set of support sticks for climbers like Money Plant.',99.00,129.00,'INR','active','in_stock',0,0,4.10,33,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(701,1,'bundle','Indoor Starter Kit','indoor-starter-kit','BND-INDOOR-01','Money Plant + Ceramic Pot 6 inch + Vermicompost — perfect starter combo.',649.00,747.00,'INR','active','in_stock',1,1,4.75,28,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','bestseller')),NOW(),NOW(),NULL,'gst5');

-- Plant profiles
INSERT IGNORE INTO `plant_profiles`
(`id`,`product_id`,`common_name`,`scientific_name`,`local_names`,`plant_kind`,`indoor_outdoor`,`sunlight`,`water_requirement`,`soil_type`,
 `temperature_min_c`,`temperature_max_c`,`humidity_requirement`,`growth_rate`,`mature_height_cm`,`mature_width_cm`,
 `flowering_season`,`fruiting_season`,`planting_season`,`bloom_color`,`flowering_duration`,`lifespan`,
 `difficulty_level`,`care_level`,`propagation_method`,`toxicity_info`,`pet_safety`,`benefits`,`uses`,
 `growing_instructions`,`planting_instructions`,`pruning_instructions`,`fertilization_instructions`,`pest_disease_info`,`harvest_info`,
 `meta`,`created_at`,`updated_at`)
VALUES
(1,101,'Money Plant','Epipremnum aureum',JSON_OBJECT('hi','मनी प्लांट','mr','मनी प्लांट'),'climber','indoor','bright_indirect','medium','well_draining',
 18.00,30.00,'medium','fast',200,60,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','cutting','Mildly toxic if ingested','toxic',JSON_ARRAY('Air purifying','Easy propagation'),JSON_ARRAY('ornamental'),
 'Place in bright indirect light. Avoid harsh afternoon sun.','Use well-draining potting mix and a pot with drainage holes.','Trim long vines to encourage bushier growth.','Feed monthly in growing season with mild fertilizer.','Watch for mealybugs and spider mites; wipe leaves regularly.',NULL,
 NULL,NOW(),NOW()),
(2,102,'Snake Plant','Sansevieria trifasciata',JSON_OBJECT('hi','सेंसेविरिया'),'succulent','indoor','low','low','well_draining',
 15.00,32.00,'low','slow',120,40,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','division','Mildly toxic if ingested','toxic',JSON_ARRAY('Air purifying','Low water needs'),JSON_ARRAY('ornamental'),
 'Thrives in low to bright indirect light. Do not overwater.','Plant in sandy, well-draining mix.','Remove damaged leaves at the base.','Fertilize lightly 2–3 times a year.','Root rot from overwatering is the main risk.',NULL,
 NULL,NOW(),NOW()),
(3,103,'Peace Lily','Spathiphyllum wallisii',JSON_OBJECT('hi','पीस लिली'),'flowering','indoor','bright_indirect','medium','well_draining',
 18.00,28.00,'high','medium',60,50,
 JSON_ARRAY('spring','summer'),JSON_ARRAY(),JSON_ARRAY('year_round'),'white','several weeks','perennial',
 'easy','medium','division','Toxic if ingested','toxic',JSON_ARRAY('Air purifying','Attractive blooms'),JSON_ARRAY('ornamental'),
 'Keep soil lightly moist; drooping leaves usually mean it needs water.','Use rich, well-draining indoor mix.','Remove spent blooms and yellow leaves.','Feed every 6 weeks in growing season.','Watch for tip burn from fluoride in water.',NULL,
 NULL,NOW(),NOW()),
(4,104,'Areca Palm','Dypsis lutescens',JSON_OBJECT('hi','अरेका पाम'),'palm','indoor','bright_indirect','medium','well_draining',
 18.00,30.00,'high','medium',180,100,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'moderate','medium','division','Generally considered safer','safe',JSON_ARRAY('Air purifying','Tropical look'),JSON_ARRAY('ornamental'),
 'Needs bright indirect light and regular misting in dry rooms.','Use peat-based mix with good drainage.','Trim brown frond tips as needed.','Feed monthly in spring and summer.','Spider mites in dry air; increase humidity.',NULL,
 NULL,NOW(),NOW()),
(5,105,'Jade Plant','Crassula ovata',JSON_OBJECT('hi','जेड प्लांट'),'succulent','indoor','full_sun','low','sandy',
 15.00,30.00,'low','slow',90,60,
 JSON_ARRAY('winter'),JSON_ARRAY(),JSON_ARRAY('year_round'),'pink',NULL,'perennial',
 'easy','low','cutting','Mildly toxic','toxic',JSON_ARRAY('Lucky plant','Drought tolerant'),JSON_ARRAY('ornamental'),
 'Needs bright light; water only when soil is dry.','Use cactus/succulent mix.','Pinch tips to shape.','Fertilize sparingly in growing season.','Overwatering causes stem rot.',NULL,
 NULL,NOW(),NOW()),
(6,106,'Rose','Rosa indica',JSON_OBJECT('hi','गुलाब'),'shrub','outdoor','full_sun','medium','loamy',
 10.00,35.00,'medium','medium',120,90,
 JSON_ARRAY('winter','spring'),JSON_ARRAY(),JSON_ARRAY('monsoon','winter'),'red','seasonal','perennial',
 'moderate','medium','cutting','Thorns; petals edible varieties vary','unknown',JSON_ARRAY('Fragrance','Cut flowers'),JSON_ARRAY('ornamental'),
 'Needs 5–6 hours of direct sun and good air circulation.','Plant in rich loamy soil with compost.','Prune after flowering flush.','Feed rose fertilizer every 3–4 weeks in season.','Watch for aphids, black spot; avoid wet foliage overnight.',NULL,
 NULL,NOW(),NOW()),
(7,107,'Tulsi','Ocimum tenuiflorum',JSON_OBJECT('hi','तुलसी'),'herb','outdoor','full_sun','medium','well_draining',
 15.00,35.00,'medium','fast',60,40,
 JSON_ARRAY('summer'),JSON_ARRAY(),JSON_ARRAY('monsoon','summer'),'purple',NULL,'perennial',
 'easy','low','seed','Culinary/medicinal herb','safe',JSON_ARRAY('Medicinal','Aromatic'),JSON_ARRAY('medicinal','culinary'),
 'Keep in full sun; pinch tips for bushier growth.','Use potting mix with compost; ensure drainage.','Harvest leaves regularly to encourage growth.','Feed organic compost monthly.','Avoid waterlogging.',NULL,
 NULL,NOW(),NOW()),
(8,108,'Tomato','Solanum lycopersicum',JSON_OBJECT('hi','टमाटर'),'vegetable','outdoor','full_sun','high','loamy',
 15.00,32.00,'medium','fast',120,60,
 JSON_ARRAY(),JSON_ARRAY('winter','spring'),JSON_ARRAY('monsoon','winter'),'yellow',NULL,'annual',
 'moderate','medium','seed','Green parts can be toxic','unknown',JSON_ARRAY('Homegrown produce'),JSON_ARRAY('culinary'),
 'Needs full sun, staking, and consistent watering.','Rich soil with compost; deep containers for balcony.','Remove suckers for indeterminate types.','Feed vegetable fertilizer every 2 weeks after flowering.','Watch for fruit borers and leaf curl.', 'Harvest when fruits are firm and colored.',
 NULL,NOW(),NOW()),
(9,109,'Mango','Mangifera indica',JSON_OBJECT('hi','आम'),'tree','outdoor','full_sun','medium','loamy',
 10.00,40.00,'medium','medium',800,400,
 JSON_ARRAY(),JSON_ARRAY('summer'),JSON_ARRAY('monsoon'),NULL,NULL,'perennial',
 'moderate','medium','grafting','Sap can irritate skin','unknown',JSON_ARRAY('Fruit tree','Shade'),JSON_ARRAY('culinary','ornamental'),
 'Plant in open sunny space with room to grow.','Deep pit with compost and native soil mix.','Formative prune in early years.','Feed organic manure twice a year.','Protect young grafts from waterlogging.', 'Fruiting takes multiple seasons depending on graft.',
 NULL,NOW(),NOW()),
(10,110,'Marigold','Tagetes erecta',JSON_OBJECT('hi','गेंदा'),'flowering','outdoor','full_sun','medium','well_draining',
 15.00,35.00,'medium','fast',45,30,
 JSON_ARRAY('winter','monsoon'),JSON_ARRAY(),JSON_ARRAY('monsoon','winter'),'orange','seasonal','annual',
 'easy','low','seed','Generally safe','safe',JSON_ARRAY('Festive flowers','Pest deterrent'),JSON_ARRAY('ornamental'),
 'Sow in sunny beds or pots; keep soil moist until germination.','Loose soil with compost.','Deadhead spent blooms.','Light feeding after seedlings establish.','Avoid overcrowding to reduce fungal issues.',NULL,
 NULL,NOW(),NOW());

INSERT IGNORE INTO `pot_profiles` (`id`,`product_id`,`material`,`diameter_cm`,`height_cm`,`capacity_liters`,`drainage_holes`,`indoor_outdoor_suitability`,`meta`,`created_at`,`updated_at`,`color`) VALUES
(1,201,'ceramic',15.00,14.00,2.50,1,'both',NULL,NOW(),NOW(),'white'),
(2,202,'terracotta',20.00,18.00,5.00,1,'outdoor',NULL,NOW(),NOW(),'natural');

INSERT IGNORE INTO `fertilizer_profiles` (`id`,`product_id`,`npk_ratio`,`suitable_plant_types`,`application_frequency`,`application_quantity`,`usage_instructions`,`organic`,`meta`,`created_at`,`updated_at`,`form`) VALUES
(1,301,NULL,JSON_ARRAY('plant','tree','vegetable'),'every 30 days','2-3 handfuls per medium pot','Mix into top soil and water thoroughly.',1,NULL,NOW(),NOW(),'granular'),
(2,302,'20-20-20',JSON_ARRAY('plant','flowering','vegetable'),'every 15 days','1/2 tsp per litre water','Dissolve in water and apply to moist soil.',0,NULL,NOW(),NOW(),'powder');

INSERT IGNORE INTO `soil_profiles` (`id`,`product_id`,`composition`,`ph_range`,`suitable_for`,`usage_instructions`,`meta`,`created_at`,`updated_at`,`organic`,`weight_kg`) VALUES
(1,401,'Cocopeat','5.5-6.5',JSON_ARRAY('indoor','seedling'),'Soak block in water until expanded; mix with compost as needed.',NULL,NOW(),NOW(),1,5.00),
(2,402,'Cocopeat + Compost + Perlite','6.0-7.0',JSON_ARRAY('indoor','outdoor'),'Fill pot directly; ready to plant.',NULL,NOW(),NOW(),1,10.00);

INSERT IGNORE INTO `tool_profiles` (`id`,`product_id`,`material`,`size`,`usage`,`warranty_months`,`meta`,`created_at`,`updated_at`,`brand_name`) VALUES
(1,501,'stainless_steel','medium','pruning stems and light branches',6,NULL,NOW(),NOW(),'GreenLeaf');

INSERT IGNORE INTO `accessory_profiles` (`id`,`product_id`,`material`,`usage`,`indoor_outdoor_suitability`,`meta`,`created_at`,`updated_at`,`pack_qty`) VALUES
(1,601,'bamboo','Support for climbing plants','both',NULL,NOW(),NOW(),10);

-- Images (professional Unsplash links)
INSERT IGNORE INTO `product_images` (`id`,`product_id`,`url`,`alt`,`is_primary`,`sort_order`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,101,'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000','Money Plant',1,1,NULL,NOW(),NOW(),NULL),
(2,101,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','Money Plant trailing',0,2,NULL,NOW(),NOW(),NULL),
(3,102,'https://images.unsplash.com/photo-1593482892290-f54927ae2b7a?w=1000','Snake Plant',1,1,NULL,NOW(),NOW(),NULL),
(4,103,'https://images.unsplash.com/photo-1593691509543-c55fb32e7356?w=1000','Peace Lily',1,1,NULL,NOW(),NOW(),NULL),
(5,104,'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000','Areca Palm',1,1,NULL,NOW(),NOW(),NULL),
(6,105,'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000','Jade Plant',1,1,NULL,NOW(),NOW(),NULL),
(7,106,'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000','Red Rose',1,1,NULL,NOW(),NOW(),NULL),
(8,107,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','Tulsi plant',1,1,NULL,NOW(),NOW(),NULL),
(9,108,'https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=1000','Tomato plant',1,1,NULL,NOW(),NOW(),NULL),
(10,109,'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000','Mango sapling',1,1,NULL,NOW(),NOW(),NULL),
(11,110,'https://images.unsplash.com/photo-1501004318641-b39e6451bec6?w=1000','Marigold',1,1,NULL,NOW(),NOW(),NULL),
(12,201,'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000','Ceramic pot',1,1,NULL,NOW(),NOW(),NULL),
(13,202,'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000','Terracotta pot',1,1,NULL,NOW(),NOW(),NULL),
(14,301,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Vermicompost',1,1,NULL,NOW(),NOW(),NULL),
(15,302,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','NPK fertilizer',1,1,NULL,NOW(),NOW(),NULL),
(16,401,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Cocopeat',1,1,NULL,NOW(),NOW(),NULL),
(17,402,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Potting mix',1,1,NULL,NOW(),NOW(),NULL),
(18,501,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Garden pruner',1,1,NULL,NOW(),NOW(),NULL),
(19,601,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Plant support sticks',1,1,NULL,NOW(),NOW(),NULL),
(20,701,'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000','Indoor starter kit',1,1,NULL,NOW(),NOW(),NULL);

-- Category mappings
INSERT IGNORE INTO `product_categories` (`product_id`,`category_id`,`created_at`,`updated_at`) VALUES
(101,1,NOW(),NOW()),(101,11,NOW(),NOW()),(101,12,NOW(),NOW()),
(102,1,NOW(),NOW()),(102,11,NOW(),NOW()),(102,12,NOW(),NOW()),
(103,1,NOW(),NOW()),(103,3,NOW(),NOW()),(103,12,NOW(),NOW()),
(104,1,NOW(),NOW()),(104,12,NOW(),NOW()),
(105,1,NOW(),NOW()),(105,11,NOW(),NOW()),
(106,2,NOW(),NOW()),(106,3,NOW(),NOW()),(106,13,NOW(),NOW()),
(107,2,NOW(),NOW()),(107,5,NOW(),NOW()),
(108,5,NOW(),NOW()),(108,13,NOW(),NOW()),
(109,4,NOW(),NOW()),(109,2,NOW(),NOW()),
(110,6,NOW(),NOW()),(110,3,NOW(),NOW()),
(201,7,NOW(),NOW()),(202,7,NOW(),NOW()),
(301,8,NOW(),NOW()),(302,8,NOW(),NOW()),
(401,8,NOW(),NOW()),(402,8,NOW(),NOW()),
(501,9,NOW(),NOW()),
(601,10,NOW(),NOW()),
(701,1,NOW(),NOW());

INSERT IGNORE INTO `product_tags` (`product_id`,`tag_id`,`created_at`,`updated_at`) VALUES
(101,1,NOW(),NOW()),(101,2,NOW(),NOW()),(101,3,NOW(),NOW()),(101,6,NOW(),NOW()),
(102,1,NOW(),NOW()),(102,2,NOW(),NOW()),(102,3,NOW(),NOW()),(102,6,NOW(),NOW()),
(103,2,NOW(),NOW()),(103,7,NOW(),NOW()),
(106,5,NOW(),NOW()),(106,8,NOW(),NOW()),
(107,1,NOW(),NOW()),(107,6,NOW(),NOW()),
(108,7,NOW(),NOW()),(108,8,NOW(),NOW()),
(110,5,NOW(),NOW()),(110,7,NOW(),NOW()),
(301,6,NOW(),NOW()),
(701,1,NOW(),NOW()),(701,6,NOW(),NOW()),(701,7,NOW(),NOW());

-- Related / frequently bought together
INSERT IGNORE INTO `product_relations` (`product_id`,`related_product_id`,`relation_type`,`sort_order`,`meta`,`created_at`,`updated_at`) VALUES
(101,201,'fbt',1,NULL,NOW(),NOW()),
(101,301,'fbt',2,NULL,NOW(),NOW()),
(101,401,'fbt',3,NULL,NOW(),NOW()),
(101,601,'fbt',4,NULL,NOW(),NOW()),
(101,102,'related',1,NULL,NOW(),NOW()),
(101,103,'related',2,NULL,NOW(),NOW()),
(102,201,'fbt',1,NULL,NOW(),NOW()),
(102,402,'fbt',2,NULL,NOW(),NOW()),
(106,202,'fbt',1,NULL,NOW(),NOW()),
(106,302,'fbt',2,NULL,NOW(),NOW()),
(701,101,'related',1,NULL,NOW(),NOW()),
(701,201,'related',2,NULL,NOW(),NOW());

INSERT IGNORE INTO `product_bundles` (`id`,`product_id`,`name`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,701,'Indoor Starter Kit Bundle','active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `product_bundle_items` (`id`,`bundle_id`,`product_id`,`quantity`,`created_at`,`updated_at`,`meta`) VALUES
(1,1,101,1,NOW(),NOW(),NULL),
(2,1,201,1,NOW(),NOW(),NULL),
(3,1,301,1,NOW(),NOW(),NULL);

-- =============================================================================
-- 6) INVENTORY
-- =============================================================================
INSERT IGNORE INTO `inventory_items`
(`id`,`warehouse_id`,`product_id`,`product_variant_id`,`qty_on_hand`,`qty_reserved`,`qty_damaged`,`low_stock_threshold`,`meta`,`created_at`,`updated_at`,`version`,`deleted_at`)
VALUES
(1,1,101,NULL,120,2,0,10,NULL,NOW(),NOW(),1,NULL),
(2,1,102,NULL,90,0,1,10,NULL,NOW(),NOW(),1,NULL),
(3,1,103,NULL,60,0,0,8,NULL,NOW(),NOW(),1,NULL),
(4,1,104,NULL,40,0,0,5,NULL,NOW(),NOW(),1,NULL),
(5,1,105,NULL,75,0,0,8,NULL,NOW(),NOW(),1,NULL),
(6,1,106,NULL,80,0,0,10,NULL,NOW(),NOW(),1,NULL),
(7,1,107,NULL,150,0,0,15,NULL,NOW(),NOW(),1,NULL),
(8,1,108,NULL,55,0,0,10,NULL,NOW(),NOW(),1,NULL),
(9,1,109,NULL,25,0,0,5,NULL,NOW(),NOW(),1,NULL),
(10,1,110,NULL,200,0,0,20,NULL,NOW(),NOW(),1,NULL),
(11,1,201,NULL,100,1,0,10,NULL,NOW(),NOW(),1,NULL),
(12,1,202,NULL,70,0,0,10,NULL,NOW(),NOW(),1,NULL),
(13,1,301,NULL,180,0,0,20,NULL,NOW(),NOW(),1,NULL),
(14,1,302,NULL,90,0,0,10,NULL,NOW(),NOW(),1,NULL),
(15,1,401,NULL,110,0,0,15,NULL,NOW(),NOW(),1,NULL),
(16,1,402,NULL,95,0,0,10,NULL,NOW(),NOW(),1,NULL),
(17,1,501,NULL,40,0,0,5,NULL,NOW(),NOW(),1,NULL),
(18,1,601,NULL,130,0,0,15,NULL,NOW(),NOW(),1,NULL),
(19,1,701,NULL,35,0,0,5,NULL,NOW(),NOW(),1,NULL);

INSERT IGNORE INTO `stock_movements`
(`inventory_item_id`,`product_id`,`product_variant_id`,`warehouse_id`,`type`,`qty_delta`,`reference_type`,`reference_id`,`note`,`actor_user_id`,`created_at`,`meta`)
VALUES
(1,101,NULL,1,'purchase_in',120,'seed',NULL,'Initial stock',1,NOW(),NULL),
(2,102,NULL,1,'purchase_in',91,'seed',NULL,'Initial stock',1,NOW(),NULL),
(11,201,NULL,1,'purchase_in',101,'seed',NULL,'Initial stock',1,NOW(),NULL);

-- =============================================================================
-- 7) CAMPAIGNS / BANNERS / COUPONS / PROMOTIONS
-- =============================================================================
INSERT IGNORE INTO `campaigns`
(`id`,`slug`,`title`,`subtitle`,`description`,`type`,`season_code`,`image_url`,`starts_at`,`ends_at`,`status`,`priority`,`rules_json`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(1,'monsoon-plants-2026','Monsoon Plants','Best plants for rainy season','Shop plants that thrive in monsoon humidity and outdoor planters.','seasonal','monsoon','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1200','2026-06-01 00:00:00','2026-09-30 23:59:59','active',100,JSON_OBJECT('tags',JSON_ARRAY('monsoon')),NULL,NOW(),NOW(),NULL),
(2,'indoor-gardening','Indoor Gardening Essentials','Green up your home','Curated indoor plants, pots and soil for apartments.','evergreen','all','https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1200','2026-01-01 00:00:00','2026-12-31 23:59:59','active',90,NULL,NULL,NOW(),NOW(),NULL),
(3,'festival-green-sale','Festival Green Sale','Celebrate with plants','Special festive offers on flowering plants and kits.','festival','festival','https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1200','2026-10-01 00:00:00','2026-11-15 23:59:59','scheduled',80,NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `campaign_products` (`campaign_id`,`product_id`,`sort_order`,`created_at`,`updated_at`) VALUES
(1,106,1,NOW(),NOW()),(1,107,2,NOW(),NOW()),(1,110,3,NOW(),NOW()),(1,108,4,NOW(),NOW()),
(2,101,1,NOW(),NOW()),(2,102,2,NOW(),NOW()),(2,103,3,NOW(),NOW()),(2,104,4,NOW(),NOW()),(2,701,5,NOW(),NOW()),
(3,106,1,NOW(),NOW()),(3,110,2,NOW(),NOW()),(3,701,3,NOW(),NOW());

INSERT IGNORE INTO `banners`
(`id`,`title`,`image_url`,`placement`,`link_type`,`link_value`,`sort_order`,`starts_at`,`ends_at`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(1,'Monsoon Special','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1400','home','campaign','monsoon-plants-2026',1,'2026-06-01 00:00:00','2026-09-30 23:59:59','active',NULL,NOW(),NOW(),NULL),
(2,'Indoor Jungle','https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1400','home','campaign','indoor-gardening',2,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL),
(3,'Starter Kits','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1400','home','product','indoor-starter-kit',3,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `coupons`
(`id`,`code`,`name`,`discount_type`,`discount_value`,`min_order_amount`,`max_discount_amount`,`usage_limit_total`,`usage_limit_per_user`,`starts_at`,`ends_at`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`is_public`,`stackable`)
VALUES
(1,'WELCOME10','Welcome 10% Off','percent',10.00,299.00,150.00,1000,1,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0),
(2,'MONSOON10','Monsoon 10% Off','percent',10.00,499.00,200.00,500,2,'2026-06-01 00:00:00','2026-09-30 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0),
(3,'FLAT50','Flat ₹50 Off','fixed',50.00,399.00,50.00,500,2,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0);

INSERT IGNORE INTO `promotions`
(`id`,`name`,`slug`,`type`,`status`,`starts_at`,`ends_at`,`priority`,`rules_json`,`meta`,`created_at`,`updated_at`,`deleted_at`,`discount_type`,`discount_value`,`stackable`)
VALUES
(1,'Indoor Bestsellers Flash','indoor-bestsellers-flash','flash','active','2026-08-01 00:00:00','2026-08-31 23:59:59',50,JSON_OBJECT('product_ids',JSON_ARRAY(101,102,103)),NULL,NOW(),NOW(),NULL,'percent',5.00,0);

INSERT IGNORE INTO `promotion_products` (`promotion_id`,`product_id`,`created_at`,`updated_at`) VALUES
(1,101,NOW(),NOW()),(1,102,NOW(),NOW()),(1,103,NOW(),NOW());

INSERT IGNORE INTO `recommendation_rules` (`id`,`code`,`name`,`type`,`rules_json`,`status`,`priority`,`meta`,`created_at`,`updated_at`,`deleted_at`,`description`) VALUES
(1,'beginner_indoor','Beginner Indoor Plants','beginner',JSON_OBJECT('difficulty_level','easy','indoor_outdoor','indoor'),'active',10,NULL,NOW(),NOW(),NULL,'Easy indoor plants'),
(2,'low_sunlight','Low Sunlight Plants','low_sunlight',JSON_OBJECT('sunlight',JSON_ARRAY('low','bright_indirect')),'active',10,NULL,NOW(),NOW(),NULL,'Plants for low light'),
(3,'monsoon_picks','Monsoon Picks','seasonal',JSON_OBJECT('season','monsoon'),'active',10,NULL,NOW(),NOW(),NULL,'Seasonal monsoon picks');

-- =============================================================================
-- 8) WISHLIST / CART (Asha)
-- =============================================================================
INSERT IGNORE INTO `wishlists` (`id`,`user_id`,`product_id`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,104,NULL,NOW(),NOW(),NULL),
(2,3,701,NULL,NOW(),NOW(),NULL),
(3,4,101,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `carts` (`id`,`user_id`,`cart_token`,`currency`,`coupon_code`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,NULL,'INR',NULL,'active',NULL,NOW(),NOW(),NULL),
(2,NULL,'guest_cart_demo_001','INR',NULL,'active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `cart_items` (`id`,`cart_id`,`product_id`,`product_variant_id`,`quantity`,`unit_price_snapshot`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,1,101,NULL,2,299.00,NULL,NOW(),NOW(),NULL),
(2,1,201,NULL,1,249.00,NULL,NOW(),NOW(),NULL),
(3,2,102,NULL,1,399.00,NULL,NOW(),NOW(),NULL);

-- Reserve sample qty already reflected in inventory for demo order below
UPDATE `inventory_items` SET `qty_reserved` = 2 WHERE `id` = 1 AND EXISTS (SELECT 1 FROM `inventory_items` i WHERE i.id = 1);

-- =============================================================================
-- 9) SAMPLE ORDERS / PAYMENTS / SHIPMENTS / REVIEWS
-- =============================================================================
INSERT IGNORE INTO `orders`
(`id`,`order_number`,`user_id`,`status`,`currency`,`subtotal`,`discount_total`,`tax_total`,`shipping_total`,`grand_total`,
 `coupon_code`,`payment_method`,`shipping_method_id`,`notes`,`shipping_address_json`,`billing_address_json`,
 `placed_at`,`confirmed_at`,`cancelled_at`,`cancel_reason`,`meta`,`created_at`,`updated_at`,`deleted_at`,`ip`,`platform`,`request_id`,`warehouse_id`)
VALUES
(9001,'ORD-20260801-00001',3,'DELIVERED','INR',598.00,59.80,0.00,49.00,587.20,
 'WELCOME10','razorpay',1,'Leave at gate',
 JSON_OBJECT('name','Asha Kumar','phone','9876543210','line1','12 Green Street','city','Pune','state','Maharashtra','postal_code','411001','country','IN'),
 JSON_OBJECT('name','Asha Kumar','phone','9876543210','line1','12 Green Street','city','Pune','state','Maharashtra','postal_code','411001','country','IN'),
 '2026-08-01 10:15:00','2026-08-01 10:16:30',NULL,NULL,NULL,'2026-08-01 10:15:00','2026-08-05 18:00:00',NULL,'103.21.45.67','android','req_seed_9001',1),
(9002,'ORD-20260808-00002',4,'SHIPPED','INR',798.00,0.00,0.00,49.00,847.00,
 NULL,'razorpay',1,NULL,
 JSON_OBJECT('name','Ravi Sharma','phone','9876543211','line1','88 Lake View Road','city','Bengaluru','state','Karnataka','postal_code','560001','country','IN'),
 NULL,
 '2026-08-08 14:20:00','2026-08-08 14:21:10',NULL,NULL,NULL,'2026-08-08 14:20:00','2026-08-09 11:00:00',NULL,'49.36.12.10','android','req_seed_9002',1),
(9003,'ORD-20260810-00003',3,'PENDING_PAYMENT','INR',548.00,0.00,0.00,49.00,597.00,
 NULL,'razorpay',1,NULL,
 JSON_OBJECT('name','Asha Kumar','phone','9876543210','line1','12 Green Street','city','Pune','state','Maharashtra','postal_code','411001','country','IN'),
 NULL,
 '2026-08-10 01:10:00',NULL,NULL,NULL,NULL,'2026-08-10 01:10:00','2026-08-10 01:10:00',NULL,'103.21.45.67','ios','req_seed_9003',1);

INSERT IGNORE INTO `order_items`
(`id`,`order_id`,`product_id`,`product_variant_id`,`sku`,`name`,`unit_price`,`quantity`,`line_total`,`product_type`,`thumbnail_url`,`meta`,`created_at`,`updated_at`,`tax_amount`,`discount_amount`)
VALUES
(1,9001,101,NULL,'PLT-MONEY-001','Money Plant',299.00,2,598.00,'plant','https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=400',NULL,'2026-08-01 10:15:00','2026-08-01 10:15:00',0.00,59.80),
(2,9002,104,NULL,'PLT-ARECA-001','Areca Palm',799.00,1,798.00,'plant','https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=400',NULL,'2026-08-08 14:20:00','2026-08-08 14:20:00',0.00,0.00),
(3,9003,102,NULL,'PLT-SNAKE-001','Snake Plant',399.00,1,399.00,'plant','https://images.unsplash.com/photo-1593482892290-f54927ae2b7a?w=400',NULL,'2026-08-10 01:10:00','2026-08-10 01:10:00',0.00,0.00),
(4,9003,201,NULL,'POT-CER-6','Ceramic Pot 6 inch',249.00,1,249.00,'pot','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=400',NULL,'2026-08-10 01:10:00','2026-08-10 01:10:00',0.00,0.00);

INSERT IGNORE INTO `order_status_histories` (`order_id`,`from_status`,`to_status`,`actor_user_id`,`note`,`meta`,`created_at`,`request_id`) VALUES
(9001,NULL,'PENDING_PAYMENT',3,'Order placed',NULL,'2026-08-01 10:15:00','req_seed_9001'),
(9001,'PENDING_PAYMENT','CONFIRMED',NULL,'Payment success',NULL,'2026-08-01 10:16:30','req_seed_9001p'),
(9001,'CONFIRMED','PROCESSING',2,'Packed queue',NULL,'2026-08-01 15:00:00',NULL),
(9001,'PROCESSING','PACKED',2,'Ready',NULL,'2026-08-02 11:00:00',NULL),
(9001,'PACKED','SHIPPED',2,'Courier pickup',NULL,'2026-08-02 16:00:00',NULL),
(9001,'SHIPPED','OUT_FOR_DELIVERY',NULL,'Ofd',NULL,'2026-08-05 09:00:00',NULL),
(9001,'OUT_FOR_DELIVERY','DELIVERED',NULL,'Delivered',NULL,'2026-08-05 18:00:00',NULL),
(9002,NULL,'PENDING_PAYMENT',4,'Order placed',NULL,'2026-08-08 14:20:00','req_seed_9002'),
(9002,'PENDING_PAYMENT','CONFIRMED',NULL,'Payment success',NULL,'2026-08-08 14:21:10',NULL),
(9002,'CONFIRMED','SHIPPED',2,'Shipped',NULL,'2026-08-09 11:00:00',NULL),
(9003,NULL,'PENDING_PAYMENT',3,'Awaiting payment',NULL,'2026-08-10 01:10:00','req_seed_9003');

INSERT IGNORE INTO `payments`
(`id`,`order_id`,`user_id`,`provider`,`method`,`amount`,`currency`,`status`,`idempotency_key`,`provider_order_id`,`provider_payment_id`,`provider_signature`,`failure_code`,`failure_message`,`paid_at`,`raw_response_json`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(5501,9001,3,'razorpay','razorpay',587.20,'INR','success','idem_9001_1','order_seed_9001','pay_seed_9001','sig_seed',NULL,NULL,'2026-08-01 10:16:30',JSON_OBJECT('seed',true),NULL,'2026-08-01 10:15:30','2026-08-01 10:16:30',NULL),
(5502,9002,4,'razorpay','razorpay',847.00,'INR','success','idem_9002_1','order_seed_9002','pay_seed_9002','sig_seed',NULL,NULL,'2026-08-08 14:21:10',JSON_OBJECT('seed',true),NULL,'2026-08-08 14:20:20','2026-08-08 14:21:10',NULL),
(5503,9003,3,'razorpay','razorpay',597.00,'INR','pending','idem_9003_1','order_seed_9003',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:10:20','2026-08-10 01:10:20',NULL);

INSERT IGNORE INTO `shipments`
(`id`,`order_id`,`status`,`carrier`,`tracking_number`,`tracking_url`,`shipped_at`,`delivered_at`,`meta`,`created_at`,`updated_at`,`deleted_at`,`shipping_method_id`,`warehouse_id`,`eta_date`,`weight_grams`)
VALUES
(1,9001,'DELIVERED','Delhivery','DLVSEED9001','https://track.example.com/DLVSEED9001','2026-08-02 16:00:00','2026-08-05 18:00:00',NULL,'2026-08-02 16:00:00','2026-08-05 18:00:00',NULL,1,1,'2026-08-05',2500),
(2,9002,'SHIPPED','Delhivery','DLVSEED9002','https://track.example.com/DLVSEED9002','2026-08-09 11:00:00',NULL,NULL,'2026-08-09 11:00:00','2026-08-09 11:00:00',NULL,1,1,'2026-08-12',5000);

INSERT IGNORE INTO `shipment_events` (`shipment_id`,`status`,`description`,`event_at`,`meta`,`created_at`,`updated_at`,`location`) VALUES
(1,'SHIPPED','Picked up from Pune warehouse','2026-08-02 16:00:00',NULL,NOW(),NOW(),'Pune'),
(1,'IN_TRANSIT','In transit to destination city','2026-08-03 10:00:00',NULL,NOW(),NOW(),'Hub'),
(1,'OUT_FOR_DELIVERY','Out for delivery','2026-08-05 09:00:00',NULL,NOW(),NOW(),'Pune'),
(1,'DELIVERED','Delivered to customer','2026-08-05 18:00:00',NULL,NOW(),NOW(),'Pune'),
(2,'SHIPPED','Picked up from Pune warehouse','2026-08-09 11:00:00',NULL,NOW(),NOW(),'Pune');

INSERT IGNORE INTO `reviews`
(`id`,`product_id`,`user_id`,`order_id`,`rating`,`title`,`body`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`moderated_by`,`moderated_at`)
VALUES
(1,101,3,9001,5,'Healthy plant','Arrived well packed and growing fast. Perfect for my balcony corner.', 'approved',NULL,'2026-08-06 12:00:00','2026-08-06 12:30:00',NULL,2,'2026-08-06 12:30:00'),
(2,101,4,NULL,5,'Great for beginners','Very easy to maintain. Highly recommended.', 'approved',NULL,'2026-07-20 09:00:00','2026-07-20 10:00:00',NULL,2,'2026-07-20 10:00:00'),
(3,102,5,NULL,4,'Low maintenance hero','Needs almost no care. Looks premium.', 'approved',NULL,'2026-07-25 11:00:00','2026-07-25 12:00:00',NULL,2,'2026-07-25 12:00:00'),
(4,104,4,9002,5,'Beautiful palm','Packaging was excellent. Plant looks fresh.', 'pending',NULL,'2026-08-09 19:00:00','2026-08-09 19:00:00',NULL,NULL,NULL);

-- =============================================================================
-- 10) NOTIFICATIONS / TEMPLATES / SUPPORT
-- =============================================================================
INSERT IGNORE INTO `notification_templates` (`id`,`code`,`channel`,`subject`,`body`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`locale`,`name`) VALUES
(1,'order_confirmed','email','Order {{order_number}} confirmed','Hi {{name}}, your order {{order_number}} is confirmed.','active',NULL,NOW(),NOW(),NULL,'en','Order Confirmed Email'),
(2,'order_shipped','push','Your order is on the way','Order {{order_number}} has been shipped. Track: {{tracking_number}}','active',NULL,NOW(),NOW(),NULL,'en','Order Shipped Push'),
(3,'order_delivered','in_app','Delivered','Order {{order_number}} was delivered. Rate your plants!','active',NULL,NOW(),NOW(),NULL,'en','Order Delivered In-App');

INSERT IGNORE INTO `notifications` (`id`,`user_id`,`type`,`title`,`body`,`data_json`,`is_read`,`read_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(1,3,'order_delivered','Order delivered','Order ORD-20260801-00001 was delivered. Enjoy your plants!',JSON_OBJECT('order_id',9001,'order_number','ORD-20260801-00001'),0,NULL,NULL,'2026-08-05 18:05:00','2026-08-05 18:05:00',NULL),
(2,3,'campaign','Monsoon specials are live','Explore monsoon plants with extra care tips.',JSON_OBJECT('campaign','monsoon-plants-2026'),0,NULL,NULL,'2026-08-06 09:00:00','2026-08-06 09:00:00',NULL),
(3,4,'order_shipped','Your order is on the way','Order ORD-20260808-00002 has been shipped.',JSON_OBJECT('order_id',9002,'tracking_number','DLVSEED9002'),1,'2026-08-09 12:00:00',NULL,'2026-08-09 11:05:00','2026-08-09 12:00:00',NULL);

INSERT IGNORE INTO `support_tickets` (`id`,`user_id`,`order_id`,`subject`,`status`,`priority`,`meta`,`created_at`,`updated_at`,`deleted_at`,`assigned_to`) VALUES
(1,3,9001,'Ask about Money Plant care','open','normal',NULL,NOW(),NOW(),NULL,2);

INSERT IGNORE INTO `support_messages` (`id`,`ticket_id`,`user_id`,`message`,`created_at`,`updated_at`,`meta`,`is_staff`) VALUES
(1,1,3,'How often should I water my Money Plant in monsoon?',NOW(),NOW(),NULL,0),
(2,1,2,'Water when the top soil feels dry — usually 2–3 times a week. Avoid waterlogging.',NOW(),NOW(),NULL,1);

-- =============================================================================
-- 11) ATTRIBUTE + SCHEMA FIELD MAPS (future-ready demos)
-- =============================================================================
INSERT IGNORE INTO `attribute_definitions`
(`id`,`code`,`name`,`data_type`,`applies_to_product_types`,`is_filterable`,`is_required`,`unit`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(1,'fragrance_level','Fragrance Level','string',JSON_ARRAY('plant','flowering'),1,0,NULL,NULL,NOW(),NOW(),NULL),
(2,'pot_color_code','Pot Color Code','string',JSON_ARRAY('pot'),1,0,NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `attribute_values` (`product_id`,`variant_id`,`attribute_definition_id`,`value_string`,`value_number`,`value_json`,`created_at`,`updated_at`) VALUES
(106,NULL,1,'strong',NULL,NULL,NOW(),NOW()),
(201,NULL,2,'WHI',NULL,NULL,NOW(),NOW());

INSERT IGNORE INTO `schema_field_maps`
(`id`,`entity`,`logical_field`,`physical_table`,`physical_column`,`data_type`,`api_field`,`is_required`,`is_active`,`meta`,`created_at`,`updated_at`)
VALUES
(1,'plant','sunlight_requirement','plant_profiles','sunlight','string','plant.sunlight',1,1,NULL,NOW(),NOW()),
(2,'plant','water_need','plant_profiles','water_requirement','string','plant.water_requirement',1,1,NULL,NOW(),NOW()),
(3,'product','list_price','products','price','number','price',1,1,NULL,NOW(),NOW()),
(4,'product','seo_slug','products','slug','string','slug',1,1,NULL,NOW(),NOW()),
(5,'order','public_number','orders','order_number','string','order_number',1,1,NULL,NOW(),NOW());

-- =============================================================================
-- 12) SAMPLE API REQUEST LOGS (for admin/debug screens)
-- =============================================================================
INSERT IGNORE INTO `api_request_logs`
(`id`,`request_id`,`method`,`path`,`route_name`,`status_code`,`user_id`,`platform`,`app_version`,`ip`,`user_agent`,
 `request_headers_json`,`request_body_json`,`response_body_json`,`error_code`,`duration_ms`,`created_at`,`meta`,`correlation_id`,`endpoint_code`,`is_success`,`deleted_at`)
VALUES
(1,'req_seed_home','GET','/api/v1/home','home',200,NULL,'android','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 JSON_OBJECT('Accept','application/json'),NULL,JSON_OBJECT('success',true,'message','Home retrieved successfully'),NULL,45,NOW(),NULL,NULL,'HOME-01',1,NULL),
(2,'req_seed_login','POST','/api/v1/auth/login','auth.login',200,3,'android','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 JSON_OBJECT('Accept','application/json'),JSON_OBJECT('email','asha@example.com','password','***'),JSON_OBJECT('success',true,'message','Login successful'),NULL,120,NOW(),NULL,NULL,'AUTH-02',1,NULL),
(3,'req_seed_prod','GET','/api/v1/products/money-plant','products.show',200,3,'android','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true,'data',JSON_OBJECT('id',101,'name','Money Plant')),NULL,38,NOW(),NULL,NULL,'PROD-02',1,NULL),
(4,'req_seed_cart','GET','/api/v1/cart','cart.show',200,3,'ios','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true,'message','Cart retrieved successfully'),NULL,27,NOW(),NULL,NULL,'CART-01',1,NULL);

INSERT IGNORE INTO `api_outbound_logs`
(`request_id`,`provider`,`operation`,`http_method`,`url`,`status_code`,`request_body_json`,`response_body_json`,`duration_ms`,`success`,`error_message`,`reference_type`,`reference_id`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
('req_seed_9001p','razorpay','create_order','POST','https://api.razorpay.com/v1/orders',200,JSON_OBJECT('amount',58720,'currency','INR'),JSON_OBJECT('id','order_seed_9001','status','created'),210,1,NULL,'payment',5501,NULL,NOW(),NOW(),NULL);

-- =============================================================================
-- 13) EXTRA SAMPLE DATA (richer catalog + users + orders for UI/API demos)
-- =============================================================================

-- More customers (password = Secret@123)
INSERT IGNORE INTO `users` (`id`,`name`,`email`,`phone`,`password`,`status`,`email_verified_at`,`phone_verified_at`,`last_login_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(6,'Kabir Singh','kabir@example.com','9876543213','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NOW(),NULL,NOW(),NOW(),NULL),
(7,'Sneha Reddy','sneha@example.com','9876543214','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL),
(8,'Arjun Mehta','arjun@example.com','9876543215','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL),
(9,'Priya Nair','priya@example.com','9876543216','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NOW(),JSON_OBJECT('preferred_language','en'),NOW(),NOW(),NULL),
(10,'Vikram Joshi','vikram@example.com','9876543217','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL),
(11,'Inventory Manager','inventory@nursery.test','9000000003','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL),
(12,'Order Manager','orders@nursery.test','9000000004','$2y$12$njbm5b3LVe46DywrvD5pveU.AslmnHwqPg948NVCbKM9IPPzdFvi2','active',NOW(),NOW(),NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `user_role` (`user_id`,`role_id`,`created_at`,`updated_at`) VALUES
(6,8,NOW(),NOW()),(7,8,NOW(),NOW()),(8,8,NOW(),NOW()),(9,8,NOW(),NOW()),(10,8,NOW(),NOW()),
(11,4,NOW(),NOW()),(12,5,NOW(),NOW());

INSERT IGNORE INTO `customer_profiles` (`id`,`user_id`,`date_of_birth`,`gender`,`preferred_language`,`marketing_opt_in`,`default_address_id`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(4,6,'1992-03-18','male','en',1,NULL,NULL,NOW(),NOW(),NULL),
(5,7,'1996-07-09','female','en',1,NULL,NULL,NOW(),NOW(),NULL),
(6,8,'1988-12-01','male','hi',0,NULL,NULL,NOW(),NOW(),NULL),
(7,9,'1995-09-25','female','en',1,NULL,NULL,NOW(),NOW(),NULL),
(8,10,'1991-04-14','male','en',1,NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `addresses` (`id`,`user_id`,`label`,`name`,`phone`,`line1`,`line2`,`city`,`state`,`postal_code`,`country`,`is_default`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(5,6,'Home','Kabir Singh','9876543213','22 Palm Avenue',NULL,'Mumbai','Maharashtra','400001','IN',1,NULL,NOW(),NOW(),NULL),
(6,7,'Home','Sneha Reddy','9876543214','7 Jubilee Hills Road',NULL,'Hyderabad','Telangana','500033','IN',1,NULL,NOW(),NOW(),NULL),
(7,8,'Home','Arjun Mehta','9876543215','40 Civil Lines',NULL,'Jaipur','Rajasthan','302001','IN',1,NULL,NOW(),NOW(),NULL),
(8,9,'Home','Priya Nair','9876543216','18 Marine Drive','Flat 3B','Kochi','Kerala','682001','IN',1,NULL,NOW(),NOW(),NULL),
(9,10,'Home','Vikram Joshi','9876543217','9 MG Road',NULL,'Indore','Madhya Pradesh','452001','IN',1,NULL,NOW(),NOW(),NULL),
(10,9,'Office','Priya Nair','9876543216','InfoPark Phase 2',NULL,'Kochi','Kerala','682042','IN',0,NULL,NOW(),NOW(),NULL);

UPDATE `customer_profiles` SET `default_address_id` = 5 WHERE `id` = 4 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 5);
UPDATE `customer_profiles` SET `default_address_id` = 6 WHERE `id` = 5 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 6);
UPDATE `customer_profiles` SET `default_address_id` = 7 WHERE `id` = 6 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 7);
UPDATE `customer_profiles` SET `default_address_id` = 8 WHERE `id` = 7 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 8);
UPDATE `customer_profiles` SET `default_address_id` = 9 WHERE `id` = 8 AND EXISTS (SELECT 1 FROM `addresses` a WHERE a.id = 9);

INSERT IGNORE INTO `user_devices` (`id`,`user_id`,`platform`,`device_id`,`push_token`,`app_version`,`is_active`,`last_seen_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(4,6,'android','kabir-android-001','fcm_sample_token_kabir','1.0.1',1,NOW(),NULL,NOW(),NOW(),NULL),
(5,7,'ios','sneha-ios-001','apns_sample_token_sneha','1.0.1',1,NOW(),NULL,NOW(),NOW(),NULL),
(6,9,'android','priya-android-001','fcm_sample_token_priya','1.0.2',1,NOW(),NULL,NOW(),NOW(),NULL);

-- Extra categories / brands / tags
INSERT IGNORE INTO `categories` (`id`,`parent_id`,`name`,`slug`,`image_url`,`sort_order`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`description`) VALUES
(14,NULL,'Medicinal Plants','medicinal-plants','https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=800',11,'active',NULL,NOW(),NOW(),NULL,'Ayurvedic and home remedies'),
(15,NULL,'Succulents','succulents','https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=800',12,'active',NULL,NOW(),NOW(),NULL,'Low-water stylish plants'),
(16,NULL,'Hanging Plants','hanging-plants','https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=800',13,'active',NULL,NOW(),NOW(),NULL,'Trailing plants for shelves & baskets'),
(17,1,'Office Plants','office-plants','https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=800',3,'active',NULL,NOW(),NOW(),NULL,'Desk and cabin friendly'),
(18,3,'Seasonal Flowers','seasonal-flowers','https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800',1,'active',NULL,NOW(),NOW(),NULL,'Festival and season blooms');

INSERT IGNORE INTO `brands` (`id`,`name`,`slug`,`logo_url`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`description`) VALUES
(5,'HerbHouse','herbhouse','https://placehold.co/200x80/52796f/ffffff?text=HerbHouse','active',NULL,NOW(),NOW(),NULL,'Herbs and medicinal plants'),
(6,'ToolPro Garden','toolpro-garden','https://placehold.co/200x80/354f52/ffffff?text=ToolPro','active',NULL,NOW(),NOW(),NULL,'Durable garden tools');

INSERT IGNORE INTO `tags` (`id`,`name`,`slug`,`created_at`,`updated_at`,`deleted_at`,`status`) VALUES
(9,'Pet Safe','pet-safe',NOW(),NOW(),NULL,'active'),
(10,'Office','office',NOW(),NOW(),NULL,'active'),
(11,'Medicinal','medicinal',NOW(),NOW(),NULL,'active'),
(12,'Succulent','succulent',NOW(),NOW(),NULL,'active'),
(13,'Hanging','hanging',NOW(),NOW(),NULL,'active'),
(14,'Gift','gift',NOW(),NOW(),NULL,'active'),
(15,'Summer','summer',NOW(),NOW(),NULL,'active'),
(16,'Winter','winter',NOW(),NOW(),NULL,'active');

-- Extra products (plants, pots, tools, care, seeds, trees)
INSERT IGNORE INTO `products`
(`id`,`brand_id`,`product_type`,`name`,`slug`,`sku`,`description`,`price`,`compare_at_price`,`currency`,`status`,`stock_status`,`is_featured`,`is_new`,`rating_avg`,`rating_count`,`published_at`,`meta`,`created_at`,`updated_at`,`deleted_at`,`tax_class`)
VALUES
(111,2,'plant','ZZ Plant','zz-plant','PLT-ZZ-001','Nearly indestructible glossy-leaf indoor plant for offices and low light.',549.00,649.00,'INR','active','in_stock',1,0,4.65,95,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','low_maintenance','office')),NOW(),NOW(),NULL,'gst5'),
(112,2,'plant','Aloe Vera','aloe-vera','PLT-ALOE-001','Medicinal succulent for sunny windows. Gel useful for skin care.',249.00,299.00,'INR','active','in_stock',1,0,4.70,240,NOW(),JSON_OBJECT('badges',JSON_ARRAY('medicinal','beginner')),NOW(),NOW(),NULL,'gst5'),
(113,2,'plant','Spider Plant','spider-plant','PLT-SPIDER-001','Classic air-purifying hanging plant with baby plantlets.',279.00,329.00,'INR','active','in_stock',1,0,4.55,118,NOW(),JSON_OBJECT('badges',JSON_ARRAY('air-purifying','hanging','pet-safe')),NOW(),NOW(),NULL,'gst5'),
(114,2,'plant','Rubber Plant','rubber-plant','PLT-RUBBER-001','Bold indoor statement plant with large glossy leaves.',699.00,799.00,'INR','active','in_stock',1,1,4.35,52,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','office')),NOW(),NOW(),NULL,'gst5'),
(115,1,'plant','Boston Fern','boston-fern','PLT-FERN-001','Lush hanging fern for humid bathrooms and shaded balconies.',399.00,449.00,'INR','active','in_stock',0,0,4.25,70,NOW(),JSON_OBJECT('badges',JSON_ARRAY('hanging','monsoon')),NOW(),NOW(),NULL,'gst5'),
(116,1,'plant','Lavender','lavender-plant','PLT-LAV-001','Fragrant flowering herb for sunny balconies and gardens.',349.00,399.00,'INR','active','in_stock',1,0,4.40,88,NOW(),JSON_OBJECT('badges',JSON_ARRAY('balcony','summer')),NOW(),NOW(),NULL,'gst5'),
(117,5,'plant','Mint Plant','mint-plant','PLT-MINT-001','Fresh mint for kitchen gardens and chutneys.',129.00,149.00,'INR','active','in_stock',0,1,4.60,160,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','medicinal')),NOW(),NOW(),NULL,'gst5'),
(118,5,'plant','Lemongrass','lemongrass','PLT-LEMONGRASS-001','Aromatic medicinal grass for tea and natural mosquito deterrent.',179.00,199.00,'INR','active','in_stock',0,0,4.50,74,NOW(),JSON_OBJECT('badges',JSON_ARRAY('medicinal','monsoon')),NOW(),NOW(),NULL,'gst5'),
(119,1,'plant','Hibiscus (Red)','hibiscus-red','PLT-HIB-RED-001','Bright red flowering shrub for outdoor gardens and large pots.',399.00,459.00,'INR','active','in_stock',1,0,4.45,102,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','balcony')),NOW(),NOW(),NULL,'gst5'),
(120,1,'plant','Bougainvillea','bougainvillea','PLT-BOUG-001','Colorful climber for sunny walls, gates and terraces.',349.00,399.00,'INR','active','in_stock',1,0,4.30,91,NOW(),JSON_OBJECT('badges',JSON_ARRAY('summer','balcony')),NOW(),NOW(),NULL,'gst5'),
(121,1,'plant','Echeveria Succulent','echeveria-succulent','PLT-ECHEV-001','Rosette succulent perfect for desks and mini gardens.',199.00,229.00,'INR','active','in_stock',0,1,4.55,67,NOW(),JSON_OBJECT('badges',JSON_ARRAY('succulent','new-arrival','gift')),NOW(),NOW(),NULL,'gst5'),
(122,1,'plant','Pothos Golden','pothos-golden','PLT-POTHOS-001','Golden trailing pothos — bright, easy, great for shelves.',329.00,379.00,'INR','active','in_stock',1,0,4.60,145,NOW(),JSON_OBJECT('badges',JSON_ARRAY('hanging','beginner','bestseller')),NOW(),NOW(),NULL,'gst5'),
(123,1,'tree','Lemon Sapling','lemon-sapling','TRE-LEMON-001','Grafted lemon sapling for terrace and garden fruit.',749.00,849.00,'INR','active','in_stock',1,0,4.20,44,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift')),NOW(),NOW(),NULL,'gst5'),
(124,1,'tree','Guava Sapling','guava-sapling','TRE-GUAVA-001','Hardy fruit sapling suitable for Indian gardens.',599.00,699.00,'INR','active','in_stock',0,0,4.15,36,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(125,1,'seed','Coriander Seeds Pack','coriander-seeds-pack','SED-CORI-001','Fast-growing coriander seeds for kitchen gardens.',49.00,59.00,'INR','active','in_stock',0,0,4.40,210,NOW(),JSON_OBJECT('badges',JSON_ARRAY('beginner')),NOW(),NOW(),NULL,'gst5'),
(126,1,'seed','Chili Seeds Pack','chili-seeds-pack','SED-CHILI-001','Spicy chili seeds for pots and kitchen gardens.',59.00,69.00,'INR','active','in_stock',0,1,4.35,98,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(127,1,'plant','Chrysanthemum','chrysanthemum','PLT-CHRYS-001','Festive flowering plant for winter and celebrations.',299.00,349.00,'INR','active','in_stock',1,0,4.50,120,NOW(),JSON_OBJECT('badges',JSON_ARRAY('winter','gift')),NOW(),NOW(),NULL,'gst5'),
(128,1,'plant','Croton','croton-plant','PLT-CROTON-001','Colorful foliage plant for bright indoor/outdoor corners.',449.00,499.00,'INR','active','in_stock',0,0,4.10,48,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(203,3,'pot','Self Watering Pot 7 inch','self-watering-pot-7-inch','POT-SELF-7','Modern self-watering planter for busy plant parents.',399.00,449.00,'INR','active','in_stock',1,1,4.55,63,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(204,3,'pot','Hanging Basket 10 inch','hanging-basket-10-inch','POT-HANG-10','Durable hanging basket for ferns and pothos.',279.00,329.00,'INR','active','in_stock',0,0,4.25,41,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(205,3,'pot','Ceramic Pot 10 inch','ceramic-pot-10-inch','POT-CER-10','Large ceramic planter for Areca and Rubber plants.',449.00,529.00,'INR','active','in_stock',1,0,4.40,55,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(303,4,'fertilizer','Rose Bloom Booster 500g','rose-bloom-booster-500g','FER-ROSE-500','Special feed for flowering roses and hibiscus.',229.00,269.00,'INR','active','in_stock',0,0,4.35,72,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(304,4,'fertilizer','Seaweed Extract 250ml','seaweed-extract-250ml','FER-SEA-250','Organic liquid seaweed tonic for stress recovery.',199.00,229.00,'INR','active','in_stock',1,1,4.60,84,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(403,4,'soil','Cactus & Succulent Mix 5kg','cactus-succulent-mix-5kg','SOL-CACT-5','Fast-draining mix for succulents and jade plants.',249.00,279.00,'INR','active','in_stock',0,0,4.45,58,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(502,6,'tool','Hand Trowel','hand-trowel','TOL-TROW-01','Ergonomic trowel for potting and transplanting.',199.00,229.00,'INR','active','in_stock',0,0,4.30,66,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(503,6,'tool','Watering Can 5L','watering-can-5l','TOL-CAN-5','Lightweight watering can with gentle rose spout.',399.00,449.00,'INR','active','in_stock',1,0,4.50,90,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller')),NOW(),NOW(),NULL,'gst5'),
(504,6,'tool','Garden Gloves Pair','garden-gloves-pair','TOL-GLOVE-01','Comfortable gloves for pruning and soil work.',149.00,179.00,'INR','active','in_stock',0,0,4.20,77,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(602,1,'accessory','Moss Stick 3 ft','moss-stick-3ft','ACC-MOSS-3','Support pole for Money Plant and climbers.',149.00,179.00,'INR','active','in_stock',0,0,4.15,50,NOW(),NULL,NOW(),NOW(),NULL,'gst5'),
(603,1,'accessory','Plant Mister Spray Bottle','plant-mister-spray-bottle','ACC-MIST-01','Fine mist sprayer for ferns and palms.',179.00,199.00,'INR','active','in_stock',0,1,4.40,61,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(702,1,'bundle','Balcony Flower Kit','balcony-flower-kit','BND-BALC-01','Rose + Terracotta Pot 8 inch + Rose Bloom Booster.',799.00,927.00,'INR','active','in_stock',1,1,4.65,22,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift','new-arrival')),NOW(),NOW(),NULL,'gst5'),
(703,1,'bundle','Succulent Desk Kit','succulent-desk-kit','BND-SUCC-01','Echeveria + Ceramic Pot 6 inch + Cactus Mix.',549.00,647.00,'INR','active','in_stock',1,1,4.70,19,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift','office')),NOW(),NOW(),NULL,'gst5');

-- Plant profiles for new plants/trees/seeds
INSERT IGNORE INTO `plant_profiles`
(`id`,`product_id`,`common_name`,`scientific_name`,`local_names`,`plant_kind`,`indoor_outdoor`,`sunlight`,`water_requirement`,`soil_type`,
 `temperature_min_c`,`temperature_max_c`,`humidity_requirement`,`growth_rate`,`mature_height_cm`,`mature_width_cm`,
 `flowering_season`,`fruiting_season`,`planting_season`,`bloom_color`,`flowering_duration`,`lifespan`,
 `difficulty_level`,`care_level`,`propagation_method`,`toxicity_info`,`pet_safety`,`benefits`,`uses`,
 `growing_instructions`,`planting_instructions`,`pruning_instructions`,`fertilization_instructions`,`pest_disease_info`,`harvest_info`,
 `meta`,`created_at`,`updated_at`)
VALUES
(11,111,'ZZ Plant','Zamioculcas zamiifolia',JSON_OBJECT('hi','जेड जेड प्लांट'),'foliage','indoor','low','low','well_draining',16,32,'low','slow',90,60,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','low','division','Toxic if ingested','toxic',JSON_ARRAY('Low maintenance','Office friendly'),JSON_ARRAY('ornamental'),'Allow soil to dry between waterings. Tolerates low light.','Use well-draining mix; avoid oversized pots.','Remove yellow leaflets.','Feed 2–3 times a year.','Overwatering causes rhizome rot.',NULL,NULL,NOW(),NOW()),
(12,112,'Aloe Vera','Aloe barbadensis miller',JSON_OBJECT('hi','एलोवेरा'),'succulent','indoor','full_sun','low','sandy',12,35,'low','medium',60,50,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','low','offset','Sap can irritate','toxic',JSON_ARRAY('Medicinal gel','Air purifying'),JSON_ARRAY('medicinal'),'Bright light; water deeply but infrequently.','Cactus/succulent mix with drainage.','Remove dried outer leaves.','Light feeding in growing season.','Mealybugs; avoid wet crowns.',NULL,NULL,NOW(),NOW()),
(13,113,'Spider Plant','Chlorophytum comosum',JSON_OBJECT('hi','स्पाइडर प्लांट'),'hanging','indoor','bright_indirect','medium','well_draining',15,30,'medium','fast',40,50,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','low','plantlets','Generally safe','safe',JSON_ARRAY('Air purifying','Pet safer choice'),JSON_ARRAY('ornamental'),'Bright indirect light; keep soil lightly moist.','Standard potting mix.','Trim brown tips; pot up babies.','Feed monthly in growing season.','Tip burn from fluoride.',NULL,NULL,NOW(),NOW()),
(14,114,'Rubber Plant','Ficus elastica',JSON_OBJECT('hi','रबर प्लांट'),'foliage','indoor','bright_indirect','medium','well_draining',16,30,'medium','medium',200,90,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','medium','cutting','Sap irritant','toxic',JSON_ARRAY('Statement foliage'),JSON_ARRAY('ornamental'),'Bright light; wipe leaves to remove dust.','Rich well-draining mix.','Prune to control height.','Feed monthly in summer.','Scale insects possible.',NULL,NULL,NOW(),NOW()),
(15,115,'Boston Fern','Nephrolepis exaltata',JSON_OBJECT('hi','फर्न'),'fern','indoor','bright_indirect','high','well_draining',15,28,'high','medium',70,70,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon','year_round'),NULL,NULL,'perennial','moderate','medium','division','Generally safe','safe',JSON_ARRAY('Humidity lover','Hanging beauty'),JSON_ARRAY('ornamental'),'Keep moist; mist often in dry rooms.','Peaty mix; hanging basket ideal.','Trim brown fronds.','Mild feed monthly.','Frond browning from dry air.',NULL,NULL,NOW(),NOW()),
(16,116,'Lavender','Lavandula angustifolia',JSON_OBJECT('hi','लैवेंडर'),'herb','outdoor','full_sun','low','sandy',8,32,'low','medium',60,50,JSON_ARRAY('winter','spring'),JSON_ARRAY(),JSON_ARRAY('winter'),'purple','seasonal','perennial','moderate','medium','cutting','Generally safe','safe',JSON_ARRAY('Fragrance','Pollinator friendly'),JSON_ARRAY('ornamental','culinary'),'Full sun and excellent drainage required.','Sandy mix; do not overwater.','Trim after flowering.','Low fertilizer; avoid rich nitrogen.','Root rot in monsoon if soggy.',NULL,NULL,NOW(),NOW()),
(17,117,'Mint','Mentha spicata',JSON_OBJECT('hi','पुदीना'),'herb','outdoor','partial','high','loamy',10,35,'medium','fast',40,50,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon','winter'),NULL,NULL,'perennial','easy','low','cutting','Culinary herb','safe',JSON_ARRAY('Kitchen herb'),JSON_ARRAY('culinary','medicinal'),'Keep soil moist; contain roots in pot.','Rich potting mix.','Harvest tips often.','Compost monthly.','Rust fungus if overcrowded.',NULL,NULL,NOW(),NOW()),
(18,118,'Lemongrass','Cymbopogon citratus',JSON_OBJECT('hi','लेमनग्रास'),'herb','outdoor','full_sun','medium','loamy',12,38,'medium','fast',120,80,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon'),NULL,NULL,'perennial','easy','low','division','Culinary/medicinal','safe',JSON_ARRAY('Tea','Natural deterrent'),JSON_ARRAY('culinary','medicinal'),'Sunny spot; water regularly in heat.','Fertile soil with compost.','Cut stalks for harvest.','Feed organic manure monthly.','Few pests; avoid waterlogging.',NULL,NULL,NOW(),NOW()),
(19,119,'Hibiscus','Hibiscus rosa-sinensis',JSON_OBJECT('hi','गुड़हल'),'shrub','outdoor','full_sun','medium','loamy',12,36,'medium','medium',180,120,JSON_ARRAY('summer','monsoon'),JSON_ARRAY(),JSON_ARRAY('monsoon'),'red','seasonal','perennial','easy','medium','cutting','Generally safe','safe',JSON_ARRAY('Daily blooms'),JSON_ARRAY('ornamental'),'Full sun and regular watering.','Rich soil; large pot for balcony.','Prune for shape after flush.','Flowering fertilizer every 2–3 weeks.','Aphids and mealybugs.',NULL,NULL,NOW(),NOW()),
(20,120,'Bougainvillea','Bougainvillea glabra',JSON_OBJECT('hi','बोगनवेलिया'),'climber','outdoor','full_sun','low','well_draining',12,40,'low','fast',300,200,JSON_ARRAY('winter','summer'),JSON_ARRAY(),JSON_ARRAY('monsoon','winter'),'magenta','long','perennial','easy','low','cutting','Thorns','unknown',JSON_ARRAY('Colorful bracts'),JSON_ARRAY('ornamental'),'Needs full sun; less water encourages blooms.','Well-draining mix; sturdy support.','Train and prune after flowering.','Low nitrogen feed.','Few pests; caterpillars occasional.',NULL,NULL,NOW(),NOW()),
(21,121,'Echeveria','Echeveria elegans',JSON_OBJECT('hi','इचेवेरिया'),'succulent','indoor','full_sun','low','sandy',10,32,'low','slow',15,15,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','low','leaf_cutting','Generally safe','safe',JSON_ARRAY('Desk plant','Gift friendly'),JSON_ARRAY('ornamental'),'Bright light; water only when dry.','Cactus mix essential.','Remove dried leaves.','Very light feeding.','Rot from overwatering.',NULL,NULL,NOW(),NOW()),
(22,122,'Golden Pothos','Epipremnum aureum',JSON_OBJECT('hi','पोथोस'),'climber','indoor','bright_indirect','medium','well_draining',18,30,'medium','fast',200,60,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','easy','low','cutting','Toxic if ingested','toxic',JSON_ARRAY('Air purifying','Fast trailing'),JSON_ARRAY('ornamental'),'Bright indirect light; avoid direct harsh sun.','Standard indoor mix.','Trim to bush out.','Monthly mild feed.','Mealybugs occasional.',NULL,NULL,NOW(),NOW()),
(23,123,'Lemon','Citrus limon',JSON_OBJECT('hi','निंबू'),'tree','outdoor','full_sun','medium','loamy',8,38,'medium','medium',400,300,JSON_ARRAY(),JSON_ARRAY('winter','year_round'),JSON_ARRAY('monsoon'),NULL,NULL,'perennial','moderate','medium','grafting','Thorns; peel oil','unknown',JSON_ARRAY('Homegrown citrus'),JSON_ARRAY('culinary'),'Sunny terrace/garden; consistent moisture.','Deep pot with rich draining soil.','Light prune for shape.','Citrus feed every 30–45 days.','Leaf miner / citrus pests.', 'Harvest when fruits color and soften slightly.',NULL,NOW(),NOW()),
(24,124,'Guava','Psidium guajava',JSON_OBJECT('hi','अमरूद'),'tree','outdoor','full_sun','medium','loamy',10,40,'medium','medium',500,350,JSON_ARRAY(),JSON_ARRAY('winter'),JSON_ARRAY('monsoon'),NULL,NULL,'perennial','easy','medium','grafting','Generally safe','safe',JSON_ARRAY('Fruit tree'),JSON_ARRAY('culinary'),'Full sun; water deeply in dry spells.','Large pit with compost.','Prune for open canopy.','Organic manure twice a year.','Fruit fly management needed.', 'Harvest ripe fragrant fruits.',NULL,NOW(),NOW()),
(25,125,'Coriander','Coriandrum sativum',JSON_OBJECT('hi','धनिया'),'herb','outdoor','partial','medium','loamy',10,30,'medium','fast',40,30,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('winter','monsoon'),NULL,NULL,'annual','easy','low','seed','Culinary','safe',JSON_ARRAY('Kitchen staple'),JSON_ARRAY('culinary'),'Sow in cool season; keep evenly moist.','Loose soil in trays/pots.','Cut and come again harvest.','Light compost.','Bolts in extreme heat.',NULL,NULL,NOW(),NOW()),
(26,126,'Chili','Capsicum annuum',JSON_OBJECT('hi','मिर्च'),'vegetable','outdoor','full_sun','medium','loamy',15,35,'medium','fast',70,40,JSON_ARRAY(),JSON_ARRAY('winter','summer'),JSON_ARRAY('monsoon','winter'),'white',NULL,'annual','easy','medium','seed','Culinary hot','safe',JSON_ARRAY('Homegrown spice'),JSON_ARRAY('culinary'),'Sunny pot; stake if needed.','Rich potting mix.','Harvest often to encourage fruiting.','Vegetable feed every 2 weeks after flowering.','Aphids and fruit borers.', 'Pick when green or red as preferred.',NULL,NOW(),NOW()),
(27,127,'Chrysanthemum','Chrysanthemum morifolium',JSON_OBJECT('hi','गुलदाउदी'),'flowering','outdoor','full_sun','medium','loamy',10,28,'medium','medium',50,40,JSON_ARRAY('winter'),JSON_ARRAY(),JSON_ARRAY('winter'),'yellow','seasonal','perennial','easy','medium','cutting','May irritate sensitive skin','unknown',JSON_ARRAY('Festive blooms'),JSON_ARRAY('ornamental'),'Bright sun and regular deadheading.','Rich mix; good drainage.','Pinch early for bushiness.','Bloom booster while budding.','Aphids common.',NULL,NULL,NOW(),NOW()),
(28,128,'Croton','Codiaeum variegatum',JSON_OBJECT('hi','क्रोटन'),'foliage','outdoor','bright_indirect','medium','well_draining',16,32,'medium','medium',120,80,JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial','moderate','medium','cutting','Sap irritant','toxic',JSON_ARRAY('Colorful leaves'),JSON_ARRAY('ornamental'),'Needs bright light for strong colors.','Well-draining rich mix.','Pinch tips for branching.','Monthly feed in growing season.','Spider mites in dry air.',NULL,NULL,NOW(),NOW());

INSERT IGNORE INTO `pot_profiles` (`id`,`product_id`,`material`,`diameter_cm`,`height_cm`,`capacity_liters`,`drainage_holes`,`indoor_outdoor_suitability`,`meta`,`created_at`,`updated_at`,`color`) VALUES
(3,203,'plastic',18.00,20.00,4.00,1,'indoor',JSON_OBJECT('self_watering',true),NOW(),NOW(),'graphite'),
(4,204,'plastic',25.00,15.00,6.00,1,'both',NULL,NOW(),NOW(),'brown'),
(5,205,'ceramic',25.00,22.00,8.00,1,'both',NULL,NOW(),NOW(),'matte_white');

INSERT IGNORE INTO `fertilizer_profiles` (`id`,`product_id`,`npk_ratio`,`suitable_plant_types`,`application_frequency`,`application_quantity`,`usage_instructions`,`organic`,`meta`,`created_at`,`updated_at`,`form`) VALUES
(3,303,'10-20-20',JSON_ARRAY('flowering','rose'),'every 15 days','1 tsp per litre','Apply to moist soil during blooming season.',0,NULL,NOW(),NOW(),'powder'),
(4,304,NULL,JSON_ARRAY('plant','tree','vegetable'),'every 15 days','2 ml per litre','Foliar spray or soil drench in morning.',1,NULL,NOW(),NOW(),'liquid');

INSERT IGNORE INTO `soil_profiles` (`id`,`product_id`,`composition`,`ph_range`,`suitable_for`,`usage_instructions`,`meta`,`created_at`,`updated_at`,`organic`,`weight_kg`) VALUES
(3,403,'Sand + Cocopeat + Perlite','6.0-7.0',JSON_ARRAY('succulent','cactus'),'Use directly for succulents; do not compact.',NULL,NOW(),NOW(),1,5.00);

INSERT IGNORE INTO `tool_profiles` (`id`,`product_id`,`material`,`size`,`usage`,`warranty_months`,`meta`,`created_at`,`updated_at`,`brand_name`) VALUES
(2,502,'steel','small','transplanting and potting',6,NULL,NOW(),NOW(),'ToolPro Garden'),
(3,503,'plastic','5L','watering pots and beds',3,NULL,NOW(),NOW(),'ToolPro Garden'),
(4,504,'fabric','M','hand protection while gardening',3,NULL,NOW(),NOW(),'ToolPro Garden');

INSERT IGNORE INTO `accessory_profiles` (`id`,`product_id`,`material`,`usage`,`indoor_outdoor_suitability`,`meta`,`created_at`,`updated_at`,`pack_qty`) VALUES
(2,602,'coir_moss','Climbing support for vines','indoor',NULL,NOW(),NOW(),1),
(3,603,'plastic','Humidity misting for foliage plants','indoor',NULL,NOW(),NOW(),1);

INSERT IGNORE INTO `product_bundles` (`id`,`product_id`,`name`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(2,702,'Balcony Flower Kit Bundle','active',NULL,NOW(),NOW(),NULL),
(3,703,'Succulent Desk Kit Bundle','active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `product_bundle_items` (`id`,`bundle_id`,`product_id`,`quantity`,`created_at`,`updated_at`,`meta`) VALUES
(4,2,106,1,NOW(),NOW(),NULL),(5,2,202,1,NOW(),NOW(),NULL),(6,2,303,1,NOW(),NOW(),NULL),
(7,3,121,1,NOW(),NOW(),NULL),(8,3,201,1,NOW(),NOW(),NULL),(9,3,403,1,NOW(),NOW(),NULL);

-- Images for new products
INSERT IGNORE INTO `product_images` (`id`,`product_id`,`url`,`alt`,`is_primary`,`sort_order`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(21,111,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','ZZ Plant',1,1,NULL,NOW(),NOW(),NULL),
(22,112,'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000','Aloe Vera',1,1,NULL,NOW(),NOW(),NULL),
(23,113,'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000','Spider Plant',1,1,NULL,NOW(),NOW(),NULL),
(24,114,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','Rubber Plant',1,1,NULL,NOW(),NOW(),NULL),
(25,115,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Boston Fern',1,1,NULL,NOW(),NOW(),NULL),
(26,116,'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000','Lavender',1,1,NULL,NOW(),NOW(),NULL),
(27,117,'https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=1000','Mint',1,1,NULL,NOW(),NOW(),NULL),
(28,118,'https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=1000','Lemongrass',1,1,NULL,NOW(),NOW(),NULL),
(29,119,'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000','Hibiscus',1,1,NULL,NOW(),NOW(),NULL),
(30,120,'https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=1000','Bougainvillea',1,1,NULL,NOW(),NOW(),NULL),
(31,121,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','Echeveria',1,1,NULL,NOW(),NOW(),NULL),
(32,122,'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000','Golden Pothos',1,1,NULL,NOW(),NOW(),NULL),
(33,123,'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000','Lemon sapling',1,1,NULL,NOW(),NOW(),NULL),
(34,124,'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000','Guava sapling',1,1,NULL,NOW(),NOW(),NULL),
(35,125,'https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=1000','Coriander seeds',1,1,NULL,NOW(),NOW(),NULL),
(36,126,'https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=1000','Chili seeds',1,1,NULL,NOW(),NOW(),NULL),
(37,127,'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000','Chrysanthemum',1,1,NULL,NOW(),NOW(),NULL),
(38,128,'https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=1000','Croton',1,1,NULL,NOW(),NOW(),NULL),
(39,203,'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000','Self watering pot',1,1,NULL,NOW(),NOW(),NULL),
(40,204,'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000','Hanging basket',1,1,NULL,NOW(),NOW(),NULL),
(41,205,'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000','Ceramic pot 10 inch',1,1,NULL,NOW(),NOW(),NULL),
(42,303,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Rose fertilizer',1,1,NULL,NOW(),NOW(),NULL),
(43,304,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Seaweed extract',1,1,NULL,NOW(),NOW(),NULL),
(44,403,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Succulent mix',1,1,NULL,NOW(),NOW(),NULL),
(45,502,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Hand trowel',1,1,NULL,NOW(),NOW(),NULL),
(46,503,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Watering can',1,1,NULL,NOW(),NOW(),NULL),
(47,504,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Garden gloves',1,1,NULL,NOW(),NOW(),NULL),
(48,602,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Moss stick',1,1,NULL,NOW(),NOW(),NULL),
(49,603,'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000','Plant mister',1,1,NULL,NOW(),NOW(),NULL),
(50,702,'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000','Balcony flower kit',1,1,NULL,NOW(),NOW(),NULL),
(51,703,'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000','Succulent desk kit',1,1,NULL,NOW(),NOW(),NULL),
(52,102,'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000','Snake Plant side view',0,2,NULL,NOW(),NOW(),NULL),
(53,103,'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000','Peace Lily bloom',0,2,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `product_categories` (`product_id`,`category_id`,`created_at`,`updated_at`) VALUES
(111,1,NOW(),NOW()),(111,11,NOW(),NOW()),(111,17,NOW(),NOW()),
(112,1,NOW(),NOW()),(112,14,NOW(),NOW()),(112,15,NOW(),NOW()),
(113,1,NOW(),NOW()),(113,12,NOW(),NOW()),(113,16,NOW(),NOW()),
(114,1,NOW(),NOW()),(114,17,NOW(),NOW()),
(115,1,NOW(),NOW()),(115,16,NOW(),NOW()),
(116,2,NOW(),NOW()),(116,3,NOW(),NOW()),(116,13,NOW(),NOW()),
(117,5,NOW(),NOW()),(117,14,NOW(),NOW()),
(118,2,NOW(),NOW()),(118,14,NOW(),NOW()),
(119,2,NOW(),NOW()),(119,3,NOW(),NOW()),(119,13,NOW(),NOW()),
(120,2,NOW(),NOW()),(120,3,NOW(),NOW()),
(121,1,NOW(),NOW()),(121,15,NOW(),NOW()),
(122,1,NOW(),NOW()),(122,11,NOW(),NOW()),(122,16,NOW(),NOW()),
(123,4,NOW(),NOW()),(123,2,NOW(),NOW()),
(124,4,NOW(),NOW()),(124,2,NOW(),NOW()),
(125,6,NOW(),NOW()),(125,5,NOW(),NOW()),
(126,6,NOW(),NOW()),(126,5,NOW(),NOW()),
(127,3,NOW(),NOW()),(127,18,NOW(),NOW()),
(128,2,NOW(),NOW()),
(203,7,NOW(),NOW()),(204,7,NOW(),NOW()),(205,7,NOW(),NOW()),
(303,8,NOW(),NOW()),(304,8,NOW(),NOW()),(403,8,NOW(),NOW()),
(502,9,NOW(),NOW()),(503,9,NOW(),NOW()),(504,9,NOW(),NOW()),
(602,10,NOW(),NOW()),(603,10,NOW(),NOW()),
(702,3,NOW(),NOW()),(702,13,NOW(),NOW()),
(703,15,NOW(),NOW()),(703,17,NOW(),NOW());

INSERT IGNORE INTO `product_tags` (`product_id`,`tag_id`,`created_at`,`updated_at`) VALUES
(111,1,NOW(),NOW()),(111,3,NOW(),NOW()),(111,6,NOW(),NOW()),(111,10,NOW(),NOW()),
(112,1,NOW(),NOW()),(112,11,NOW(),NOW()),(112,12,NOW(),NOW()),
(113,2,NOW(),NOW()),(113,9,NOW(),NOW()),(113,13,NOW(),NOW()),
(114,7,NOW(),NOW()),(114,10,NOW(),NOW()),(114,4,NOW(),NOW()),
(115,5,NOW(),NOW()),(115,13,NOW(),NOW()),
(116,8,NOW(),NOW()),(116,15,NOW(),NOW()),
(117,7,NOW(),NOW()),(117,11,NOW(),NOW()),
(118,5,NOW(),NOW()),(118,11,NOW(),NOW()),
(119,6,NOW(),NOW()),(119,8,NOW(),NOW()),
(120,8,NOW(),NOW()),(120,15,NOW(),NOW()),
(121,7,NOW(),NOW()),(121,12,NOW(),NOW()),(121,14,NOW(),NOW()),
(122,1,NOW(),NOW()),(122,6,NOW(),NOW()),(122,13,NOW(),NOW()),
(123,14,NOW(),NOW()),
(127,14,NOW(),NOW()),(127,16,NOW(),NOW()),
(203,7,NOW(),NOW()),
(304,7,NOW(),NOW()),
(503,6,NOW(),NOW()),
(702,7,NOW(),NOW()),(702,14,NOW(),NOW()),
(703,7,NOW(),NOW()),(703,10,NOW(),NOW()),(703,14,NOW(),NOW());

INSERT IGNORE INTO `product_relations` (`product_id`,`related_product_id`,`relation_type`,`sort_order`,`meta`,`created_at`,`updated_at`) VALUES
(111,205,'fbt',1,NULL,NOW(),NOW()),(111,402,'fbt',2,NULL,NOW(),NOW()),(111,102,'related',1,NULL,NOW(),NOW()),
(112,201,'fbt',1,NULL,NOW(),NOW()),(112,403,'fbt',2,NULL,NOW(),NOW()),(112,121,'related',1,NULL,NOW(),NOW()),
(113,204,'fbt',1,NULL,NOW(),NOW()),(113,603,'fbt',2,NULL,NOW(),NOW()),
(114,205,'fbt',1,NULL,NOW(),NOW()),(114,304,'fbt',2,NULL,NOW(),NOW()),
(119,202,'fbt',1,NULL,NOW(),NOW()),(119,303,'fbt',2,NULL,NOW(),NOW()),(119,106,'related',1,NULL,NOW(),NOW()),
(122,201,'fbt',1,NULL,NOW(),NOW()),(122,602,'fbt',2,NULL,NOW(),NOW()),(122,101,'related',1,NULL,NOW(),NOW()),
(121,201,'fbt',1,NULL,NOW(),NOW()),(121,403,'fbt',2,NULL,NOW(),NOW()),
(106,303,'related',1,NULL,NOW(),NOW()),
(702,106,'related',1,NULL,NOW(),NOW()),
(703,121,'related',1,NULL,NOW(),NOW());

-- Inventory for new SKUs (+ more stock at second warehouse for popular items)
INSERT IGNORE INTO `inventory_items`
(`id`,`warehouse_id`,`product_id`,`product_variant_id`,`qty_on_hand`,`qty_reserved`,`qty_damaged`,`low_stock_threshold`,`meta`,`created_at`,`updated_at`,`version`,`deleted_at`)
VALUES
(20,1,111,NULL,70,0,0,8,NULL,NOW(),NOW(),1,NULL),
(21,1,112,NULL,110,0,0,12,NULL,NOW(),NOW(),1,NULL),
(22,1,113,NULL,85,0,0,10,NULL,NOW(),NOW(),1,NULL),
(23,1,114,NULL,45,0,0,5,NULL,NOW(),NOW(),1,NULL),
(24,1,115,NULL,50,0,0,8,NULL,NOW(),NOW(),1,NULL),
(25,1,116,NULL,60,0,0,8,NULL,NOW(),NOW(),1,NULL),
(26,1,117,NULL,140,0,0,15,NULL,NOW(),NOW(),1,NULL),
(27,1,118,NULL,90,0,0,10,NULL,NOW(),NOW(),1,NULL),
(28,1,119,NULL,75,0,0,10,NULL,NOW(),NOW(),1,NULL),
(29,1,120,NULL,65,0,0,8,NULL,NOW(),NOW(),1,NULL),
(30,1,121,NULL,100,0,0,12,NULL,NOW(),NOW(),1,NULL),
(31,1,122,NULL,95,0,0,10,NULL,NOW(),NOW(),1,NULL),
(32,1,123,NULL,30,0,0,5,NULL,NOW(),NOW(),1,NULL),
(33,1,124,NULL,28,0,0,5,NULL,NOW(),NOW(),1,NULL),
(34,1,125,NULL,300,0,0,30,NULL,NOW(),NOW(),1,NULL),
(35,1,126,NULL,250,0,0,25,NULL,NOW(),NOW(),1,NULL),
(36,1,127,NULL,80,0,0,10,NULL,NOW(),NOW(),1,NULL),
(37,1,128,NULL,40,0,0,5,NULL,NOW(),NOW(),1,NULL),
(38,1,203,NULL,55,0,0,8,NULL,NOW(),NOW(),1,NULL),
(39,1,204,NULL,48,0,0,8,NULL,NOW(),NOW(),1,NULL),
(40,1,205,NULL,42,0,0,6,NULL,NOW(),NOW(),1,NULL),
(41,1,303,NULL,70,0,0,10,NULL,NOW(),NOW(),1,NULL),
(42,1,304,NULL,85,0,0,10,NULL,NOW(),NOW(),1,NULL),
(43,1,403,NULL,60,0,0,8,NULL,NOW(),NOW(),1,NULL),
(44,1,502,NULL,90,0,0,10,NULL,NOW(),NOW(),1,NULL),
(45,1,503,NULL,55,0,0,8,NULL,NOW(),NOW(),1,NULL),
(46,1,504,NULL,120,0,0,15,NULL,NOW(),NOW(),1,NULL),
(47,1,602,NULL,100,0,0,12,NULL,NOW(),NOW(),1,NULL),
(48,1,603,NULL,80,0,0,10,NULL,NOW(),NOW(),1,NULL),
(49,1,702,NULL,25,0,0,5,NULL,NOW(),NOW(),1,NULL),
(50,1,703,NULL,30,0,0,5,NULL,NOW(),NOW(),1,NULL),
(51,2,101,NULL,40,0,0,10,NULL,NOW(),NOW(),1,NULL),
(52,2,102,NULL,35,0,0,8,NULL,NOW(),NOW(),1,NULL),
(53,2,111,NULL,20,0,0,5,NULL,NOW(),NOW(),1,NULL),
(54,2,112,NULL,25,0,0,5,NULL,NOW(),NOW(),1,NULL);

-- More campaigns / banners / coupons
INSERT IGNORE INTO `campaigns`
(`id`,`slug`,`title`,`subtitle`,`description`,`type`,`season_code`,`image_url`,`starts_at`,`ends_at`,`status`,`priority`,`rules_json`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(4,'summer-balcony-2026','Summer Balcony Specials','Sun-loving color','Bougainvillea, hibiscus, lavender and pots for sunny balconies.','seasonal','summer','https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=1200','2026-03-01 00:00:00','2026-06-30 23:59:59','active',85,NULL,NULL,NOW(),NOW(),NULL),
(5,'gift-plants','Gift a Plant','Thoughtful green gifts','Kits and easy plants perfect for gifting.','evergreen','all','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1200','2026-01-01 00:00:00','2026-12-31 23:59:59','active',70,NULL,NULL,NOW(),NOW(),NULL),
(6,'winter-flowers-2026','Winter Flowering Plants','Seasonal blooms','Chrysanthemum and winter favorites.','seasonal','winter','https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1200','2026-11-01 00:00:00','2027-02-28 23:59:59','scheduled',75,NULL,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `campaign_products` (`campaign_id`,`product_id`,`sort_order`,`created_at`,`updated_at`) VALUES
(4,116,1,NOW(),NOW()),(4,119,2,NOW(),NOW()),(4,120,3,NOW(),NOW()),(4,202,4,NOW(),NOW()),(4,702,5,NOW(),NOW()),
(5,701,1,NOW(),NOW()),(5,702,2,NOW(),NOW()),(5,703,3,NOW(),NOW()),(5,121,4,NOW(),NOW()),(5,123,5,NOW(),NOW()),
(6,127,1,NOW(),NOW()),(6,106,2,NOW(),NOW()),(6,116,3,NOW(),NOW()),
(2,111,6,NOW(),NOW()),(2,113,7,NOW(),NOW()),(2,122,8,NOW(),NOW()),
(1,115,5,NOW(),NOW()),(1,118,6,NOW(),NOW());

INSERT IGNORE INTO `banners`
(`id`,`title`,`image_url`,`placement`,`link_type`,`link_value`,`sort_order`,`starts_at`,`ends_at`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(4,'Gift Green','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1400','home','campaign','gift-plants',4,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL),
(5,'Summer Balcony','https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=1400','home','campaign','summer-balcony-2026',5,'2026-03-01 00:00:00','2026-06-30 23:59:59','active',NULL,NOW(),NOW(),NULL),
(6,'Office Plants','https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1400','home','category','office-plants',6,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL),
(7,'Tools & Care','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1400','catalog','category','gardening-tools',1,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `coupons`
(`id`,`code`,`name`,`discount_type`,`discount_value`,`min_order_amount`,`max_discount_amount`,`usage_limit_total`,`usage_limit_per_user`,`starts_at`,`ends_at`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`is_public`,`stackable`)
VALUES
(4,'GREEN15','Green 15% Off','percent',15.00,799.00,300.00,300,1,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0),
(5,'GIFT100','Gift Flat 100','fixed',100.00,999.00,100.00,200,1,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0),
(6,'FIRST50','First Order 50','fixed',50.00,299.00,50.00,5000,1,'2026-01-01 00:00:00','2026-12-31 23:59:59','active',NULL,NOW(),NOW(),NULL,1,0);

-- Wishlists / carts for more users
INSERT IGNORE INTO `wishlists` (`id`,`user_id`,`product_id`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(4,3,111,NULL,NOW(),NOW(),NULL),(5,3,122,NULL,NOW(),NOW(),NULL),(6,3,702,NULL,NOW(),NOW(),NULL),
(7,5,112,NULL,NOW(),NOW(),NULL),(8,5,117,NULL,NOW(),NOW(),NULL),(9,5,703,NULL,NOW(),NOW(),NULL),
(10,6,119,NULL,NOW(),NOW(),NULL),(11,6,120,NULL,NOW(),NOW(),NULL),
(12,7,114,NULL,NOW(),NOW(),NULL),(13,7,205,NULL,NOW(),NOW(),NULL),
(14,9,123,NULL,NOW(),NOW(),NULL),(15,9,701,NULL,NOW(),NOW(),NULL),
(16,10,503,NULL,NOW(),NOW(),NULL),(17,4,122,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `carts` (`id`,`user_id`,`cart_token`,`currency`,`coupon_code`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(3,5,NULL,'INR','FIRST50','active',NULL,NOW(),NOW(),NULL),
(4,7,NULL,'INR',NULL,'active',NULL,NOW(),NOW(),NULL),
(5,9,NULL,'INR',NULL,'active',NULL,NOW(),NOW(),NULL),
(6,NULL,'guest_cart_demo_002','INR',NULL,'active',NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `cart_items` (`id`,`cart_id`,`product_id`,`product_variant_id`,`quantity`,`unit_price_snapshot`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(4,1,301,NULL,1,149.00,NULL,NOW(),NOW(),NULL),
(5,1,401,NULL,1,179.00,NULL,NOW(),NOW(),NULL),
(6,3,112,NULL,1,249.00,NULL,NOW(),NOW(),NULL),
(7,3,117,NULL,2,129.00,NULL,NOW(),NOW(),NULL),
(8,3,201,NULL,1,249.00,NULL,NOW(),NOW(),NULL),
(9,4,114,NULL,1,699.00,NULL,NOW(),NOW(),NULL),
(10,4,205,NULL,1,449.00,NULL,NOW(),NOW(),NULL),
(11,5,703,NULL,1,549.00,NULL,NOW(),NOW(),NULL),
(12,5,603,NULL,1,179.00,NULL,NOW(),NOW(),NULL),
(13,6,121,NULL,2,199.00,NULL,NOW(),NOW(),NULL),
(14,6,203,NULL,1,399.00,NULL,NOW(),NOW(),NULL);

-- More orders / payments / shipments
INSERT IGNORE INTO `orders`
(`id`,`order_number`,`user_id`,`status`,`currency`,`subtotal`,`discount_total`,`tax_total`,`shipping_total`,`grand_total`,
 `coupon_code`,`payment_method`,`shipping_method_id`,`notes`,`shipping_address_json`,`billing_address_json`,
 `placed_at`,`confirmed_at`,`cancelled_at`,`cancel_reason`,`meta`,`created_at`,`updated_at`,`deleted_at`,`ip`,`platform`,`request_id`,`warehouse_id`)
VALUES
(9004,'ORD-20260715-00004',5,'DELIVERED','INR',498.00,50.00,0.00,49.00,497.00,
 'FIRST50','razorpay',1,NULL,
 JSON_OBJECT('name','Meera Patel','phone','9876543212','line1','15 Garden Lane','city','Ahmedabad','state','Gujarat','postal_code','380015','country','IN'),NULL,
 '2026-07-15 11:00:00','2026-07-15 11:02:00',NULL,NULL,NULL,'2026-07-15 11:00:00','2026-07-18 17:30:00',NULL,'49.15.20.1','android','req_seed_9004',1),
(9005,'ORD-20260805-00005',6,'OUT_FOR_DELIVERY','INR',748.00,0.00,0.00,99.00,847.00,
 NULL,'razorpay',2,'Call before delivery',
 JSON_OBJECT('name','Kabir Singh','phone','9876543213','line1','22 Palm Avenue','city','Mumbai','state','Maharashtra','postal_code','400001','country','IN'),NULL,
 '2026-08-05 09:30:00','2026-08-05 09:32:00',NULL,NULL,NULL,'2026-08-05 09:30:00','2026-08-09 08:00:00',NULL,'103.88.12.4','android','req_seed_9005',1),
(9006,'ORD-20260807-00006',7,'PROCESSING','INR',1148.00,100.00,0.00,49.00,1097.00,
 'GIFT100','razorpay',1,NULL,
 JSON_OBJECT('name','Sneha Reddy','phone','9876543214','line1','7 Jubilee Hills Road','city','Hyderabad','state','Telangana','postal_code','500033','country','IN'),NULL,
 '2026-08-07 16:10:00','2026-08-07 16:12:00',NULL,NULL,NULL,'2026-08-07 16:10:00','2026-08-08 10:00:00',NULL,'49.207.1.9','ios','req_seed_9006',1),
(9007,'ORD-20260809-00007',9,'CONFIRMED','INR',899.00,0.00,0.00,49.00,948.00,
 NULL,'cod',1,'COD order',
 JSON_OBJECT('name','Priya Nair','phone','9876543216','line1','18 Marine Drive','city','Kochi','state','Kerala','postal_code','682001','country','IN'),NULL,
 '2026-08-09 13:00:00','2026-08-09 13:00:00',NULL,NULL,NULL,'2026-08-09 13:00:00','2026-08-09 13:00:00',NULL,'103.44.1.2','android','req_seed_9007',1),
(9008,'ORD-20260802-00008',10,'CANCELLED','INR',399.00,0.00,0.00,49.00,448.00,
 NULL,'razorpay',1,'Changed mind',
 JSON_OBJECT('name','Vikram Joshi','phone','9876543217','line1','9 MG Road','city','Indore','state','Madhya Pradesh','postal_code','452001','country','IN'),NULL,
 '2026-08-02 19:00:00',NULL,'2026-08-02 19:20:00','Changed mind',NULL,'2026-08-02 19:00:00','2026-08-02 19:20:00',NULL,'49.36.90.2','web','req_seed_9008',1),
(9009,'ORD-20260728-00009',4,'DELIVERED','INR',628.00,0.00,0.00,49.00,677.00,
 NULL,'razorpay',1,NULL,
 JSON_OBJECT('name','Ravi Sharma','phone','9876543211','line1','88 Lake View Road','city','Bengaluru','state','Karnataka','postal_code','560001','country','IN'),NULL,
 '2026-07-28 12:00:00','2026-07-28 12:03:00',NULL,NULL,NULL,'2026-07-28 12:00:00','2026-08-01 15:00:00',NULL,'49.36.12.10','android','req_seed_9009',1);

INSERT IGNORE INTO `order_items`
(`id`,`order_id`,`product_id`,`product_variant_id`,`sku`,`name`,`unit_price`,`quantity`,`line_total`,`product_type`,`thumbnail_url`,`meta`,`created_at`,`updated_at`,`tax_amount`,`discount_amount`)
VALUES
(5,9004,112,NULL,'PLT-ALOE-001','Aloe Vera',249.00,1,249.00,'plant','https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=400',NULL,'2026-07-15 11:00:00','2026-07-15 11:00:00',0.00,25.00),
(6,9004,117,NULL,'PLT-MINT-001','Mint',129.00,1,129.00,'plant','https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=400',NULL,'2026-07-15 11:00:00','2026-07-15 11:00:00',0.00,15.00),
(7,9004,125,NULL,'SED-CORI-001','Coriander Seeds Pack',49.00,2,98.00,'seed','https://images.unsplash.com/photo-1466692476866-aef1dfb1e735?w=400',NULL,'2026-07-15 11:00:00','2026-07-15 11:00:00',0.00,10.00),
(8,9005,119,NULL,'PLT-HIB-RED-001','Hibiscus (Red)',399.00,1,399.00,'plant','https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=400',NULL,'2026-08-05 09:30:00','2026-08-05 09:30:00',0.00,0.00),
(9,9005,120,NULL,'PLT-BOUG-001','Bougainvillea',349.00,1,349.00,'plant','https://images.unsplash.com/photo-1463936575829-25148e1670d9?w=400',NULL,'2026-08-05 09:30:00','2026-08-05 09:30:00',0.00,0.00),
(10,9006,114,NULL,'PLT-RUBBER-001','Rubber Plant',699.00,1,699.00,'plant','https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=400',NULL,'2026-08-07 16:10:00','2026-08-07 16:10:00',0.00,60.00),
(11,9006,205,NULL,'POT-CER-10','Ceramic Pot 10 inch',449.00,1,449.00,'pot','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=400',NULL,'2026-08-07 16:10:00','2026-08-07 16:10:00',0.00,40.00),
(12,9007,123,NULL,'TRE-LEMON-001','Lemon Sapling',749.00,1,749.00,'tree','https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=400',NULL,'2026-08-09 13:00:00','2026-08-09 13:00:00',0.00,0.00),
(13,9007,502,NULL,'TOL-TROW-01','Hand Trowel',199.00,1,199.00,'tool','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400',NULL,'2026-08-09 13:00:00','2026-08-09 13:00:00',0.00,0.00),
(14,9008,111,NULL,'PLT-ZZ-001','ZZ Plant',549.00,1,399.00,'plant','https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=400',NULL,'2026-08-02 19:00:00','2026-08-02 19:00:00',0.00,0.00),
(15,9009,122,NULL,'PLT-POTHOS-001','Pothos Golden',329.00,1,329.00,'plant','https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=400',NULL,'2026-07-28 12:00:00','2026-07-28 12:00:00',0.00,0.00),
(16,9009,201,NULL,'POT-CER-6','Ceramic Pot 6 inch',249.00,1,249.00,'pot','https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=400',NULL,'2026-07-28 12:00:00','2026-07-28 12:00:00',0.00,0.00),
(17,9009,301,NULL,'FER-VERMI-1','Organic Vermicompost 1kg',149.00,1,149.00,'fertilizer','https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400',NULL,'2026-07-28 12:00:00','2026-07-28 12:00:00',0.00,0.00);

INSERT IGNORE INTO `order_status_histories` (`order_id`,`from_status`,`to_status`,`actor_user_id`,`note`,`meta`,`created_at`,`request_id`) VALUES
(9004,NULL,'PENDING_PAYMENT',5,'Order placed',NULL,'2026-07-15 11:00:00','req_seed_9004'),
(9004,'PENDING_PAYMENT','CONFIRMED',NULL,'Paid',NULL,'2026-07-15 11:02:00',NULL),
(9004,'CONFIRMED','DELIVERED',2,'Delivered',NULL,'2026-07-18 17:30:00',NULL),
(9005,NULL,'PENDING_PAYMENT',6,'Order placed',NULL,'2026-08-05 09:30:00','req_seed_9005'),
(9005,'PENDING_PAYMENT','CONFIRMED',NULL,'Paid',NULL,'2026-08-05 09:32:00',NULL),
(9005,'CONFIRMED','SHIPPED',12,'Shipped express',NULL,'2026-08-07 10:00:00',NULL),
(9005,'SHIPPED','OUT_FOR_DELIVERY',NULL,'OFD Mumbai',NULL,'2026-08-09 08:00:00',NULL),
(9006,NULL,'PENDING_PAYMENT',7,'Order placed',NULL,'2026-08-07 16:10:00','req_seed_9006'),
(9006,'PENDING_PAYMENT','CONFIRMED',NULL,'Paid',NULL,'2026-08-07 16:12:00',NULL),
(9006,'CONFIRMED','PROCESSING',12,'Preparing',NULL,'2026-08-08 10:00:00',NULL),
(9007,NULL,'PENDING_PAYMENT',9,'COD placed',NULL,'2026-08-09 13:00:00','req_seed_9007'),
(9007,'PENDING_PAYMENT','CONFIRMED',12,'COD confirmed',NULL,'2026-08-09 13:00:00',NULL),
(9008,NULL,'PENDING_PAYMENT',10,'Order placed',NULL,'2026-08-02 19:00:00','req_seed_9008'),
(9008,'PENDING_PAYMENT','CANCELLED',10,'Customer cancelled',NULL,'2026-08-02 19:20:00',NULL),
(9009,NULL,'PENDING_PAYMENT',4,'Order placed',NULL,'2026-07-28 12:00:00','req_seed_9009'),
(9009,'PENDING_PAYMENT','CONFIRMED',NULL,'Paid',NULL,'2026-07-28 12:03:00',NULL),
(9009,'CONFIRMED','DELIVERED',2,'Delivered',NULL,'2026-08-01 15:00:00',NULL);

INSERT IGNORE INTO `payments`
(`id`,`order_id`,`user_id`,`provider`,`method`,`amount`,`currency`,`status`,`idempotency_key`,`provider_order_id`,`provider_payment_id`,`provider_signature`,`failure_code`,`failure_message`,`paid_at`,`raw_response_json`,`meta`,`created_at`,`updated_at`,`deleted_at`)
VALUES
(5504,9004,5,'razorpay','razorpay',497.00,'INR','success','idem_9004_1','order_seed_9004','pay_seed_9004','sig_seed',NULL,NULL,'2026-07-15 11:02:00',JSON_OBJECT('seed',true),NULL,'2026-07-15 11:00:30','2026-07-15 11:02:00',NULL),
(5505,9005,6,'razorpay','razorpay',847.00,'INR','success','idem_9005_1','order_seed_9005','pay_seed_9005','sig_seed',NULL,NULL,'2026-08-05 09:32:00',JSON_OBJECT('seed',true),NULL,'2026-08-05 09:30:20','2026-08-05 09:32:00',NULL),
(5506,9006,7,'razorpay','razorpay',1097.00,'INR','success','idem_9006_1','order_seed_9006','pay_seed_9006','sig_seed',NULL,NULL,'2026-08-07 16:12:00',JSON_OBJECT('seed',true),NULL,'2026-08-07 16:10:20','2026-08-07 16:12:00',NULL),
(5507,9007,9,'cod','cod',948.00,'INR','pending','idem_9007_1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,JSON_OBJECT('cod',true),'2026-08-09 13:00:00','2026-08-09 13:00:00',NULL),
(5508,9008,10,'razorpay','razorpay',448.00,'INR','cancelled','idem_9008_1','order_seed_9008',NULL,NULL,'CANCELLED','User cancelled','2026-08-02 19:20:00',NULL,NULL,'2026-08-02 19:00:20','2026-08-02 19:20:00',NULL),
(5509,9009,4,'razorpay','razorpay',677.00,'INR','success','idem_9009_1','order_seed_9009','pay_seed_9009','sig_seed',NULL,NULL,'2026-07-28 12:03:00',JSON_OBJECT('seed',true),NULL,'2026-07-28 12:00:20','2026-07-28 12:03:00',NULL);

INSERT IGNORE INTO `shipments`
(`id`,`order_id`,`status`,`carrier`,`tracking_number`,`tracking_url`,`shipped_at`,`delivered_at`,`meta`,`created_at`,`updated_at`,`deleted_at`,`shipping_method_id`,`warehouse_id`,`eta_date`,`weight_grams`)
VALUES
(3,9004,'DELIVERED','BlueDart','BDSEED9004','https://track.example.com/BDSEED9004','2026-07-16 10:00:00','2026-07-18 17:30:00',NULL,'2026-07-16 10:00:00','2026-07-18 17:30:00',NULL,1,1,'2026-07-18',1800),
(4,9005,'OUT_FOR_DELIVERY','Delhivery','DLVSEED9005','https://track.example.com/DLVSEED9005','2026-08-07 10:00:00',NULL,NULL,'2026-08-07 10:00:00','2026-08-09 08:00:00',NULL,2,1,'2026-08-09',3200),
(5,9009,'DELIVERED','Delhivery','DLVSEED9009','https://track.example.com/DLVSEED9009','2026-07-29 14:00:00','2026-08-01 15:00:00',NULL,'2026-07-29 14:00:00','2026-08-01 15:00:00',NULL,1,1,'2026-08-01',2100);

INSERT IGNORE INTO `shipment_events` (`shipment_id`,`status`,`description`,`event_at`,`meta`,`created_at`,`updated_at`,`location`) VALUES
(3,'SHIPPED','Picked up','2026-07-16 10:00:00',NULL,NOW(),NOW(),'Pune'),
(3,'DELIVERED','Delivered','2026-07-18 17:30:00',NULL,NOW(),NOW(),'Ahmedabad'),
(4,'SHIPPED','Picked up','2026-08-07 10:00:00',NULL,NOW(),NOW(),'Pune'),
(4,'IN_TRANSIT','In transit to Mumbai','2026-08-08 09:00:00',NULL,NOW(),NOW(),'Hub'),
(4,'OUT_FOR_DELIVERY','Out for delivery','2026-08-09 08:00:00',NULL,NOW(),NOW(),'Mumbai'),
(5,'SHIPPED','Picked up','2026-07-29 14:00:00',NULL,NOW(),NOW(),'Pune'),
(5,'DELIVERED','Delivered','2026-08-01 15:00:00',NULL,NOW(),NOW(),'Bengaluru');

-- More reviews (approved + pending) for richer product pages
INSERT IGNORE INTO `reviews`
(`id`,`product_id`,`user_id`,`order_id`,`rating`,`title`,`body`,`status`,`meta`,`created_at`,`updated_at`,`deleted_at`,`moderated_by`,`moderated_at`)
VALUES
(5,112,5,9004,5,'Useful medicinal plant','Aloe arrived healthy. Great for home first-aid gel.','approved',NULL,'2026-07-19 10:00:00','2026-07-19 11:00:00',NULL,2,'2026-07-19 11:00:00'),
(6,117,5,9004,4,'Fresh mint','Growing well in kitchen window.','approved',NULL,'2026-07-19 10:05:00','2026-07-19 11:00:00',NULL,2,'2026-07-19 11:00:00'),
(7,119,6,9005,5,'Blooming already','Hibiscus looks premium. Fast delivery.','pending',NULL,'2026-08-09 20:00:00','2026-08-09 20:00:00',NULL,NULL,NULL),
(8,122,4,9009,5,'Beautiful pothos','Trailing nicely within a week.','approved',NULL,'2026-08-02 09:00:00','2026-08-02 10:00:00',NULL,2,'2026-08-02 10:00:00'),
(9,111,8,NULL,5,'Perfect office plant','ZZ plant needs almost no water. Love it.','approved',NULL,'2026-07-10 08:00:00','2026-07-10 09:00:00',NULL,2,'2026-07-10 09:00:00'),
(10,111,9,NULL,4,'Glossy leaves','Packaging was secure. Slightly smaller than expected.','approved',NULL,'2026-07-12 12:00:00','2026-07-12 13:00:00',NULL,2,'2026-07-12 13:00:00'),
(11,113,7,NULL,5,'Pet-friendly choice','Spider plant is thriving in hanging basket.','approved',NULL,'2026-07-22 15:00:00','2026-07-22 16:00:00',NULL,2,'2026-07-22 16:00:00'),
(12,106,3,NULL,4,'Good rose plant','Healthy stems, waiting for next bloom flush.','approved',NULL,'2026-06-30 11:00:00','2026-06-30 12:00:00',NULL,2,'2026-06-30 12:00:00'),
(13,701,3,NULL,5,'Best starter kit','Everything needed in one box. Gifted to a friend too.','approved',NULL,'2026-07-05 14:00:00','2026-07-05 15:00:00',NULL,2,'2026-07-05 15:00:00'),
(14,703,9,NULL,5,'Cute desk kit','Echeveria + pot combo looks premium on my desk.','approved',NULL,'2026-08-01 10:00:00','2026-08-01 11:00:00',NULL,2,'2026-08-01 11:00:00'),
(15,503,10,NULL,4,'Useful watering can','Light and easy to pour.','approved',NULL,'2026-07-18 09:00:00','2026-07-18 10:00:00',NULL,2,'2026-07-18 10:00:00'),
(16,102,6,NULL,5,'Snake plant champion','Still perfect after 2 months of neglect.','approved',NULL,'2026-07-01 08:00:00','2026-07-01 09:00:00',NULL,2,'2026-07-01 09:00:00'),
(17,107,7,NULL,5,'Tulsi for home','Fresh aroma every morning.','approved',NULL,'2026-06-20 07:00:00','2026-06-20 08:00:00',NULL,2,'2026-06-20 08:00:00'),
(18,120,8,NULL,4,'Colorful climber','Needs full sun — now flowering well.','approved',NULL,'2026-07-08 16:00:00','2026-07-08 17:00:00',NULL,2,'2026-07-08 17:00:00'),
(19,201,4,9009,5,'Nice ceramic pot','Matches indoor décor perfectly.','approved',NULL,'2026-08-02 09:10:00','2026-08-02 10:00:00',NULL,2,'2026-08-02 10:00:00'),
(20,301,4,9009,5,'Quality compost','Plants responded quickly after mixing.','approved',NULL,'2026-08-02 09:15:00','2026-08-02 10:00:00',NULL,2,'2026-08-02 10:00:00');

-- Refresh denormalized ratings for heavily reviewed products
UPDATE `products` SET `rating_avg` = 4.70, `rating_count` = 130 WHERE `id` = 101;
UPDATE `products` SET `rating_avg` = 4.75, `rating_count` = 212 WHERE `id` = 102;
UPDATE `products` SET `rating_avg` = 4.70, `rating_count` = 97 WHERE `id` = 111;
UPDATE `products` SET `rating_avg` = 4.80, `rating_count` = 30 WHERE `id` = 701;

INSERT IGNORE INTO `notifications` (`id`,`user_id`,`type`,`title`,`body`,`data_json`,`is_read`,`read_at`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(4,5,'order_delivered','Order delivered','Order ORD-20260715-00004 was delivered.',JSON_OBJECT('order_id',9004),0,NULL,NULL,'2026-07-18 17:35:00','2026-07-18 17:35:00',NULL),
(5,6,'order_shipped','Out for delivery','Order ORD-20260805-00005 is out for delivery.',JSON_OBJECT('order_id',9005,'tracking_number','DLVSEED9005'),0,NULL,NULL,'2026-08-09 08:05:00','2026-08-09 08:05:00',NULL),
(6,7,'order_confirmed','Order confirmed','Order ORD-20260807-00006 is confirmed and processing.',JSON_OBJECT('order_id',9006),1,'2026-08-07 17:00:00',NULL,'2026-08-07 16:15:00','2026-08-07 17:00:00',NULL),
(7,9,'order_confirmed','COD order confirmed','Order ORD-20260809-00007 confirmed (Cash on Delivery).',JSON_OBJECT('order_id',9007),0,NULL,NULL,'2026-08-09 13:05:00','2026-08-09 13:05:00',NULL),
(8,3,'campaign','Gift a Plant festival','Explore curated gift kits with special offers.',JSON_OBJECT('campaign','gift-plants'),0,NULL,NULL,'2026-08-08 09:00:00','2026-08-08 09:00:00',NULL),
(9,4,'campaign','Summer balcony picks','Hibiscus & Bougainvillea are trending this week.',JSON_OBJECT('campaign','summer-balcony-2026'),0,NULL,NULL,'2026-08-08 09:00:00','2026-08-08 09:00:00',NULL),
(10,10,'promo','FLAT50 still available','Use FLAT50 on orders above ₹399.',JSON_OBJECT('coupon','FLAT50'),0,NULL,NULL,'2026-08-08 10:00:00','2026-08-08 10:00:00',NULL),
(11,6,'wishlist','Price drop alert','Hibiscus is on a seasonal offer in Summer Balcony campaign.',JSON_OBJECT('product_id',119),0,NULL,NULL,'2026-08-06 11:00:00','2026-08-06 11:00:00',NULL);

INSERT IGNORE INTO `support_tickets` (`id`,`user_id`,`order_id`,`subject`,`status`,`priority`,`meta`,`created_at`,`updated_at`,`deleted_at`,`assigned_to`) VALUES
(2,6,9005,'Need delivery slot change','open','high',NULL,NOW(),NOW(),NULL,2),
(3,7,9006,'Ask about Rubber Plant light needs','open','normal',NULL,NOW(),NOW(),NULL,2);

INSERT IGNORE INTO `support_messages` (`id`,`ticket_id`,`user_id`,`message`,`created_at`,`updated_at`,`meta`,`is_staff`) VALUES
(3,2,6,'Can you deliver after 7 PM today?',NOW(),NOW(),NULL,0),
(4,2,2,'We have informed the courier for evening delivery attempt.',NOW(),NOW(),NULL,1),
(5,3,7,'My flat gets only morning sun. Will Rubber Plant be okay?',NOW(),NOW(),NULL,0),
(6,3,2,'Yes — place it near the brightest window and rotate weekly.',NOW(),NOW(),NULL,1);

INSERT IGNORE INTO `api_request_logs`
(`id`,`request_id`,`method`,`path`,`route_name`,`status_code`,`user_id`,`platform`,`app_version`,`ip`,`user_agent`,
 `request_headers_json`,`request_body_json`,`response_body_json`,`error_code`,`duration_ms`,`created_at`,`meta`,`correlation_id`,`endpoint_code`,`is_success`,`deleted_at`)
VALUES
(5,'req_seed_search','GET','/api/v1/search','search',200,NULL,'android','1.0.1','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true,'message','Products retrieved successfully'),NULL,52,NOW(),JSON_OBJECT('q','indoor plants'),NULL,'SEARCH-01',1,NULL),
(6,'req_seed_plants','GET','/api/v1/plants','plants.index',200,NULL,'ios','1.0.1','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true),NULL,61,NOW(),JSON_OBJECT('filter','difficulty_level=easy'),NULL,'PLANT-01',1,NULL),
(7,'req_seed_wish','GET','/api/v1/wishlist','wishlist.index',200,3,'android','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true),NULL,33,NOW(),NULL,NULL,'WISH-01',1,NULL),
(8,'req_seed_orders','GET','/api/v1/orders','orders.index',200,3,'ios','1.0.0','103.21.45.67','GreenLeafApp/1.0',
 NULL,NULL,JSON_OBJECT('success',true),NULL,40,NOW(),NULL,NULL,'ORDER-02',1,NULL),
(9,'req_seed_camp','GET','/api/v1/campaigns/monsoon-plants-2026','campaigns.show',200,NULL,'web','','49.36.1.1','Mozilla',
 NULL,NULL,JSON_OBJECT('success',true),NULL,29,NOW(),NULL,NULL,'CAMP-02',1,NULL),
(10,'req_seed_admin_inv','GET','/api/v1/admin/inventory','admin.inventory',200,11,'web','','127.0.0.1','AdminPanel',
 NULL,NULL,JSON_OBJECT('success',true),NULL,70,NOW(),NULL,NULL,'ADM-INV-01',1,NULL);

-- =============================================================================
-- APPENDIX: Safe insert patterns (reference)
-- =============================================================================
-- 1) INSERT IGNORE (used above) — skip on duplicate PK/UNIQUE
--    INSERT IGNORE INTO users (id, email, ...) VALUES (3, 'asha@example.com', ...);
--
-- 2) INSERT ... WHERE NOT EXISTS — skip by business key even without fixed id
--    INSERT INTO tags (name, slug, created_at, updated_at, status)
--    SELECT 'Beginner', 'beginner', NOW(), NOW(), 'active'
--    WHERE NOT EXISTS (SELECT 1 FROM tags WHERE slug = 'beginner');
--
-- 3) ON DUPLICATE KEY UPDATE — insert or refresh selected columns
--    INSERT INTO settings (`key`, `value`, `type`, `group_name`, created_at, updated_at)
--    VALUES ('store.name', 'GreenLeaf Nursery', 'string', 'store', NOW(), NOW())
--    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW();
--
-- =============================================================================
-- DONE
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Safe sample data apply finished (existing rows skipped via INSERT IGNORE)' AS status;
SELECT 'ALL sample passwords = Secret@123 | See SAMPLE_LOGIN_CREDENTIALS.md for full email list' AS credentials;
SELECT COUNT(*) AS products FROM products;
SELECT COUNT(*) AS categories FROM categories;
SELECT COUNT(*) AS customers FROM users WHERE id >= 3 AND id <= 10;
SELECT COUNT(*) AS orders FROM orders;
SELECT COUNT(*) AS reviews FROM reviews;
SELECT COUNT(*) AS banners FROM banners;
