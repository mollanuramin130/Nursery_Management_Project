# PHASE 4 — Customer Experience & Retention

**Status:** PARTIALLY COMPLETE (mature CX foundation shipped; push/loyalty/admin return console deferred)  
**Date:** 2026-08-11  
**Architecture:** One MySQL DB → one Laravel `/api/v1` → Website + Android + Admin

---

## 1. Audit results

See [PHASE_4_CUSTOMER_EXPERIENCE_AUDIT.md](./PHASE_4_CUSTOMER_EXPERIENCE_AUDIT.md).

Most CX building blocks already existed (reviews, wishlist, reorder, returns create, notifications inbox API, related/recommendations). Gaps were primarily **client wiring** and **additive order/preference payloads**.

---

## 2. Reviews

- Public list + create already existed; verified purchase enforced server-side.
- Additive: `verified_purchase` on list/create payloads; rating `distribution` in meta; `GET /products/{id}/review-eligibility`.
- Website + Android: summary, distribution (web), verified badge, eligibility-gated write form, empty “No reviews yet.”

## 3. Wishlist

- API unchanged (C/R/D + move-to-cart).
- Website: clearer empty state, stock/unavailable row, disabled move-to-cart when OOS.
- Android: optimistic heart toggle with rollback on API failure.

## 4. Returns

- Existing `POST /orders/{id}/returns` reused.
- Additive: `can_return`, `return_reasons`, `returns[]` on order detail; `GET /orders/{id}/returns`; `GET /customer/returns`.
- Website: Return CTA + modal on order detail; `/account/returns` list.
- Android: Return bottom sheet on order detail.
- Admin approve/reject lifecycle still deferred (see API gaps).

## 5. Refund experience

- Customers do **not** initiate refunds.
- Additive: `refunds[]` on order detail (amount, status, reason, date).
- Displayed on Website + Android order detail when present.

## 6. Reorder

- Existing `POST /orders/{id}/reorder` (current price/stock) unchanged.
- Clients already show added / unavailable / price-changed summaries.

## 7. Notifications

- Existing list / mark-read / mark-all / device register.
- Middleware now `auth:api` + `active.user`.
- Return request creates in-app `RETURN_UPDATED` notification.
- Website: `/account/notifications` + header Alerts link.
- Android: `/notifications` inbox (replaces stub).
- **No FCM/APNs send** in this phase (token register only).

## 8. Preferences

- Additive `GET/PUT /customer/preferences` using `customer_profiles` + `meta`.
- Fields: `marketing_opt_in`, `preferred_language`, `notify_orders`, `notify_promotions`, `notify_email`, `notify_push`.
- Website `/account/preferences`; Android `/account/preferences`.

## 9. Recently viewed

- Client-local only (Website `localStorage`; Android secure storage).
- Shown on PDP after related/recommendations. No new DB table.

## 10. Recommendations

- Existing `/related` and `/recommendations` rails retained on PDP.
- No AI / fake personalization.

## 11. Database changes

**None.** See [PHASE_4_DATABASE_CHANGES.md](./PHASE_4_DATABASE_CHANGES.md).

## 12. API changes (additive)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/products/{id}/review-eligibility` | Auth |
| GET | `/orders/{id}/returns` | Auth |
| GET | `/customer/returns` | Auth |
| GET/PUT | `/customer/preferences` | Auth |
| — | Order detail/list | `can_return`, returns, refunds, reviewable ids, reasons |
| — | Reviews list meta | `distribution`, `verified_purchase` |
| — | Notifications routes | `active.user` |

Gaps: [PHASE_4_API_GAPS.md](./PHASE_4_API_GAPS.md).

## 13. Website changes

- Account nav: Returns, Notifications, Preferences
- Order detail: return flow, refunds, returns status, review eligibility hint
- PDP: review polish, recently viewed rail
- Wishlist polish
- Header Alerts link

## 14. Mobile changes

- Notifications + Preferences screens + routes
- Order detail return + refunds/returns sections
- Review eligibility + verified badge
- Recently viewed rail
- Wishlist optimistic rollback

## 15. Admin impact

- Review moderate endpoint already exists; **pending queue list still missing** (document for Admin phase).
- Return lifecycle management still Admin-gap.
- No new Admin screens in Phase 4.

## 16. Security

- Customer identity from JWT; ownership checks on orders/returns/notifications/preferences.
- Review eligibility / verified purchase computed server-side.
- Return ownership, delivered-only, duplicate blocking in `ReturnService`.
- Notifications filtered by `user_id`.

## 17. Performance

- Reviews/notifications/returns paginated.
- Recently viewed is local (no server round-trip).
- Order list eager-loads `returnRequests` for `can_return`.

## 18. Testing

- `tests/Feature/Phase4CustomerExperienceTest.php` (preferences, notification scoping, review eligibility/create, `can_return`).
- Phase 3 suite still green.

## 19. Known limitations

- No push send pipeline.
- No admin return approve/reject UI/API.
- No customer-initiated refunds.
- Recently viewed not synced across devices.
- Review image upload unused.
- Loyalty not implemented.

## 20. Future improvements

- Admin review queue + return console
- FCM/APNs with deep links
- Server-side recently viewed (optional)
- Loyalty / segmentation phase
- Real PSP refund customer visibility polish
