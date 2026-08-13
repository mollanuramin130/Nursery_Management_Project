# QA-36 Feature Parity Matrix

**Date:** 2026-08-13 · **Scope:** TEST / local only · **GREEN:** NO  
**Method:** Source inventory + route/shell audit + prior commerce suites + targeted fixes  
**Device:** `2d3714f` / vivo 1951 (connected; `adb reverse` required)

Statuses: **PASS** · **PARTIAL** · **MISSING** · **BROKEN** · **INTENTIONAL** · **N/A** · **BLOCKED** · **UNVERIFIED**

A feature is **PASS** only when the user can complete it on that client (not merely because an API exists).

---

## 1. Customer Web vs Mobile vs API

| Feature | API | Customer Web | Customer Mobile | Data parity | UI parity | Status | Evidence |
|---------|-----|--------------|-----------------|-------------|-----------|--------|----------|
| Register | PASS | PASS | PASS | same | platform chrome | **PASS** | routes + API |
| Login | PASS | PASS | PASS | same | platform | **PASS** | BFF / Bearer |
| Logout | PASS | PASS | PASS | same | platform | **PASS** | smoke |
| Session refresh | PASS | PASS | PASS | same | N/A | **PASS** | single-flight |
| Forgot password | PASS | PASS | PASS | same | platform | **PASS** | |
| Reset password | PASS | PASS | PASS | same | platform | **PASS** | |
| Profile view/edit | PASS | PASS | PASS | same | platform | **PASS** | |
| Address CRUD | PASS | PASS | PASS | same | platform | **PASS** | Mobile forms full-screen (intentional) |
| Home | PASS | PASS | PASS | same | platform | **PASS** | |
| Categories | PASS | PASS | PASS | same | platform | **PASS** | Web `/category/[slug]` extra |
| Product listing | PASS | PASS | PASS | same | pages vs infinite | **PASS** | `/shop` · `/catalog` |
| Product detail | PASS | PASS | PASS | same | platform | **PASS** | full-screen Mobile (intentional) |
| Search | PASS | PASS | PASS | same | platform | **PASS** | shell after QA-36-007 |
| Filters / sort / pagination | PASS | PASS | PASS | same | UX pattern | **PASS** | |
| Product variants | PASS | PARTIAL | PARTIAL | API has variants | no PDP picker either | **PARTIAL** | both clients |
| Images / stock | PASS | PASS | PASS | same | platform | **PASS** | |
| Offers / seasonal / campaigns | PASS | PASS | PASS | same | platform | **PASS** | |
| Cart add/remove/qty | PASS | PASS | PASS | same | platform | **PASS** | |
| Coupon / free delivery | PASS | PASS | PASS | same | platform | **PASS** | server totals |
| Empty cart | PASS | PASS | PASS | same | platform | **PASS** | |
| Wishlist add/remove/view | PASS | PASS | PASS | same | platform | **PASS** | flicker **FIXED** QA-36-008 |
| Checkout preview / address / shipping | PASS | PASS | PASS | same | platform | **PASS** | server authority |
| COD | PASS | PASS | PASS | same | platform | **PASS** | |
| Razorpay Checkout | PASS | PASS | PASS | same | platform | **PASS** | TEST keys |
| Dynamic QR | PASS | PASS | MISSING | API | Web mode UI | **INTENTIONAL** | QA-28; settle **UNVERIFIED** |
| UPI Intent | PASS | PASS | PASS | same | Mobile primary | **PASS** / settle **BLOCKED** | |
| Payment retry / fail / success | PASS | PASS | PASS | same | labels aligned | **PASS** | QA-35/36 gates |
| Orders list / detail | PASS | PASS | PASS | same | platform | **PASS** | shell on detail after QA-36-007 |
| Timeline | PASS | PASS | PASS | same | marketing titles OK | **PASS** | |
| Cancel / Return / Refund view | PASS | PASS | PASS | same | platform | **PASS** | |
| Reorder | PASS | PASS | PASS | same | platform | **PASS** | |
| Notifications list / unread / mark-read / deeplink | PASS | PASS | PASS | same | path diff | **PASS** | `/returns` remap QA-36-005 |
| Account hub | PASS | PASS | PASS | same | platform | **PASS** | |
| Bottom nav primary tabs | N/A | N/A (top nav) | PASS | N/A | Home/Shop/Cart/Orders/Account | **PASS** | QA-36-007 shell fix |

---

## 2. Screen-by-screen (Customer)

