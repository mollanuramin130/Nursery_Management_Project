# QA-37 Navigation Matrix — Customer Web ↔ Customer Mobile

**Date:** 2026-08-13 · **GREEN:** NO  
**Policy:** Primary tabs keep shell bottom nav. Auth / PDP / checkout / address forms are full-screen.

---

## Mobile bottom navigation policy

### PRIMARY (bottom nav required)

| Tab | Route | Shell |
|-----|-------|-------|
| Home | `/` | Yes |
| Shop | `/categories` (+ catalog/search/offers…) | Yes |
| Cart | `/cart` | Yes |
| Orders | `/orders` (+ `/orders/:id`) | Yes |
| Account | `/account` (+ wishlist, notifications, subpages) | Yes |

### DETAIL / ACTION (bottom nav intentionally hidden)

| Route | Reason |
|-------|--------|
| `/login` `/register` `/forgot-password` `/reset-password` | Auth focus |
| `/product/:slug` | Sticky commerce bar |
| `/checkout` | Payment focus |
| `/account/addresses*` | Sticky save bar |

### QA-36-007 reconfirmed (QA-37)

Browse/account routes remain **inside** `StatefulShellRoute`. No regression. Tests: `shell_nav_routes_test.dart`.

---

## Mobile route inventory

| Path | Parent / shell | Auth | Bottom nav | Deep link | Back |
|------|----------------|------|------------|-----------|------|
| `/` | Shell Home | No | Yes | Yes | Exit/app |
| `/categories` | Shell Shop | No | Yes | Yes | Tab |
| `/catalog` | Shell Shop | No | Yes | Yes | Tab/back |
| `/search` | Shell Shop | No | Yes | Yes | Tab/back |
| `/offers` `/find-your-plant` `/campaigns/:slug` | Shell Shop | No | Yes | Yes | Tab/back |
| `/cart` | Shell Cart | No | Yes | Yes | Tab |
| `/orders` | Shell Orders | Yes* | Yes | Yes | Tab |
| `/orders/:id` | Nested Orders | Yes* | Yes | Yes | To list |
| `/account` | Shell Account | Soft | Yes | Yes | Tab |
| `/account/profile\|rewards\|subscriptions\|preferences\|returns\|reviews` | Nested Account | Yes* | Yes | Yes | To account |
| `/wishlist` `/notifications` | Shell Account branch | Yes* | Yes | Yes | Tab/back |
| `/product/:slug` | Outside | No | **No** | Yes | Pop |
| `/checkout` | Outside | Yes | **No** | — | Pop |
| `/account/addresses*` | Outside | Yes | **No** | Yes | Pop |
| Auth routes | Outside | No | **No** | Yes | Pop |

\*Signed-in required for data; guests see sign-in empty states where applicable.

---

## Web route inventory (chrome ≠ bottom nav)

| Path | Auth | Nav chrome | Notes |
|------|------|------------|-------|
| `/` `/shop` `/search` `/product/[slug]` | Mixed | Header | |
| `/cart` `/checkout` | Mixed | Header | Checkout focus OK |
| `/wishlist` | Yes | Header | |
| `/account/*` orders/returns/notifications/addresses/profile | Yes | Header + account nav | |
| `/login` `/register` `/forgot-password` `/reset-password` | No | Minimal | |

Web uses header/account nav — **not** a defect vs Mobile bottom tabs.

---

## Journey checks

| Journey | Result |
|---------|--------|
| Home → Shop → Product → Cart → Checkout → Payment → Order | **PASS** (shell lost only on PDP/checkout — intentional) |
| Home → Account → Orders → Order Detail → Return | **PASS** (shell retained on order/return) |
| Home → Wishlist → Product → Wishlist → Cart | **PASS** |
| Search → Product → Cart | **PASS** |
| Notifications → Order Detail | **PASS** |
| Logout → Login → shell tabs | **PASS** |

No dead-end primary routes after QA-36-007.
