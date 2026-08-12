# Phase D Documentation — Product Details + Wishlist + Reviews + Recommendations

**Date:** 2026-08-11  
**Architecture:** Database → PHP `/api/v1` → Website + Android

---

## 1. Features implemented

| Area | Capability |
|------|------------|
| PDP | Detail by slug/id, gallery, price, stock, badges, SKU, available_qty, plant care |
| Wishlist | List / add / remove / contains check |
| Reviews | Public approved list + summary; authenticated create (pending moderation) |
| Related | `GET /products/{id}/related` + embedded on detail |
| Recommendations | `GET /products/{id}/recommendations?type=similar` |
| Website | PDP rails, review submit, wishlist login redirect |
| Android | PDP reviews section, recommendations rail, toxicity, badges/SKU |

---

## 2–6. Database

**No CREATE/ALTER required.** See `database/phase_d.sql`.

Tables: `products`, `product_images`, `plant_profiles`, `product_relations` (not `product_related`), `recommendation_rules`, `wishlists`, `reviews`, `review_images`.

Sample data: `database/nursery_sample_data.sql`.

---

## 7–10. API endpoints

### Product detail — `GET /products/{idOrSlug}` (public)

Includes `badges`, `available_qty`, images, plant profile, `related`, `frequently_bought_together`.

### Plant care — `GET /plants/{idOrSlug}/care` (public)

### Related — `GET /products/{id}/related` (public)

### Recommendations — `GET /products/{id}/recommendations?type=` (public)

`type`: `similar` | `fbt` | `beginner` | `low_sunlight` | `seasonal` | `personalized`

### Wishlist (Bearer + `active.user`)

| Method | Path |
|--------|------|
| GET | `/wishlist` |
| GET | `/wishlist/contains?product_ids[]=101` |
| POST | `/wishlist` `{ "product_id": 101 }` |
| DELETE | `/wishlist/{productId}` |

### Reviews

| Method | Path | Auth |
|--------|------|------|
| GET | `/products/{id}/reviews?sort=&per_page=` | Public (approved only) |
| POST | `/products/{id}/reviews` | Bearer — purchase required; status `pending` |

List `meta.summary`: `{ rating_avg, rating_count }`

---

## 11–14. Behavior notes

- **Default badges** derived server-side (`sale`, `new`, `low-stock` + meta).
- **Review create:** one per user/product; must have purchased (order statuses CONFIRMED…DELIVERED); starts `pending`.
- **Wishlist contains:** returns `{ product_ids: number[] }` for membership checks.
- **Delete default address** N/A here; wishlist remove is soft-delete of row.

---

## 15–17. Clients

### Website
- `/product/[slug]` — gallery, care, reviews (+ submit), related, recommendations
- `/wishlist` — list
- Wishlist heart → login with `?next=/product/{slug}`

### Android
- `/product/:slug` — gallery, care (+ toxicity), reviews (+ submit), related, recommendations
- `/wishlist` — list
- Auth redirect for wishlist/review write

---

## 18. State management

- Website: Zustand `wishlist` store  
- Android: `WishlistProvider` (ID set) + screen fetch for rows  
- Reviews: screen-local fetch on both (no global review store)

---

## 19–20. Performance / security

- Eager-load images/profiles on detail  
- Reviews paginated; wishlist contains capped at 50 ids  
- Ownership on wishlist by `user_id`  
- Purchase gate on review create  
- `active.user` on wishlist + review write  

---

## 21. Accessibility

- Review rating buttons with pressed state  
- Meaningful product image alts on web gallery  
- Android Semantics on wishlist heart (existing)

---

## 22. Testing results

| Check | Result |
|-------|--------|
| Product detail badges | API returns badges on detail |
| Wishlist contains | New endpoint |
| Reviews list | OK |
| Review create (no purchase) | 403 |
| Related / recommendations | OK |
| Website tsc | OK |
| Flutter analyze / test / APK | See final report |

---

## 23. Known limitations

1. Personalized recommendations are category-based (not view/wishlist history).  
2. FBT uses curated `product_relations`, not co-purchase analytics.  
3. Review image **upload** not implemented (read path ready).  
4. No admin review queue UI in customer apps.  
5. Reset/moderation is admin-only.

---

## 24. Future improvements

- Co-purchase FBT  
- Review photo upload  
- Wishlist sync badge on shell tab (Android)  
- Variant selection UI when variants seeded  
