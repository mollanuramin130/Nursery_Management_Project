# Nursery Platform — Implementation Playbook

**Purpose:** One efficient file to turn the design docs into a **fully functional** API + Website + Mobile app.  
**Not another design essay** — only setup steps, checklists, commands, and templates.

| Design docs (already done) | This file |
|----------------------------|-----------|
| `PROJECT_DEVELOPMENT_GUIDE.md` | **How to build & ship** |
| `DATABASE_DESIGN.md` | Migrations / DB apply |
| `nursery_sample_data.sql` | Seed after migrate |
| `SAMPLE_LOGIN_CREDENTIALS.md` | Test logins |

---

## 0. Verdict (read once)

| Question | Answer |
|----------|--------|
| Enough design to start? | **Yes** |
| Live shop without code? | **No** |
| Need more design `.md` before coding? | **No** |
| **Next step** | Scaffold `nursery-api` → Phase 0–1 |

**Build order (do not skip):**

```text
1) nursery-api (Laravel)
2) .env + migrate + sample SQL
3) Auth + Catalog APIs
4) OpenAPI + Postman
5) Next.js website (against live API)
6) Flutter app (same API)
7) Cart → Checkout → Payments + webhooks
8) Hostinger deploy (SSL + cron)
```

---

## 1. Create `nursery-api` (Phase 0) — NOW

### 1.1 Scaffold

```bash
cd /Users/nuramin/Desktop/Nursery_Platform/apps
composer create-project laravel/laravel nursery-api
cd nursery-api

# useful packages (pick JWT approach from guide)
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

Recommended module layout: copy structure from `PROJECT_DEVELOPMENT_GUIDE.md` §3  
(`app/Modules/Auth`, `Catalog`, `Cart`, … + `app/Shared/Support/ApiResponse.php`).

### 1.2 Phase 0 done when

- [ ] `GET /api/v1/app/config` returns JSON envelope  
- [ ] AUTH-01 … AUTH-07 work  
- [ ] `ApiResponse` + global exception handler match guide §4  
- [ ] Roles/permissions seeded  
- [ ] Persistent login (refresh token) works per guide §5.3  

### 1.3 Phase 1 done when

- [ ] Categories / products / plants / search APIs work  
- [ ] Sample data visible in responses  
- [ ] Admin can create/update a product (basic)  

---

## 2. `.env.example` (copy into `nursery-api/.env.example`)

Create this file in the API repo. Copy to `.env` and fill secrets locally / on Hostinger.

```env
APP_NAME="GreenLeaf Nursery API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Kolkata

LOG_CHANNEL=stack
LOG_LEVEL=debug

# ---- Database switch: local | staging | production via these vars only ----
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nursery_local
DB_USERNAME=root
DB_PASSWORD=

# Hostinger example:
# DB_HOST=localhost
# DB_DATABASE=u123_nursery_prod
# DB_USERNAME=u123_prod
# DB_PASSWORD=********

BROADCAST_CONNECTION=log
CACHE_STORE=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=file

# ---- JWT / Auth (persistent login) ----
JWT_SECRET=
JWT_TTL=60
JWT_REFRESH_TTL=129600
# 129600 minutes = 90 days

# ---- CORS (website origin) ----
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://example.com

# ---- API logging ----
API_REQUEST_LOG_ENABLED=true
API_REQUEST_LOG_SAMPLE_RATE=1.0
API_REQUEST_LOG_MAX_BODY_CHARS=20000
API_OUTBOUND_LOG_ENABLED=true

# ---- Mail ----
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# ---- Payments (fill in checkout phase) ----
PAYMENT_DRIVER=razorpay
RAZORPAY_KEY=
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

# ---- Push (optional early; required for mobile notifications) ----
FCM_SERVER_KEY=
# or Firebase credentials path
FIREBASE_CREDENTIALS=

# ---- App force-update ----
APP_MIN_ANDROID_VERSION=1.0.0
APP_MIN_IOS_VERSION=1.0.0
APP_FORCE_UPDATE=false

