# PHASE 9 — Customer Engagement & Loyalty

## 1. Review architecture

- Reuses `reviews` + `review_images` + `products.rating_avg/count`.
- Customer create → `pending` → Admin moderate (`approved` / `rejected` / `hidden`).
- Aggregates recompute only on moderation.
- Verified purchase: delivered (or post-delivery return/refund) order ownership.
- Optional image URLs on create (max 5); no new storage system.

## 2. Rating architecture

- Stars 1–5 validated server-side.
- Public list meta includes `rating_avg`, `rating_count`, `distribution`.
- Sort: `newest` | `highest` | `lowest`.

## 3. Wishlist architecture

- **Unchanged.** Existing Wishlist module is the canonical favorites system.
- No separate Favorites table.

## 4. Loyalty architecture

- Foundation only: `loyalty_accounts` + `loyalty_transactions` ledger.
- Earn on **order DELIVERED** (FulfillmentService), not at checkout.
- Reverse on refund recording (AdminRefundService), proportional + idempotent.
- Manual admin adjust with required reason + audit.
- Points are **not money**; checkout redemption is deferred.

## 5. Points ledger

Types: `EARN`, `REVERSE`, `ADJUST` (+ reserved `REDEEM`, `EXPIRE`).

Idempotency keys:

- `earn:order:{id}`
- `reverse:refund:{id}`
- manual keys supplied by admin

## 6–7. Refund / return integration

- Refund stub creates proportional point reversal when earn exists.
- Returns do not auto-award; earn already tied to delivery.

## 8. RBAC

| Permission | Use |
|------------|-----|
| `reviews.view` | Admin review list/detail |
| `reviews.moderate` | Approve/reject/hide |
| `loyalty.view` | Accounts/ledger/dashboard |
| `loyalty.adjust` | Manual point adjust |

Assigned to admin/super_admin; content_manager gets reviews; customer_support gets reviews + loyalty.

## 9. Notifications

- `REVIEW_MODERATED` on approve/reject/hide
- `LOYALTY_UPDATED` on earn/reverse/adjust

## 10. Audit logging

- `review.create`, `review.moderate`
- `loyalty.adjust`
- Existing refund/fulfillment audits unchanged

## 11–13. Client integration

| Surface | Changes |
|---------|---------|
| Admin | `/reviews`, `/reviews/[id]`, `/loyalty` |
| Website | Order “Write a review”, `/account/reviews`, `/account/rewards`, PDP `?review=1` |
| Android | Order review CTA, Rewards screen, PDP openForm |

## 14. Security

- Review/wishlist/loyalty ownership enforced in services.
- Admin permission middleware mandatory.
- Customer payloads omit internal moderation notes.

## 15. Performance

- Server-side pagination on reviews, loyalty accounts/transactions, admin queues.

## 16. Testing

`tests/Feature/Phase9EngagementTest.php` — eligibility, duplicates, invalid rating, moderation, loyalty earn/idempotency/reverse, adjust permission, ownership isolation.
