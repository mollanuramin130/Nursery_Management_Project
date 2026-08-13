# PROJECT_AUDIT.md — GreenLeaf Nursery Platform

**Date:** 2026-08-11  
**Role:** Lead software architect — Phase 0 discovery  
**Constraint:** Read-only. No code was modified, deleted, renamed, refactored, or generated as production implementation during this audit.

---

## 1. Executive Summary

GreenLeaf Nursery is already a **working multi-client e-commerce platform** built around:

| Layer | Path | Role |
|-------|------|------|
| PHP REST API | `apps/nursery-api` | Laravel modular monolith — `/api/v1` |
| Customer Website | `apps/nursery-web` | Next.js 16 App Router storefront |
| Customer Android | `apps/nursery_app` | Flutter (Provider + go_router) |
| Database | MySQL via Laravel migrations + `database/*.sql` seeds | Single shared DB |
| Admin | **API only** under `/api/v1/admin/*` | **No admin web portal app in repo** |

Customer journeys through **Phases B–H** are substantially implemented on **one shared API**:

Auth → Catalog → Wishlist → Cart/Coupon → Checkout/Payment → Orders/Tracking/Cancel/Reorder → Offers/Campaigns/Find Your Plant.

**Phase I** concluded Android is **NOT READY FOR RELEASE** (signing, production HTTPS, store listing, device/payment E2E)—not because core shop features are missing.

### What this means for planning

- **Do not rebuild** working customer Website/Android/API commerce flows.
- **Largest product gap:** dedicated **Admin Web Portal** (UI) consuming existing Admin APIs.
- **Largest platform gap:** **iOS** app (docs aspirational; Flutter project is Android-oriented with no `ios/` tree).
- **Largest production gaps:** payment/refund ops hardening, Play release config, privacy/legal surfaces, support tickets UI.

### PHASE 0 STATUS

**READY FOR PHASE 1**

(Phase 1 should mean: *planned implementation work that follows Requirement → DB → API → clients*, starting from the roadmap in §18—not a rebuild.)

---

## 2. Current Architecture

```
CUSTOMER WEBSITE (Next.js)  ─┐
                             ├──►  Laravel PHP REST API (/api/v1)  ──►  MySQL
CUSTOMER ANDROID (Flutter)  ─┘              │
                                            │
ADMIN (API only today)  ────────────────────┘
   (Admin Web Portal + Admin Mobile Ops app)
```

**Principles already followed (largely):**

- One database  
- One business logic in PHP services  
- One JSON envelope  
- Website + Android as presentation clients  
- No direct DB access from clients  

**Not yet present:**

- Separate Admin SPA/portal package  
- iOS customer app  
- Full support-ticket client flows  

---

## 3. Directory Structure

```
Nursery_Platform/
├── README.md
├── apps/
│   ├── nursery-api/          # Laravel API (modules, migrations, smoke scripts)
│   ├── nursery-web/          # Next.js customer storefront
│   └── nursery_app/          # Flutter Android customer app
├── database/                 # Sample data + phase verification SQL notes
└── docs/                     # Guides + PHASE_B…PHASE_I documentation
```

### API modules (`apps/nursery-api/app/Modules/`)

| Active modules (with routes) | Placeholder (`.gitkeep` only) |
|------------------------------|-------------------------------|
| System, Auth, Catalog, Campaign, Cart, Wishlist, Customer, Delivery, Order, Payment, Promotion, Review, Notification, Inventory, Admin | Plant, Report, Supplier, Support |

Plant UX is implemented inside **Catalog**. Supplier/report logic exists under **Admin**, not empty shells.

---

## 4. Backend Analysis

| Aspect | Finding |
|--------|---------|
| Framework | Laravel (modular monolith) |
| Routing | `routes/api.php` → `prefix('v1')` → each module `Routes/api.php` |
| Pattern | Controller → Service → Model → `ApiResponse` |
| Shared | `app/Shared/` (ApiResponse, middleware, exceptions) |
| Integrations | `app/Integrations/Payment/` (Razorpay, COD, gateway manager) |
| Auth | JWT (`tymon/jwt-auth`) + hashed refresh tokens |
| RBAC | roles / permissions / `permission:{slug}` middleware; `super_admin` bypass |
| Inventory | Reserve / commit / release used by checkout & cancel |
| Logging | Request IDs; API request body logging configurable |

