# PHASE 4 — Database Changes

**Decision:** No new tables and no new migrations in Phase 4.

All required customer-experience capabilities map to existing schema:

| Feature | Existing tables / columns | Notes |
|---------|---------------------------|-------|
| Reviews | `reviews`, `review_images`, `products.rating_avg`, `products.rating_count` | Verified purchase enforced in service; no extra flag column required |
| Wishlist | `wishlists` | Unchanged |
| Returns | `return_requests`, `return_items` | Unchanged |
| Refunds (customer view) | `refunds` | Read-only exposure on order detail |
| Notifications | `notifications`, `user_devices`, `notification_templates` | Inbox only; push send deferred |
| Preferences | `customer_profiles.marketing_opt_in`, `preferred_language`, `meta` | Preference toggles stored in existing columns / `meta` JSON |
| Related / recommendations | `product_relations`, `recommendation_rules` | Unchanged |
| Recently viewed | — | **Client-local** storage only |
| Reorder | `orders`, `order_items`, `carts` | Unchanged |

## Alternatives considered

| Idea | Decision |
|------|----------|
| `recently_viewed` table | Deferred — local storage is enough for Phase 4 |
| `loyalty_points` / tiers | Deferred — retention foundation only |
| `verified_purchase` column on reviews | Unnecessary — create path already requires a purchased order item |
| Push provider secrets table | Deferred until FCM/APNs is adopted |

## Impact

- **Website / Mobile:** Prefer existing endpoints + additive JSON fields.
- **Admin:** No schema dependency for review list/return console (API gaps remain documented).
- **Migration strategy:** N/A — deploy code only.
