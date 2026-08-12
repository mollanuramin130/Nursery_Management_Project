# GreenLeaf Nursery Platform — Run, Deploy & Customize Guide

This guide is for anyone who downloads or clones **Nursery_Platform** and wants to:

1. Start the database and API server  
2. Run the customer website  
3. Run the customer Android app  
4. Run the **Admin Web Portal**  
5. Run the **Admin Mobile** (GreenLeaf Ops) app  
6. Know **exact folders** to edit for customization  
7. Deploy to a live server  

**Project root (example on this Mac):**  
`/Users/nuramin/Desktop/Nursery_Platform`

Everywhere below, `<PROJECT_ROOT>` means that folder.

---

## 1. What this project contains

| App | Package / folder | Stack | Purpose |
|-----|------------------|-------|---------|
| **API** | `apps/nursery-api` | Laravel (PHP) + MySQL + JWT | Backend `/api/v1` |
| **Website** | `apps/nursery-web` | Next.js (React/TypeScript) | Customer storefront |
| **Android app** | `apps/nursery_app` | Flutter | Customer mobile storefront |
| **Admin Web** | `apps/nursery-admin` | Next.js (React/TypeScript) | Staff / admin portal (port **3001**) |
| **Admin Mobile** | `apps/nursery_admin_mobile` | Flutter | Staff ops app (**GreenLeaf Ops**) |
| **SQL seeds** | `database/` | MySQL dump | Sample products, users, orders |
| **Design docs** | `docs/` | Markdown | API contracts, DB design, credentials |

```text
Nursery_Platform/
├── README.md
├── docs/
│   ├── RUN_DEPLOY_AND_CUSTOMIZE.md   ← this file
│   ├── SAMPLE_LOGIN_CREDENTIALS.md
│   ├── PROJECT_DEVELOPMENT_GUIDE.md
│   ├── DATABASE_DESIGN.md
│   ├── IMPLEMENTATION_PLAYBOOK.md
│   ├── PHASE_19_FINAL_REPORT.md      ← Admin Mobile
│   └── PHASE_1_ADMIN_PORTAL.md       ← Admin Web
├── database/
│   ├── nursery_sample_data.sql
│   └── nursery_sample_data_reset.sql
└── apps/
    ├── nursery-api/              ← API code
    ├── nursery-web/              ← Customer website
    ├── nursery_app/              ← Customer Android (Flutter)
    ├── nursery-admin/            ← Admin Web Portal
    └── nursery_admin_mobile/     ← Admin Mobile (GreenLeaf Ops)
```

**Important:** Website, customer Android, Admin Web, and Admin Mobile all call the **same API**. Always start **MySQL → API** before any client. Do **not** use customer accounts in Admin apps (staff only).

---

## 2. Prerequisites (install once)

| Tool | Needed for | Check command | Typical version |
|------|------------|---------------|-----------------|
| **PHP** | API | `php -v` | 8.3+ |
| **Composer** | API | `composer -V` | 2.x |
| **MySQL** (XAMPP / Homebrew / etc.) | Database | `mysql --version` | 8.x |
| **Node.js + npm** | Website + Admin Web | `node -v` / `npm -v` | Node 20+ |
| **Flutter SDK** | Customer + Admin Mobile | `flutter --version` | 3.38+ |
| **Android Studio** + SDK | Flutter apps | — | Recent stable |
| **Git** | Clone project | `git --version` | any |

Optional:

- Physical Android phone with **USB debugging** ON, or an Android emulator  
- Xcode (only if you build Admin Mobile / iOS targets)  
- Postman (API testing) — collection lives in `apps/nursery-api/postman/`

---

## 3. Correct startup order (every time)

```text
1) Start MySQL
2) Create / migrate DB + load sample data (first time only)
3) Start API              →  http://127.0.0.1:8000
4) Start Website          →  http://localhost:3000      (customer)
5) Start Admin Web        →  http://127.0.0.1:3001      (staff)
6) Start Customer Android and/or Admin Mobile (emulator or phone)
```

Keep the **API terminal open** while using web or mobile clients.

---

## 4. Database — create and seed

### 4.1 Start MySQL

**XAMPP (macOS):** open XAMPP → Start **MySQL**  
Or from Terminal (XAMPP path):

```bash
sudo /Applications/XAMPP/xamppfiles/bin/mysql.server start
```

### 4.2 Create the database (first time)

