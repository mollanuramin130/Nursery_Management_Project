# Phase C Documentation — Home + Categories + Search + Filters + Listing

**Platform:** GreenLeaf Plant Nursery & Gardening E-commerce  
**Layers:** Database → PHP REST API (`/api/v1`) → Website (Next.js) → Android (Flutter)  
**Date:** 2026-08-11

---

## 1. Features implemented

| Area | Capability |
|------|------------|
| Home | Aggregated `GET /home` (banners, categories, campaigns, featured, best sellers, new arrivals, recommended) |
| Categories | Hierarchical categories + subcategories; website `/categories`, Android Categories tab |
| Product listing | Server-side filters, sort, pagination via `GET /products` and `GET /search` |
| Search | Full-text-ish product search + suggestions `GET /search/suggestions` |
| Filters | Category, type, price, placement, sunlight, water, difficulty, availability (API-backed) |
| Sorting | newest, popular, price_asc, price_desc, rating |
| Pagination | Server meta `pagination`; website page controls; Android infinite scroll |
| Stock / badges | API `stock_status` + derived badges (`sale`, `new`, `low-stock`) |
| Seasonal | Campaigns/banners from API (not hardcoded) |
| Empty / loading / error | Listing empty states, skeletons, retry |
| Website UX | Home rails, categories page, shop/search filters sidebar, URL query sync |
| Android UX | Home carousels, CatalogProvider, filter/sort sheets, dedicated SearchScreen |

---

## 2. Database changes

**No new tables required.** Phase C reuses the existing catalog/commerce schema.

See `database/phase_c.sql` for:

- Index documentation
- Optional stock-status updates for QA (low stock / out of stock)
- Banner link_type corrections for deep-link testing

Full seed catalog: `database/nursery_sample_data.sql` (≥20 products, category tree, plant profiles, campaigns, banners).

---

## 3–6. CREATE / ALTER / INDEX / SAMPLE

### CREATE TABLE

None for Phase C.

### ALTER TABLE

None for Phase C.

### Indexes (existing — used by discovery)

Documented in `database/phase_c.sql`. Key query fields: `products.slug`, `products.status`, `products.price`, `products.stock_status`, `categories.parent_id`, `plant_profiles.water_requirement`, etc.

### Sample data

Use:

```bash
# After migrations
mysql -u root nursery_local < database/nursery_sample_data.sql
mysql -u root nursery_local < database/phase_c.sql
```

Includes realistic nursery products (Areca Palm, Snake Plant, Money Plant, …), price ranges, discounts (`compare_at_price`), ratings, plant attributes, campaigns.

---

## 7–10. API endpoints

Base: `/api/v1`  
Envelope: `{ success, message, data, errors, meta }`

### Home

`GET /home` — public  

```json
{
  "success": true,
  "message": "Home retrieved successfully",
  "data": {
    "banners": [],
    "categories": [],
    "campaigns": [],
    "featured_products": [],
    "new_arrivals": [],
    "best_sellers": [],
    "recommended_for_you": []
  },
  "errors": null,
  "meta": {}
}
```

### Categories

- `GET /categories`
- `GET /categories/{slug}`
- Category products: `GET /products?category={slug}`

### Products / Search

- `GET /products`
- `GET /search` (same filters; search-oriented entry)
- `GET /search/suggestions?q=`

### Campaigns / Banners

- `GET /campaigns`
- `GET /campaigns/{slug}`
- `GET /banners` (also embedded in home)

---

## 8–9. Request parameters (listing)

| Param | Notes |
|-------|--------|
| `q` | Search term |
| `category` | Category slug |
| `product_type` | plant, tree, seed, pot, … |
| `min_price` / `max_price` | Numeric |
| `indoor_outdoor` | indoor \| outdoor \| both |
| `sunlight` | low \| bright_indirect \| full_sun |
| `water_requirement` | low \| medium \| high (`moderate` alias → `medium`) |
| `difficulty_level` | easy \| moderate \| advanced |
| `availability` | in_stock \| low_stock \| out_of_stock |
| `min_rating` | number |
| `season` | tag / planting season |
| `sort` | newest \| popular \| price_asc \| price_desc \| rating |
| `page` / `per_page` | Server pagination (`per_page` clamped 1–50) |

