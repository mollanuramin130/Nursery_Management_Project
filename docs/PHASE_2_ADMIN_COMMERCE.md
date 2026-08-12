# PHASE 2 — Admin Commerce & Business Operations

**Date:** 2026-08-11  
**Status:** COMPLETE (with documented limitations)

## Summary

Extended `apps/nursery-admin` with operational modules on the existing Laravel Admin REST API. Phase 1 shell/auth/orders/products/categories were preserved.

## Modules implemented

| Module | Admin routes | Permission |
|--------|--------------|------------|
| Inventory | `/inventory` | `inventory.view` / `inventory.adjust` |
| Coupons | `/coupons`, `/coupons/new`, `/coupons/[id]/edit` | `campaigns.manage` |
| Campaigns | `/campaigns`, create/edit | `campaigns.manage` |
| Banners | `/banners`, create/edit | `campaigns.manage` |
| Customers | `/customers`, `/customers/[id]` | `users.manage` |
| Refunds | `/refunds` | `payments.refund` |
| Suppliers | `/suppliers` | `inventory.adjust` |
| Settings | `/settings` | `users.manage` |
| Audit logs | `/audit-logs` | `users.manage` |

## APIs consumed

Existing:

- `GET/POST /admin/inventory`, `POST /admin/inventory/adjust`
- Coupon/Campaign/Banner CRUD
- Supplier CRUD
- `GET/PUT /admin/settings`
- `GET /admin/audit-logs`
- `POST /admin/refunds`
- `GET/PUT /admin/users`

## APIs added (additive, backward compatible)

| Endpoint | Reason |
|----------|--------|
| `GET /admin/inventory/movements` | Stock history from `stock_movements` |
| `GET /admin/coupons/{id}` | Edit form detail |
| `GET /admin/campaigns/{id}` | Edit form + product_ids |
| `GET /admin/banners/{id}` | Edit form detail |
| `GET /admin/refunds` | Refund list (create already existed) |
| `GET /admin/users/{id}` | Customer detail + order stats |

## APIs modified (additive fields only)

| Endpoint | Added fields |
|----------|--------------|
| `GET /admin/inventory` | `thumbnail_url`, `categories`, `stock_status`, `updated_at`, filters `q`, `low_stock` |
| `GET /admin/coupons` | usage fields + `used_count`, filters `q`, `status` |
| `GET /admin/campaigns` | `image_url`, `product_count`, `subtitle`, filters |
| `GET /admin/users` | `role`, `status`, server pagination meta |

## Database changes

**No database changes were required for this phase.**

All features reuse existing tables (`inventory_items`, `stock_movements`, `coupons`, `coupon_redemptions`, `campaigns`, `banners`, `refunds`, `suppliers`, `settings`, `audit_logs`, `users`).

See also: `docs/PHASE_2_DATABASE_CHANGES.md`

## Permission notes

Actual seed permissions (unchanged):

`products.*`, `inventory.*`, `orders.*`, `payments.refund`, `campaigns.manage`, `users.manage`, `reports.view`

Sample `admin@nursery.test` intentionally lacks `users.manage` (see `nursery_sample_data.sql`). Use `superadmin@nursery.test` for Customers / Settings / Audit Logs.

## Customer Website / Mobile impact

Admin changes write to the same DB/API:

- Inventory adjust → product sellable stock
- Coupon activate/deactivate → checkout coupon validation
- Campaign publish (`status=active` + schedule) → `/campaigns` customer API
- Banner active window → homepage banners
- Customer status → login/`active.user` behavior

No customer Website/Android code changes were required.

## Refund / payment limitation

Refund create remains **local stub** (`meta.mode = local_stub`). It does not call a live payment provider.

See: `docs/PHASE_2_PAYMENT_LIMITATIONS.md`

## Known API gaps

See: `docs/ADMIN_API_GAPS.md`

Highlights:

- Coupon product/category/customer restrictions not in schema/API
- Campaign categories / SEO meta not in Admin campaign API
- Banner has single `image_url` (no mobile-specific image)
- Stock movements lack previous/new quantity columns
- Absolute stock set is UI-computed delta over `adjustment`
- Inventory list is not paginated server-side (full warehouse set)

## How to run

```bash
cd apps/nursery-api && php artisan serve --host=127.0.0.1 --port=8000
cd apps/nursery-admin && npm run dev   # :3001
```

Ensure CORS includes `:3001` (already in `.env`).

## Recommended Phase 3

- Reports / analytics UI
- Notifications
- Granular permission splits (coupons.read vs campaigns.manage)
- True payment-gateway refunds
- Inventory server-side pagination
- Admin users & roles UI (beyond customers)
