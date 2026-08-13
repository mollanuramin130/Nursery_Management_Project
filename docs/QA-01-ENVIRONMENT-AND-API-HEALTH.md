# QA-01 — Environment and API Health

Developer guide for GreenLeaf Nursery connectivity (QA-01).  
**Secrets:** placeholders only — never commit real passwords, JWT secrets, or payment keys.

---

## 1. Required software

| Tool | Use |
|------|-----|
| MySQL 8+ | Database |
| PHP 8.2+ / Composer | Laravel API |
| Node.js + npm | Customer Web + Admin Web |
| Flutter SDK | Customer Mobile + Admin Mobile |

---

## 2. MySQL setup

1. Start MySQL.
2. Create database (example): `nursery_local`.
3. In `apps/nursery-api/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursery_local
DB_USERNAME=<YOUR_USERNAME>
DB_PASSWORD=<YOUR_PASSWORD>
```

---

## 3. API setup

```bash
cd apps/nursery-api
cp .env.example .env
composer install
php artisan key:generate
# Configure DB_* and JWT_SECRET=<YOUR_JWT_SECRET>
```

---

## 4. Migration setup

```bash
cd apps/nursery-api
php artisan migrate
php artisan migrate:status
```

Do **not** use `migrate:fresh` unless you intentionally wipe local data.

---

## 5. Seed / sample setup

Load project sample SQL once (from repo root), e.g.:

```bash
# Example — use your MySQL client:
# mysql -u <USER> -p nursery_local < database/nursery_sample_data.sql
```

Documented local smoke accounts (after sample data):

| Role | Email | Password |
|------|-------|----------|
| Customer | `asha@example.com` | `Secret@123` |
| Admin | `admin@nursery.test` | `Secret@123` |

---

## 6. Laravel start command

```bash
cd apps/nursery-api
php artisan serve --host=0.0.0.0 --port=8000
```

| Audience | Base |
|----------|------|
| Desktop browser | `http://127.0.0.1:8000/api/v1` |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| Physical device | `http://<LAN_IP>:8000/api/v1` |

`--host=0.0.0.0` is required for phones on the LAN.  
Do **not** hardcode a personal LAN IP in source.

---

## 7. Customer Web start

```bash
cd apps/nursery-web
cp .env.example .env.local
# NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev
```

Open: `http://localhost:3000`

---

## 8. Customer Mobile start

```bash
cd apps/nursery_app
flutter pub get
# Emulator (default dart-define):
flutter run
# Physical:
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://<LAN_IP>:8000/api/v1
```

Default debug URL is Android-emulator-only (`10.0.2.2`). Release builds refuse local/emulator hosts.

---

## 9. Admin Web start

```bash
cd apps/nursery-admin
cp .env.example .env.local
# NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev   # port 3001
```

Open: `http://localhost:3001`

---

## 10. Admin Mobile start

```bash
cd apps/nursery_admin_mobile
flutter pub get
flutter run
# Physical:
flutter run -d <DEVICE_ID> --dart-define=API_BASE_URL=http://<LAN_IP>:8000/api/v1
```

---

## 11. API URL configuration

| Client | Variable | Example value |
|--------|----------|---------------|
| Customer Web | `NEXT_PUBLIC_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` |
| Admin Web | `NEXT_PUBLIC_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` |
| Customer Mobile | `--dart-define=API_BASE_URL=...` | emulator `http://10.0.2.2:8000/api/v1` |
| Admin Mobile | `--dart-define=API_BASE_URL=...` | same pattern |

Must include `/api/v1` once (avoid `/api/v1/api/v1`).

---

## 12–15. Browser / emulator / simulator / LAN

| Environment | API host concept |
|-------------|------------------|
| Browser | `127.0.0.1` / `localhost` |
| Android emulator | `10.0.2.2` |
| iOS simulator | usually Mac `127.0.0.1` (confirm for your setup) |
| Physical Android / iPhone | developer machine LAN IP + `serve --host=0.0.0.0` |

---

## 16. CORS configuration

API env:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001,http://127.0.0.1:3001
```

Implemented in `apps/nursery-api/config/cors.php`.  
Production with empty list fails closed (no `*`).

Verified: OPTIONS + POST login with Origin `:3000` and `:3001`.

---

## 17. Health endpoint

Actual routes (`System` module):

| Route | Behavior |
|-------|----------|
| `GET /api/v1/health` | Envelope + DB check; 503 if DB unhealthy |
| `GET /api/v1/health/live` | `{ "status": "ok" }` |
| `GET /api/v1/health/ready` | `{ status, database, cache }` — **200** when DB healthy, **503** when not |

Ready (healthy) example:

```json
{"status":"ok","database":"healthy","cache":"healthy"}
```

Login UIs also probe ready via client `api-health` helpers.

---

## 18. Login smoke test

```bash
cd apps/nursery-api
bash scripts/qa01_smoke.sh
# Extended (cart + checkout preview + admin):
bash scripts/qa01_connectivity_smoke.sh
```

HTTP **429** = auth throttle (wait ~60s). Scripts retry once.

---

## 19. Common errors

| Symptom | Likely cause |
|---------|----------------|
| Unable to connect | API not running / wrong base URL |
| CORS browser error | Origin missing from `CORS_ALLOWED_ORIGINS` |
| Phone cannot reach API | Using `10.0.2.2` on physical device, or serve bound to 127.0.0.1 only |
| Login 429 | Throttle — not a broken contract |
| Empty catalog / login user missing | Sample SQL / seed not loaded |

---

## 20. Troubleshooting checklist

1. `curl http://127.0.0.1:8000/api/v1/health/ready`
2. Confirm `.env` / `.env.local` / dart-define
3. Confirm CORS origins for ports **3000** and **3001**
4. Confirm sample accounts
5. For devices: LAN IP + `--host=0.0.0.0`
6. Re-run smoke scripts

Also see: `RUN.txt`, `docs/ENVIRONMENT_SETUP.md`, `docs/QA-01-REPORT.md`.
