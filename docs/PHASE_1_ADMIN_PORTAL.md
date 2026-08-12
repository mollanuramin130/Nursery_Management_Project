# PHASE 1 — Admin Web Portal Foundation

**Date:** 2026-08-11  
**Status:** Implemented (foundation)

## Goal

Ship a separate Admin Web Portal that operates GreenLeaf Nursery commerce through the **existing** Laravel Admin REST API. Customer Website and Android apps were not rebuilt.

## Deliverable

| Item | Path |
|------|------|
| Admin app | `apps/nursery-admin` (Next.js 16 + TypeScript + App Router) |
| Local URL | `http://127.0.0.1:3001` |
| API base | `NEXT_PUBLIC_API_BASE_URL` → `/api/v1` |

## What was built

1. Admin scaffold + operational design tokens  
2. JWT login / refresh / logout session handling  
3. Staff-only gate (customers rejected after `/auth/me`)  
4. Permission-aware sidebar navigation  
5. Shell: sidebar, topbar, breadcrumbs, responsive layout  
6. Dashboard (live KPIs + recent orders + low-stock list when permitted)  
7. Orders list (search/status/pagination) + order detail + status update  
8. Products list + create/edit foundation + image URL attach  
9. Categories hierarchical CRUD  
10. Loading / empty / error states  

Future nav items (Inventory, Coupons, Campaigns, etc.) are permission-gated and route to a Coming Soon page.

## Additive API change (justified)

Phase 1 product edit required a detail payload that did not exist.

| Change | Why |
|--------|-----|
| `GET /api/v1/admin/products/{id}` | Edit form needs description, plant, images, inventory, category_ids |
| Admin product list `stock_qty` (+ `compare_at_price`) | Operational stock column on products table |

No second backend, no admin DB copy, no business logic duplicated in the frontend beyond UX transition mirrors.

## Auth / RBAC

- Login: `POST /auth/login`  
- Profile + permissions: `GET /auth/me`  
- Refresh: `POST /auth/refresh`  
- Frontend permission checks hide nav/actions only  
- Backend `permission:*` middleware + `super_admin` bypass remain authoritative  

Order transitions mirror `OrderStateMachine` for UX; invalid transitions still fail with API `409`.

## API gaps documented (not invented in UI)

| Gap | Notes |
|-----|-------|
| Orders: no payment/date/sort query params | Only `status`, `q`, `per_page`, `page` |
| Orders detail: no per-line discount | Items return unit/qty/line_total only |
| Categories list: no product_count | Tree shows parent/status/sort only |
| Categories GET requires `products.write` | Backend quirk — readers without write cannot list |
| Dashboard: no per-status order counts beyond KPIs | Uses `orders_today`, `pending_payment`, `orders_to_ship`, etc. |
| No warehouses list endpoint | Create product uses `NEXT_PUBLIC_DEFAULT_WAREHOUSE_ID` |
| Product images: URL-based only | `POST /admin/products/{id}/images` expects hosted URLs |
| Inventory adjust UI | Deferred to later phase (`/admin/inventory` exists) |

## How to run

```bash
# API
cd apps/nursery-api && php artisan serve --host=127.0.0.1 --port=8000

# Admin
cd apps/nursery-admin && npm install && npm run dev
```

Staff logins: `docs/SAMPLE_LOGIN_CREDENTIALS.md`  
Recommended: `admin@nursery.test` / `Secret@123`

## Smoke checklist

- [ ] Login as admin → Dashboard KPIs load  
- [ ] Login as customer → rejected  
- [ ] Orders list filters + pagination  
- [ ] Order detail shows items/payment/shipping/timeline  
- [ ] Status update only offers allowed transitions; refresh after success  
- [ ] Products list shows stock; create + edit works  
- [ ] Categories tree create/edit/delete  
- [ ] `orders@nursery.test` sees Orders, not Products write  
- [ ] Logout clears session  

## Out of scope (later phases)

Advanced inventory UI, coupons/campaigns/banners, refunds, customers, suppliers, reports, settings, audit log viewer, notifications, advanced analytics.