**What works and should remain:** modular services (`CheckoutService`, `CartService`, `PaymentService`, `CampaignService`, `PlantFinderService`, Admin controllers).

**What needs improvement (not rewrite):** inactive-user middleware consistency on admin/notification routes; refund gateway integration; promotions table unused at runtime; empty module shells cleaned up or implemented.

---

## 5. API Analysis

### Envelope (standard)

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "errors": null,
  "meta": { "request_id": "...", "timestamp": "...", "pagination": {} }
}
```

### Major endpoint groups

| Domain | Examples |
|--------|----------|
| System | `GET /`, `GET /app/config` |
| Auth | register, login, refresh, logout, me, forgot/reset |
| Catalog | home, categories, products, plants, care, search, recommendations |
| Finder | `GET /plant-finder/options`, `POST /plant-finder/match` |
| Campaign | banners, offers, campaigns, featured, campaign products |
| Cart | cart CRUD, coupon, move-to-wishlist (`optional.jwt`) |
| Wishlist | list/add/remove/move-to-cart |
| Customer | profile, addresses |
| Order | preview, place, list, detail, tracking, cancel, reorder, returns |
| Payment | initiate, verify, status, retry-payment, webhooks |
| Promotion | public coupons |
| Review | list/create |
| Notification | list, devices |
| Admin | dashboard, products, categories, orders, refunds, campaigns, banners, coupons, users, inventory, suppliers, POs, reports, settings, audit |

### Auth / authorization

| Middleware | Use |
|------------|-----|
| `auth:api` | JWT required |
| `active.user` | Customer must be `status=active` (most customer routes) |
| `optional.jwt` | Cart/shipping guest+user |
| `permission:*` | Admin RBAC |

---

## 6. Database Analysis

Schema is mature for nursery commerce:

| Domain | Representative tables |
|--------|------------------------|
| Identity | `users`, `roles`, `permissions`, `refresh_tokens`, `addresses`, `customer_profiles`, `audit_logs` |
| Catalog | `products`, `categories`, `brands`, `tags`, `plant_profiles`, variants, images, relations |
| Merchandising | `campaigns`, `campaign_products`, `banners`, `coupons`, `promotions` (+ pivot), `coupon_redemptions` |
| Cart | `carts`, `cart_items`, `wishlists` |
| Inventory | warehouses, inventory items, movements, suppliers, purchase orders |
| Orders | `orders`, `order_items`, `order_status_histories`, shipments, returns |
| Payments | `payments`, `refunds` |
| Engagement | reviews, notifications, support_* tables |

**Relationships:** generally normalized with FKs; order lines snapshot prices/names; addresses snapshotted on order.

**No new core tables required** for Admin UI Phase if Admin APIs already cover CRUD—**explicitly: Admin portal UI requires no DB change for MVP**, only a client.

**Design notes / debt:**

- `promotions` seeded but not applied at runtime  
- Support tables without Support module routes  
- Refund status string mismatch risk (`captured` vs `success`) documented in prior audits  

---

## 7. Website Analysis

| Item | Detail |
|------|--------|
| Stack | Next.js 16, React 19, Tailwind 4, Zustand, axios |
| State | `auth`, `cart`, `wishlist`, `ui`, `toast` stores |
| Tokens | `localStorage` (weaker than Flutter secure storage) |
| Default API | `http://127.0.0.1:8000/api/v1` |
| Key routes | `/`, `/shop`, `/product/[slug]`, `/cart`, `/checkout`, `/offers`, `/campaigns/[slug]`, `/find-your-plant`, `/account/*` |

**Works (preserve):** catalog/search/filters, PDP, wishlist, cart/coupon, checkout+Razorpay/COD, orders cancel/reorder, offers/campaigns/finder, auth/addresses.

**Improve later:** consolidate ad-hoc `apiGet` vs `services.ts`; reduce localStorage token risk; remove/gate login demo banner for production builds; legal/privacy pages.

---

## 8. Mobile Analysis

| Item | Detail |
|------|--------|
| Stack | Flutter, Provider, go_router, Dio, secure storage, Razorpay SDK |
| Default API | `http://10.0.2.2:8000/api/v1` (+ release assert against cleartext/local) |
| Shell | Home, Categories, Cart, Orders, Account |
| Extra | Catalog, Search, Product, Wishlist, Checkout, Offers, Campaign, Find Your Plant |

**Works (preserve):** feature parity with Website through Phase H; secure tokens; release guards from Phase I.

