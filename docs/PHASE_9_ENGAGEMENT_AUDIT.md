# PHASE 9 — Engagement Audit (pre-implementation)

**Date:** 2026-08-11  
**Status:** Audit complete — implement MVP below

## 1. Already exists (reuse)

| Area | Support |
|------|---------|
| Reviews tables | `reviews`, `review_images`; `products.rating_avg/count` |
| Customer review APIs | list, eligibility, create (pending) |
| Admin moderate | `POST /admin/reviews/{id}/moderate` via `products.write` |
| Wishlist | Full API + Website + Android |
| Order signals | `reviewable_product_ids`, return/refund summaries |
| Notifications / Audit / RBAC infra | Reuse services |

## 2. Partial

| Area | Gap |
|------|-----|
| Admin reviews | Moderate-by-id only — **no list/queue UI** |
| Review images | Table exists — **no create upload** (URL attach only feasible) |
| Eligibility | Purchase statuses include pre-delivery — tighten to **DELIVERED** |
| Order → review CTA | Web hint only; Android IDs unused |
| Recommendations | Rule-based; not AI |
| Recently viewed | Client-local only |

## 3. Missing

- Dedicated `reviews.view` / `reviews.moderate`
- Loyalty ledger + APIs + UI
- Store credit wallet (document — do not invent full wallet)
- Referral platform (document foundation only)
- Review/loyalty notifications
- Engagement analytics KPIs
- My Reviews / Rewards account screens

## 4–8. Planned changes

| Layer | Change |
|-------|--------|
| DB | `loyalty_accounts`, `loyalty_transactions` (+ indexes); no duplicate review tables |
| API | Admin reviews list/show; customer my-reviews; loyalty balance/history/adjust; earn on DELIVERED; reverse on refund |
| Admin | `/reviews`, `/loyalty` |
| Website | Review CTAs, `/account/reviews`, `/account/rewards` |
| Android | Order review CTAs, Rewards screen |

## 9. Reuse

Wishlist as favorites. Existing ReviewService + moderate + rating recompute. NotificationService + AuditLogger. AdminRefundService for reverse hooks.

## 10. Risks

- Points are **not money** — no checkout redemption in Phase 9 (document as gap)
- Earn must be idempotent per order
- Do not rebuild wishlist or invent guest wishlist
- Phase 8 final docs incomplete — returns foundation already in code
