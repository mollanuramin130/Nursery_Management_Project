# QA-17 BACKUP / RESTORE DRILL

**Date:** 2026-08-12  
**Scope:** Isolated local MySQL drill — **not** production host  
**Source DB:** `nursery_local` (left intact)  
**Restore target:** temporary `nursery_qa17_restore` (dropped after verification)

---

## 1. Result

| Check | Result |
|-------|--------|
| Local backup | **PASS** |
| Local restore to isolated DB | **PASS** |
| Row-count integrity (users/orders/products/payments/inventory/tables) | **PASS** (`DATA_MATCH:YES`) |
| Active DB untouched | **PASS** (live users count unchanged) |
| Production backup/restore | **UNVERIFIED** |
| Routines/triggers dump | **PARTIAL** — MariaDB `mysql.proc` mismatch forced `--skip-routines --skip-triggers` |

---

## 2. Procedure executed

Tooling: XAMPP `mysqldump` / `mysql` at `/Applications/XAMPP/xamppfiles/bin/`

1. Dump `nursery_local` with `--single-transaction --skip-routines --skip-triggers`  
2. Create empty `nursery_qa17_restore`  
3. Import dump  
4. Compare counts vs source  
5. Drop restore DB; delete dump file from `/tmp`

---

## 3. Timing & size

| Metric | Value |
|--------|-------|
| Backup time (RPO sample) | ~1 s |
| Restore time (RTO sample) | ~4 s |
| Dump size | ~437 KB |
| Tables | 93 → 93 |

### Count verification

| Table / metric | Source | Restored |
|----------------|--------|----------|
| users | 16 | 16 |
| orders | 48 | 48 |
| products | 52 | 52 |
| payments | 13 | 13 |
| inventory_items | 56 | 56 |
| tables | 93 | 93 |

---

## 4. RPO / RTO notes

| Metric | This drill | Production recommendation |
|--------|------------|---------------------------|
| RPO | Instant dump of local DB | Define scheduled dumps (e.g. hourly/daily) + retention |
| RTO | ~4 s restore of small local DB | Measure on prod-sized dump; include app boot + health |

---

## 5. What was / was not covered

**Covered:** schema + critical commerce row counts; restore into isolated DB; no destruction of `nursery_local`.

**Not covered:** production host; encrypted secrets vault restore; uploaded `storage/` assets; application boot against restored DB with full API suite; routines/triggers (tooling MariaDB proc mismatch); point-in-time recovery; failover.

---

## 6. Failure recovery observed

First attempt with `--routines --triggers` failed (`mysql.proc` column count). Retry without routines/triggers succeeded. Document for ops: run `mysql_upgrade` on MariaDB hosts before relying on routine dumps, or use managed backup tooling that handles version skew.

---

## 7. Classification

| Environment | Status |
|-------------|--------|
| Local isolated drill | **PASS** |
| Production / staging drill | **UNVERIFIED** |

Do **not** claim production backup readiness from this drill alone.
