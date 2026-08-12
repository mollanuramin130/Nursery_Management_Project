# Phase H — Offers + Campaigns + Find Your Plant API

**Base:** `/api/v1`  
**Envelope:** `{ success, message, data, errors, meta }`  
**Auth:** Public for discovery endpoints (no login required)

---

## Concepts

| Concept | Meaning |
|---------|---------|
| **Campaign** | Merchandising collection (`campaigns` + `campaign_products`) |
| **Offer / sale** | Catalog price where `compare_at_price > price` |
| **Coupon** | Customer-entered code (existing cart API) — listed on offers feed when public |
| **Find Your Plant** | Deterministic attribute matching — not AI |

Campaign status returned to clients is **server-derived lifecycle**: `draft` | `scheduled` | `active` | `expired` | `disabled`.

---

## GET `/offers`

Sale products + featured/upcoming campaigns + public coupons.

| Query | Notes |
|-------|--------|
| `product_type` | Optional filter on sale products |
| `per_page` | Sale product page size (default 12) |
| `page` | Via Laravel paginator on sale products |

**Success `data`:**

```json
{
  "featured_campaigns": [],
  "upcoming_campaigns": [],
  "sale_products": [
    {
      "id": 101,
      "name": "Money Plant",
      "price": 199,
      "compare_at_price": 249,
      "original_price": 249,
      "discount_amount": 50,
      "discount_percentage": 20,
      "stock_status": "in_stock"
    }
  ],
  "public_coupons": [
    { "code": "MONSOON10", "discount_type": "percent", "discount_value": 10 }
  ]
}
```

`meta.pagination` applies to `sale_products`.

---

## GET `/campaigns`

| Query | Default | Notes |
|-------|---------|--------|
| `status` | `active` | `active` (date window), `upcoming`/`scheduled`, `featured`, `all`, or raw DB status |
| `season` | — | `season_code` filter |
| `per_page` | 20 | max 50 |

Each item includes: `slug`, `title`, `subtitle`, `short_description`, `banner_image`, `image_url`, `starts_at`, `ends_at`, `status` (lifecycle), `is_featured`, `priority`.

---

## GET `/campaigns/featured`

Active campaigns ordered by priority (limit query, default 6).

---

## GET `/campaigns/{slug}`

Campaign detail + paginated **active** products (standard product card + discount fields).

**404** if slug unknown.

---

## GET `/campaigns/{slug}/products`

Products only (+ `meta.campaign` summary).

---

## GET `/products?on_sale=1`

Catalog filter: `compare_at_price IS NOT NULL AND compare_at_price > price`.  
Also supports `pet_safety`, `tag`.

---

## GET `/plant-finder/options`

Whitelist of wizard values for clients.

---

## POST `/plant-finder/match`

**Throttle:** 30/min  
**Body (validated whitelist):**

```json
{
  "location": "indoor",
  "sunlight": "bright_indirect",
  "watering": "weekly",
  "experience": "beginner",
  "purpose": ["low_maintenance", "air_purifying"]
}
```

**Success:**

```json
{
  "criteria": { "...normalized..." },
  "results": [
    {
      "product": { "...ProductPresenter.card..." },
      "match_score": 94,
      "match_reasons": ["Matches your sunlight", "Beginner friendly"]
    }
  ],
  "approximate": false
}
```

**Scoring (max 100):** location +25, sunlight +25, watering +20, experience +15, purpose up to +15.  
**Hard filters:** indoor request excludes outdoor-only plants (and inverse).  
**Deterministic sort:** score DESC, rating DESC, id ASC.

**422** on invalid criteria.

---

## Product detail enrichment

`GET /products/{idOrSlug}` may include:

```json
"active_campaigns": [{ "id": 1, "slug": "monsoon-plants-2026", "title": "Monsoon Plants" }]
```

---

## Cart / checkout

Campaign and finder products use existing:

- `POST /cart/items`
- checkout + payment unchanged

No separate campaign cart.