**Gaps:** no iOS target tree; Play signing/keystore missing; branded icon weak; device E2E/payment on release **not verified**; thin automated tests; some account placeholders (notifications/legal).

**Phase I recommendation (existing doc):** **NOT READY FOR RELEASE**.

---

## 9. Admin Analysis

| Layer | Status |
|-------|--------|
| Admin REST API | **Implemented** (dashboard, products, categories, orders/status, refunds, campaigns, banners, coupons, users, inventory, suppliers, POs, reports, settings, audit, review moderation) |
| Admin Web Portal | **Implemented** (`apps/nursery-admin`) |
| Admin Mobile | **Implemented** (`apps/nursery_admin_mobile` — GreenLeaf Ops; Phase 19) |
| Staff login | Sample admins in `docs/SAMPLE_LOGIN_CREDENTIALS.md` via same `/auth/login` + permissions |

**Conclusion:** Backend is ahead of UI. Building Admin Portal is primarily a **frontend + UX** phase against existing APIs, with selective API gaps filled only when UI proves need.

---

## 10. Existing Features

| Feature | API | Website | Android | Notes |
|---------|-----|---------|---------|-------|
| Register/Login/Refresh/Logout | ✓ | ✓ | ✓ | JWT |
| Profile / Addresses | ✓ | ✓ | ✓ | |
| Home / Categories / Search / Filters | ✓ | ✓ | ✓ | |
| Product detail / care / reviews | ✓ | ✓ | ✓ | |
| Wishlist | ✓ | ✓ | ✓ | |
| Cart / Coupon / Free delivery | ✓ | ✓ | ✓ | Server totals |
| Checkout / COD / Razorpay | ✓ | ✓ | ✓ | Stub gated |
| Orders / Tracking / Cancel / Reorder | ✓ | ✓ | ✓ | |
| Offers / Campaigns | ✓ | ✓ | ✓ | |
| Find Your Plant | ✓ | ✓ | ✓ | Deterministic scoring |
| Banners deep links | ✓ | Partial/wired | ✓ | |
| Admin CRUD APIs | ✓ | — | — | No portal UI |
| Notifications API | ✓ | Weak/none | Placeholder | |
| Returns API | ✓ | — | — | Limited/no client UX |

---

## 11. Missing Features (classified)

### CRITICAL

| Gap | Why |
|-----|-----|
| Admin Web Portal UI | Ops cannot manage production catalog/orders without raw API/tools |
| Production payment configuration & verified E2E | Real money path must be proven before go-live |
| Android Play release blockers (keystore, prod HTTPS, listing/privacy) | Cannot publish |

### HIGH

| Gap | Why |
|-----|-----|
| iOS customer app | Stated platform goal; currently Android-only |
| Real gateway refunds (not stub `processed`) | Finance integrity |
| Support ticket flows (tables exist, module empty) | Customer service |
| Inactive-user enforcement on admin/notification routes | Security consistency |
| Privacy/Terms/Data Safety surfaces | Store + trust |

### MEDIUM

| Gap | Why |
|-----|-----|
| Wire `promotions` flash engine or remove from ops mental model | Dead schema |
| Returns UX on Website/Android | API partially exists |
| Push notifications end-to-end | API/devices exist; clients incomplete |
| Admin roles management UI beyond assigning slugs | Usability |
| Website token storage hardening | XSS risk |
| Campaign `rules_json` auto-merchandising | Manual pivots only |

### LOW

| Gap | Why |
|-----|-----|
| Empty module shell cleanup | Clarity |
| Route naming alignment (`/shop` vs `/catalog`) | Cosmetic DX |
| Broader Flutter integration tests | Quality |
| google_fonts offline packaging | Perf polish |
| Admin mobile app | Explicitly optional later |

---

## 12. Technical Debt

1. Dual client call styles (thin services vs direct API calls)  
2. Placeholder modules vs logic in Catalog/Admin  
3. Unused `promotions` runtime  
4. Sample credentials documented and used in smoke scripts (OK for local; dangerous if deployed)  
5. Login page demo credential banner (web)  
6. Refund lookup status inconsistency risk  
7. API request body logging default on in example env  
8. Flutter account stub toasts for legal/notifications  
9. No build flavors (dev/staging/prod) for mobile  
10. Phase docs numerous—need a single living architecture index (this file)

---

## 13. Security Issues

