# Production Runbook — GreenLeaf Nursery

Project-specific operations for:

| App | Path |
|-----|------|
| API | `apps/nursery-api` |
| Website | `apps/nursery-web` |
| Admin | `apps/nursery-admin` |
| Android | `apps/nursery_app` |

Related: `PHASE_13_DEPLOYMENT_ARCHITECTURE.md`, `PRODUCTION_LAUNCH_CHECKLIST.md`, `PRODUCTION_BACKUP_PLAN.md`, `PRODUCTION_MONITORING_PLAN.md`, `RUN_DEPLOY_AND_CUSTOMIZE.md`.

**Never run on production:** `php artisan migrate:fresh`, `db:wipe`, sample SQL import.

---

## 0. Pre-flight (every release)

```bash
# API
cd apps/nursery-api && composer install && php artisan test

# Website
cd apps/nursery-web && npm ci && npm run lint && npm run build

# Admin
cd apps/nursery-admin && npm ci && npm run lint && npm run build

# Android (optional gate)
cd apps/nursery_app && flutter analyze
```

Confirm staging smoke PASS before touching production.

---

## 1. Backup

```bash
mysqldump -u USER -p --single-transaction --routines --triggers DBNAME \
  | gzip > /backups/nursery_prod_$(date +%F_%H%M).sql.gz
```

Copy off-host. Record filename in the change ticket.  
See `PRODUCTION_BACKUP_PLAN.md`.

---

## 2. Maintenance (optional)

```bash
cd /var/www/nursery-api   # adjust path
php artisan down --retry=60 --secret=ops-bypass-token
# Frontend may still show static pages; prefer short window.
```

Bring up after smoke:

```bash
php artisan up
```

---

## 3. API deployment (Hostinger / VPS)

```bash
cd /var/www/nursery-api
git fetch && git checkout <release-tag-or-sha>
composer install --no-dev --optimize-autoloader
```

Ensure `.env` matches `apps/nursery-api/.env.production.example` profile:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- Live Razorpay + webhook secret
- `CORS_ALLOWED_ORIGINS` = HTTPS shop + admin
- `CACHE_STORE=redis` if multiple app nodes

---

## 4. Migration

```bash
php artisan migrate --force
php artisan migrate:status
```

If migration fails: **stop**, restore from backup (§9), do not continue frontend cutover.

---

## 5. Cache / optimize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Avoid event:cache unless you have verified listeners load correctly on this Laravel version.
php artisan storage:link   # once
```

Clear if misconfigured:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

---

## 6. Queue + scheduler

Supervisor: install `docs/deploy/supervisor-nursery-api-worker.conf.example` → `supervisorctl reread && supervisorctl update && supervisorctl restart nursery-api-worker:*`

After code deploy:

```bash
php artisan queue:restart
php artisan queue:failed
```

Cron (once per host):

```cron
* * * * * cd /var/www/nursery-api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs: inventory reservation release (hourly), subscriptions due (15m).

---

## 7. Website deployment

```bash
cd /var/www/nursery-web   # or Vercel project
# Build-time env:
# NEXT_PUBLIC_API_BASE_URL=https://api.example.com/api/v1
# NEXT_PUBLIC_SITE_ENV=production
# NEXT_PUBLIC_STORE_NAME=GreenLeaf Nursery
npm ci
npm run build
npm start   # or host process manager / Vercel promote
```

Staging must use `NEXT_PUBLIC_SITE_ENV=staging` (robots noindex).

---

## 8. Admin deployment

```bash
cd /var/www/nursery-admin
# NEXT_PUBLIC_API_BASE_URL=https://api.example.com/api/v1
# NEXT_PUBLIC_SITE_ENV=production
npm ci
npm run build
npm start -- -p 3001   # or reverse-proxy to process
```

Restrict admin host (VPN / IP allowlist / Cloudflare Access recommended — operator choice).

---

## 9. Health check

```bash
curl -fsS https://api.example.com/api/v1/health
curl -fsS https://api.example.com/api/v1/health/live
curl -fsS https://api.example.com/api/v1/health/ready
curl -fsS https://api.example.com/up
```

Expect DB healthy / status ok. Capture `X-Request-Id` on failures.

---

## 10. Smoke test (minimum)

1. Customer site loads over HTTPS  
2. Register or login  
3. Catalog + PDP  
4. Add to cart → checkout preview  
5. COD **or** Razorpay (mode matching env) → order visible  
6. Admin login → order detail → status update  
7. Notification row or email log for order event  

Full checklist: `PRODUCTION_LAUNCH_CHECKLIST.md`.

---

## 11. Monitoring (first 24–72h)

Watch: 5xx, `/health` failures, `failed_jobs`, Razorpay dashboard, checkout errors, queue worker up.  
Plan: `PRODUCTION_MONITORING_PLAN.md`.

---

## 12. Rollback

### Application

1. Redeploy previous git tag / artifact for API + web + admin.  
2. `composer install --no-dev`, `npm ci && npm run build` as needed.  
3. `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`  
4. `php artisan queue:restart`  
5. Health + smoke.

### Database

- Prefer **restore pre-deploy dump** over `migrate:rollback` for production.  
- Additive index migrations (e.g. Phase 12 reviews) may rollback safely; data migrations may not.  
- Document forward-fix if a migration is irreversible.

### Android

- Play Console staged rollout halt / previous release promote.  
- Do not force-uninstall customers.

---

## 13. Staging restore drill (backup validity)

1. Take recent dump (prod or staging).  
2. Restore into **new** staging DB.  
3. Point staging API `.env` at restored DB temporarily.  
4. `php artisan migrate:status`  
5. Smoke login + catalog + COD order + admin orders.  
6. Record duration → update RTO evidence.  

Until this passes once: backup = **NOT VERIFIED**.

---

## 14. Incident response

| Incident | First actions |
|----------|----------------|
| API outage | Health checks, PHP-FPM/nginx, `.env`, last deploy rollback |
| DB outage | Host status, connections, restore plan |
| Payment outage | Razorpay status, webhook logs, disable online pay via config if needed, keep COD |
| Queue outage | `supervisorctl status`, `queue:failed`, restart worker |
| Email outage | SMTP creds, provider status; commerce continues |
| Push outage | FCM creds; inbox still works |
| Storage outage | Disk/CDN; catalog URLs |
| Security | Rotate JWT/APP_KEY only with planned logout; revoke refresh tokens; audit_logs |

Preserve `X-Request-Id` and timestamps.

---

## 15. Android release build

```bash
cd apps/nursery_app
# Requires operator key.properties + keystore (never commit)
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://api.example.com/api/v1 \
  --dart-define=STORE_NAME=GreenLeaf Nursery
```

Release builds **refuse** localhost / `10.0.2.2` / cleartext (`AppConfig.assertReleaseConfiguration`).

---

## 16. Soft launch sequence

Internal staging → production deploy → internal prod smoke → invite-only cohort → monitor 24–72h → expand traffic.  
Do not market unlimited launch until CRITICAL checklist items PASS.
