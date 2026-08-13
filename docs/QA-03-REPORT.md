# QA-03 Report

**Date:** 2026-08-12  
**Final Status:** COMPLETE

Standing policy: `docs/QA_TESTING_POLICY.md`

---

## 1. Scope

Cart / coupon / free-delivery / variant-id integrity across Laravel API, Customer Web, and Customer Mobile (real Android device).

---

## 2. Bugs Fixed

| ID | Bug | Root Cause | Fix | Status |
|----|-----|------------|-----|--------|
| QA-CART-001 | Free-delivery differed cart vs checkout | Different merchandise bases | Both use `subtotal − discount` via `CartService::freeDeliveryMeta` | **FIXED** |
| QA-CART-002 | `variant_id` vs `product_variant_id` | Client typing / mapping | API returns `variant_id`; Web + Mobile map correctly | **FIXED** |
| (found) | Cart apply skipped coupon usage caps | Apply weaker than checkout | Shared `assertCouponUsageAvailable` | **FIXED** |

---

## 3. Files Changed

(See prior QA-03 implementation + this closeout.)

- `CartService.php`, `CheckoutService.php`, `Qa03CartTotalsTest.php`
- `nursery_app` `models.dart`, `test/cart_mapping_test.dart`
- `nursery-web` `qa-unit-checks.ts`, `package.json` `test:unit`
- Docs: this report, matrix, bug register, feature matrix, roadmap

---

## 4. Unit Tests

| Test | Result |
|------|--------|
| `Qa03CartTotalsTest` free-delivery meta | **PASS** |
| Flutter `cart_mapping_test.dart` | **PASS** |
| Flutter `auth_messages_test.dart` | **PASS** |
| Web `npm run test:unit` | **PASS** |

---

## 5. API / Integration Tests

| Flow | Result |
|------|--------|
| `GET /health/ready` (127.0.0.1 + 192.168.1.3) | **PASS** |
| Login + cart CRUD + coupon + preview agree | **PASS** |
| Qty 0 → 422; overstock → 409; client `unit_price` ignored | **PASS** |
| `php artisan test --filter='Qa03\|Qa02'` (19) | **PASS** |

---

## 6. Frontend/Mobile Tests

| Flow | Result |
|------|--------|
| Customer Web Playwright: login (token seed after throttle), add, qty, WELCOME10, checkout preview `Place order — ₹722` | **PASS** |
| Customer Mobile vivo 1951 real device: home, cart, qty±, WELCOME10, checkout preview, remove item, app restart restore | **PASS** |
| Mobile UI ↔ API cart for `admin@nursery.test` (Vermicompost ₹149, FD remaining ₹850) | **PASS** |

---

## 7. Regression Tests

| Prior | Result |
|-------|--------|
| QA-AUTH-001..004 PHPUnit | **PASS** |
| QA-CHK-001 gate (no silent cart payable when preview fails; place order uses preview total) | **PASS** (observed) |

---

## 8. Manual/E2E Smoke Tests

| Flow | Result |
|------|--------|
| Device: `flutter run -d 2d3714f --dart-define=API_BASE_URL=http://192.168.1.3:8000/api/v1` | **PASS** (APK installed; debug attach flaky; app used via adb) |
| Web: `http://127.0.0.1:3000` + API | **PASS** |
| Same-account Web↔Mobile simultaneous cart sync | **UNVERIFIED** (Web=Asha, Mobile=admin@nursery.test); both clients match API for their own carts |

---

## 9. Remaining Issues

- Auth login throttle (429) during aggressive smoke — ops, not cart logic
- Web eslint pre-existing `set-state-in-effect` errors (not cart)
- QA-CHK-001 / QA-PAY-001 full payment E2E → QA-04
- Catalog variants rarely used; `variant_id` null on simple products (expected)

---

## 10. Environment Limitations

- Flutter debug “log reader stopped” on vivo; smoke used installed APK + uiautomator
- Same customer on Web and Mobile simultaneously not executed

---

## 11. Final Status

**COMPLETE**

**QA-03 COMPLETE — QA-04 READY**
