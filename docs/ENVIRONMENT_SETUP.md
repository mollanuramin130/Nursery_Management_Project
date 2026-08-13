# GreenLeaf Nursery — Environment Setup

**Audience:** local / staging developers  
**Secrets:** never commit real passwords, JWT secrets, or payment keys. Use placeholders below.

---

## 1. Requirements

| Component | Notes |
|-----------|--------|
| MySQL 8+ | Database `nursery_local` (or your chosen name) |
| PHP 8.2+ / Composer | Laravel API (`apps/nursery-api`) |
| Node.js + npm | Customer Web + Admin Web |
| Flutter SDK | Customer Mobile + Admin Mobile |
| Same API for all clients | `/api/v1` on Laravel |

---

## 2. MySQL setup

1. Start MySQL.
2. Create database (example name): `nursery_local`.
3. Set credentials in `apps/nursery-api/.env` (copy from `.env.example`):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursery_local
DB_USERNAME=<YOUR_USERNAME>
DB_PASSWORD=<YOUR_PASSWORD>
```

Do **not** put real passwords in docs or git.

---

## 3. Laravel setup

```bash
cd apps/nursery-api
cp .env.example .env
php artisan key:generate
# Set DB_* and JWT_SECRET (or run jwt:secret if used by project)
composer install
```

Important env (placeholders only):

```env
APP_ENV=local
APP_URL=http://localhost:8000
CUSTOMER_WEB_URL=http://127.0.0.1:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001,http://127.0.0.1:3001
JWT_SECRET=<YOUR_JWT_SECRET>
```

---

## 4. Migration

```bash
cd apps/nursery-api
php artisan migrate
php artisan migrate:status
```

Apply pending migrations before relying on newer Phase 15–20 features.  
QA-01 connectivity does **not** require inventing new schema.

---

## 5. Seed / sample data

Preferred local path (existing project asset):

```bash
# From repo root — load once into empty/local DB
# (exact import command depends on your MySQL client)
# Example:
# mysql -u <USER> -p nursery_local < database/nursery_sample_data.sql
```

Also review Laravel seeders under `apps/nursery-api/database/seeders` if used.

Documented smoke accounts (after sample SQL):

| Role | Email | Password |
|------|-------|----------|
| Customer | `asha@example.com` | `Secret@123` |
| Admin | `admin@nursery.test` | `Secret@123` |

Do not hardcode these into production builds.

---

## 6. API startup

```bash
cd apps/nursery-api
php artisan serve --host=0.0.0.0 --port=8000
```

| Who | URL |
|-----|-----|
| Browser / desktop | `http://127.0.0.1:8000/api/v1` |
| Health | `http://127.0.0.1:8000/api/v1/health/ready` |
| Smoke | `bash scripts/qa01_smoke.sh` |

`--host=0.0.0.0` is required for physical phones on the LAN.

---

## 7. Customer Web startup

```bash
cd apps/nursery-web
cp .env.example .env.local
# NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev
```

Open: `http://localhost:3000`  
Login page shows API readiness banner.

---

## 8. Customer Mobile startup

```bash
cd apps/nursery_app
flutter pub get
```

**Android emulator (default):**

```bash
flutter run
# default API_BASE_URL = http://10.0.2.2:8000/api/v1
```

**Physical device (same Wi‑Fi as machine):**

```bash
# macOS example:
ipconfig getifaddr en0
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://<LAN_IP>:8000/api/v1
```

Release builds refuse emulator/`localhost` HTTP defaults (`AppConfig.assertReleaseConfiguration`).

---

## 9. Admin Web startup

```bash
cd apps/nursery-admin
cp .env.example .env.local
# NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev   # binds port 3001
```

Open: `http://localhost:3001` (or `http://127.0.0.1:3001`)

---

## 10. Admin Mobile startup

```bash
cd apps/nursery_admin_mobile
flutter pub get
flutter run   # emulator default http://10.0.2.2:8000/api/v1
# Physical:
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://<LAN_IP>:8000/api/v1
```

---

## 11–13. URL matrix (networking)

| Client | Typical API base |
|--------|------------------|
| Desktop browser | `http://127.0.0.1:8000/api/v1` |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Physical Android | `http://<DEVELOPER_LAN_IP>:8000/api/v1` |

Do **not** hardcode a personal LAN IP in source. Use env / `--dart-define`.

---

## 14. `API_BASE_URL` / `NEXT_PUBLIC_API_BASE_URL`

| App | Variable | Must include `/api/v1` |
|-----|----------|-------------------------|
| Customer Web | `NEXT_PUBLIC_API_BASE_URL` | Yes |
| Admin Web | `NEXT_PUBLIC_API_BASE_URL` | Yes |
| Customer Mobile | `--dart-define=API_BASE_URL=...` | Yes |
| Admin Mobile | `--dart-define=API_BASE_URL=...` | Yes |

Avoid double prefixes (`.../api/v1/api/v1`).

---

## 15. CORS

Set on API:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001,http://127.0.0.1:3001
```

Production: set explicit HTTPS origins; empty list fails closed (no `*`).

Allowed client headers in use include: `Content-Type`, `Authorization`, `Accept`, `X-Cart-Token`, `X-Platform`, `X-App-Version`, `X-Request-Id`, `X-Device-Id`, `X-Client`.

---

## 16. Health check

```bash
curl -s http://127.0.0.1:8000/api/v1/health/ready
# expect: {"status":"ok","database":"healthy",...}
```

Also: `/api/v1/health`, `/api/v1/health/live`.

---

## 17. Login smoke test

```bash
cd apps/nursery-api
bash scripts/qa01_smoke.sh
```

Or manually POST `/api/v1/auth/login` with seeded emails (do not log tokens).

If HTTP **429**: auth throttle — wait ~60s (not a connectivity failure).

---

## Related docs

- `RUN.txt` — short run order  
- `docs/RUN_DEPLOY_AND_CUSTOMIZE.md` — fuller deploy guide  
- `docs/qa/QA-01-REPORT.md` — QA-01 evidence  
- `docs/qa/QA-01-ENVIRONMENT-MATRIX.md` — verification matrix  
