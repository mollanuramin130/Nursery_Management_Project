# QA-00 — Complete System Forensic Audit

**Date:** 2026-08-12  
**Mode:** AUDIT ONLY — no code, schema, or dependency changes were made.  
**Source of truth:** Repository code + routes + prior phase docs (code wins on conflict).

---

## 1. Executive summary

GreenLeaf is a multi-client industrial nursery commerce stack on a single Laravel `/api/v1` API. Prior phases delivered substantial surface area (catalog through fulfillment, CRM, analytics, admin mobile). **QA-00 finds the customer login/order pain is usually not a wrong JSON field name** — Web/Mobile login contracts match the API — but a combination of **environment/configuration failures**, **opaque 401 messages**, **checkout total fallback bugs**, **payment stub asymmetry**, and **mobile parity gaps** (returns).

| Metric | Value |
|--------|------:|
| Applications analyzed | **6** (API, Customer Web, Customer Mobile, Admin Web, Admin Mobile, MySQL/docs) |
| API route declarations (Modules) | **~276** |
| Approx HTTP endpoint methods | **~259** |
| Migration files | **24** |
| Eloquent model files (Modules) | **~66** |
| Phase final reports reviewed | **16+** |
| CRITICAL bugs registered | **2** |
| HIGH | **6** |
| MEDIUM | **8** |
| LOW | **4** |

**Production readiness (code):** Soft-launch capable **after** clearing config/smoke + payment stub web guard + ops backups.  
**Do not claim zero bugs.**

Companion docs:

- `QA_FEATURE_MATRIX.md`
- `QA_MASTER_BUG_REGISTER.md`
- `QA_REMEDIATION_ROADMAP.md`
- `QA_DATABASE_CHANGE_PROPOSAL.md` (none required)

---

## 2. Architecture map

| Project | Path | Stack | Entry | API base | Auth | State | Build / Test |
|---------|------|-------|-------|----------|------|-------|--------------|
| API | `apps/nursery-api` | Laravel PHP 8.3, MySQL, JWT | `public/index.php` | `/api/v1` | JWT + refresh | Server | `php artisan test` |
| Customer Web | `apps/nursery-web` | Next.js / TS | `src/app` | `NEXT_PUBLIC_API_BASE_URL` | Bearer localStorage | Zustand | `npm run build` / lint |
| Customer Mobile | `apps/nursery_app` | Flutter | `lib/main.dart` | `--dart-define=API_BASE_URL` | Secure storage | Provider | `flutter test` |
| Admin Web | `apps/nursery-admin` | Next.js / TS | `src/app` | same | Bearer localStorage | Zustand | `npm run build` |
| Admin Mobile | `apps/nursery_admin_mobile` | Flutter | `lib/main.dart` | dart-define | Secure storage | Provider | `flutter test` |
| DB | MySQL | migrations in API | — | — | — | — | — |

```
Customer Web · Customer Mobile · Admin Web · Admin Mobile
                    ↓  HTTPS JSON
              Laravel REST /api/v1
                    ↓
           Services / State machines
                    ↓
                   MySQL
```

No frontend may access MySQL directly — **confirmed** (clients are HTTP-only).

---

## 3. API inventory (summary)

Modules with routes: Auth, Catalog, Cart, Order, Payment, Customer, Wishlist, Campaign, Plant, Promotion, Inventory (admin), Admin, Delivery (shipping methods), Notification, Review, Report, System (health), Marketing/CRM (admin), etc.

**Auth:** register, login, refresh, logout, me, forgot/reset password  
**Commerce:** products, categories, search, cart*, wishlist, checkout/preview, orders*, shipping/methods, payments*, addresses  
**Admin:** orders, products, categories, inventory*, warehouses, suppliers, POs, fulfillment*, returns, refunds, customers, marketing, analytics, settings, audit, users…  

\*Full per-endpoint tables live in phase API docs; QA-00 contract audit focused on auth/cart/checkout/payment (highest failure impact).

**Envelope:** Most endpoints use `{ success, message, data, errors, meta }` via `ApiResponse`. Health may be thinner — verify before assuming.

---

## 4. Database inventory

- **24** migrations including Phase 17–21 additive tables/columns  
- Core: users, roles, products, inventory_items, stock_movements, carts, orders, order_items, payments, shipments, shipment_events, returns, refunds, warehouses, suppliers, campaigns, etc.  
- Phase 20: `shipments.assigned_driver_user_id`  
- **No QA-00 mandatory schema change**

**Status casing risk:** Order statuses UPPER_SNAKE; shipment statuses often lowercase.

---

## 5–8. Client audits (condensed)

### Customer Web
- Strong feature surface (account returns, reviews, subs, rewards).  
- Login contract OK.  
- Issues: preview total fallback (QA-CHK-001), local_stub pay (QA-PAY-001), localStorage JWT (QA-SEC-001), no reset-password page.

### Customer Mobile
- Near-parity with web for core commerce.  
- Gaps: **returns**, dedicated **reviews** account page.  
- Physical device requires LAN `API_BASE_URL` (not `10.0.2.2`).

### Admin Web
- Broad ops + marketing + analytics.  
- Users & Roles → coming-soon.  
- Fulfillment desk complete (Phase 7/20).

