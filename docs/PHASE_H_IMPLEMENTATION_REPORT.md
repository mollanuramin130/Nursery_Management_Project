# Phase H — Implementation Report

**Date:** 2026-08-11  
**Status:** Complete (API + Website + Android; promotions engine still unused by design)

---

## A. Audit Summary

Schema already had campaigns, plant profiles, sale pricing, coupons, banners. Gaps were offers feed, scored plant finder, campaign lifecycle enrichment, Website finder/offers quality, and Android Offers + Find Your Plant. Audit: `PHASE_H_OFFERS_SEASONAL_FIND_PLANT_AUDIT.md`.

## B. Database Changes

**None required.** Reused `campaigns`, `campaign_products`, `products.compare_at_price`, `plant_profiles`, `tags`, `coupons`.

## C. SQL Migration

`database/phase_h_offers_campaigns_find_plant.sql` — verification SELECTs / notes only.

## D. Sample INSERT Data

Existing `nursery_sample_data.sql` campaigns + plant traits used. No new fake production inserts.

## E. Campaign Model

Existing `campaigns` + pivot. Public API defaults to **active** window. Lifecycle: `scheduled` / `active` / `expired` / `disabled` / `draft` derived server-side.

## F. Offer Model

Composable feed: sale products (`compare_at > price`) + featured/upcoming campaigns + public coupons. Coupons remain cart-applied; not automatic campaign discounts.

## G. Seasonal Campaign Model

`type` + `season_code` on campaigns; seasonal collections via campaign products (not name heuristics).

## H. Plant Attribute Model

`plant_profiles` + tags/benefits. No new EAV table.

## I. Finder Matching Algorithm

Hard placement exclude; soft scores for sun/water/experience/purpose; deterministic ranking; reasons returned; approximate fallback when no ≥50 score matches.

## J. API Endpoints

| Method | Path |
|--------|------|
| GET | `/offers` |
| GET | `/campaigns` (default active) |
| GET | `/campaigns/featured` |
| GET | `/campaigns/{slug}` |
| GET | `/campaigns/{slug}/products` |
| GET | `/products?on_sale=1` (+ `pet_safety`, `tag`) |
| GET | `/plant-finder/options` |
| POST | `/plant-finder/match` |

## K. Examples

See `PHASE_H_API_DOCUMENTATION.md`. Smoke: `php apps/nursery-api/scripts/phase_h_offers_finder_smoke.php` — all PASS.

## L. Website Screens

- `/offers` — featured campaign, seasonal grid, sale products, coupons, upcoming  
- `/campaigns/[slug]` — hero, status, SEO metadata, product grid  
- `/find-your-plant` — wizard → match API → scores/reasons → cart  
- Home — banner deep link CTA + Find your plant / Offers  
- Shop — `on_sale` query supported  

## M. Android Screens

- `/offers` — OffersScreen  
- `/find-your-plant` — one question per screen + results  
- `/campaigns/:slug` — richer hero/status  
- Home CTAs + banner `offers` / `find_plant`  
- Account tiles → Offers / Find your plant  

## N. Cross-Platform Tests

Same APIs; finder deterministic smoke; campaign slug shared. Manual: same campaign/cart user across web ↔ Android.

## O. Cart Integration

`ProductCard` / finder Add to Cart → existing `POST /cart/items` / CartProvider.

## P. Checkout Integration

Unchanged Phase F pipeline.

## Q. Security Review

| Item | Result |
|------|--------|
| Finder whitelist validation | 422 on invalid |
| Campaign status authority | Server lifecycle |
| Price authority | Catalog prices only |
| Stock | Existing product/cart rules |
| SQL injection | Parameterized Eloquent |
| No fake reminders | Preview only for upcoming |

## R. Performance Review

Finder loads active plants with profiles (acceptable for current catalog size). Campaign lists paginated. Offers sale query indexed on product status; consider composite index later if catalog grows large.

## S. Regression Results

| Check | Result |
|-------|--------|
| Phase H smoke | PASS |
| Website `tsc` | Clean |
| Flutter analyze (H files) | Clean |
| Prior phases | Additive APIs; cart/checkout/orders untouched |

## T. Known Limitations

- `promotions` table still not runtime-applied  
- No campaign activation cron (query-time window)  
- `rules_json` auto-include unused  
- No push “Remind me”  
- Finder not AI / not personalized beyond answers  

## U. Future Improvements

- Wire `promotions` flash deals into pricing  
- Persist finder sessions / analytics events  
- Cache featured campaigns  
- Admin UI polish for `scheduled` status  
- Infinite scroll on Android offers sale grid  

---

## Definition of Done

- [x] Offers / campaigns / finder APIs  
- [x] Server-authoritative status & prices  
- [x] Deterministic finder with reasons  
- [x] Website + Android same APIs  
- [x] Cart reuse  
- [x] Docs + SQL notes + smoke  
- [x] Security review of Critical/High items  
