# QA-06 Test Matrix — Customer Website Regression

**Date:** 2026-08-12 · **App:** `apps/nursery-web`

| Flow | API | Web | Expected | Result | Evidence |
|------|-----|-----|----------|--------|----------|
| Auth login / bad password | PASS | PASS | Safe message | PASS | Live 401 Invalid email or password |
| Register / reset UI | PASS | PASS | Pages load | PASS | `/login` `/register` `/forgot-password` `/reset-password` 200 |
| Home | PASS | PASS | CTAs valid | PASS | `/` 200 |
| Catalog / shop | PASS | PASS | Products | PASS | `/shop` 200 |
| Search | PASS | PASS | Results + no stale overwrite | PASS | `/search?q=money` 200; race unit |
| Categories | PASS | PASS | List | PASS | `/categories` 200 |
| PDP | PASS | PASS | Detail | PASS | API product by slug |
| Wishlist | PASS | PASS | List | PASS | GET `/wishlist` 200 |
| Cart + coupon | PASS | PASS | Totals from API | PASS | WELCOME10 applied |
| Checkout preview | PASS | PASS | Authoritative grand | PASS | Preview ₹494.1 |
| COD place | PASS | PASS | CONFIRMED | PASS | `ORD-20260812-00009` |
| Duplicate X-Request-Id | PASS | PASS | Same order | PASS | Idempotent same number |
| Order detail / tracking | PASS | PASS | Status | PASS | CONFIRMED then cancel |
| Cancel | PASS | PASS | CANCELLED + inventory rules | PASS | Cancel 200 |
| Returns list | PASS | PASS | `/customer/returns` | PASS | 200 list |
| Offers | PASS | PASS | Live campaigns (Coming soon = upcoming only) | PASS | `/offers` 200 |
| Find your plant page | PASS | PASS | UI + match API | PASS | Page 200; client uses `/plant-finder/match` |
| Notifications | PASS | PASS | List | PASS | 200 |
| Account hub | PASS | PASS | Links | PASS | `/account` 200 |
| Broken Unsplash images | DATA | FIXED | No upstream 404 for known IDs | PASS | Repair script 9 rows + SafeImage |
| Search race | N/A | FIXED | Stale gen ignored | PASS | Unit + SearchBox |
| Razorpay live | — | — | Real keys | UNVERIFIED | Keys empty |
| Responsive viewport | — | — | 390–1440 | PARTIAL | Pages load; no device lab this phase |
| a11y critical | — | — | Labels on wish/gallery | PASS | Existing aria-labels retained |

## Commands

```text
npm run test:unit          # PASS
npm run build              # PASS (local CRITICAL env warning expected)
npx eslint <touched files> # PASS (clean)
npm run lint               # FAIL — 15 pre-existing set-state-in-effect errors
php artisan test --filter='Qa02|Qa03|Qa04|Qa05'  # 31 PASS
php scripts/qa06_repair_product_images.php       # 9 rows
```
