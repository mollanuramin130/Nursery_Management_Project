# QA-37 Mock Data Contract — Customer Mobile

**Location:** `apps/nursery_app/assets/mock_data/`  
**Envelope:** Matches Laravel API `{ success, message, data, errors, meta }`  
**Models:** Same Flutter parsers (`ProductSummary`, `HomeFeed`, `Cart`, `OrderSummary`, …)

---

## Files

| Asset | Maps to API | Notes |
|-------|-------------|-------|
| `home.json` | `GET /home` | banners, featured, best_sellers, categories, campaigns |
| `products.json` | `GET /products` / search list | ProductPresenter::card shape |
| `categories.json` | `GET /categories` | Top-level chips |
| `product_details.json` | `GET /products/{slug}` | Map keyed by slug → detail |
| `wishlist.json` | `GET /wishlist` | Seed rows with product cards |
| `cart.json` | `GET /cart` | Seed cart for empty-device offline |
| `orders.json` | `GET /orders` | Display-only seed |
| `order_details.json` | `GET /orders/{id}` | Keyed by id string |
| `account.json` | Profile display seed | DEBUG display only |
| `search.json` | Suggestions | Keywords → product ids |
| `notifications.json` | In-app list | Not LIVE FCM |
| `images/plant_placeholder.png` | Image fallback | Used when remote fails |

---

## ID consistency (sample seed aligned)

| id | slug | name |
|----|------|------|
| 101 | money-plant | Money Plant |
| 102 | snake-plant | Snake Plant |
| 103 | peace-lily | Peace Lily |
| 104 | areca-palm | Areca Palm |
| 105 | jade-plant | Jade Plant |
| 107 | tulsi-holy-basil | Tulsi (Holy Basil) |
| 112 | aloe-vera | Aloe Vera |
| 119 | hibiscus-red | Hibiscus (Red) |
| 201 | monstera-deliciosa | Monstera Deliciosa |
| 202 | lucky-bamboo | Lucky Bamboo |
| 203 | rose-plant | Rose Plant |

Wishlist / cart / order line items reference these IDs only.

---

## Fallback rules

| HTTP / transport | Catalog reads | Auth / pay / place order |
|------------------|---------------|---------------------------|
| Offline / timeout / 502–504 / 500 | Cache → mock | No fake success |
| 401 | No authenticated mock pretend | Session handling |
| 403 / 422 / 409 | Surface business error | No mock replace |
| 404 product | Try mock by slug; else empty | — |

---

## Image policy

`thumbnail_url` may be HTTPS. `ResilientNetworkImage` falls back to local placeholder when offline or load fails. No dynamic download required for offline browsing.