# ---- Store ----
STORE_CURRENCY=INR
STORE_SUPPORT_EMAIL=support@example.com
STORE_SUPPORT_PHONE=
```

### Apply DB + sample data

```bash
cd nursery-api
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# create DB nursery_local in MySQL, then:
php artisan migrate
mysql -u root -p nursery_local < ../nursery_sample_data.sql

php artisan storage:link
php artisan serve
# API: http://127.0.0.1:8000/api/v1
```

Test login: see `SAMPLE_LOGIN_CREDENTIALS.md` (`asha@example.com` / `Secret@123`).

---

## 3. OpenAPI + Postman (during API build)

Do this **while** building endpoints — not after everything is finished.

### 3.1 Files to add in `nursery-api`

```text
nursery-api/
├── openapi.yaml                 # source of truth for clients
├── postman/
│   ├── Nursery-API.postman_collection.json
│   └── Nursery-Local.postman_environment.json
└── README.md                    # how to import Postman
```

### 3.2 Efficient workflow

1. Implement endpoint per `PROJECT_DEVELOPMENT_GUIDE.md` §9 (`AUTH-01`, `PROD-02`, …)  
2. Add/update path in `openapi.yaml` same day  
3. Sync Postman folder (or generate from OpenAPI)  
4. Web/mobile teams use Postman examples — no guessing  

### 3.3 Minimal OpenAPI stub (expand per endpoint)

```yaml
openapi: 3.0.3
info:
  title: GreenLeaf Nursery API
  version: 1.0.0
servers:
  - url: http://127.0.0.1:8000/api/v1
    description: Local
  - url: https://api.example.com/api/v1
    description: Production
paths:
  /auth/login:
    post:
      tags: [Auth]
      summary: AUTH-02 Login
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [email, password]
              properties:
                email: { type: string }
                password: { type: string }
      responses:
        '200':
          description: OK
  /products:
    get:
      tags: [Catalog]
      summary: PROD-01 List products
      parameters:
        - in: query
          name: q
          schema: { type: string }
      responses:
        '200':
          description: OK
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
```

### 3.4 Postman environment variables

| Variable | Local example |
|----------|----------------|
| `baseUrl` | `http://127.0.0.1:8000/api/v1` |
| `accessToken` | (set by login test script) |
| `refreshToken` | (set by login test script) |
| `email` | `asha@example.com` |
| `password` | `Secret@123` |

Login test script (Postman Tests tab):

```js
const j = pm.response.json();
if (j.success && j.data) {
  pm.environment.set("accessToken", j.data.access_token);
  pm.environment.set("refreshToken", j.data.refresh_token);
}
```

### 3.5 Done when

- [ ] Every implemented endpoint has OpenAPI + Postman example  
- [ ] Collection grouped: Auth, Catalog, Cart, Orders, Payments, Admin  
- [ ] Web + mobile can call API without reading PHP source  

---

## 4. Next.js website repo (after Auth + Catalog APIs)

### 4.1 Scaffold

```bash
cd /Users/nuramin/Desktop/Nursery_Platform/apps
npx create-next-app@latest nursery-web --typescript --app --eslint --src-dir --import-alias "@/*"
cd nursery-web
npm i axios zustand   # or your preferred client/state
```

### 4.2 Env

```env
# nursery-web/.env.local
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_STORE_NAME=GreenLeaf Nursery
```

### 4.3 Minimum pages (map to APIs)

| Page | API codes |
|------|-----------|
| Home | HOME-01, BAN-01, CAMP-01 |
| Category / Search | CAT-*, PROD-01, SEARCH-01 |
| Product / Plant detail | PROD-02, PLANT-03, REV-01, PROD-04 |
| Cart / Wishlist | CART-*, WISH-* |
| Auth | AUTH-* |
| Checkout / Orders | CHECK-01, ORDER-*, PAY-* (later) |
| Account | CUST-*, ORDER-02 |

### 4.4 Rules

