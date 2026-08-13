# Production Backup & Recovery

Canonical plan: **`docs/PRODUCTION_BACKUP_PLAN.md`**.

This Phase 21 note confirms:

- Backups are **operator-owned** (host panel / cron `mysqldump`).  
- This repository does **not** claim automated cloud backups are already configured.  
- Restore: import dump → run migrations if needed → verify `php artisan` health → reattach storage/env.

**Before soft launch:** enable daily DB dumps + retention (≥30 days) and test one restore on staging.
