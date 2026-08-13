# QA-16 ROLLBACK PLAN

## Goals

Recover from a bad deploy without data corruption or secret exposure.

## Application rollback

1. Stop queue workers (prevent partial jobs).
2. Redeploy previous Git tag / artifact.
3. Restore previous built Web assets if needed.
4. `php artisan config:cache` / `route:cache` on previous release.
5. Restart workers.
6. Verify `GET /api/v1/health/ready`.
7. Verify login + one order lookup (customer + admin).

## Database rollback

**Prefer forward-fix over migrate:rollback in production.**

If a migration must be reversed:

1. Restore from **pre-deploy backup** into a staging clone first.
2. Validate app against restored clone.
3. Only then restore production from backup during maintenance window.
4. Record downtime and order/payment freeze if payments were live.

Never run destructive `migrate:rollback` on production without a tested backup restore.

## Backup / restore drill (required evidence for GREEN)

```text
1. mysqldump (or managed backup) of production
2. Restore into isolated DB nursery_restore_test
3. Point a staging API .env at restore DB
4. php artisan migrate:status
5. Login + catalog + order show
6. Destroy restore environment securely
```

**Status on QA-16 host:** UNVERIFIED (no production DB access).

## Payment-safe rollback

- If Razorpay live: pause new checkouts; reconcile pending payments via provider dashboard before DB restore.
- Do not replay webhooks blindly after restore without idempotency review.

## Decision log

Record: who, when, from-version, to-version, backup ID, verification results.