- Call **only** `/api/v1` — never hit MySQL from Next.js  
- Persist session per guide §5.3 (refresh on load)  
- Use product cards from API; don’t hardcode catalog in the frontend  

### 4.5 Done when

- [ ] Home shows seeded banners/products  
- [ ] Login as `asha@example.com` works and survives refresh  
- [ ] Product detail shows plant care block  
- [ ] Cart add/update works  

---

## 5. Flutter app repo (same APIs as web)

### 5.1 Scaffold

```bash
cd /Users/nuramin/Desktop/Nursery_Platform/apps
flutter create nursery_app
cd nursery_app
# add: dio/http, flutter_secure_storage, provider/riverpod, go_router
```

### 5.2 Env / config

```dart
// lib/core/config.dart
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1', // Android emulator → host
  );
}
```

iOS simulator often uses `http://127.0.0.1:8000/api/v1`.  
Physical device: use your machine LAN IP.

### 5.3 Must implement early

- [ ] Secure storage for access + refresh tokens (§5.3 persistent login)  
- [ ] Dio interceptor: on 401 → AUTH-03 → retry once  
- [ ] Headers: `X-Platform`, `X-App-Version`, `Authorization`  
- [ ] Screens: Home, Catalog, Product detail, Cart, Wishlist, Login, Orders  

### 5.4 Done when

- [ ] Kill app → reopen → still logged in  
- [ ] Same products as website  
- [ ] Cart/wishlist match server for logged-in user  

---

## 6. Payment keys + webhooks (checkout phase)

Do **after** ORDER-01 works with `PENDING_PAYMENT`.

### 6.1 Razorpay (example)

1. Create Razorpay account (test mode first)  
2. Put keys in API `.env`:

```env
PAYMENT_DRIVER=razorpay
RAZORPAY_KEY=rzp_test_xxx
RAZORPAY_SECRET=xxx
RAZORPAY_WEBHOOK_SECRET=whsec_xxx
```

3. Implement adapters: `PaymentGatewayInterface` → `RazorpayGateway`  
4. Endpoints: PAY-01 initiate, PAY-02 verify, PAY-03 webhook  
5. Dashboard webhook URL:

```text
https://api.example.com/api/v1/payments/webhooks/razorpay
```

Events: `payment.captured`, `payment.failed` (adjust to provider docs).

### 6.2 Safety checklist

- [ ] Never trust client-only “payment success”  
- [ ] Verify signature on verify + webhook  
- [ ] Idempotent finalize (duplicate webhook OK)  
- [ ] Reserve stock on order; commit/release on pay result  
- [ ] No real keys in git — only `.env` / Hostinger env  

### 6.3 Local webhook testing

```bash
# example: ngrok http 8000
# set Razorpay webhook to https://xxxx.ngrok.io/api/v1/payments/webhooks/razorpay
```

---

## 7. Hostinger deploy (before go-live)

### 7.1 Domains

| URL | Points to |
|-----|-----------|
| `https://api.example.com` | Laravel `public/` |
| `https://example.com` | Next.js (static export or Node host) |
| `https://admin.example.com` | Admin UI (optional) |

### 7.2 API deploy steps

```bash
# on server (document root = nursery-api/public)
git pull
composer install --no-dev --optimize-autoloader
cp .env.example .env   # or edit via hPanel — production values
php artisan key:generate   # once
php artisan migrate --force
# optional first time only:
# mysql -u ... nursery_production < nursery_sample_data.sql   # NEVER on real prod with fake users

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7.3 SSL

- Enable SSL in hPanel for `api` + main domain  
- Force HTTPS  

### 7.4 Cron (required)

```cron
* * * * * cd /home/USER/nursery-api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler should run: queues, campaign activate, payment timeout release, low-stock alerts.

