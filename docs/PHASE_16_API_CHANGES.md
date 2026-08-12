# PHASE 16 — API Changes

**Envelope:** `{ success, message, data, errors, meta }`  
**Auth:** JWT where noted; `optional.jwt` for view recording.

---

## New endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/api/v1/product-views` | Optional JWT + `X-Guest-Token` / body `guest_token` | Best-effort product view (never fails PDP) |
| `GET` | `/api/v1/recently-viewed` | Optional JWT + guest token | Recent product cards (max 12 distinct, active only) |
| `GET` | `/api/v1/search/assist?q=` | Public (throttle) | Zero-result alternatives from live catalog / search_events |
| `POST` | `/api/v1/products/{id}/stock-alert` | Auth customer | Subscribe back-in-stock |
| `DELETE` | `/api/v1/products/{id}/stock-alert` | Auth | Cancel alert |
| `GET` | `/api/v1/products/{id}/stock-alert` | Auth | Subscription status |
| `GET` | `/api/v1/customer/stock-alerts` | Auth | List active alerts |
| `GET` | `/api/v1/admin/stock-alerts` | `inventory.view` | Ops visibility |

---

## Changed responses

### `GET /api/v1/home`

Adds (when catalog data exists):

- `indoor_plants`
- `outdoor_plants`
- `low_maintenance`
- `definitions` (explainable rail meanings)
- Cache key bumped to `catalog:home:feed:v2`
- Best sellers / easy-care prefer non-`out_of_stock`

### `GET /api/v1/cart` (and all cart mutations returning cart)

Adds:

- `warnings[]` — `{ code, severity, message, product_id?, blocking }`
- `checkout_blocked` — true when any blocking warning

Codes: `product_unavailable`, `quantity_unavailable`, `low_stock`

### `GET /api/v1/products/{id}/recommendations`

Excludes `stock_status = out_of_stock`.

### Notifications

Channel map adds `stock_back_in_stock` (transactional: in_app, email, push).

---

## Database

Migration `2026_08_12_040000_phase16_cx_views_and_stock_alerts.php`:

- `product_views`
- `stock_alert_subscriptions`

---

## Privacy

- Product views: product_id, optional user_id OR guest_token, platform, timestamp — no passwords/tokens/payment data  
- Guest token: client-generated opaque id (`X-Guest-Token`)  
- Stock alerts: authenticated users only  

---

## Unchanged (by design)

Checkout/payment/order APIs, PlantFinder, review create rules, loyalty ledger, subscription mutations.
