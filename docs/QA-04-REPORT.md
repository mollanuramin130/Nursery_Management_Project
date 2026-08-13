# QA-04 REPORT — Checkout + Payment + Order Integrity

## 1. Status

**COMPLETE** (with documented UNVERIFIED: live Razorpay provider — keys empty in this environment)

## 2. Scope

Make customer checkout → payment → order reliable and production-safe across Laravel API, Customer Web, Customer Mobile, and Admin order visibility. Preserve QA-03 cart/coupon/FD behavior. No UI redesign. No mandatory DB migration.

## 3. Bugs investigated

| ID | Classification | Finding |
|----|----------------|---------|
| QA-CHK-001 | FRONTEND / SECURITY of totals | Web historically fell back to cart `grand_total` when preview missing; cart shipping is always `0` |
| QA-PAY-001 | FRONTEND + PAYMENT + BACKEND | Web could treat `local_stub` as success without production guard; Flutter already blocked in `kReleaseMode` |
| Stale preview | FRONTEND / MOBILE | Preview must invalidate on address / shipping / cart / coupon change |
| Double submit | API + CLIENT | `X-Request-Id` idempotency + client busy locks |
| Razorpay live | CONFIGURATION | Keys empty locally → cannot claim live PASS |

## 4. Root causes

1. **QA-CHK-001:** Presentation used `preview ?? cart` merchandise total → understated payable (no shipping / final rules).
2. **QA-PAY-001:** Asymmetric client guards; production must refuse stub even if API misconfigured.
3. **Stale preview:** Preview must be tied to checkout inputs; place must force fresh preview.
4. **Razorpay UNVERIFIED:** `RAZORPAY_KEY` / `SECRET` empty in local `.env` — intentional non-prod stub path (QA-PAY-002).

## 5. Bugs fixed

- **QA-CHK-001** — Web: `checkoutPayableTotal` / `canPlaceOrder`; no cart fallback; error + retry; stale invalidation on cart lines / coupon / address / shipping; fresh preview before place.
- **QA-PAY-001** — Web `localStubPayment` throws in production with safe message; API already refuses stub in production (`Qa04PaymentStubGuardTest`); Flutter release message aligned.
- Mobile: `CheckoutPreviewRules` + wired into `CheckoutScreen` place gate.
- API: `Qa04CheckoutCodTest` — COD + preview totals + idempotent `X-Request-Id` same order id.

## 6. Files changed (primary)

| Area | Paths |
|------|--------|
| Customer Web | `apps/nursery-web/src/app/checkout/page.tsx`, `src/lib/razorpay.ts`, `src/lib/qa-unit-checks.ts` |
| Customer Mobile | `lib/core/checkout_preview_rules.dart`, `lib/screens/checkout_screen.dart`, `lib/services/razorpay_checkout.dart`, `test/checkout_preview_rules_test.dart` |
| API tests | `tests/Feature/Qa04CheckoutCodTest.php`, `tests/Feature/Qa04PaymentStubGuardTest.php` |
| Docs | `QA-04-*.md`, bug register, feature matrix, roadmap |

## 7. API changes

**NONE** (behavioral reuse of existing `/checkout/preview`, `POST /orders`, `/payments/*`). No breaking contract change.

## 8. Database changes

**NONE** — see `docs/QA_DATABASE_CHANGE_PROPOSAL.md` (QA-04: no schema required).

## 9–12. Client verification

| Client | Result | Evidence |
|--------|--------|----------|
| Customer Web | PASS | Preview-required place; unit checks; COD path via API smoke same stack |
| Customer Mobile | PASS | Real device COD `ORD-20260812-00006` CONFIRMED ₹149 |
| Admin Web | PASS | `GET /admin/orders/{id}` 200 for Asha COD `ORD-20260812-00007` |
| Admin Mobile | PASS (API) / UI UNVERIFIED | Same admin orders API; interactive Admin Mobile UI not re-smoked |

## 13. Unit tests

| Suite | Result |
|-------|--------|
| PHPUnit `Qa04` + `Qa03` + `Qa02` (24) | PASS |
| Web `npm run test:unit` | PASS |
| Flutter `checkout_preview_rules_test` + `cart_mapping_test` | PASS |

## 14. Integration tests

| Flow | Result |
|------|--------|
| Asha preview → COD `POST /orders` → list → admin detail | PASS |
| Same order id retrieved with mobile-platform headers | PASS |

## 15. Regression

| Phase | Result |
|-------|--------|
| QA-02 auth PHPUnit | PASS |
| QA-03 cart/preview PHPUnit + Flutter cart mapping | PASS |

## 16. Real-device

| Step | Result |
|------|--------|
| vivo `2d3714f` + LAN API | PASS |
| Checkout review → Place order → Order confirmed | PASS (`ORD-20260812-00006`) |

## 17. Cross-platform same account

| Step | Result |
|------|--------|
| Web/API create COD as Asha | PASS `ORD-20260812-00007` |
| Mobile client headers fetch same order | PASS |
| Simultaneous live Web UI + Mobile UI same session | UNVERIFIED (API same-account PASS; dual-UI concurrent not filmed) |

## 18. Security

| Check | Result |
|-------|--------|
| Payable amount from server order/preview | PASS |
| Signature / webhook verification server-side | PASS (existing Phase 21; not weakened) |
| Production `local_stub` refused (API + Web + Flutter release) | PASS |
| Live Razorpay UI | UNVERIFIED (keys empty) |

## 19. Payment state (code truth)

Payments: initiated → pending → paid / failed / cancelled (provider + verify/webhook). Clients cannot force PAID. COD confirms order without Razorpay.

Orders: `PENDING_PAYMENT` → `CONFIRMED` (COD or paid) · `PAYMENT_FAILED` · cancel / fulfillment chain unchanged.

## 20. Remaining / UNVERIFIED

- Live Razorpay checkout / verify / webhook on real keys
- Interactive Admin Mobile order list UI after place
- Concurrent dual-browser+app same-account cart sync UI (API consistency PASS)

## 21. Risk assessment

Low for COD + preview authority. Medium until production Razorpay keys + one live verify smoke before launch.

## 22. QA-05 readiness

**YES** — checkout/payment integrity phase closed; inventory/fulfillment deep dive is QA-05.
