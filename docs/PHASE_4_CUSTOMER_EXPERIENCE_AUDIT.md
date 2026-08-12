# PHASE 4 — Customer Experience & Retention Audit

**Date:** 2026-08-11  
**Status:** Audit complete (read-only). Implementation follows this document.  
**Architecture rule:** One MySQL DB → one Laravel `/api/v1` → Website + Android + Admin.

---

## 1. Capability matrix

| Capability | Database | API | Website | Android | Verdict |
|------------|----------|-----|---------|---------|---------|
| Reviews & ratings | `reviews`, `review_images` | List + create + admin moderate | ProductReviews on PDP | ProductReviewsSection | **Reuse**; polish verified badge + eligibility |
| Wishlist | `wishlists` | Full C/R/D + move-to-cart | `/wishlist` + hearts | Wishlist screen + toggle | **Reuse**; polish empty/unavailable |
| Reorder | (orders/cart) | `POST /orders/{id}/reorder` | List + detail | List + detail | **Reuse**; clarify unavailable messaging |
| Returns | `return_requests`, `return_items` | `POST /orders/{id}/returns` only | **Missing UI** | **Missing UI** | **Wire clients**; additive list/eligibility |
| Refunds (customer view) | `refunds` | Admin only | None | None | **Additive** order detail fields; no customer initiate |
| Notifications (inbox) | `notifications`, `user_devices` | List + read + read-all + device register | **Missing** | Stub toast only | **Wire clients**; no FCM send yet |
| Customer profile | `users` + `customer_profiles` | `PUT /customer/profile` (name/phone) | Profile page | Profile screen | **Extend** preferences via profile |
| Preferences | `customer_profiles.marketing_opt_in`, language | Not exposed | None | None | **Additive** GET/PUT preferences |
| Recently viewed | None | None | None | None | **Client-local** (no new table) |
| Recommendations / related | `product_relations`, `recommendation_rules` | `/related`, `/recommendations` | PDP rails | PDP rails | **Reuse** |
| Order history | `orders` | List + detail + tracking | Account orders | Orders tab | **Reuse**; enrich post-purchase actions |
| Campaign personalization | campaigns/banners | Existing public APIs | Offers/campaigns | Offers | **Reuse**; no new personalization engine |
| Loyalty | None | None | None | None | **Defer** (retention foundation only) |
| Push send (FCM) | `user_devices.push_token` | Register only | N/A | N/A | **Document gap**; do not invent provider |

---

## 2. Feature notes

### A. Reviews
- Verified purchase enforced in `ReviewService` (order item + eligible statuses). Client must not send `verified_purchase`.
- Public list = `approved` only; create → `pending`.
- Admin: `POST /admin/reviews/{id}/moderate` exists; **no admin pending list API** (gap → Admin phase).
- Gaps for Phase 4: expose `verified_purchase` / eligibility helper; optional rating distribution; wire review CTA from delivered order detail.

### B. Wishlist
- Complete API. Auth required (no guest server wishlist).
- Polish: empty state copy, out-of-stock row state, optimistic toggle with rollback (Android).

### C. Returns
- `ReturnService`: delivered only; one active request per order; creates `RETURN_REQUESTED`.
- No customer GET returns; no admin approve/reject API in routes.
- Phase 4: add `can_return` (+ reasons) on order detail; wire return form; optional `GET /orders/{id}/returns` or customer returns list if trivial.

### D. Refunds
- Admin stub refunds (Phase 3 hardened). Customer must **see** refund status if rows exist — never initiate fake refunds.
- Phase 4: include `refunds[]` summary on order detail for owner.

### E. Reorder
- Exists; uses current price/stock via `CheckoutService::reorder`. Ensure clients show partial-unavailable summary.

### F. Notifications
- In-app CRUD-ish: list, mark read, mark all. Device register stores token; **no push send**.
- Phase 4: Website `/account/notifications` + Android `/notifications` inbox. Document FCM for later.

### G. Preferences
- `customer_profiles` has `marketing_opt_in`, `preferred_language` unused by API.
- Phase 4: additive profile/preferences endpoints; Website + Android toggles.

### H. Recently viewed
- Prefer **localStorage / SharedPreferences** (anonymous + authenticated). No DB table this phase.

### I. Recommendations
- Deterministic APIs already on PDP. Keep hierarchy; do not add AI.

### J. Retention foundation
- Prefer additive preference + notification + review/return signals. No loyalty points table.

---

## 3. Implementation priority (smallest additive)

1. Audit docs (this file) + `PHASE_4_API_GAPS.md` + `PHASE_4_DATABASE_CHANGES.md`
2. Backend additives: order `can_return` / `refunds` summary; review eligibility + `verified_purchase` in payloads; preferences on customer profile; notification `active.user`; optional customer returns list
3. Website: returns flow, notifications inbox, preferences, recently viewed rail, order detail enrichment, wishlist polish
4. Android: same
5. Tests + `PHASE_4_CUSTOMER_EXPERIENCE.md`
6. **Stop** — no loyalty, FCM send, AI reco, Admin return console (document only)

---

## 4. Explicit non-goals this phase

Subscriptions, loyalty points, AI chatbot/recommendations, social login, marketplace, multi-vendor, delivery partners, WMS, advanced CRM, inventing separate Website/Mobile APIs.