| Issue | Severity | Notes |
|-------|----------|-------|
| Admin routes omit `active.user` | HIGH | Inactive JWT may still call admin |
| Unsigned webhooks allowed in non-prod | HIGH if mis-env | Must never happen in production |
| Payment `local_stub` | MEDIUM | Blocked in Flutter release; web still allows when API returns stub—server must refuse in prod |
| Web tokens in localStorage | MEDIUM | Prefer httpOnly cookies or tighter XSS posture long-term |
| Demo password documentation | LOW–MEDIUM | Keep out of production DB |
| Admin refunds marked processed without gateway | HIGH (ops) | Financial false confidence |
| Possible password double-hash on admin user create | MEDIUM | Verify `User` cast vs `Hash::make` |
| CORS / origins | Process | Ensure production allowlist |

---

## 14. Performance Issues

| Area | Notes |
|------|-------|
| Plant finder | Scores in-memory over active plants—OK for current catalog size; needs pagination/indexing strategy at scale |
| Campaign images | Often large Unsplash URLs—rely on CDN/resize |
| Flutter cold start | Improved (cart/wishlist non-blocking); still awaits auth bootstrap |
| google_fonts | Network fetch on first paint |
| Release AAB size | ~42 MB measured in Phase I—acceptable; monitor plugins |
| N+1 | Services generally use eager loads; keep reviewing Admin list endpoints |

---

## 15. API Inconsistencies

| Topic | Detail |
|-------|--------|
| Tracking | Dedicated `GET /orders/{id}/tracking` exists; clients mainly use detail embed—OK but document preferred contract |
| Order list vs detail shapes | List summaries vs rich detail—intentional |
| Shop vs catalog path | Client routes differ; API unified `/products` |
| Admin without `active.user` vs customer with it | Inconsistency |
| Payment success status naming | `success` vs possible `captured` in refund code paths |
| Public coupons | Ensure service filters `is_public` + date window always |
| Notifications | Auth without `active.user` |

Website and Android largely share the same endpoints and envelope—**no separate Android-only business APIs found**.

---

## 16. Recommended Architecture (target)

Preserve current API-centric design:

```
                    ┌─────────────────────┐
                    │   MySQL (single)    │
                    └──────────▲──────────┘
                               │
                    ┌──────────┴──────────┐
                    │  PHP REST /api/v1   │
                    │  (business logic)   │
                    └──────────▲──────────┘
           ┌───────────────────┼───────────────────┐
           │                   │                   │
   Customer Website     Customer Mobile      Admin Web Portal
   (Next.js)            (Flutter Android     (NEW — Next.js or
                         + future iOS)         similar)
```

**Rules for all future work:**

1. Requirement → business rules → DB (if needed) → API → JSON test → Website → Mobile → Admin → integration → regression  
2. No client-side price/stock/status authority  
3. No Website-only or Android-only business endpoints  
4. Do not rewrite working B–H customer flows without a documented defect  

---

## 17. Dependency Map

```
users ──┬── addresses
        ├── carts ── cart_items ── products
        ├── wishlists ── products
        ├── orders ── order_items / payments / shipments / status_histories
        └── roles/permissions

products ── plant_profiles / categories / tags / images / inventory
campaigns ── campaign_products ── products
coupons ── coupon_redemptions ── orders
banners (deep link → campaign|product|category|offers|finder)
```

**Client dependency:** both customers → same `/api/v1` → shared services.

---

## 18. Phase-by-Phase Roadmap (recommended next)

> Numbering below is **forward planning** after completed lettered phases B–I. It does not discard existing work.

### Phase 1 — Admin Web Portal Foundation (CRITICAL)

- Scaffold `apps/nursery-admin` (or equivalent) against existing Admin APIs  
- Auth (staff login) + permission-aware navigation  
- Dashboard, Orders (view/status), Products (list/edit), Categories  
- **DB:** none required for MVP UI  
- **API:** only gap-fill if portal UX proves missing fields  

### Phase 2 — Admin Commerce Ops Completeness (HIGH)

- Inventory adjust UI, coupons, campaigns/banners, refunds (with honest status)  
- Customers/users management  
- Settings + audit log viewer  
- Harden admin `active.user` + refund gateway  

### Phase 3 — Production Hardening & Go-Live (CRITICAL for launch)

- Production Razorpay keys, webhooks, refuse stubs  
- Android keystore, prod HTTPS dart-define, branded icon, privacy/Data Safety  
- Website production env, remove demo banners  
- Cross-platform regression B–H  
- Monitoring/logging review (disable sensitive body logs in prod)  

