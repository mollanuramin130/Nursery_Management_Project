# PHASE 13 — Deployment Architecture

GreenLeaf Nursery — as-audited from repository + existing ops docs.  
**No production deploy was performed in Phase 13.**

---

## 1. Current architecture (source of truth)

| Component | Current state in repo | Assumed hosting (docs) |
|-----------|----------------------|-------------------------|
| API | `apps/nursery-api` Laravel | Hostinger / VPS — docroot `public/` |
| Database | MySQL via `.env` | Host MySQL (separate DBs per env) |
| Website | `apps/nursery-web` Next.js | Node host / Vercel / VPS `npm start` |
| Admin | `apps/nursery-admin` Next.js (port 3001 local) | Separate subdomain / host |
| Android | `apps/nursery_app` Flutter | Play Store AAB (release signing **operator-owned**) |
| Docker | **Not present** | N/A |
| CI/CD | **No project GitHub Actions** | Manual deploy |
| Media | Mostly remote image URLs; `FILESYSTEM_DISK=public` | Prefer durable disk or object storage |
| Queue | `QUEUE_CONNECTION=database` | Supervisor worker (example in `docs/deploy/`) |
| Scheduler | `routes/console.php` | Cron `schedule:run` every minute |
| Cache | `file` local; Redis recommended multi-node | Host Redis optional |
| Email | SMTP (Hostinger-style in examples) | Operator SMTP |
| Push | FCM env vars | Operator Firebase project |
| Payments | Razorpay | Test on staging; live on production only |
| Monitoring | Health endpoints + plan docs | UptimeRobot/Sentry — **NOT VERIFIED** |
| Backup | Documented plan only | **NOT VERIFIED** restore |

---

## 2. Recommended domain layout (placeholders)

Use real domains owned by the operator — do not assume these names:

| Surface | Example |
|---------|---------|
| Customer website | `https://www.example.com` |
| API | `https://api.example.com` |
| Admin | `https://admin.example.com` |
| Staging website | `https://staging.example.com` |
| Staging API | `https://api-staging.example.com` |
| Staging admin | `https://admin-staging.example.com` |
| Razorpay webhook | `POST https://api.example.com/api/v1/payments/webhooks/razorpay` |

---

## 3. Environment separation

| | Development | Staging | Production |
|--|-------------|---------|------------|
| Database | `nursery_local` | `nursery_staging` (isolated) | `nursery_prod` (isolated) |
| API URL | `http://127.0.0.1:8000` | `https://api-staging…` | `https://api…` |
| Payments | stub or Razorpay **test** | Razorpay **test** | Razorpay **live** |
| Sample SQL | Allowed | Allowed (anonymized / fake) | **Forbidden** |
| `APP_DEBUG` | true OK | **false** | **false** |
| Indexing | noindex | noindex (`NEXT_PUBLIC_SITE_ENV=staging`) | index (`production`) |

**Never** point staging/dev at the production database.

Env templates:

- `apps/nursery-api/.env.staging.example`
- `apps/nursery-api/.env.production.example`
- `apps/nursery-web/.env.staging.example` / `.env.production.example`
- `apps/nursery-admin/.env.staging.example` / `.env.production.example`

---

## 4. Environment variable classification

| Class | Examples | Allowed in browser/Android? |
|-------|----------|----------------------------|
| PUBLIC | `NEXT_PUBLIC_API_BASE_URL`, `NEXT_PUBLIC_STORE_NAME`, Razorpay **Key ID** via API payload | Yes |
| PRIVATE | `APP_URL`, DB host, mail host | Server only |
| SECRET | `APP_KEY`, `JWT_SECRET`, `DB_PASSWORD`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, `MAIL_PASSWORD`, FCM credentials | Server only — never git, never Next/Flutter bundles |

---

## 5. Queue & scheduler

**Scheduled (production cron required):**

- `inventory:release-expired-reservations` — hourly  
- `subscriptions:process-due` — every 15 minutes  

**Workers:** `php artisan queue:work database` via Supervisor — see `docs/deploy/supervisor-nursery-api-worker.conf.example`.

Jobs: notification delivery, email/push, async work. Monitor `failed_jobs`.

---

## 6. DNS / HTTPS (operator)

Document when creating DNS:

- A/AAAA or CNAME for www, api, admin (+ staging equivalents)  
- HTTPS certificates (Let’s Encrypt / host panel)  
- HTTP → HTTPS redirect  
- Email: SPF / DKIM / DMARC for transactional mail domain  

**Status:** NOT VERIFIED (no live DNS in this phase).

---

## 7. Storage / CDN

Catalog often uses absolute image URLs. If switching to local uploads, use durable storage and backups — do not rely on ephemeral containers without a volume.

CDN: optional; do not introduce without need.

---

## 8. Deployment blockers from architecture gaps

| Blocker | Severity |
|---------|----------|
| No verified staging host | CRITICAL |
| No verified production DNS/HTTPS | CRITICAL |
| No verified backup restore | CRITICAL |
| No live Razorpay + webhook on prod | CRITICAL (for online pay) |
| No release signing keystore in repo (expected) / operator must supply | HIGH |
| No privacy/terms pages in website | HIGH (legal/owner) |
| No monorepo CI pipeline | MEDIUM |
| Firebase/FCM production not configured | MEDIUM (push) |
