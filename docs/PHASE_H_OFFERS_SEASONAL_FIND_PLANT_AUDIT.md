# Phase H — Offers + Seasonal Campaigns + Find Your Plant Audit

**Date:** 2026-08-11  
**Status:** Audit complete → **implemented** (see `PHASE_H_IMPLEMENTATION_REPORT.md`, `PHASE_H_API_DOCUMENTATION.md`)  
**Principle:** Campaign ≠ Discount ≠ Coupon. No fake AI. Server-authoritative status/price/stock.

---

## Executive verdict

| Area | DB | API | Website | Android |
|------|----|-----|---------|---------|
| Seasonal campaigns | **Ready** (`campaigns`, `campaign_products`) | Partial (`GET /campaigns`, `/{slug}`) | Partial `/offers`, `/campaigns/[slug]` | Campaign detail only |
| Offers / sale listing | Ready via `compare_at_price` + unused `promotions` | **Missing** dedicated feed | Stub (popular ≠ sale) | **Missing** |
| Coupons | Ready | Ready (Phase E/F) | Cart only | Cart only |
| Plant attributes | **Ready** (`plant_profiles` + tags) | Filters on `/plants` | Client-only quiz → `/shop` | Need chips only |
| Find Your Plant matching | Ready data | **Missing** scored matcher | Wizard without scores/reasons | **Missing** |
| Banner deep links | Ready | Ready | **Not wired** on home | Wired |

**No new core tables required** for Phase H MVP.

---

## 1. Existing offer implementation

- Product sale presentation: `products.price` + `products.compare_at_price` (no separate `sale_price` column).
- `Product::badges()` adds `"sale"` when `compare_at_price > price`.
- `ProductPresenter::card` exposes both prices.
- Website/Android cards show strikethrough + % OFF.
- Public coupons: `GET /coupons` (active + `is_public`).
- **No** `GET /offers` aggregating sale products + featured campaigns.

## 2. Existing discount implementation

| Mechanism | Status |
|-----------|--------|
| Compare-at / sale price | Active (catalog) |
| Coupon percent/fixed | Active (cart/checkout) |
| `promotions` + `promotion_products` | **Schema + seed only** — no Eloquent model, not applied at runtime |
| Campaign-level discount fields | **None** (campaigns are merchandising) |

**Decision:** Do not wire full promotions engine in Phase H unless needed. Offers = sale products + campaigns. Coupons remain separate.

## 3. Existing campaign implementation

**Tables:** `campaigns`, `campaign_products`, `banners`  
**Model:** `App\Modules\Campaign\Models\Campaign` with `scopeActive` (status=`active` AND date window).  
**Admin:** CRUD + `campaigns.manage` permission.  
**Public API:**

- `GET /api/v1/campaigns` — optional `status`, `season`; default returns **all** statuses (gap)
- `GET /api/v1/campaigns/{slug}` — by slug; **no** active-window requirement; products not limited to `active`
- Home embeds campaigns via `HomeService`

**Sample campaigns:** monsoon, indoor, festival (scheduled), summer balcony, gift, winter (scheduled) in `nursery_sample_data.sql`.

**Gaps:** no `/featured`, weak public defaults, no computed lifecycle (`scheduled`/`expired`), `rules_json` unused, no activation cron (query-time window is OK for MVP).

## 4. Existing product attributes

`plant_profiles` columns used for finder:

| Finder concept | Column / source |
|----------------|-----------------|
| Location | `indoor_outdoor` (`indoor`/`outdoor`/`both`) |
| Sunlight | `sunlight` |
| Watering | `water_requirement` |
| Experience | `difficulty_level` (`easy`/`moderate`/`advanced`) |
| Pet safe | `pet_safety` |
| Season | JSON `planting_season` / flowering |
| Air purifying / low maintenance | `tags` + `benefits` JSON |

Filters already on `ProductService`: `indoor_outdoor`, `sunlight`, `water_requirement`, `difficulty_level`, `season`.  
**Missing filters:** `pet_safety`, tag slug(s), `on_sale`.

## 5. Category relationships

`product_categories` + hierarchical `categories`. Campaigns link products via pivot, not categories (category link via banners `link_type=category` OK).

## 6. Seasonal data