### Admin Mobile (GreenLeaf Ops)
- Dashboard, orders, inventory/scan, fulfillment, PO receive, suppliers, warehouses.  
- Intentionally thin vs Admin Web (no catalog CRUD/marketing/analytics).

---

## 9. Authentication audit

**Trace (Customer Web):**

UI (`login/page.tsx`) → `useAuthStore.login` → `POST /auth/login` `{ email, password, device.platform:web }` → `AuthController`/`LoginRequest` → `AuthService::login` → JWT pair → store `gl_access_token`/`gl_refresh_token` → set user from `data.user` → `fetchCart` → redirect.

**Same field contract on Customer Mobile.**

**Failure points (evidence-based):**

1. Network / wrong base URL / CORS → axios error (not field mismatch).  
2. Missing seed user / wrong password → 401 `"Unauthenticated"` (AuthService message discarded) — QA-AUTH-001.  
3. Inactive user → same opaque 401.  
4. After login, customers **do not** call `/auth/me` (admins do) — OK for customers; permissions unused on storefront.

---

## 10–13. Cart / Checkout / Payment / Order

| Step | API | Web | Mobile | Issue |
|------|-----|-----|--------|-------|
| Cart CRUD | `/cart*` | Yes + X-Cart-Token | Yes | variant typing |
| Coupon | `/cart/apply-coupon` `{code}` | Yes | Yes | free-delivery base differs |
| Preview | `/checkout/preview` | Yes | Yes | Web soft-fallback |
| Place | `/orders` | Yes | Yes | OK |
| Pay | `/payments/initiate|verify` | Yes | Yes | Web stub guard missing |
| Totals authority | Server | Mostly | Strict preview | Web can show wrong CTA |

No partial-order invent on clients for payment amount — Razorpay amount from server order (good).

---

## 14–16. Inventory / Fulfillment / Delivery

- Reserve@checkout / commit@payment / release patterns exist (prior phases).  
- Fulfillment SM + meta pick/pack + shipments (Phase 7/20).  
- Admin Mobile fulfillment hub present.  
- Gaps: delivery slots, external couriers (documented Phase 20) — **parity/product**, not silent breakage of Internal Delivery.

---

## 17. Security findings

| Sev | Finding |
|-----|---------|
| CRITICAL* | Web `local_stub` acceptance in prod builds (*if keys empty) |
| HIGH | localStorage JWTs |
| HIGH | Opaque login 401 |
| Pass | Order IDOR scoped by user_id; admin permission middleware; webhook provider_order_id binding (Phase 21) |

---

## 18. Performance findings

- Pagination present on large admin lists.  
- No load-test numbers in this audit.  
- Risk: dashboard multi-count queries; large analytics — monitor in prod.

---

## 19. Feature parity

See `QA_FEATURE_MATRIX.md`. Headline gaps: Mobile returns; Admin Users UI; Admin Mobile catalog/marketing.

---

## 20–24. Bug summary

See `QA_MASTER_BUG_REGISTER.md`.

**Critical:** QA-CFG-001, QA-PAY-001  
**High:** QA-AUTH-001/002, QA-CHK-001, QA-CART-001, QA-PAR-001, QA-SEC-001  

---

## 25–26. API / Database gaps

- Reset-password clients missing  
- Free-delivery rule inconsistency  
- variant_id naming  
- Shipment vs order status casing discipline  
- No mandatory new tables for QA-00

---

## 27. Architecture problems

- Dual payment stub policy (Web vs Flutter)  
- Web JWT storage model vs mobile secure storage  
- Phase “COMPLETE” docs vs residual gaps (process risk)

---

## 28. Recommended fix order

See `QA_REMEDIATION_ROADMAP.md` (QA-01 → QA-15).

---

## 29. Regression test requirements

| Bug class | Required test |
|-----------|----------------|
| Login config | Smoke health + login against seeded user |
| Auth messages | Feature test expects distinct 401 body (after fix) |
| Checkout preview | Web must not place order without preview |
| Payment stub | Production web refuses stub |
| Free delivery | Unit/feature cart vs checkout agreement |
| Returns mobile | Widget/E2E after parity build |

---

## 30. Production readiness assessment

| Dimension | Score |
|-----------|-------|
| API completeness | High |
| Customer Web completeness | High with defects |
| Customer Mobile parity | Medium-High |
| Admin Web | High |
| Admin Mobile ops | Medium (scoped) |
| Config/ops readiness | Low until smoke discipline |
| Security posture | Medium-High (Phase 21) |
| **Overall soft-launch** | **CONDITIONAL** — clear QA-01/QA-04 P0 + ops backlog |

**Score (subjective 0–100):** **72** — strong platform foundation; not acceptance-complete.

---

## Discrepancies: docs vs code

| Doc claim | Code reality |
|-----------|--------------|
| Phase X COMPLETE | Residual gaps (returns mobile, users UI, stubs) |
| ADMIN_API_GAPS warehouses missing | Warehouses implemented |
| Login “broken because wrong fields” (rumor) | **Not supported by code** — fields align |

---

## STOP

QA-00 does **not** implement fixes. Proceed to remediation per roadmap only when directed.
