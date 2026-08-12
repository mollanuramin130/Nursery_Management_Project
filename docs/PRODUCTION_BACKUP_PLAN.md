# Production Backup Plan

GreenLeaf Nursery — database and media backup readiness.

**Status:** Plan documented. Automated cloud backups are **operator-owned** (Hostinger/VPS). This repo does not pretend backups are already configured.

---

## What must be backed up

| Asset | Why |
|-------|-----|
| MySQL database (`nursery_*`) | Orders, payments, inventory, users, catalog |
| `.env` / secrets store | Offline disaster recovery of config (store encrypted off-server) |
| Uploaded/referenced media | If/when local disk storage is used; today many images are remote URLs |
| Migration state | Implicit in DB + code deploy |

Do **not** backup: `vendor/`, `node_modules/`, `.next/`, build caches.

---

## Frequency (recommended)

| Environment | Full DB dump | Binlog / incremental |
|-------------|--------------|----------------------|
| Production | Daily | Continuous if available |
| Staging | Weekly | Optional |
| Local | Optional | No |

Retention: **30 days** rolling full dumps minimum for production; keep one monthly dump for 12 months if compliance needs allow.

---

## How (Hostinger / VPS example)

1. Prefer host panel automated MySQL backups if available.  
2. Or cron `mysqldump`:

```bash
mysqldump -u USER -p --single-transaction --routines --triggers DBNAME \
  | gzip > /backups/nursery_$(date +%F).sql.gz
```

3. Copy dumps off-host (S3/object storage/another region).  
4. Encrypt at rest if dumps leave the VPS.

---

## Restore procedure

1. Take the site to maintenance / stop writers.  
2. Restore dump into a **new** database first when possible.  
3. Run app against restored DB in staging; smoke: login, catalog, place COD order, admin dashboard.  
4. Cut over DNS/config only after verification.  
5. Record restore time and operator in incident log.

---

## Verification procedure

Monthly:

1. Restore latest dump to staging.  
2. `php artisan migrate:status`  
3. Hit `GET /api/v1/health`  
4. Admin login + orders list  
5. Confirm row counts roughly match production snapshot notes

---

## Application notes

- Soft deletes retain recoverable rows for many entities; backups still required.  
- Payment provider is source of truth for captured funds; DB restore may desync with Razorpay — reconcile after restore.