- `campaigns.type` default `seasonal`, `season_code` (e.g. `monsoon`)
- Plant `planting_season` JSON
- Tags: `monsoon`, `summer`, `winter`, `balcony`, …
- Recommendation rule sample: `monsoon_picks`

## 7. Existing API endpoints

| Endpoint | Notes |
|----------|-------|
| `GET /home` | banners + campaigns + rails |
| `GET /banners` | placement filter |
| `GET /campaigns`, `GET /campaigns/{slug}` | exists; enrich |
| `GET /products`, `GET /plants`, `GET /search` | filters |
| `GET /coupons` | public coupons |
| `POST /cart/items` | reuse for campaign products |
| **`GET /offers`** | missing |
| **`GET /campaigns/featured`** | missing |
| **`POST /plant-finder/match`** | missing |

## 8. Existing Website screens

| Route | State |
|-------|-------|
| `/offers` | Lists campaigns + popular products (not sale-filtered) |
| `/campaigns/[slug]` | Hero + product grid |
| `/find-your-plant` | 5-step wizard → redirects to `/shop?filters` (no match score/reasons) |
| Home | Seasonal campaigns; banner `link_type` **ignored** |

## 9. Existing Android screens

| Route | State |
|-------|-------|
| `/campaigns/:slug` | Exists |
| Home | Banner deep links + seasonal campaigns |
| `/offers` | **Missing** (account toast stub) |
| `/find-your-plant` | **Missing** |

## 10. Existing gaps (priority)

1. Offers API + sale product query (`on_sale`)
2. Campaign list defaults + featured + computed status + active products only
3. Plant finder scored match API with explanations
4. Website: sale grid, upcoming, banner deep links, finder results via API
5. Android: Offers + Find Your Plant screens/routes
6. Optional PDP active-campaign badge (if cheap)

## 11. Database changes required

**None required** for MVP. Optional:

- Index on `plant_profiles.pet_safety` (nice-to-have)
- Verification SQL file `database/phase_h_offers_campaigns_find_plant.sql`
- Sample data already present — only add if products missing attributes for finder tests

**Do not** create duplicate `offers` table or EAV `product_attributes`.

## 12. API changes required

1. `GET /offers` — featured campaigns, sale products, upcoming campaigns  
2. Harden `GET /campaigns` — default active; support `lifecycle` / `include=upcoming`  
3. `GET /campaigns/featured`  
4. Enrich campaign detail (status computed, dates, product pagination)  
5. `POST /plant-finder/match` (+ optional `GET /plant-finder/options`)  
6. `GET /products?on_sale=1` filter  
7. Optionally attach `active_campaigns` on product detail

## 13. Website changes required

- Rebuild `/offers` around Offers API  
- Improve campaign detail (dates, countdown presentation-only)  
- Finder: call match API; show scores/reasons; Add to Cart; View all matches  
- Home: honor banner `link_type`/`link_value`; light “Find Your Plant” / offers CTAs  

## 14. Android changes required

- `/offers` screen + go_router  
- `/find-your-plant` multi-step wizard + results  
- Enrich campaign screen if needed  
- Account tile → real Offers route  
- Reuse `ProductCard`, `PriceText`, cart provider  

## Recommended implementation sequence

1. SQL notes + optional `on_sale`/indexes  
2. CampaignService enrichment + featured  
3. OffersService + route  
4. PlantFinderService + match endpoint  
5. Smoke tests  
6. Website pages  
7. Android screens  
8. Cross-platform + regression docs  

## Finder scoring (proposed)

| Signal | Points | Hard vs soft |
|--------|--------|--------------|
| Location / `indoor_outdoor` | +25 | Hard exclude outdoor-only when user picks indoor-only (and inverse) |
| Sunlight | +25 | Soft (partial credit for adjacent) |
| Watering | +20 | Soft |
| Experience / difficulty | +15 | Soft (beginner hard-prefer `easy`) |
| Purpose tags/benefits | +15 | Soft (multi-select sum capped) |

Max 100. Deterministic sort: score DESC, rating DESC, id ASC.

## Admin / content management

Campaigns/banners/coupons: existing admin APIs. Promotions table unused — document as future. No new admin UI in Phase H.