### Phase 4 — Customer Platform Expansion (HIGH)

- iOS target from shared Flutter codebase  
- Returns UX, notifications UX  
- Legal pages on Website  

### Phase 5 — Growth / Merchandising Intelligence (MEDIUM)

- Activate or retire `promotions`  
- Campaign `rules_json` automation  
- Finder analytics events  
- Reporting UI polish / exports  

### Phase 6 — Optional Admin Mobile (LOW) → delivered as Phase 19

- `apps/nursery_admin_mobile` (GreenLeaf Ops) consumes Admin APIs; see `docs/PHASE_19_FINAL_REPORT.md`

### Phase 20 — Advanced Delivery + Fulfillment (additive on Phase 7)

- Pick scan verify, driver assign, ETA reschedule, POD meta; Admin Mobile fulfillment hub  
- See `docs/PHASE_20_FINAL_REPORT.md`

### Phase 21 — Enterprise hardening + production readiness

- Webhook payment binding fix, CI, cleartext/headers/CORS hardening  
- See `docs/PHASE_21_FINAL_REPORT.md` and `docs/PRODUCTION_READINESS_CHECKLIST.md`

---

## 19. Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Building Admin by rewriting APIs | Breaks Website/Android | Reuse Admin APIs; additive only |
| Shipping Android with emulator API default | App unusable/insecure | Enforce dart-define + existing release assert |
| Treating stub payments as live | False orders/revenue | Prod env gates already designed—verify ops |
| Stub refunds marked processed | Financial disputes | Real Razorpay refund or pending-only status |
| Scope creep into redesign | Delays launch | Preserve B–H; Phase 1 = Admin UI |
| Catalog growth kills finder | Latency | Index + limit candidates before scoring |
| Dual token storage models | Security inconsistency | Plan web hardening without breaking sessions |

---

## 20. Recommended Next Phase

**Start Phase 1: Admin Web Portal Foundation**

Rationale:

1. Customer Website + Android already cover the industrial shopper journey via one API.  
2. Admin **JSON API already exists**—highest leverage is a portal UI, not another rewrite of catalog/cart/checkout.  
3. Unlocks real operations (orders, catalog, inventory) required for industrial use.  
4. Follows mandatory workflow: requirements/business rules → (no DB for MVP) → API gap-fill only if needed → Admin UI → regression against customer clients.

**Explicitly do next:**

- Write Admin Portal requirements + screen inventory mapped to existing `/admin/*` endpoints  
- Confirm which endpoints need additive fields (document before coding)  
- Scaffold Admin app; implement auth + dashboard + orders + products first  

**Explicitly do not next:**

- Rebuild Website or Android shop  
- Replace Provider/Zustand  
- Invent parallel APIs per client  

---

## Appendix A — Documentation Inventory

| Doc set | Purpose |
|---------|---------|
| `README.md`, `RUN_DEPLOY_AND_CUSTOMIZE.md`, `PROJECT_DEVELOPMENT_GUIDE.md`, `DATABASE_DESIGN.md` | Platform guides |
| `PHASE_B` … `PHASE_H` | Feature audits/API/reports |
| `PHASE_I_*` | Android Play QA / not-ready release |
| `PAYMENT_CONFIGURATION.md`, `SAMPLE_LOGIN_CREDENTIALS.md` | Ops/local |
| `database/phase_*.sql`, `nursery_sample_data*.sql` | Seeds / verification |

---

## Appendix B — Sample credentials (local only)

Documented in `docs/SAMPLE_LOGIN_CREDENTIALS.md` — password `Secret@123` for sample users (e.g. `asha@example.com`, `admin@nursery.test`). **Not for production.**

---

## Appendix C — Preserve vs change

| Preserve | Change only with defect/roadmap |
|----------|-----------------------------------|
| `/api/v1` envelope & module services | Additive Admin portal client |
| Cart/checkout/payment server authority | Real refunds; prod stub refusal verification |
| Order state machine (UPPERCASE) | Admin `active.user` middleware |
| Campaign + plant-finder APIs | Optional promotions engine |
| Flutter Provider + go_router | iOS later; Play signing |
| Next.js Zustand storefront | Token storage hardening |

---

## PHASE 0 STATUS

# READY FOR PHASE 1

**Phase 1 focus:** Admin Web Portal (foundation) on existing Admin APIs, without rebuilding working customer Website/Android commerce.
