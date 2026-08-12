# GreenLeaf Nursery Platform

Plant Nursery e-commerce platform: **Laravel API** + **Next.js website** + **Flutter Android app**.

## Folder layout

```text
Nursery_Platform/
├── README.md
├── docs/
│   ├── RUN_DEPLOY_AND_CUSTOMIZE.md   ← start here to run everything
│   ├── PROJECT_DEVELOPMENT_GUIDE.md
│   ├── DATABASE_DESIGN.md
│   ├── IMPLEMENTATION_PLAYBOOK.md
│   └── SAMPLE_LOGIN_CREDENTIALS.md
├── database/
│   ├── nursery_sample_data.sql
│   └── nursery_sample_data_reset.sql
└── apps/
    ├── nursery-api/          ← Laravel API (`/api/v1`)
    ├── nursery-web/          ← Next.js customer website
    ├── nursery-admin/        ← Next.js Admin Web Portal
    └── nursery_app/          ← Flutter Android app
```

## Exact packages to run / edit

| App | Run from | Main code to modify |
|-----|----------|---------------------|
| **API** | `apps/nursery-api` | `apps/nursery-api/app/Modules/` |
| **Website** | `apps/nursery-web` | `apps/nursery-web/src/app/` |
| **Admin** | `apps/nursery-admin` | `apps/nursery-admin/src/` |
| **Android** | `apps/nursery_app` | `apps/nursery_app/lib/` |

## Quick start (local)

**Order:** MySQL → API → Website → Android.

### 1) Database
- Start MySQL (e.g. XAMPP)
- Create DB: `nursery_local`
- Configure `apps/nursery-api/.env`, then migrate + optional sample SQL (details in the run guide)

### 2) API
```bash
cd apps/nursery-api
composer install
php artisan serve
```
API: `http://127.0.0.1:8000/api/v1`

### 3) Website
```bash
cd apps/nursery-web
cp .env.example .env.local
npm install
npm run dev
```
Site: `http://localhost:3000`

### 4) Admin portal
```bash
cd apps/nursery-admin
cp .env.example .env.local
npm install
npm run dev
```
Admin: `http://127.0.0.1:3001` (staff accounts only — see sample credentials)

### 5) Android app
```bash
cd apps/nursery_app
flutter pub get
flutter devices
flutter run
```

### Logins (after sample SQL)
See `docs/SAMPLE_LOGIN_CREDENTIALS.md`  
Password for all sample users: `Secret@123`

## Full guide (run, deploy, customize)

**Read this:** [`docs/RUN_DEPLOY_AND_CUSTOMIZE.md`](docs/RUN_DEPLOY_AND_CUSTOMIZE.md)

## Other docs
1. `docs/IMPLEMENTATION_PLAYBOOK.md` — build & Hostinger checklist  
2. `docs/PROJECT_DEVELOPMENT_GUIDE.md` — API contracts  
3. `docs/DATABASE_DESIGN.md` — tables/columns  