---

## 11. Pagination structure

```json
"meta": {
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 120,
    "last_page": 6
  }
}
```

---

## 12–14. Search / filter / sort behavior

- Search matches product `name`, `sku`, `slug` (indexed columns; parameterized `LIKE`).
- Suggestions return mixed `category` + `product` rows (min query length 2).
- Filters applied in SQL via `ProductService` (including `whereHas` plant profile).
- Changing search/filter/sort resets clients to page 1.
- Android: request-id race guard + debounce on suggestions (~280ms).
- Website: SearchBox debounce (~220ms) to `/search/suggestions`.

---

## 15–16. Website screens

| Route | Purpose |
|-------|---------|
| `/` | Home discovery |
| `/categories` | Category hierarchy (new) |
| `/category/[slug]` | Category listing + filters |
| `/shop` | Full catalog |
| `/search` | Search results + filters |
| `/campaigns/[slug]` | Seasonal collection |
| `/product/[slug]` | PDP |

---

## 17. Android screens / routes

| Route | Purpose |
|-------|---------|
| `/` | Home |
| `/categories` | Categories |
| `/catalog` | Listing (filters via query) |
| `/search` | Dedicated SearchScreen (suggestions + recent) |
| `/campaigns/:slug` | Campaign products |
| `/product/:slug` | PDP |

**State:** `CatalogProvider` (per CatalogScreen) — products, loading, loadingMore, filters, page, hasMore, request-id cancellation.

---

## 18. State management changes

- Flutter: added `lib/providers/catalog_provider.dart`; CatalogScreen hosts a scoped provider.
- Auth/Cart providers unchanged (Phase B preserved).
- Website: URL query params remain source of listing state (`catalog-query.ts`).

---

## 19. Performance

- Server pagination; eager-load `images` / `plantProfile` on listing.
- Home aggregation single endpoint (avoid N section round-trips).
- Clients: lazy images (`cached_network_image` / Next Image), grid builders, infinite scroll append (no full-list replace on page 2+).

---

## 20. Security

- Query params validated/clamped server-side (`per_page`, numeric prices).
- Eloquent parameterized queries (no raw string concat for filters).
- Suggestions throttled (`throttle:60,1`).
- Sort uses whitelist `match` with safe default.

---

## 21. Accessibility

- Website: search label, filter controls as links/buttons, product alt text where provided.
- Android: Semantics on banners; 48dp targets; accessible filter/sort sheets; search clear/submit.

---

## 22. Testing results

### API smoke (local)

| Check | Result |
|-------|--------|
| `GET /home` | OK — banners, categories, product rails |
| `GET /search/suggestions?q=arec` | OK — Areca Palm |
| `GET /products?sort=price_asc` | OK — pagination + badges |
| `GET /products?water_requirement=medium` | OK |
| `GET /products?water_requirement=moderate` | OK (alias → medium) |
| `GET /categories` | OK — hierarchy with children |
| PHP syntax (Phase C files) | OK |

### Android

Run: `dart format`, `flutter analyze`, `flutter test`, `flutter build apk --debug` (see final report).

### Website

Home/categories/shop/search consume same APIs; SearchBox uses suggestions endpoint.

---

## 23. Known limitations

- Best sellers currently ranked by `rating_count` / `rating_avg` (order-line sales aggregation not wired yet).
- Search is `LIKE`, not full-text engine (acceptable at current catalog size).
- Recent searches on Android stored via secure storage (local device only).
- `Notify Me` for OOS not implemented (no backend subscription API).

---

## 24. Future improvements

- Full-text / Meilisearch for search
- Sales-derived best-seller ranking
- Facet counts per filter value
- Image CDN size variants
- Stock notify waitlist API

---

## Architecture

```
DATABASE
    ↓
PHP REST API /api/v1
    ↓
Shared contracts & commerce rules
    ↓
┌─────────────┬──────────────┐
│  Website    │   Android    │
│  Responsive │  Mobile-first│
└─────────────┴──────────────┘
```