| Screen | Web | Mobile | Bottom nav (Mobile) | Loading/Empty/Error/Success | Notes |
|--------|-----|--------|---------------------|-----------------------------|-------|
| Splash | N/A | soft launch | N/A | N/A | |
| Login / Register / Forgot / Reset | PASS | PASS | **No** intentional | PASS | auth full-screen |
| Home | PASS | PASS | **Yes** | PASS | |
| Shop / Categories | PASS | PASS | **Yes** | PASS | Shop tab → Categories |
| Catalog listing | PASS | PASS | **Yes** after fix | PASS | was outside shell → QA-36-007 |
| Search | PASS | PASS | **Yes** after fix | PASS | |
| Product detail | PASS | PASS | **No** intentional | PASS | sticky commerce bar |
| Wishlist | PASS | PASS | **Yes** after fix | PASS | flicker FIXED |
| Cart | PASS | PASS | **Yes** | PASS | sticky + shell |
| Checkout / Payment | PASS | PASS | **No** intentional | PASS | |
| Order success / detail | PASS | PASS | **Yes** after fix | PASS | nested under `/orders` |
| Orders list | PASS | PASS | **Yes** | PASS | |
| Returns / Refund view | PASS | PASS | **Yes** (returns in account branch) | PASS | |
| Notifications | PASS | PASS | **Yes** after fix | PASS | |
| Account / Profile | PASS | PASS | **Yes** (profile in account branch) | PASS | |
| Addresses list/form | PASS | PASS | **No** intentional (sticky save) | PASS | |
| Logout / session | PASS | PASS | returns to shell | PASS | BFF smoke |

---

## 3. Admin Web vs Mobile vs API

| Feature | API | Admin Web | Admin Mobile | Parity | Status |
|---------|-----|-----------|--------------|--------|--------|
| Dashboard | PASS | PASS | PASS | ops KPIs | **PASS** |
| Orders / transitions | PASS | PASS | PASS | labels aligned | **PASS** |
| Payments (order-scoped) | PASS | PARTIAL | PARTIAL | no dedicated payments console | **PARTIAL** |
| Customers / CRM | PASS | PASS | MISSING | — | **INTENTIONAL** QA-ADM-002 |
| Inventory / movements | PASS | PASS | PASS | complex transfers → Web | **PASS** / PARTIAL transfers |
| Returns workflow UI | PASS | PASS | MISSING | deep-link → orders | **INTENTIONAL** QA-ADM-002 |
| Refunds admin UI | PASS | PASS | MISSING | — | **INTENTIONAL** QA-ADM-002 |
| Promotions / Catalog | PASS | PASS | MISSING | — | **INTENTIONAL** QA-ADM-002 |
| Reports / analytics | PASS | PASS | MISSING | — | **INTENTIONAL** QA-ADM-002 |
| Notifications | PASS | PASS | PASS | returns remap | **PASS** |

---

## 4. Bottom navigation architecture (Customer Mobile)

### Primary destinations (always)

Home · Shop (Categories) · Cart · Orders · Account

### Inside shell (bottom nav shown) — after QA-36-007

`/` · `/categories` · `/catalog` · `/search` · `/offers` · `/find-your-plant` · `/campaigns/:slug` · `/cart` · `/orders` · `/orders/:id` · `/account` · `/account/profile|rewards|subscriptions|preferences|returns|reviews` · `/wishlist` · `/notifications`

### Outside shell (intentional full-screen)

`/login` · `/register` · `/forgot-password` · `/reset-password` · `/product/:slug` · `/checkout` · `/account/addresses*` (sticky save bar)

### Root cause of user report

Browse routes (`/catalog`, `/search`, wishlist, notifications, order detail) were registered as **top-level** `GoRoute`s outside `StatefulShellRoute`, so the shell `NavigationBar` disappeared. Fixed by moving those routes into the correct shell branches — **not** by duplicating nav bars per screen.

---

## 5. Wishlist removal flicker (QA-36-008)

| Step | Before | After |
|------|--------|-------|
| Tap Remove | API then `_reload()` | Optimistic local remove |
| During request | `FutureBuilder` → full skeleton | List stays; row already gone |
| Success | Skeleton → new list | Keep correct list |
| Failure | — | Restore row + error toast |

No `sleep` / artificial delay.

---

## 6. Terminology consistency

| Concept | Customer (Web+Mobile) | Admin |
|---------|----------------------|-------|
| PENDING_PAYMENT | Order placed | Pending payment (**INTENTIONAL**) |
| CONFIRMED | Confirmed / Order confirmed | Confirmed |
| Payment success | Paid | Paid |
| COD payment | Not required (COD) | COD / labels |

---

## 7. Scorecard summary

| Bucket | Items |
|--------|-------|
| Missing (customer) | Dynamic QR UI on Mobile (**INTENTIONAL**); variant PDP picker both clients (**PARTIAL**) |
| Broken | None open after QA-36-007/008 |
| Partial | Variant picker; Admin payments console |
| UI inconsistencies | Platform chrome; return status title-casing cosmetic |
| Navigation | Bottom nav inconsistency **FIXED** |
| Loading/flicker | Wishlist remove **FIXED** |
| Data inconsistencies | None material found (server authoritative) |
| Intentional | QA-ADM-002; payment path Web QR vs Mobile Intent; customer vs admin pending copy |
| Environment | `adb reverse` for device API |
| Provider | UPI-app settle **BLOCKED**; QR settle **UNVERIFIED** |
