# QA-17 PRODUCTION READINESS

**Date:** 2026-08-12  
**Command:** `php artisan nursery:production-readiness` / `--strict`

---

## 1. This-host results

| Command | APP_ENV | Exit | Interpretation |
|---------|---------|------|----------------|
| `nursery:production-readiness` | local | **0** | No P0 findings for **local** profile (expected) |
| `nursery:production-readiness --strict` | local | **1** | **FAIL** — strict gate requires `APP_ENV=production` |

QA-17 fixed a misleading behavior: previously `--strict` could exit 0 on local because local profile emits no P0 findings. Strict now **rejects non-production environments**.

---

## 2. Production profile requirements (checker)

When `APP_ENV=production`, checker expects (non-exhaustive):

- `APP_DEBUG=false`
- HTTPS `APP_URL`
- Razorpay key/secret/webhook configured
- JWT / secrets present
- CORS not wide-open localhost-only patterns inappropriate for prod
- Related filesystem/queue expectations as encoded in `ProductionReadinessChecker`

**Production host run of `--strict`:** **UNVERIFIED** (no production host in this session).

---

## 3. Configuration probe (secrets not printed)

| Item | Observed |
|------|----------|
| APP_ENV | local |
| APP_DEBUG | true |
| APP_URL | http://localhost:8000 |
| Razorpay KEY/SECRET/WEBHOOK | EMPTY |
| composer audit | No advisories found |
| Schedule jobs | Registered |

---

## 4. GREEN gate assessment

| Mandatory for GREEN | Status |
|---------------------|--------|
| Strict readiness on production | **UNVERIFIED / FAIL on this host** |
| Razorpay live | **BLOCKED** |
| HTTPS + DEBUG false | **UNVERIFIED** on prod |
| Backup/restore local | **PASS** (local only) |
| Backup/restore prod | **UNVERIFIED** |

**Verdict:** Production readiness for GREEN is **not met**. Local tooling is improved and regression-covered.
