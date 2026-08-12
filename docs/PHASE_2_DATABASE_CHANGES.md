# PHASE 2 — Database Changes

**No database changes were required for this phase.**

Existing tables already support Phase 2 Admin modules:

| Module | Tables reused |
|--------|----------------|
| Inventory | `inventory_items`, `stock_movements`, `warehouses`, `products` |
| Coupons | `coupons`, `coupon_redemptions` |
| Campaigns | `campaigns`, `campaign_product` (pivot) |
| Banners | `banners` |
| Customers | `users`, `user_role`, `orders` |
| Refunds | `refunds`, `orders`, `payments` |
| Suppliers | `suppliers`, `purchase_orders` |
| Settings | `settings` |
| Audit | `audit_logs` |

### Alternatives considered

| Need | Decision |
|------|----------|
| Inventory history | Reuse `stock_movements` (no new history table) |
| Coupon used count | Count `coupon_redemptions` (no `used_count` column) |
| Customer stats | Aggregate from `orders` on user show |
| Absolute stock set | Frontend converts to delta; backend `adjustment` unchanged |

### Backward compatibility

No columns renamed/removed. Customer Website and Mobile APIs unchanged at the contract level aside from Admin-only additive responses.
