# GreenLeaf Nursery Admin

Next.js Admin Web Portal for GreenLeaf Nursery operations.

Consumes the existing Laravel Admin REST API (`/api/v1/admin/*`) — does not talk to MySQL directly.

## Run locally

```bash
# API (separate terminal)
cd apps/nursery-api && php artisan serve --host=127.0.0.1 --port=8000

# Admin portal
cd apps/nursery-admin
cp .env.example .env.local   # if needed
npm install
npm run dev                  # http://127.0.0.1:3001
```

## Sample staff login

See `docs/SAMPLE_LOGIN_CREDENTIALS.md`:

- `admin@nursery.test` / `Secret@123`
- `superadmin@nursery.test` / `Secret@123`
- `orders@nursery.test` / `Secret@123` (orders only)

Customer accounts are rejected by the Admin portal.

## Phase 1 scope

- Auth + JWT refresh session
- Permission-aware shell (sidebar / topbar / breadcrumbs)
- Dashboard (live KPIs)
- Orders list + detail + status transitions
- Products list + create/edit foundation
- Categories CRUD (tree view)