```bash
# XAMPP mysql client example:
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS nursery_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If your MySQL user has a password, add `-p`.

### 4.3 Configure API `.env`

```bash
cd <PROJECT_ROOT>/apps/nursery-api
cp .env.example .env
```

Edit `.env` so DB matches your MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursery_local
DB_USERNAME=root
DB_PASSWORD=

APP_URL=http://localhost:8000
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:3001,http://127.0.0.1:3001
```

Include **3001** so the Admin Web Portal can call the API from the browser.

Then:

```bash
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan storage:link
```

### 4.4 Load sample data (recommended for local demo)

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < <PROJECT_ROOT>/database/nursery_sample_data.sql
```

To wipe sample rows and re-import, see `database/nursery_sample_data_reset.sql` then re-run the sample SQL.

### 4.5 Test logins (after sample SQL)

See: `docs/SAMPLE_LOGIN_CREDENTIALS.md`

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@nursery.test` | `Secret@123` |
| Customer | `asha@example.com` | `Secret@123` |

Use **Admin** (and other staff) accounts for Admin Web (`:3001`) and Admin Mobile. Use **Customer** accounts for the storefront website and customer Android app.

**Do not use these passwords in production.**

---

## 5. Run the API

**Folder to run from:**

```text
<PROJECT_ROOT>/apps/nursery-api
```

**Commands:**

```bash
cd <PROJECT_ROOT>/apps/nursery-api
php artisan serve
```

**API base URL:**

```text
http://127.0.0.1:8000/api/v1
```

Example health/config style call (after server is up):

```bash
curl http://127.0.0.1:8000/api/v1/app/config
```

Login example:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"asha@example.com","password":"Secret@123"}'
```

**Postman:**

- Collection: `apps/nursery-api/postman/Nursery-API.postman_collection.json`
- Environment: `apps/nursery-api/postman/Nursery-Local.postman_environment.json`
- OpenAPI stub: `apps/nursery-api/openapi.yaml`
- Full contracts: `docs/PROJECT_DEVELOPMENT_GUIDE.md`

**Optional queue worker** (notifications / jobs):

```bash
cd <PROJECT_ROOT>/apps/nursery-api
php artisan queue:listen --tries=1
```

---

## 6. Run the website (Next.js)

**Folder to run from:**

```text
<PROJECT_ROOT>/apps/nursery-web
```

**First time:**

```bash
cd <PROJECT_ROOT>/apps/nursery-web
cp .env.example .env.local
npm install
```

`.env.local` must point at your local API:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_STORE_NAME=GreenLeaf Nursery
```

**Start dev server:**

```bash
npm run dev
```