### 7.5 Production `.env` essentials

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
DB_HOST=localhost
DB_DATABASE=...
QUEUE_CONNECTION=database
CACHE_STORE=file
CORS_ALLOWED_ORIGINS=https://example.com
PAYMENT_DRIVER=razorpay
# live keys
```

### 7.6 Go-live done when

- [ ] `https://api.example.com/api/v1/app/config` works on SSL  
- [ ] Website talks to API over HTTPS  
- [ ] Mobile uses production `API_BASE_URL`  
- [ ] Cron running  
- [ ] Payment webhook hits production URL  
- [ ] Backups enabled  

---

## 8. Optional (nice, not blocking start)

| Item | When | Efficient approach |
|------|------|--------------------|
| Figma screens | Parallel with Phase 1 | Only Home, PDP, Cart, Checkout |
| Brand assets | Before marketing launch | Logo, colors as CSS variables |
| CI pipeline | After Phase 0 | GitHub Action: `composer test` + pint |
| Redis | After Hostinger → VPS | Cache + queues |
| Meilisearch | If MySQL search feels weak | Swap behind SEARCH-01 |

---

## 9. Master delivery checklist

### A. Backend (`nursery-api`)

- [ ] Repo created  
- [ ] `.env.example` committed; `.env` not committed  
- [ ] Migrations = `DATABASE_DESIGN.md`  
- [ ] Sample data loaded on local  
- [ ] Phase 0 Auth APIs  
- [ ] Phase 1 Catalog/Plants/Search  
- [ ] Cart / Wishlist / Inventory  
- [ ] Orders + Payments + Webhooks  
- [ ] OpenAPI + Postman updated  
- [ ] Hostinger API live + cron  

### B. Website (`nursery-web`)

- [ ] Repo created  
- [ ] `NEXT_PUBLIC_API_BASE_URL` set  
- [ ] Home / catalog / PDP / auth / cart  
- [ ] Persistent login  
- [ ] Checkout UI wired to PAY-*  
- [ ] Production domain + SSL  

### C. Mobile (`nursery_app`)

- [ ] Repo created  
- [ ] Secure token storage  
- [ ] Silent refresh  
- [ ] Same core screens as web  
- [ ] Push device register (DEV-01)  
- [ ] Store builds (Play Store / App Store) when ready  

### D. Payments & ops

- [ ] Test keys → live keys  
- [ ] Webhook verified  
- [ ] Support email/phone in settings  
- [ ] Admin can process orders  

---

## 10. Suggested folder layout on disk

```text
/Users/nuramin/Desktop/Nursery_Platform/
├── README.md
├── docs/
│   ├── PROJECT_DEVELOPMENT_GUIDE.md
│   ├── DATABASE_DESIGN.md
│   ├── IMPLEMENTATION_PLAYBOOK.md        # THIS FILE
│   └── SAMPLE_LOGIN_CREDENTIALS.md
├── database/
│   ├── nursery_sample_data.sql
│   └── nursery_sample_data_reset.sql
└── apps/
    ├── nursery-api/                      # CREATE NEXT (Laravel)
    ├── nursery-web/                      # CREATE in parallel (Next.js)
    └── nursery_app/                      # CREATE later (Flutter Android)
```

Old reference Java projects stay in `~/Desktop/Code_Clone` — **do not modify them**.

---

## 11. What to do in the next 60 minutes

```bash
# 1) API
cd /Users/nuramin/Desktop/Nursery_Platform/apps
composer create-project laravel/laravel nursery-api
cd nursery-api
# add .env.example from §2 of this file
php artisan serve

# 2) Prove health
curl -s http://127.0.0.1:8000 | head

# 3) Then implement AUTH-02 + HOME-01 + PROD-01 from docs/PROJECT_DEVELOPMENT_GUIDE.md
```

When those three endpoints return the standard JSON envelope, website and mobile work can start in parallel against Postman mocks / local API.

---

**Rule:** If implementation and design disagree, update `PROJECT_DEVELOPMENT_GUIDE.md` / `DATABASE_DESIGN.md` first, then code.  
**Rule:** Use this playbook for *how*; use the development guide for *exact request/response shapes*.
