# PHASE 17 — CRM Architecture Audit

**Date:** 2026-08-12  
**Platform:** GreenLeaf Nursery  
**Authority:** Laravel PHP REST API + MySQL (single backend)

---

## Verdict summary

| Domain | Status | Notes |
|--------|--------|-------|
| Customer / profile | **EXISTS** | `users`, roles, customer profile |
| Addresses | **EXISTS** | Customer addresses module |
| Orders / payment / checkout | **EXISTS** | Central order/payment services |
| Wishlist / reviews | **EXISTS** | Existing modules |
| Coupons | **EXISTS** | Validation stays in Promotion module |
| Merchandising campaigns | **EXISTS** | `campaigns` / products / banners (catalog promo) |
| Loyalty / subscriptions | **EXISTS** | Existing modules |
| Notifications (in-app / email / push) | **EXISTS** | Phase 11; marketing category + prefs |
| Communication preferences | **EXISTS** | `marketing_opt_in`, `notify_promotions`, transactional separate |
| Product views / search | **EXISTS** | Phase 15–16 events |
| Cart | **EXISTS** | `user_id`, `updated_at`, `status=active` |
| Abandoned cart automation | **PARTIAL → IMPLEMENTED P17** | Data existed; jobs + eligibility added |
| Customer 360 Admin | **PARTIAL → IMPLEMENTED P17** | Was thin `AdminUserController` |
| Dynamic segments | **MISSING → IMPLEMENTED P17** | `customer_segments` + SQL criteria |
| Marketing automations / deliveries | **MISSING → IMPLEMENTED P17** | Separate from catalog `campaigns` |
| Campaign attribution | **PARTIAL → IMPROVED P17** | `orders.campaign_id` + checkout optional field |
| Opens / clicks analytics | **PARTIAL** | Provider-limited; not fabricated |
| SMS | **NOT SUITABLE** | Not in architecture; not introduced |
| Plant-category purchase segments | **PARTIAL** | Taxonomy exists; segment field not yet in criteria set |
| Repeat-purchase soil/fertilizer journeys | **PARTIAL** | Design only; needs category business rules |
| Visual 8-step campaign wizard | **PARTIAL** | Operational create + activate flow; not full wizard UI |

---

## Pre-Phase-17 findings (detail)

### Customer information — EXISTS
- Auth users with customer role, profile, phone, status, registration, last login
- Addresses, orders, wishlist, reviews, coupons redemptions, loyalty, subscriptions
- Preferences API already on customer website (`/account/preferences`)

### Marketing vs merchandising — IMPORTANT
- **Catalog campaigns** (`campaigns` table): seasonal merchandising, banners, product links — status model `draft|active|inactive`
- **Marketing automations** (new): welcome, abandoned cart, post-purchase, reactivation, manual blast — status `draft|active|paused|archived`
- Do **not** conflate the two. Automations may *reference* a catalog campaign + coupon.

### Admin customers — was PARTIAL
- List/detail via `/admin/users?role=customer` + `users.manage`
- Not a CRM 360 (no lifecycle, prefs, activity, loyalty snapshot)

### Cart abandonment — was PARTIAL
- Cart rows can be inactive by `updated_at`, but no recovery job, frequency, or eligibility stop rules

### Attribution — was MISSING / documented gap in Phase 15
- Analytics could not safely attribute revenue to campaigns without `orders.campaign_id`

---

## Post-Phase-17 inventory

| Capability | Location |
|------------|----------|
| Customer 360 | `Customer360Service` + `GET /admin/customers/{id}/360` |
| Segments | `customer_segments` + Admin CRM controller |
| Automations | `marketing_automations` + deliveries |
| Config | `config/marketing.php` |
| Jobs/commands | `marketing:seed`, `marketing:process-abandoned-carts`, `marketing:process-post-purchase` |
| Admin UI | `/marketing`, `/marketing/automations`, `/customers/segments`, Customer 360 tabs |
| Prefs (customer) | Existing website preferences — unchanged authority |

---

## Explicit non-goals verified

- No second CRM database  
- No Next.js / Flutter business rules for eligibility  
- No fabricated opens, clicks, or attributed revenue without `campaign_id`  
- No SMS provider  
- Coupon validation not duplicated  
