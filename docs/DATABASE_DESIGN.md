# Nursery Platform — Database Design Guide

Companion to `PROJECT_DEVELOPMENT_GUIDE.md`.

This file is the **database contract**: how to switch databases, every table name, columns, mapping strategy, request/response logging, and how to upgrade safely later.

| Item | Value |
|------|--------|
| Engine | MySQL 8 |
| Charset | `utf8mb4` / collation `utf8mb4_unicode_ci` |
| ORM | Laravel Eloquent + migrations |
| Money | `DECIMAL(12,2)` |
| Primary keys | `BIGINT UNSIGNED AUTO_INCREMENT` (internal) |
| Public codes | `order_number`, `sku`, `slug` (strings, unique) |
| Soft delete | `deleted_at` where listed |
| Timestamps | `created_at`, `updated_at` on almost every table |

**Rule:** Change this file first when adding/renaming columns or tables, then write a Laravel migration.

---

## Table of Contents

1. [Database switching mechanism](#1-database-switching-mechanism)
2. [Design principles (future-safe)](#2-design-principles-future-safe)
3. [Extensibility toolkit](#3-extensibility-toolkit)
4. [Request & response logging](#4-request--response-logging)
5. [Table catalog (names + column counts)](#5-table-catalog-names--column-counts)
6. [Full column definitions](#6-full-column-definitions)
7. [Mapping & relation tables](#7-mapping--relation-tables)
8. [Indexes & constraints](#8-indexes--constraints)
9. [Migration & upgrade playbook](#9-migration--upgrade-playbook)
10. [Seed & environment data](#10-seed--environment-data)
11. [Checklist before go-live](#11-checklist-before-go-live)

**Sample data SQL:** after migrations, load [`nursery_sample_data.sql`](./nursery_sample_data.sql) for professional demo content (products, campaigns, orders, logins).

---

## 1. Database switching mechanism

Use **one codebase**, switch DB with environment variables. Never hardcode host/user/password in PHP.

### 1.1 `.env` switching (primary technique)

```env
# ---- LOCAL ----
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursery_local
DB_USERNAME=root
DB_PASSWORD=secret

# ---- STAGING (Hostinger / test) ----
# APP_ENV=staging
# DB_HOST=localhost
# DB_DATABASE=u123_nursery_stg
# DB_USERNAME=u123_stg
# DB_PASSWORD=********

# ---- PRODUCTION ----
# APP_ENV=production
# DB_HOST=localhost
# DB_DATABASE=u123_nursery_prod
# DB_USERNAME=u123_prod
# DB_PASSWORD=********
```

Laravel reads these in `config/database.php`:

```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'nursery'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => true,
    'engine' => 'InnoDB',
],
```

### 1.2 Recommended database names

| Environment | Database name example | Purpose |
|-------------|----------------------|---------|
| Local | `nursery_local` | Developer machine |
| Staging | `nursery_staging` | QA / client UAT |
| Production | `nursery_production` | Live |

Use **separate DB users** per environment with least privilege.

### 1.3 Optional named connections (advanced)

For rare cases (reporting replica, import DB):

```env
DB_CONNECTION=mysql

DB_HOST=127.0.0.1
DB_DATABASE=nursery_local

DB_REPORT_HOST=127.0.0.1
DB_REPORT_DATABASE=nursery_reports
```

```php
// config/database.php
'reporting' => [
    'driver' => 'mysql',
    'host' => env('DB_REPORT_HOST'),
    'database' => env('DB_REPORT_DATABASE'),
    // ...
],
```

```php
DB::connection('reporting')->table('...');
```

Day-1 nursery app needs only the default `mysql` connection.

### 1.4 Switch steps (safe)

```bash
# 1) Point .env to target DB
# 2) Clear config cache
php artisan config:clear

# 3) Run migrations on that DB
php artisan migrate --force

# 4) (optional) seed
php artisan db:seed --force
```

Never run experimental migrations on production without backup.

### 1.5 Backup before switch / migrate

```bash
mysqldump -u USER -p nursery_production > backup_$(date +%F_%H%M).sql
```

---

## 2. Design principles (future-safe)

| Principle | Practice |
|-----------|----------|
| Stable core columns | Keep money, status, FK, SKU as real typed columns |
| Flexible extras | Put rare/future fields in `meta` JSON or attribute tables |
| Mapping tables | Many-to-many via pivot tables (never CSV in one column) |
| Soft delete | Prefer `deleted_at` over hard delete for catalog/customers |
| Audit everything important | `audit_logs` + `api_request_logs` + status histories |
| No business logic in DB triggers (phase 1) | Keep logic in Laravel services |
| Additive migrations | Prefer ADD COLUMN / new tables; avoid renames that break clients |
| Version public API separately | DB can evolve; `/api/v1` stays stable with Resources |

### 2.1 Standard columns on business tables

Most tables include:

| Column | Type | Why |
|--------|------|-----|
| `id` | BIGINT UNSIGNED PK | Internal identity |
| `created_at` | TIMESTAMP | Audit |
| `updated_at` | TIMESTAMP | Audit |
| `deleted_at` | TIMESTAMP NULL | Soft delete (where listed) |
| `meta` | JSON NULL | Future custom fields without new migration every time |

Use `meta` for **non-critical / rare** extras. Do **not** put price, stock, order status only inside `meta`.

### 2.2 Status columns

Store statuses as `VARCHAR(40)` (or backed enum in PHP), not free text essays.

Examples: `draft`, `active`, `PENDING_PAYMENT`, `CONFIRMED`, `success`.

---

## 3. Extensibility toolkit

Use these four techniques together.

### 3.1 Technique A — Typed extension tables (product types)

```
products  (commercial core)
   ├── plant_profiles
   ├── fertilizer_profiles
   ├── pot_profiles
   └── ...
```

**When to use:** Fields you filter/search often (sunlight, NPK, pot diameter).

### 3.2 Technique B — `meta` JSON column

```sql
meta JSON NULL
-- example: {"display_priority": 10, "legacy_sku": "OLD-123"}
```

**When to use:** Occasional flags, vendor-specific extras, A/B test markers.

**Rules:**
- Document keys in this file under “Meta key registry”
- Validate known keys in FormRequest when possible
- Do not query JSON for hot filters in phase 1 if you can use a real column

### 3.3 Technique C — Attribute definition + values (EAV-lite)

For unknown future product specs without new tables every time:

| Table | Role |
|-------|------|
| `attribute_definitions` | Declares attribute (`npk_secondary`, `pot_color_code`) |
| `attribute_values` | Value per product/variant |

**When to use:** Specs that vary widely and are shown on PDP but not always filtered.

### 3.4 Technique D — Mapping / pivot tables

Never store `category_ids = "1,2,3"`. Use:

- `product_categories`
- `product_tags`
- `campaign_products`
- `role_permission`
- `user_role`

**When to use:** any many-to-many or configurable link.

### 3.5 Technique E — Column mapping registry (upgrade helper)

Table `schema_field_maps` describes logical field → physical column for admin tools / importers / future migrations.

See §6 table `schema_field_maps`.

### 3.6 Meta key registry (start empty, grow here)

| Entity | Meta key | Type | Meaning |
|--------|----------|------|---------|
| `products` | `legacy_sku` | string | Old ERP code |
| `products` | `display_priority` | int | Manual sort boost |
| `orders` | `gift_message` | string | Optional gift note |
| `users` | `preferred_language` | string | `en` / `hi` |

Add rows to this registry whenever you invent a new `meta` key.

---

## 4. Request & response logging

To maintain / debug the whole project’s API traffic (Postman, mobile, web, webhooks), use dedicated log tables.  
This is **not** a replacement for domain tables; it is observability.

### 4.1 What to store

| Store | Do not store |
|-------|----------------|
| Method, path, status code | Passwords (mask) |
| Request headers (redacted) | Full payment card data |
| Request body (redacted) | Raw refresh/access tokens (mask) |
| Response body (optional, truncated) | Gateway secrets |
| `request_id`, user id, IP, latency | CVV / OTP codes |

### 4.2 Tables

#### `api_request_logs` — ~22 columns

One row per HTTP API call (sampling allowed in production).

#### `api_outbound_logs` — ~18 columns

Calls **from** our app to Razorpay / SMS / shipping providers.

#### `webhook_inbox` — ~14 columns

Raw inbound webhooks for replay / idempotency.

See full columns in §6.

### 4.3 Config flags

```env
API_REQUEST_LOG_ENABLED=true
API_REQUEST_LOG_SAMPLE_RATE=1.0   # 1.0 = all; use 0.1 in heavy prod traffic
API_REQUEST_LOG_MAX_BODY_CHARS=20000
API_OUTBOUND_LOG_ENABLED=true
```

### 4.4 Retention

| Environment | Keep logs |
|-------------|-----------|
| Local | 7 days |
| Staging | 30 days |
| Production | 30–90 days (cron purge job) |

---

## 5. Table catalog (names + column counts)

Column counts are **design targets** (including `id`, timestamps, `meta` where listed).  
A migration may add more later; update this table when you do.

### 5.1 Identity & access

| # | Table | Cols (approx) | Purpose |
|---|-------|---------------|---------|
| 1 | `users` | 14 | Login identity |
| 2 | `customer_profiles` | 12 | Customer extras |
| 3 | `roles` | 7 | Role master |
| 4 | `permissions` | 7 | Permission master |
| 5 | `role_permission` | 5 | Role ↔ permission map |
| 6 | `user_role` | 5 | User ↔ role map |
| 7 | `refresh_tokens` | 11 | Persistent login |
| 8 | `password_reset_tokens` | 5 | Reset flow |
| 9 | `user_devices` | 12 | Push devices |
| 10 | `addresses` | 16 | Shipping/billing addresses |
| 11 | `audit_logs` | 14 | Admin/domain audit |

### 5.2 Catalog

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 12 | `categories` | 12 | Nested categories |
| 13 | `brands` | 10 | Brands |
| 14 | `products` | 22 | Commercial product core |
| 15 | `product_variants` | 14 | Size/SKU variants |
| 16 | `product_images` | 10 | Media |
| 17 | `product_videos` | 9 | Media |
| 18 | `product_categories` | 5 | Product ↔ category |
| 19 | `tags` | 7 | Tags |
| 20 | `product_tags` | 5 | Product ↔ tag |
| 21 | `product_relations` | 8 | related / fbt / upsell |
| 22 | `plant_profiles` | 36 | Plant-specific |
| 23 | `fertilizer_profiles` | 12 | Fertilizer-specific |
| 24 | `pot_profiles` | 12 | Pot-specific |
| 25 | `tool_profiles` | 10 | Tool-specific |
| 26 | `soil_profiles` | 10 | Soil-specific |
| 27 | `care_product_profiles` | 11 | Pesticide/care |
| 28 | `accessory_profiles` | 9 | Accessories |
| 29 | `attribute_definitions` | 12 | Extensible attributes |
| 30 | `attribute_values` | 9 | Attribute values |
| 31 | `product_bundles` | 8 | Bundle header |
| 32 | `product_bundle_items` | 7 | Bundle lines |

### 5.3 Inventory & supply

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 33 | `warehouses` | 10 | Warehouses |
| 34 | `inventory_items` | 14 | Stock per variant/warehouse |
| 35 | `stock_movements` | 14 | Stock ledger |
| 36 | `suppliers` | 14 | Suppliers |
| 37 | `purchase_orders` | 14 | PO header |
| 38 | `purchase_order_items` | 11 | PO lines |

### 5.4 Cart / wishlist / promotions

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 39 | `carts` | 10 | Cart header |
| 40 | `cart_items` | 11 | Cart lines |
| 41 | `wishlists` | 7 | Wishlist rows |
| 42 | `coupons` | 18 | Coupon master |
| 43 | `coupon_redemptions` | 9 | Who used coupon |
| 44 | `promotions` | 16 | Sale rules |
| 45 | `promotion_products` | 6 | Promo ↔ product |
| 46 | `campaigns` | 16 | Seasonal campaigns |
| 47 | `campaign_products` | 6 | Campaign ↔ product |
| 48 | `banners` | 14 | Home banners |

### 5.5 Orders / payments / delivery

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 49 | `orders` | 28 | Order header |
| 50 | `order_items` | 16 | Order lines (price snapshot) |
| 51 | `order_status_histories` | 10 | Status audit |
| 52 | `payments` | 20 | Payment attempts |
| 53 | `refunds` | 14 | Refunds |
| 54 | `return_requests` | 12 | Returns |
| 55 | `return_items` | 9 | Return lines |
| 56 | `shipments` | 16 | Shipment header |
| 57 | `shipment_events` | 9 | Tracking events |
| 58 | `shipping_methods` | 12 | Delivery options |
| 59 | `shipping_zones` | 10 | Zone rules |
| 60 | `tax_rates` | 10 | Tax config |

### 5.6 Engagement & support

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 61 | `reviews` | 14 | Product reviews |
| 62 | `review_images` | 7 | Review photos |
| 63 | `notification_templates` | 12 | Templates |
| 64 | `notifications` | 12 | In-app notifications |
| 65 | `recommendation_rules` | 12 | Rule-based reco |
| 66 | `support_tickets` | 12 | Support |
| 67 | `support_messages` | 9 | Ticket messages |

### 5.7 Platform / config / logging / mapping

| # | Table | Cols | Purpose |
|---|-------|------|---------|
| 68 | `settings` | 8 | Key-value settings |
| 69 | `schema_field_maps` | 12 | Logical→physical field map |
| 70 | `api_request_logs` | 22 | Inbound API req/res log |
| 71 | `api_outbound_logs` | 18 | Outbound provider log |
| 72 | `webhook_inbox` | 14 | Inbound webhooks |
| 73 | `media_files` | 12 | Optional central media |
| 74 | `jobs` | Laravel default | Queue |
| 75 | `failed_jobs` | Laravel default | Failed queue |
| 76 | `migrations` | Laravel default | Migration history |
| 77 | `cache` / `cache_locks` | Laravel default | Cache driver DB (optional) |
| 78 | `sessions` | Laravel default | Only if session driver=db |

**Domain tables to implement in Phase 0–4:** roughly **#1–73** (skip Laravel internals you don’t enable).

---

## 6. Full column definitions

Notation: `PK` primary key, `FK` foreign key, `UQ` unique, `IDX` index recommended.

---

### 6.1 Identity

#### `users` (14)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| name | VARCHAR(120) | |
| email | VARCHAR(190) UQ | nullable if phone-only later |
| phone | VARCHAR(20) UQ | nullable |
| password | VARCHAR(255) | hashed |
| status | VARCHAR(20) | `active`,`blocked` |
| email_verified_at | TIMESTAMP NULL | |
| phone_verified_at | TIMESTAMP NULL | |
| last_login_at | TIMESTAMP NULL | |
| meta | JSON NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | soft delete |

#### `customer_profiles` (12)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| user_id | BIGINT FK UQ → users.id | |
| date_of_birth | DATE NULL | |
| gender | VARCHAR(20) NULL | |
| preferred_language | VARCHAR(10) | default `en` |
| marketing_opt_in | TINYINT(1) | |
| default_address_id | BIGINT NULL | |
| meta | JSON NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | |

#### `roles` (7)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| name | VARCHAR(80) UQ |
| slug | VARCHAR(80) UQ |
| description | VARCHAR(255) NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `permissions` (7)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| name | VARCHAR(120) UQ |
| slug | VARCHAR(120) UQ | e.g. `products.write` |
| description | VARCHAR(255) NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `role_permission` (5) — mapping

| Column | Type |
|--------|------|
| id | BIGINT PK |
| role_id | BIGINT FK |
| permission_id | BIGINT FK |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

UQ `(role_id, permission_id)`

#### `user_role` (5) — mapping

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| role_id | BIGINT FK |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

UQ `(user_id, role_id)`

#### `refresh_tokens` (11)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| user_id | BIGINT FK | |
| token_hash | VARCHAR(64) UQ | sha256 of token |
| device_id | VARCHAR(100) NULL | |
| platform | VARCHAR(20) NULL | web/android/ios |
| expires_at | TIMESTAMP | |
| revoked_at | TIMESTAMP NULL | |
| replaced_by_token_id | BIGINT NULL | rotation chain |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| meta | JSON NULL | |

#### `password_reset_tokens` (5)

| Column | Type |
|--------|------|
| email | VARCHAR(190) |
| token | VARCHAR(255) |
| created_at | TIMESTAMP NULL |
| (Laravel may use email as key; follow framework default) | |

#### `user_devices` (12)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| platform | VARCHAR(20) |
| device_id | VARCHAR(100) NULL |
| push_token | VARCHAR(255) |
| app_version | VARCHAR(30) NULL |
| is_active | TINYINT(1) |
| last_seen_at | TIMESTAMP NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

UQ optional `(user_id, push_token)`

#### `addresses` (16)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| label | VARCHAR(50) NULL |
| name | VARCHAR(120) |
| phone | VARCHAR(20) |
| line1 | VARCHAR(255) |
| line2 | VARCHAR(255) NULL |
| city | VARCHAR(100) |
| state | VARCHAR(100) |
| postal_code | VARCHAR(20) |
| country | CHAR(2) | default `IN` |
| is_default | TINYINT(1) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `audit_logs` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| actor_user_id | BIGINT NULL |
| action | VARCHAR(80) |
| entity_type | VARCHAR(80) |
| entity_id | BIGINT NULL |
| before_json | JSON NULL |
| after_json | JSON NULL |
| ip | VARCHAR(45) NULL |
| user_agent | VARCHAR(255) NULL |
| request_id | VARCHAR(60) NULL |
| created_at | TIMESTAMP |
| meta | JSON NULL |
| (no updated_at required) | |
| (pad/meta as needed) | |

Practical columns: `id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent, request_id, meta, created_at` (+ optional `updated_at`).

---

### 6.2 Catalog

#### `categories` (12)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| parent_id | BIGINT NULL FK → categories.id |
| name | VARCHAR(120) |
| slug | VARCHAR(160) UQ |
| image_url | VARCHAR(500) NULL |
| sort_order | INT | default 0 |
| status | VARCHAR(20) | active/inactive |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| description | TEXT NULL |

#### `brands` (10)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| name | VARCHAR(120) |
| slug | VARCHAR(160) UQ |
| logo_url | VARCHAR(500) NULL |
| status | VARCHAR(20) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| description | TEXT NULL |

#### `products` (22)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| brand_id | BIGINT NULL FK | |
| product_type | VARCHAR(40) IDX | plant, pot, … |
| name | VARCHAR(200) | |
| slug | VARCHAR(220) UQ | |
| sku | VARCHAR(80) UQ | |
| description | TEXT NULL | |
| price | DECIMAL(12,2) | |
| compare_at_price | DECIMAL(12,2) NULL | |
| currency | CHAR(3) | INR |
| status | VARCHAR(20) | draft/active/archived |
| stock_status | VARCHAR(20) | in_stock/out_of_stock/preorder |
| is_featured | TINYINT(1) | |
| is_new | TINYINT(1) | |
| rating_avg | DECIMAL(3,2) | denormalized |
| rating_count | INT | denormalized |
| published_at | TIMESTAMP NULL | |
| meta | JSON NULL | future extras |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | |
| tax_class | VARCHAR(40) NULL | optional |

#### `product_variants` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| sku | VARCHAR(80) UQ |
| name | VARCHAR(120) NULL | e.g. 6 inch |
| price | DECIMAL(12,2) NULL | override |
| compare_at_price | DECIMAL(12,2) NULL |
| attributes_json | JSON NULL | {size: "6in"} |
| status | VARCHAR(20) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| barcode | VARCHAR(64) NULL |
| weight_grams | INT NULL |

#### `product_images` (10)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| url | VARCHAR(500) |
| alt | VARCHAR(200) NULL |
| is_primary | TINYINT(1) |
| sort_order | INT |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `product_videos` (9)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| url | VARCHAR(500) |
| thumbnail_url | VARCHAR(500) NULL |
| sort_order | INT |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `plant_profiles` (36)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK UQ |
| common_name | VARCHAR(160) |
| scientific_name | VARCHAR(190) NULL |
| local_names | JSON NULL |
| plant_kind | VARCHAR(40) NULL |
| indoor_outdoor | VARCHAR(20) IDX |
| sunlight | VARCHAR(40) IDX |
| water_requirement | VARCHAR(40) IDX |
| soil_type | VARCHAR(60) NULL |
| temperature_min_c | DECIMAL(5,2) NULL |
| temperature_max_c | DECIMAL(5,2) NULL |
| humidity_requirement | VARCHAR(20) NULL |
| growth_rate | VARCHAR(20) NULL |
| mature_height_cm | INT NULL |
| mature_width_cm | INT NULL |
| flowering_season | JSON NULL |
| fruiting_season | JSON NULL |
| planting_season | JSON NULL |
| bloom_color | VARCHAR(60) NULL |
| flowering_duration | VARCHAR(80) NULL |
| lifespan | VARCHAR(40) NULL |
| difficulty_level | VARCHAR(20) IDX |
| care_level | VARCHAR(20) NULL |
| propagation_method | VARCHAR(80) NULL |
| toxicity_info | TEXT NULL |
| pet_safety | VARCHAR(20) NULL |
| benefits | JSON NULL |
| uses | JSON NULL |
| growing_instructions | TEXT NULL |
| planting_instructions | TEXT NULL |
| pruning_instructions | TEXT NULL |
| fertilization_instructions | TEXT NULL |
| pest_disease_info | TEXT NULL |
| harvest_info | TEXT NULL |
| meta | JSON NULL |
| created_at / updated_at | TIMESTAMP |

*(If count exceeds 36 with timestamps, that is fine — keep timestamps.)*

#### `fertilizer_profiles` (12)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK UQ |
| npk_ratio | VARCHAR(40) NULL |
| suitable_plant_types | JSON NULL |
| application_frequency | VARCHAR(80) NULL |
| application_quantity | VARCHAR(80) NULL |
| usage_instructions | TEXT NULL |
| organic | TINYINT(1) | |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| form | VARCHAR(40) NULL | liquid/granular |

#### `pot_profiles` (12)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK UQ |
| material | VARCHAR(60) NULL |
| diameter_cm | DECIMAL(8,2) NULL |
| height_cm | DECIMAL(8,2) NULL |
| capacity_liters | DECIMAL(8,2) NULL |
| drainage_holes | TINYINT(1) | |
| indoor_outdoor_suitability | VARCHAR(20) NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| color | VARCHAR(40) NULL |

#### `tool_profiles` / `soil_profiles` / `care_product_profiles` / `accessory_profiles`

Follow same pattern: `id`, `product_id UQ`, typed fields, `meta`, timestamps.  
Exact fields match `PROJECT_DEVELOPMENT_GUIDE.md` §8.3.

#### `attribute_definitions` (12)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| code | VARCHAR(80) UQ | e.g. `bloom_fragrance` |
| name | VARCHAR(120) | |
| data_type | VARCHAR(20) | string/number/boolean/json |
| applies_to_product_types | JSON NULL | ["plant","tree"] |
| is_filterable | TINYINT(1) | |
| is_required | TINYINT(1) | |
| unit | VARCHAR(30) NULL | |
| meta | JSON NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | |

#### `attribute_values` (9)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| variant_id | BIGINT NULL FK |
| attribute_definition_id | BIGINT FK |
| value_string | VARCHAR(255) NULL |
| value_number | DECIMAL(18,4) NULL |
| value_json | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

UQ `(product_id, variant_id, attribute_definition_id)`

---

### 6.3 Inventory

#### `warehouses` (10)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| code | VARCHAR(40) UQ |
| name | VARCHAR(120) |
| city | VARCHAR(100) NULL |
| is_default | TINYINT(1) |
| status | VARCHAR(20) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `inventory_items` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| warehouse_id | BIGINT FK |
| product_id | BIGINT FK |
| product_variant_id | BIGINT NULL FK |
| qty_on_hand | INT | |
| qty_reserved | INT | |
| qty_damaged | INT | |
| low_stock_threshold | INT | |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| version | INT | optimistic lock optional |
| (sellable computed in app) | |
| deleted_at | TIMESTAMP NULL | rarely used |

UQ `(warehouse_id, product_id, product_variant_id)`

#### `stock_movements` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| inventory_item_id | BIGINT FK |
| product_id | BIGINT FK |
| product_variant_id | BIGINT NULL |
| warehouse_id | BIGINT FK |
| type | VARCHAR(40) | purchase_in, reserve, sale, release, adjust, damage, return_in |
| qty_delta | INT | signed |
| reference_type | VARCHAR(40) NULL | order/payment/po |
| reference_id | BIGINT NULL |
| note | VARCHAR(255) NULL |
| actor_user_id | BIGINT NULL |
| created_at | TIMESTAMP | |
| meta | JSON NULL | |
| (updated_at optional) | |

---

### 6.4 Cart / wishlist

#### `carts` (10)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT NULL FK UQ | one active cart per user |
| cart_token | VARCHAR(80) NULL UQ | guest |
| currency | CHAR(3) |
| coupon_code | VARCHAR(40) NULL |
| status | VARCHAR(20) | active/converted/abandoned |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `cart_items` (11)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| cart_id | BIGINT FK |
| product_id | BIGINT FK |
| product_variant_id | BIGINT NULL FK |
| quantity | INT |
| unit_price_snapshot | DECIMAL(12,2) NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| (extra reserved) | |

UQ `(cart_id, product_id, product_variant_id)`

#### `wishlists` (7)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| product_id | BIGINT FK |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

UQ `(user_id, product_id)` where `deleted_at` null (enforce in app or partial unique)

---

### 6.5 Coupons / campaigns

#### `coupons` (18)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| code | VARCHAR(40) UQ |
| name | VARCHAR(120) |
| discount_type | VARCHAR(20) | percent/fixed |
| discount_value | DECIMAL(12,2) |
| min_order_amount | DECIMAL(12,2) NULL |
| max_discount_amount | DECIMAL(12,2) NULL |
| usage_limit_total | INT NULL |
| usage_limit_per_user | INT NULL |
| starts_at | TIMESTAMP NULL |
| ends_at | TIMESTAMP NULL |
| status | VARCHAR(20) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| is_public | TINYINT(1) |
| stackable | TINYINT(1) |

#### `campaigns` (16)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| slug | VARCHAR(160) UQ |
| title | VARCHAR(200) |
| subtitle | VARCHAR(255) NULL |
| description | TEXT NULL |
| type | VARCHAR(40) | seasonal/festival/flash |
| season_code | VARCHAR(40) NULL |
| image_url | VARCHAR(500) NULL |
| starts_at | TIMESTAMP |
| ends_at | TIMESTAMP |
| status | VARCHAR(20) |
| priority | INT |
| rules_json | JSON NULL | auto-include rules |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `banners` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| title | VARCHAR(200) |
| image_url | VARCHAR(500) |
| placement | VARCHAR(40) | home/... |
| link_type | VARCHAR(40) NULL |
| link_value | VARCHAR(255) NULL |
| sort_order | INT |
| starts_at | TIMESTAMP NULL |
| ends_at | TIMESTAMP NULL |
| status | VARCHAR(20) |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

---

### 6.6 Orders / payments

#### `orders` (28)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| order_number | VARCHAR(40) UQ | ORD-... |
| user_id | BIGINT FK | |
| status | VARCHAR(40) IDX | state machine |
| currency | CHAR(3) | |
| subtotal | DECIMAL(12,2) | |
| discount_total | DECIMAL(12,2) | |
| tax_total | DECIMAL(12,2) | |
| shipping_total | DECIMAL(12,2) | |
| grand_total | DECIMAL(12,2) | |
| coupon_code | VARCHAR(40) NULL | |
| payment_method | VARCHAR(40) NULL | |
| shipping_method_id | BIGINT NULL | |
| notes | VARCHAR(500) NULL | |
| shipping_address_json | JSON | snapshot |
| billing_address_json | JSON NULL | snapshot |
| placed_at | TIMESTAMP NULL | |
| confirmed_at | TIMESTAMP NULL | |
| cancelled_at | TIMESTAMP NULL | |
| cancel_reason | VARCHAR(255) NULL | |
| meta | JSON NULL | future |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP NULL | |
| ip | VARCHAR(45) NULL | |
| platform | VARCHAR(20) NULL | web/android/ios |
| request_id | VARCHAR(60) NULL | |
| warehouse_id | BIGINT NULL | fulfillment |

#### `order_items` (16)

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| order_id | BIGINT FK | |
| product_id | BIGINT FK | |
| product_variant_id | BIGINT NULL | |
| sku | VARCHAR(80) | snapshot |
| name | VARCHAR(200) | snapshot |
| unit_price | DECIMAL(12,2) | snapshot |
| quantity | INT | |
| line_total | DECIMAL(12,2) | |
| product_type | VARCHAR(40) NULL | snapshot |
| thumbnail_url | VARCHAR(500) NULL | |
| meta | JSON NULL | |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| tax_amount | DECIMAL(12,2) | default 0 |
| discount_amount | DECIMAL(12,2) | default 0 |

Snapshot columns protect history if product name/price changes later.

#### `order_status_histories` (10)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| order_id | BIGINT FK |
| from_status | VARCHAR(40) NULL |
| to_status | VARCHAR(40) |
| actor_user_id | BIGINT NULL |
| note | VARCHAR(255) NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| (updated_at optional) | |
| request_id | VARCHAR(60) NULL |

#### `payments` (20)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| order_id | BIGINT FK |
| user_id | BIGINT FK |
| provider | VARCHAR(40) |
| method | VARCHAR(40) |
| amount | DECIMAL(12,2) |
| currency | CHAR(3) |
| status | VARCHAR(30) | initiated/pending/success/failed/cancelled |
| idempotency_key | VARCHAR(80) UQ |
| provider_order_id | VARCHAR(120) NULL |
| provider_payment_id | VARCHAR(120) NULL IDX |
| provider_signature | VARCHAR(255) NULL |
| failure_code | VARCHAR(60) NULL |
| failure_message | VARCHAR(255) NULL |
| paid_at | TIMESTAMP NULL |
| raw_response_json | JSON NULL | redacted provider payload |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

#### `refunds` / `return_requests` / `return_items` / `shipments` / `shipment_events`

Mirror order style: FKs + status + money/qty snapshots + `meta` + timestamps.  
Tracking fields on `shipments`: `carrier`, `tracking_number`, `tracking_url`, `status`, `eta_date`, `assigned_driver_user_id` (Phase 20), `meta` (POD / reschedule).

---

### 6.7 Reviews / notifications

#### `reviews` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| user_id | BIGINT FK |
| order_id | BIGINT NULL FK |
| rating | TINYINT | 1–5 |
| title | VARCHAR(160) NULL |
| body | TEXT NULL |
| status | VARCHAR(20) | pending/approved/rejected |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |
| moderated_by | BIGINT NULL |
| moderated_at | TIMESTAMP NULL |

UQ `(product_id, user_id)`

#### `notifications` (12)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| type | VARCHAR(60) |
| title | VARCHAR(200) |
| body | TEXT |
| data_json | JSON NULL |
| is_read | TINYINT(1) |
| read_at | TIMESTAMP NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP NULL |

---

### 6.8 Platform mapping & API logs

#### `settings` (8)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| key | VARCHAR(120) UQ |
| value | TEXT NULL |
| type | VARCHAR(20) | string/json/bool/number |
| group_name | VARCHAR(60) NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

#### `schema_field_maps` (12) — column / table mapping registry

Use this so upgrades, CSV import, admin UI, and future API field aliases stay easy.

| Column | Type | Example |
|--------|------|---------|
| id | BIGINT PK | |
| entity | VARCHAR(80) | `product`, `plant_profile`, `order` |
| logical_field | VARCHAR(80) | `sunlight_requirement` |
| physical_table | VARCHAR(80) | `plant_profiles` |
| physical_column | VARCHAR(80) | `sunlight` |
| data_type | VARCHAR(30) | string |
| api_field | VARCHAR(80) NULL | `plant.sunlight` |
| is_required | TINYINT(1) | |
| is_active | TINYINT(1) | |
| meta | JSON NULL | transform rules |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

UQ `(entity, logical_field)`

**Why this helps later**
- Rename API field without renaming DB column immediately
- Map CSV/ERP columns → nursery columns
- Generate admin forms dynamically
- Plan migrations (`old_column` → `new_column`) using `meta`

#### `api_request_logs` (22) — maintain request/response

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| request_id | VARCHAR(60) IDX | from envelope meta |
| method | VARCHAR(10) | GET/POST… |
| path | VARCHAR(255) IDX | `/api/v1/orders` |
| route_name | VARCHAR(120) NULL | |
| status_code | SMALLINT | |
| user_id | BIGINT NULL | |
| platform | VARCHAR(20) NULL | |
| app_version | VARCHAR(30) NULL | |
| ip | VARCHAR(45) NULL | |
| user_agent | VARCHAR(255) NULL | |
| request_headers_json | JSON NULL | redacted |
| request_body_json | JSON NULL | redacted/truncated |
| response_body_json | JSON NULL | optional/truncated |
| error_code | VARCHAR(60) NULL | |
| duration_ms | INT NULL | |
| created_at | TIMESTAMP IDX | |
| meta | JSON NULL | |
| correlation_id | VARCHAR(60) NULL | |
| endpoint_code | VARCHAR(40) NULL | AUTH-02, ORDER-01 |
| is_success | TINYINT(1) | |
| deleted_at | TIMESTAMP NULL | purge soft |

#### `api_outbound_logs` (18)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| request_id | VARCHAR(60) NULL |
| provider | VARCHAR(40) | razorpay/msg91/... |
| operation | VARCHAR(80) | initiate_payment |
| http_method | VARCHAR(10) NULL |
| url | VARCHAR(500) NULL |
| status_code | SMALLINT NULL |
| request_body_json | JSON NULL | redacted |
| response_body_json | JSON NULL | redacted |
| duration_ms | INT NULL |
| success | TINYINT(1) | |
| error_message | VARCHAR(255) NULL |
| reference_type | VARCHAR(40) NULL | payment/order |
| reference_id | BIGINT NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP NULL |
| deleted_at | TIMESTAMP NULL |

#### `webhook_inbox` (14)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| provider | VARCHAR(40) |
| event_type | VARCHAR(80) NULL |
| idempotency_key | VARCHAR(120) UQ | |
| headers_json | JSON NULL |
| payload_json | JSON | |
| status | VARCHAR(20) | received/processed/failed |
| processed_at | TIMESTAMP NULL |
| error_message | VARCHAR(255) NULL |
| related_payment_id | BIGINT NULL |
| related_order_id | BIGINT NULL |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

## 7. Mapping & relation tables

These tables are the backbone of flexible linking.

| Mapping table | From | To | Extra columns |
|---------------|------|----|---------------|
| `user_role` | users | roles | — |
| `role_permission` | roles | permissions | — |
| `product_categories` | products | categories | `is_primary` optional |
| `product_tags` | products | tags | — |
| `product_relations` | products | products | `relation_type` (`related`,`fbt`,`upsell`) |
| `campaign_products` | campaigns | products | `sort_order` |
| `promotion_products` | promotions | products | — |
| `product_bundle_items` | bundles | products | `quantity` |
| `schema_field_maps` | logical field | physical column | api_field |

### 7.1 `product_categories` (5)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| category_id | BIGINT FK |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

UQ `(product_id, category_id)`

### 7.2 `product_tags` (5)

Same shape as above with `tag_id`.

### 7.3 `product_relations` (8)

| Column | Type |
|--------|------|
| id | BIGINT PK |
| product_id | BIGINT FK |
| related_product_id | BIGINT FK |
| relation_type | VARCHAR(30) | related/fbt/upsell |
| sort_order | INT |
| meta | JSON NULL |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

UQ `(product_id, related_product_id, relation_type)`

### 7.4 One-table-to-another mapping pattern (template)

Whenever you need a new link later:

```sql
CREATE TABLE <a>_<b> (
  id BIGINT UNSIGNED PK AI,
  <a>_id BIGINT UNSIGNED NOT NULL,
  <b>_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  meta JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE (<a>_id, <b>_id),
  INDEX (<b>_id)
);
```

No need to redesign core tables.

---

## 8. Indexes & constraints

| Table | Constraint / index |
|-------|--------------------|
| users | UQ email, UQ phone |
| products | UQ sku, UQ slug; IDX (status, product_type); FULLTEXT (name, description) |
| plant_profiles | IDX indoor_outdoor, sunlight, difficulty_level |
| inventory_items | UQ (warehouse_id, product_id, product_variant_id) |
| carts | UQ cart_token; UQ user_id (active cart handled in app if multiple historical) |
| cart_items | UQ (cart_id, product_id, product_variant_id) |
| wishlists | UQ (user_id, product_id) |
| orders | UQ order_number; IDX (user_id, created_at); IDX status |
| payments | UQ idempotency_key; IDX provider_payment_id; IDX order_id |
| reviews | UQ (product_id, user_id) |
| refresh_tokens | UQ token_hash; IDX (user_id, expires_at) |
| api_request_logs | IDX (created_at), IDX (request_id), IDX (path, status_code) |
| webhook_inbox | UQ idempotency_key |
| schema_field_maps | UQ (entity, logical_field) |

Foreign keys: enable on all mapping and child tables (`ON DELETE RESTRICT` for orders/payments; `CASCADE` only for pure dependents like `cart_items` → `carts`).

---

## 9. Migration & upgrade playbook

### 9.1 Folder layout

```
database/migrations/
  2026_08_10_000001_create_users_table.php
  2026_08_10_000002_create_roles_tables.php
  ...
database/seeders/
  RolePermissionSeeder.php
  SettingsSeeder.php
  SchemaFieldMapSeeder.php
```

### 9.2 Adding a new column later (safe)

1. Add column definition to **this file**
2. Create migration `add_xyz_to_products_table`
3. Prefer nullable or default for existing rows
4. Update API Resource only if response should expose it
5. Update `schema_field_maps` if logical/API name differs
6. Deploy migrate on staging → production

```bash
php artisan make:migration add_fragrance_to_plant_profiles_table
php artisan migrate
```

### 9.3 Adding a new product type later

1. Add `product_type` value in app enum
2. Create `*_profiles` table (copy pot/fertilizer pattern)
3. Map in Catalog service (`product_type` → profile model)
4. No change to `orders` / `payments`

### 9.4 Renaming a column (avoid if possible)

Prefer:

1. Add new column
2. Backfill data
3. Dual-read in code
4. Switch writes
5. Drop old column in a later release

Record mapping in `schema_field_maps.meta`:

```json
{ "deprecated_column": "old_name", "replaced_by": "new_name", "since": "2026-10-01" }
```

### 9.5 Switching DB with schema already applied

```bash
# backup old
mysqldump -u... old_db > old.sql

# point .env to new DB
php artisan config:clear
php artisan migrate --force

# optional data copy
mysql -u... new_db < old.sql
```

---

## 10. Seed & environment data

### 10.1 Ready-made SQL seed (recommended for demo / API testing)

File: **`nursery_sample_data.sql`** (project root)

```bash
# after migrations (SAFE — skips rows that already exist)
/Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < /Users/nuramin/Desktop/Nursery_Platform/database/nursery_sample_data.sql

# optional full wipe then re-seed (LOCAL/STAGING ONLY — destructive)
# /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < /Users/nuramin/Desktop/Nursery_Platform/database/nursery_sample_data_reset.sql
# /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < /Users/nuramin/Desktop/Nursery_Platform/database/nursery_sample_data.sql
```

`database/nursery_sample_data.sql` uses **`INSERT IGNORE`** (insert if not exists / skip if PK or UNIQUE already exists). Safe to run many times.

Includes: roles, admin + customers, categories, 18+ products with plant/pot/fertilizer profiles, images, inventory, campaigns, banners, coupons, wishlist/cart, orders/payments/shipments, reviews, notifications, API log samples.

**Password for every sample user:** `Secret@123`  
Full list also in **`SAMPLE_LOGIN_CREDENTIALS.md`**.

| ID | Name | Email (username) | Password | Role | Phone |
|----|------|------------------|----------|------|-------|
| 1 | Nursery Super Admin | `superadmin@nursery.test` | `Secret@123` | super_admin | 9000000001 |
| 2 | Ops Admin | `admin@nursery.test` | `Secret@123` | admin | 9000000002 |
| 3 | Asha Kumar | `asha@example.com` | `Secret@123` | customer | 9876543210 |
| 4 | Ravi Sharma | `ravi@example.com` | `Secret@123` | customer | 9876543211 |
| 5 | Meera Patel | `meera@example.com` | `Secret@123` | customer | 9876543212 |
| 6 | Kabir Singh | `kabir@example.com` | `Secret@123` | customer | 9876543213 |
| 7 | Sneha Reddy | `sneha@example.com` | `Secret@123` | customer | 9876543214 |
| 8 | Arjun Mehta | `arjun@example.com` | `Secret@123` | customer | 9876543215 |
| 9 | Priya Nair | `priya@example.com` | `Secret@123` | customer | 9876543216 |
| 10 | Vikram Joshi | `vikram@example.com` | `Secret@123` | customer | 9876543217 |
| 11 | Inventory Manager | `inventory@nursery.test` | `Secret@123` | inventory_manager | 9000000003 |
| 12 | Order Manager | `orders@nursery.test` | `Secret@123` | order_manager | 9000000004 |

Use **only on local/staging**. Never run the reset SQL on production.

### 10.2 Minimum Laravel seeders (if not using the SQL file)

| Seeder | Data |
|--------|------|
| Roles/Permissions | all roles from API guide |
| Settings | currency INR, tax defaults, log flags |
| Warehouse | one default warehouse |
| Shipping methods | Standard |
| SchemaFieldMapSeeder | core product/plant/order fields |
| Notification templates | order confirmed/shipped |

Do **not** seed fake production customers/orders on live DB.

---

## 11. Checklist before go-live

- [ ] `.env` points to correct DB (`nursery_production`)
- [ ] `php artisan migrate --force` clean on empty DB
- [ ] FK + UQ constraints created
- [ ] Soft deletes working on products/users/addresses
- [ ] Refresh tokens hashed
- [ ] Payment idempotency unique
- [ ] Order item snapshots present
- [ ] `api_request_logs` redaction tested (no passwords/tokens)
- [ ] `schema_field_maps` seeded for plant + product core fields
- [ ] Backup cron configured
- [ ] Staging and production DBs are separate

---

## Appendix A — Quick ER (core commerce)

```text
users ──< addresses
users ──< refresh_tokens
users ──1 carts ──< cart_items >── products
users ──< wishlists >── products
users ──< orders ──< order_items >── products
orders ──< payments
orders ──< shipments
products ──1 plant_profiles (or other profile)
products ──< product_images
products >──< categories  (product_categories)
products >──< tags        (product_tags)
inventory_items >── products / variants / warehouses
api_request_logs (observability)
schema_field_maps (upgrade mapping)
attribute_definitions / attribute_values (future specs)
```

---

## Appendix B — How API request/response ties to DB

| API moment | DB writes |
|------------|-----------|
| AUTH-02 login | `users.last_login_at`, `refresh_tokens`, optional `user_devices`, `api_request_logs` |
| PROD-02 get product | usually read-only + `api_request_logs` |
| CART-02 add item | `carts`, `cart_items` |
| ORDER-01 place | `orders`, `order_items`, `inventory_items`, `stock_movements`, `order_status_histories` |
| PAY-01/02 | `payments`, maybe `api_outbound_logs`, order status, stock commit |
| PAY-03 webhook | `webhook_inbox` then payments/orders |
| REV-02 | `reviews` |
| ADM changes | target table + `audit_logs` |

Domain truth lives in business tables.  
`api_request_logs` keeps the full HTTP story for debugging and support.

---

## Appendix C — Relationship to PROJECT_DEVELOPMENT_GUIDE.md

| Topic | Where |
|-------|--------|
| Endpoint request/response JSON | `PROJECT_DEVELOPMENT_GUIDE.md` §9 |
| Auth persistent login | `PROJECT_DEVELOPMENT_GUIDE.md` §5.3 |
| Table/column physical design | **this file** |
| DB switch / migrate / extend | **this file** |

When both files conflict on a column name, update **both**, then migrate.

---

**Implementer rule:** New feature needing storage → choose in order:

1. Existing typed column / extension profile  
2. Mapping/pivot table  
3. `attribute_definitions` + `attribute_values`  
4. `meta` JSON  
5. Only then create a brand-new table

---

## Phase 17 — CRM / Marketing (2026-08-12)

| Table | Purpose |
|-------|---------|
| `customer_segments` | Named segment + `criteria_json` (AND rules), `is_system`, status |
| `marketing_automations` | Journey/blast definitions; optional FKs to segment/campaign/coupon |
| `marketing_deliveries` | Per-user delivery ledger with unique `idempotency_key` |

### Column additions

| Table | Column | Purpose |
|-------|--------|---------|
| `orders` | `campaign_id` (nullable FK → campaigns) | Safe attribution at checkout |
| `coupons` | `campaign_id` (nullable FK → campaigns) | Merchandising link only |

Indexes: automation status/type; deliveries (automation_id,user_id,status), (user_id,created_at); unique idempotency_key.

Retention: deliveries operational (≥90 days recommended). System segments archived, not hard-deleted casually.

See `docs/PHASE_17_CRM_DESIGN.md`.
