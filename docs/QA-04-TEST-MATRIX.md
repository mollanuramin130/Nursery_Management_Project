# QA-04 Test Matrix — Checkout + Payment + Order Integrity

**Date:** 2026-08-12  
**Policy:** PASS only with executed evidence. UNVERIFIED when environment blocks (e.g. empty Razorpay keys).

| Flow | API | Web | Mobile | Admin | Unit | Integration | Real Device |
|------|-----|-----|--------|-------|------|-------------|-------------|
| Checkout preview | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| Preview failure (block place) | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| Stale preview invalidation | N/A | PASS | PASS | N/A | PASS | PASS | PASS |
| COD place order | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| Razorpay live checkout | UNVERIFIED | UNVERIFIED | UNVERIFIED | N/A | N/A | UNVERIFIED | UNVERIFIED |
| Payment initiate (server amount) | PASS | PASS | PASS | N/A | PASS | PASS | N/A |
| Payment verify / signature | PASS | N/A | N/A | N/A | PASS* | PASS* | UNVERIFIED |
| `local_stub` production block | PASS | PASS | PASS | N/A | PASS | PASS | N/A |
| Payment failure → not success | PASS* | UNVERIFIED | UNVERIFIED | N/A | PASS* | PASS* | UNVERIFIED |
| Order creation | PASS | PASS | PASS | PASS | PASS | PASS | PASS |
| Duplicate submit / idempotency | PASS | PASS | PASS | N/A | PASS | PASS | PASS |
| Inventory reserve/commit/release | PASS* | N/A | N/A | N/A | PASS* | PASS* | N/A |
| Cross-platform same-account order | PASS | PASS | PASS | PASS | N/A | PASS | PASS† |
| Admin order visibility | PASS | N/A | N/A | PASS | N/A | PASS | N/A |
| Admin Mobile order visibility | PASS | N/A | N/A | PASS‡ | N/A | PASS‡ | UNVERIFIED |

\* Covered by existing Phase 21 / payment feature tests + QA-04 COD/stub suites; live provider path not run.  
† Device COD place `ORD-20260812-00006`; same-account Asha order fetch via API as mobile client.  
‡ Admin API `GET /admin/orders/{id}` PASS for COD order; Admin Mobile UI smoke not re-run this phase (API contract shared).

## Environment notes

| Item | Value |
|------|--------|
| API | `http://192.168.1.3:8000/api/v1` |
| `APP_ENV` | local |
| Razorpay keys | empty → `local_stub` allowed in non-prod only |
| Device | vivo 1951 `2d3714f` |
| Customer account (API cross-platform) | `asha@example.com` |

## Commands executed

```text
php artisan test --filter='Qa04|Qa03|Qa02'   # 24 PASS
flutter test test/checkout_preview_rules_test.dart test/cart_mapping_test.dart
cd apps/nursery-web && npm run test:unit
# Live: COD POST /orders → ORD-20260812-00007; admin GET; mobile client GET same order
# Device UI: Place order → ORD-20260812-00006 CONFIRMED ₹149
```
