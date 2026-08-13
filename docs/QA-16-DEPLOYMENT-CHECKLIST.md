# QA-16 DEPLOYMENT CHECKLIST

Never commit filled `.env`. Use vault/secrets manager.

## Pre-deploy

- [ ] Tag release candidate in Git
- [ ] Copy `apps/nursery-api/.env.production.example` → host `.env`
- [ ] Fill secrets (APP_KEY, DB, JWT, Razorpay, mail)
- [ ] Customer/Admin Web env: HTTPS `NEXT_PUBLIC_API_BASE_URL`
- [ ] Mobile release builds: HTTPS API `--dart-define=API_BASE_URL=…`
- [ ] CORS = production origins only
- [ ] `APP_DEBUG=false`
- [ ] Run `php artisan nursery:production-readiness --strict` → must exit 0 for paid GREEN

## Deploy API

- [ ] `composer install --no-dev -o`
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `php artisan config:cache && php artisan route:cache`
- [ ] Start queue worker (`queue:work` or supervisor)
- [ ] Cron: `* * * * * php artisan schedule:run`
- [ ] Health: `GET /api/v1/health/ready`

## Deploy Web

- [ ] `npm ci && npm run build` (Customer + Admin)
- [ ] Serve behind HTTPS
- [ ] Confirm no localhost API in production env

## Post-deploy smoke

- [ ] Customer login
- [ ] Catalog
- [ ] COD order (or Razorpay if keys live)
- [ ] Admin login + order visibility
- [ ] Fulfillment one order
- [ ] Logs free of secrets/card data

## Backup (required for GREEN)

- [ ] Take DB dump before migrate
- [ ] Store off-host
- [ ] Document restore command
- [ ] Restore drill on isolated host (see rollback plan)

## Razorpay (required for paid GREEN)

- [ ] Live/test keys on host
- [ ] Webhook URL + secret
- [ ] Successful payment + verify + webhook + duplicate webhook tests signed off
