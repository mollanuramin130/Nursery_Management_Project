# Production Deployment Checklist

Use before every production cutover.

## Backend (Laravel API)

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set and unique
- [ ] `JWT_SECRET` set
- [ ] `JWT_BLACKLIST_ENABLED=true`
- [ ] DB credentials from secure env (not committed)
- [ ] Migrations applied (`php artisan migrate --force`)
- [ ] `php artisan config:cache` / `route:cache` as appropriate
- [ ] `CORS_ALLOWED_ORIGINS` = production Website + Admin HTTPS origins only
- [ ] `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`
- [ ] `RAZORPAY_KEY` / `SECRET` / `WEBHOOK_SECRET` set for live pay
- [ ] HTTPS terminated (Hostinger/nginx/Cloudflare)
- [ ] Rate limits active (auth/payment/checkout/webhook)
- [ ] `GET /api/v1/health` returns healthy
- [ ] Logs writable; request body logging off or sampled
- [ ] Backups verified (`PRODUCTION_BACKUP_PLAN.md`)

## Customer Website (Next.js)

- [ ] `NEXT_PUBLIC_API_BASE_URL=https://…/api/v1`
- [ ] `npm run build` succeeds
- [ ] No localhost defaults in production env
- [ ] Image remote hosts allow production CDN/storage
- [ ] Login/checkout smoke against production API

## Admin Portal (Next.js)

- [ ] `NEXT_PUBLIC_API_BASE_URL=https://…/api/v1`
- [ ] Build succeeds
- [ ] Staff login works; customer login rejected
- [ ] Sample credentials **not** prefilled (`NODE_ENV=production`)
- [ ] RBAC: blocked user cannot call Admin APIs
- [ ] Refund UI warning understood (no live PSP refunds yet)

## Android

- [ ] Real `key.properties` / upload keystore (never commit)
- [ ] `flutter build appbundle --release --dart-define=API_BASE_URL=https://…/api/v1`
- [ ] Cleartext disabled in release
- [ ] Razorpay key matches backend mode
- [ ] Device smoke: browse → cart → checkout → order

## Database

- [ ] Backup taken pre-migrate
- [ ] Phase 3 indexes migrated
- [ ] Restore drill scheduled

## Post-deploy smoke

- [ ] Customer register/login
- [ ] Catalog + product detail
- [ ] COD or Razorpay order
- [ ] Admin dashboard + order status update
- [ ] Inventory adjust
- [ ] Health endpoint green
