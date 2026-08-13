# QA-16 PRODUCTION READINESS

**Date:** 2026-08-12

## Verdict

**NOT GREEN.** Ready for **YELLOW** COD soft-launch / UAT after deploy-host hardening. Paid checkout blocked until Razorpay live.

## Checker

```bash
cd apps/nursery-api
php artisan nursery:production-readiness
# On production hosts:
php artisan nursery:production-readiness --strict
```

`--strict` exits non-zero when any P0 finding exists (debug on, missing Razorpay, localhost CORS, non-HTTPS APP_URL, unsigned webhooks, missing JWT).

## Production profile must have

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://…`
- `CORS_ALLOWED_ORIGINS` = HTTPS shop + admin only
- `JWT_SECRET` set
- `RAZORPAY_KEY` / `RAZORPAY_SECRET` / `RAZORPAY_WEBHOOK_SECRET` set for paid
- `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false`
- Queue worker + `php artisan schedule:run` via cron
- `php artisan storage:link`
- HTTPS termination

## Local profile (this QA host)

Correctly reports no P0 for `local` even with empty Razorpay (stub allowed non-prod).

## Security residual

**QA-SEC-001 remains OPEN** and requires documented risk acceptance for public Web.
