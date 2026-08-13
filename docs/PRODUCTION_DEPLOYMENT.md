# Production Deployment

GreenLeaf Nursery — deploy order and rollback. Complements `PRODUCTION_RUNBOOK.md` and `PRODUCTION_DEPLOYMENT_CHECKLIST.md`.

## Order

1. **MySQL** — backup, then migrate  
2. **API** (`apps/nursery-api`) — code, `.env` production, `composer install --no-dev`, `config:cache`, `route:cache`, queue workers, scheduler cron  
3. **Customer Web** (`apps/nursery-web`) — `NEXT_PUBLIC_API_BASE_URL=https://…/api/v1`, `SITE_ENV=production`, build  
4. **Admin Web** (`apps/nursery-admin`) — same API URL HTTPS, build on admin host  
5. **Mobile** — release builds with `--dart-define=API_BASE_URL=https://…/api/v1` (customer + ops)

## Required production env (API)

- `APP_ENV=production`, `APP_DEBUG=false`  
- `JWT_SECRET`, strong `APP_KEY`  
- `CORS_ALLOWED_ORIGINS` = shop + admin HTTPS only (no empty / no `*`)  
- `RAZORPAY_*` + `RAZORPAY_WEBHOOK_SECRET`  
- `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`  

## Health

- `GET /api/v1/health/live`  
- `GET /api/v1/health/ready` (DB)

## Rollback

| Layer | Approach |
|-------|----------|
| API code | Redeploy previous release artifact; avoid `migrate:rollback` unless migration is reversible and tested |
| DB | Restore from dump if forward migration is unsafe |
| Web | Redeploy previous `.next` / prior Vercel deployment |
| Mobile | Store listing rollback / previous APK — users may still hold old clients; keep API backward compatible (`/api/v1`) |

## CI

`.github/workflows/ci.yml` runs API PHPUnit on PR. Wire branch protection when remote exists.