Open: [http://localhost:3000](http://localhost:3000)

**Production-style local build:**

```bash
npm run build
npm start
```

---

## 7. Run the Android app (Flutter)

**Folder to run from:**

```text
<PROJECT_ROOT>/apps/nursery_app
```

This project is **Android-only** (there is an `android/` folder; no `web/` / `ios/` app targets by default).

### 7.1 One-time setup

```bash
cd <PROJECT_ROOT>/apps/nursery_app
flutter pub get
flutter doctor
```

Install / enable **Flutter** and **Dart** plugins in Android Studio if the IDE shows:

`Unknown run configuration type FlutterRunConfigurationType`

### 7.2 Connect a device

```bash
flutter devices
```

Options:

1. **Emulator**

```bash
flutter emulators --launch Medium_Phone_API_36.1
# wait until home screen appears
flutter run
```

2. **Physical phone**

- Enable Developer options → USB debugging  
- Unlock phone and accept “Allow USB debugging?”  
- USB mode = File transfer / MTP  
- Confirm with `adb devices` (must say `device`, not `unauthorized`)

```bash
flutter run -d <DEVICE_ID>
```

### 7.3 API URL for the phone / emulator (critical)

Default in code (`lib/core/config.dart`) is the **Android emulator** loopback:

```text
http://10.0.2.2:8000/api/v1
```

That works for emulators talking to API on your Mac.  
For a **physical phone**, use your Mac’s LAN IP:

```bash
# find your Mac IP (example)
ipconfig getifaddr en0

flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000/api/v1
```

Replace `192.168.x.x` with your real IP. Phone and Mac must be on the same Wi‑Fi. API must be running (`php artisan serve --host=0.0.0.0` if the phone cannot reach `127.0.0.1`).

Recommended when testing on a physical device:

```bash
cd <PROJECT_ROOT>/apps/nursery-api
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 8. Run the Admin Web Portal (Next.js)

**Folder to run from:**

```text
<PROJECT_ROOT>/apps/nursery-admin
```

This is the **staff / operations** portal (not the customer storefront). It uses Admin REST routes under `/api/v1/admin/*` and the same JWT login as the API.

**First time:**

```bash
cd <PROJECT_ROOT>/apps/nursery-admin
cp .env.example .env.local
npm install
```

`.env.local` must point at your local API:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_DEFAULT_WAREHOUSE_ID=1
NEXT_PUBLIC_SITE_ENV=local
```

**Start dev server** (port **3001**, so it does not clash with the customer site on 3000):

```bash
npm run dev
```

Open: [http://127.0.0.1:3001](http://127.0.0.1:3001)

**Staff login** (from sample SQL — see `docs/SAMPLE_LOGIN_CREDENTIALS.md`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@nursery.test` | `Secret@123` |
| Super Admin | `superadmin@nursery.test` | `Secret@123` |
| Orders staff | `orders@nursery.test` | `Secret@123` |

Customer accounts (e.g. `asha@example.com`) are **rejected** by the Admin portal.

**Production-style local build:**

```bash
npm run build
npm start
```

**If the browser shows CORS errors:** ensure API `.env` includes `http://127.0.0.1:3001` in `CORS_ALLOWED_ORIGINS`, then restart `php artisan serve`.

More detail: `docs/PHASE_1_ADMIN_PORTAL.md`, `docs/PRODUCTION_RUNBOOK.md`.

---

## 9. Run the Admin Mobile app — GreenLeaf Ops (Flutter)

**Folder to run from:**

```text
<PROJECT_ROOT>/apps/nursery_admin_mobile
```

This is a **separate** Flutter app for warehouse / order / inventory staff. Do **not** run or customize `apps/nursery_app` when you need Admin Mobile.

### 9.1 One-time setup

```bash
cd <PROJECT_ROOT>/apps/nursery_admin_mobile
flutter pub get
flutter doctor
```

### 9.2 Connect a device

```bash
flutter devices
```

Then:

```bash
# Android emulator (default API URL already uses 10.0.2.2)
flutter run

# Or explicitly:
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

**Physical phone** (same Wi‑Fi as your Mac):

```bash
# find Mac LAN IP
ipconfig getifaddr en0

# API must listen on all interfaces
cd <PROJECT_ROOT>/apps/nursery-api
php artisan serve --host=0.0.0.0 --port=8000

# Admin Mobile
cd <PROJECT_ROOT>/apps/nursery_admin_mobile
flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000/api/v1
```

Replace `192.168.x.x` with your real LAN IP.

### 9.3 Login

Use **staff** accounts only (`admin@nursery.test`, etc.). Customer-only accounts are rejected after `/auth/me`.

Camera permission is required for SKU / barcode scan screens.

### 9.4 Tests

```bash
cd <PROJECT_ROOT>/apps/nursery_admin_mobile
flutter test
flutter analyze lib test
```

More detail: `apps/nursery_admin_mobile/README.md`, `docs/PHASE_19_ADMIN_MOBILE_RELEASE.md`, `docs/PHASE_19_FINAL_REPORT.md`.

---

## 10. Exact paths — where to modify code

### 10.1 API (backend) — edit here

**Root package:** `apps/nursery-api`

| What you want to change | Exact path |
|-------------------------|------------|
| Feature modules (Auth, Cart, Order, Plant, …) | `apps/nursery-api/app/Modules/` |
| Shared API helpers / middleware / envelope | `apps/nursery-api/app/Shared/` |
| Route registration | `apps/nursery-api/routes/api.php` + each module’s `Routes/` |
| DB migrations | `apps/nursery-api/database/migrations/` |
| Seeders | `apps/nursery-api/database/seeders/` |
| Config (CORS, mail, payments, …) | `apps/nursery-api/config/` + `.env` |
| Payments / SMS / push integrations | `apps/nursery-api/app/Integrations/` |
| Eloquent models (shared) | `apps/nursery-api/app/Models/` |

Module folders currently include:

```text
Admin, Auth, Campaign, Cart, Catalog, Customer, Delivery,
Inventory, Notification, Order, Payment, Plant, Promotion,
Report, Review, Supplier, Support, System, Wishlist
```

Example — change login API:

```text
apps/nursery-api/app/Modules/Auth/
```

### 10.2 Website (customer) — edit here

**Root package:** `apps/nursery-web`

| What you want to change | Exact path |
|-------------------------|------------|
| Pages / routes (App Router) | `apps/nursery-web/src/app/` |
| Reusable UI components | `apps/nursery-web/src/components/` |
| API client / helpers | `apps/nursery-web/src/lib/` |
| Client state (Zustand stores) | `apps/nursery-web/src/store/` |
| Public assets (images, icons) | `apps/nursery-web/public/` |
| Env / API base URL | `apps/nursery-web/.env.local` |
| Dependencies / scripts | `apps/nursery-web/package.json` |

Useful page folders:

```text
apps/nursery-web/src/app/login/
apps/nursery-web/src/app/shop/
apps/nursery-web/src/app/product/[slug]/
apps/nursery-web/src/app/cart/
apps/nursery-web/src/app/checkout/
apps/nursery-web/src/app/wishlist/
apps/nursery-web/src/app/account/
apps/nursery-web/src/app/category/[slug]/
apps/nursery-web/src/app/campaigns/
apps/nursery-web/src/app/search/
```

### 10.3 Customer Android app — edit here

**Root package:** `apps/nursery_app`

| What you want to change | Exact path |
|-------------------------|------------|
| All Dart UI / logic | `apps/nursery_app/lib/` |
| Screens | `apps/nursery_app/lib/screens/` |
| API client | `apps/nursery_app/lib/core/api_client.dart` |
| API base URL / store name | `apps/nursery_app/lib/core/config.dart` |
| Providers / state | `apps/nursery_app/lib/providers/` |
| Models | `apps/nursery_app/lib/models/` |
| Theme | `apps/nursery_app/lib/theme/` |
| Widgets | `apps/nursery_app/lib/widgets/` |
| Android native (Gradle, manifest) | `apps/nursery_app/android/` |
| Dependencies | `apps/nursery_app/pubspec.yaml` |
| App entrypoint | `apps/nursery_app/lib/main.dart` |

Screens today:

```text
home_screen.dart, catalog_screen.dart, product_detail_screen.dart,
cart_screen.dart, wishlist_screen.dart, orders_screen.dart,
account_screen.dart, login_screen.dart, shell_screen.dart
```

### 10.4 Admin Web Portal — edit here

**Root package:** `apps/nursery-admin`

| What you want to change | Exact path |
|-------------------------|------------|
| Pages / routes (App Router) | `apps/nursery-admin/src/app/` |
| Admin shell / layout | `apps/nursery-admin/src/app/(admin)/` |
| Login | `apps/nursery-admin/src/app/login/` |
| API clients | `apps/nursery-admin/src/lib/api/` |
| Auth / session helpers | `apps/nursery-admin/src/lib/` |
| UI components | `apps/nursery-admin/src/components/` |
| Env / API base URL | `apps/nursery-admin/.env.local` |
| Dependencies / scripts | `apps/nursery-admin/package.json` |

Useful areas:

```text
apps/nursery-admin/src/app/(admin)/dashboard/
apps/nursery-admin/src/app/(admin)/orders/
apps/nursery-admin/src/app/(admin)/products/
apps/nursery-admin/src/app/(admin)/inventory/
apps/nursery-admin/src/app/(admin)/purchase-orders/
apps/nursery-admin/src/app/(admin)/suppliers/
apps/nursery-admin/src/app/(admin)/warehouses/
apps/nursery-admin/src/app/(admin)/marketing/
apps/nursery-admin/src/app/(admin)/analytics/
```

### 10.5 Admin Mobile (GreenLeaf Ops) — edit here

**Root package:** `apps/nursery_admin_mobile`

| What you want to change | Exact path |
|-------------------------|------------|
| All Dart UI / logic | `apps/nursery_admin_mobile/lib/` |
| Feature screens | `apps/nursery_admin_mobile/lib/features/` |
| API client | `apps/nursery_admin_mobile/lib/core/api_client.dart` |
| API base URL | `apps/nursery_admin_mobile/lib/core/config.dart` |
| Secure session | `apps/nursery_admin_mobile/lib/core/session_storage.dart` |
| RBAC helpers | `apps/nursery_admin_mobile/lib/core/permissions.dart` |
| Providers | `apps/nursery_admin_mobile/lib/providers/` |
| Models | `apps/nursery_admin_mobile/lib/models/` |
| Theme | `apps/nursery_admin_mobile/lib/theme/` |
| Shared widgets | `apps/nursery_admin_mobile/lib/shared/` |
| Android / iOS native | `apps/nursery_admin_mobile/android/`, `ios/` |
| Dependencies | `apps/nursery_admin_mobile/pubspec.yaml` |
| App entrypoint | `apps/nursery_admin_mobile/lib/main.dart` |

Feature folders:

```text
auth/, dashboard/, orders/, inventory/, purchasing/, more/, shell/
```

### 10.6 Sample SQL / docs (not app runtime code)

| Item | Path |
|------|------|
| Sample data | `database/nursery_sample_data.sql` |
| Reset helper | `database/nursery_sample_data_reset.sql` |
| Login cheat sheet | `docs/SAMPLE_LOGIN_CREDENTIALS.md` |
| API contracts | `docs/PROJECT_DEVELOPMENT_GUIDE.md` |
| DB design | `docs/DATABASE_DESIGN.md` |
| Build/ship playbook | `docs/IMPLEMENTATION_PLAYBOOK.md` |
| Admin Web (Phase 1+) | `docs/PHASE_1_ADMIN_PORTAL.md` |
| Admin Mobile (Phase 19) | `docs/PHASE_19_FINAL_REPORT.md` |

---

## 11. Daily developer cheat sheet

Open several terminals as needed:

**Terminal A — MySQL** (if not already running via XAMPP GUI)

**Terminal B — API**

```bash
cd <PROJECT_ROOT>/apps/nursery-api
php artisan serve --host=0.0.0.0 --port=8000
```

**Terminal C — Customer website**

```bash
cd <PROJECT_ROOT>/apps/nursery-web
npm run dev
```

**Terminal D — Admin Web Portal**

```bash
cd <PROJECT_ROOT>/apps/nursery-admin
npm run dev
# → http://127.0.0.1:3001
```

**Terminal E — Customer Android**

```bash
cd <PROJECT_ROOT>/apps/nursery_app
flutter devices
flutter run --dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1
```

**Terminal F — Admin Mobile (GreenLeaf Ops)**

```bash
cd <PROJECT_ROOT>/apps/nursery_admin_mobile
flutter devices
# emulator:
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
# physical phone:
flutter run --dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1
```

---

## 12. Customize safely (recommended workflow)

1. Change **API** first if you need new fields/endpoints  
2. Update Postman / verify with `curl`  
3. Update the right client(s):
   - Customer web: `apps/nursery-web`
   - Customer Android: `apps/nursery_app`
   - Admin Web: `apps/nursery-admin`
   - Admin Mobile: `apps/nursery_admin_mobile`
4. Keep env URLs in sync:
   - API: `.env` → `APP_URL`, `CORS_ALLOWED_ORIGINS` (include `:3000` and `:3001`)
   - Customer web: `.env.local` → `NEXT_PUBLIC_API_BASE_URL`
   - Admin web: `.env.local` → `NEXT_PUBLIC_API_BASE_URL`
   - Flutter apps: `--dart-define=API_BASE_URL=...` or edit each app’s `lib/core/config.dart`

Never commit real secrets (`.env`, payment keys, JWT secrets). Only commit `.env.example`.

---

## 13. Deploy overview

### 13.1 API (e.g. Hostinger / VPS)

1. Upload or git-clone `apps/nursery-api` to the server  
2. Point the domain document root to `public/`  
3. Create production MySQL DB  
4. Copy `.env.example` → `.env`, set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://admin.yourdomain.com
```

5. On server:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate          # once
php artisan jwt:secret            # once
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Cron (Laravel scheduler):

```cron
* * * * * cd /path/to/nursery-api && php artisan schedule:run >> /dev/null 2>&1
```

7. **Do not** load `nursery_sample_data.sql` on a real production shop (fake users).

More detail: `docs/IMPLEMENTATION_PLAYBOOK.md` §7.

### 13.2 Customer website

```bash
cd apps/nursery-web
# set production API URL
# NEXT_PUBLIC_API_BASE_URL=https://api.yourdomain.com/api/v1
npm ci
npm run build
npm start
# or deploy to Vercel / Node host with the same env var
```

### 13.3 Admin Web Portal

```bash
cd apps/nursery-admin
# NEXT_PUBLIC_API_BASE_URL=https://api.yourdomain.com/api/v1
# NEXT_PUBLIC_SITE_ENV=production
npm ci
npm run build
npm start
# typically hosted on admin.yourdomain.com (port 3001 locally)
```

Do not expose the Admin portal without HTTPS and staff-only access controls at the edge (VPN / IP allowlist / auth) where your security policy requires it.

### 13.4 Customer Android app

```bash
cd apps/nursery_app
flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.yourdomain.com/api/v1
# APK: build/app/outputs/flutter-apk/app-release.apk
```

For Play Store use an app bundle:

```bash
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://api.yourdomain.com/api/v1
```

### 13.5 Admin Mobile (GreenLeaf Ops)

```bash
cd apps/nursery_admin_mobile
flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.yourdomain.com/api/v1
# or
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://api.yourdomain.com/api/v1
```

Release builds **refuse** localhost / cleartext API URLs (see `lib/core/config.dart`). Always pass a production HTTPS `API_BASE_URL`. Do not commit signing keys.

More detail: `docs/PHASE_19_ADMIN_MOBILE_RELEASE.md`.

---

## 14. Troubleshooting

| Problem | Fix |
|---------|-----|
| `No supported devices connected` (Flutter) | Start emulator or plug in phone. Confirm target with `flutter devices`. |
| Phone not in `flutter devices` | Unlock phone, accept USB debugging, `adb kill-server && adb start-server`, try another cable. |
| `Unknown run configuration type FlutterRunConfigurationType` | Install Flutter + Dart plugins in Android Studio and restart. Or use CLI `flutter run`. |
| Customer web cannot call API (CORS) | Add `http://localhost:3000` (and `127.0.0.1`) to API `CORS_ALLOWED_ORIGINS`, restart API. |
| Admin Web CORS / blocked login | Add `http://127.0.0.1:3001` and `http://localhost:3001` to `CORS_ALLOWED_ORIGINS`, restart API. |
| Admin Web login says not staff / forbidden | Use `admin@nursery.test` (or other staff), not customer accounts. |
| Admin Mobile rejects login after auth | Same — staff accounts only; customer roles are blocked in-app. |
| App on phone cannot reach API | Use LAN IP + `php artisan serve --host=0.0.0.0`. Emulator uses `10.0.2.2`. |
| First `flutter run` stuck on Gradle | Wait several minutes; first Android build is slow. |
| Empty catalog / login fails | Run migrations + import `database/nursery_sample_data.sql`. |
| Port 3000 vs 3001 confusion | Customer site = **3000** (`nursery-web`); Admin portal = **3001** (`nursery-admin`). |
| Wrong Flutter project edited | Customer = `nursery_app`; Admin Ops = `nursery_admin_mobile`. |
| Xcode / CocoaPods doctor errors | Needed only if you build iOS targets (Admin Mobile has `ios/`). |

---

## 15. Quick path reference card

| Goal | Path / command |
|------|----------------|
| Run API | `cd apps/nursery-api && php artisan serve` |
| Run customer website | `cd apps/nursery-web && npm run dev` → `:3000` |
| Run Admin Web | `cd apps/nursery-admin && npm run dev` → `:3001` |
| Run customer Android | `cd apps/nursery_app && flutter run` |
| Run Admin Mobile | `cd apps/nursery_admin_mobile && flutter run` |
| Edit API features | `apps/nursery-api/app/Modules/` |
| Edit customer website | `apps/nursery-web/src/app/` |
| Edit Admin Web pages | `apps/nursery-admin/src/app/` |
| Edit customer Android | `apps/nursery_app/lib/` |
| Edit Admin Mobile | `apps/nursery_admin_mobile/lib/` |
| Edit Admin Web API URL | `apps/nursery-admin/.env.local` |
| Edit customer web API URL | `apps/nursery-web/.env.local` |
| Edit Flutter API URL | `--dart-define=API_BASE_URL=...` or each `lib/core/config.dart` |
| Sample SQL | `database/nursery_sample_data.sql` |
| Sample logins | `docs/SAMPLE_LOGIN_CREDENTIALS.md` |

---

**Related docs**

1. `SAMPLE_LOGIN_CREDENTIALS.md` — test accounts  
2. `PROJECT_DEVELOPMENT_GUIDE.md` — API contracts  
3. `DATABASE_DESIGN.md` — tables & columns  
4. `IMPLEMENTATION_PLAYBOOK.md` — build & Hostinger checklist  
5. `PHASE_1_ADMIN_PORTAL.md` — Admin Web portal  
6. `PHASE_19_FINAL_REPORT.md` — Admin Mobile (GreenLeaf Ops)  
7. `PRODUCTION_RUNBOOK.md` — production deploy notes  
