# QA-07 Test Matrix — Customer Mobile Parity

**Date:** 2026-08-12  
**Device:** vivo 1951 (`2d3714f`) · API `http://192.168.1.3:8000/api/v1`

| Flow | API | Mobile | Unit | Integration | Real Device |
|------|-----|--------|------|-------------|-------------|
| Returns list `GET /customer/returns` | PASS | PASS | PASS | PASS | PASS |
| Return detail `GET /returns/{id}` | PASS | PASS | PASS | PASS | PASS |
| Return request from order | PASS | PASS (existing) | N/A | PASS | PARTIAL* |
| My Reviews `GET /customer/reviews` | PASS | PASS | PASS | PASS | PASS |
| Account nav Returns/Reviews | N/A | PASS | N/A | N/A | PASS |
| Auth guest gate on new screens | PASS | PASS | N/A | PASS | N/A |
| Login / session | PASS | PASS | PASS | PASS | PASS |
| Cart qty / coupon UI | PASS | PASS | PASS | PASS | PASS |
| Checkout preview + COD place | PASS | PASS | PASS | PASS | PASS (`ORD-20260812-00010`) |
| Orders list + detail / tracking UI | PASS | PASS | N/A | PASS | PASS |
| Razorpay live | — | — | — | — | UNVERIFIED |

\* Return **create** verified earlier via API smoke (`RETURN_REQUESTED` on `ORD-20260812-00007`); device UI verified list/detail for that return. Fresh create-from-order tap on device this closeout = not re-run (order already returned).

## Commands

```text
php artisan test --filter='Qa07|Qa02|Qa03|Qa04|Qa05'  # 34 PASS
flutter test test/customer_account_mapping_test.dart test/checkout_preview_rules_test.dart
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.3:8000/api/v1
```
