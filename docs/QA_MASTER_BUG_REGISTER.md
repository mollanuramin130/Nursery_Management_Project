# QA Master Bug Register

**Date:** 2026-08-13 · **Through QA-42A** · Release: **YELLOW** (GREEN blocked — LIVE Razorpay / prod `--strict` / Firebase / ops). **QA-42A refresh UX PARTIAL** — see `docs/QA-42A-REFRESH-UX-REPORT.md`. QA-27 LIVE readiness unchanged (**NOT READY**).

Severity: CRITICAL / HIGH / MEDIUM / LOW  
Classification tags: API · BACKEND · FRONTEND · MOBILE · ADMIN · CONFIGURATION · AUTHENTICATION · PAYMENT · SECURITY · PARITY · UX

---

## CRITICAL

### QA-CFG-001 — Customer login / checkout fails when API unreachable or misconfigured
- **Area:** Configuration / Auth / Checkout  
- **Applications:** Customer Web, Customer Mobile  
- **Severity:** CRITICAL · **Priority:** P0  
- **Root cause:** **CONFIGURATION / NETWORK / CORS / SEED / HOST BINDING** — not email/password/token field mismatch  
- **Fix (QA-01):** Health probes on all four login UIs; smoke scripts; env docs; CORS verified for `:3000`/`:3001`; API login + cart + checkout preview + admin auth path verified on this host  
- **Verified:** API health, Customer Web + Admin Web reachability, seeded customer/admin login, authenticated requests, cart/preview connectivity  
- **Not available this session:** Flutter emulator / physical-device interactive runs  
- **Regression:** `bash apps/nursery-api/scripts/qa01_smoke.sh`; `bash apps/nursery-api/scripts/qa01_connectivity_smoke.sh`  
- **Status:** **PARTIALLY VERIFIED** (do not mark FIXED until mobile runtime smoke is completed)  
- **Deferred from this bug:** QA-AUTH-001 message mapping → QA-02  

### QA-PAY-001 — Customer Web accepts `local_stub` payment mode without release guard
- **Area:** Payment · **App:** Customer Web  
- **Severity:** CRITICAL (if shipped to production with empty Razorpay keys) · **Priority:** P0  
- **Evidence:** `apps/nursery-web/src/lib/razorpay.ts`, checkout + order detail stub branches; Flutter blocks stub in `kReleaseMode`  
- **Expected:** Production web refuses stub / requires live keys  
- **Actual (pre-fix):** Web could treat stub as success when API returns `mode: local_stub`  
- **Root cause:** FRONTEND + PAYMENT asymmetry  
- **Fix (QA-04):** `localStubPayment` throws in production with safe user message; API gateway + pending reuse refuse stub in production; Flutter release message aligned  
- **Test added:** `Qa04PaymentStubGuardTest` + Web `qa-unit-checks`  
- **Status:** **FIXED** / **CLOSED** (QA-04 COMPLETE; live Razorpay UI UNVERIFIED — keys empty)  

---

## HIGH

### QA-AUTH-001 — Failed login always returns generic `"Unauthenticated"`
- **Area:** Authentication · **App:** API (+ all clients)  
- **Severity:** HIGH · **Priority:** P1  
- **Evidence:** `AuthService` throws `'Invalid email or password'` / `'Account is blocked'` but `bootstrap/app.php` AuthenticationException renderer returns fixed `'Unauthenticated'`  
- **Impact:** Operators cannot distinguish bad password vs blocked vs network; looks like “login broken”  
- **Root cause:** BACKEND error mapping  
- **Fix (QA-02):** Whitelist safe AuthenticationException messages + `meta.error_code` (`AUTH_INVALID_CREDENTIALS`, `AUTH_ACCOUNT_BLOCKED`); clients map blocked vs credentials  
- **Files:** `bootstrap/app.php`, `auth_messages.dart`, Admin Mobile `ApiException.userMessage`, Web `auth-messages.ts`  
- **Test added:** `tests/Feature/Qa02AuthMessagesTest.php`  
- **Regression:** Live curl wrong password; PHPUnit PASS  
- **Status:** **FIXED** (API VERIFIED; client interactive smoke PARTIAL — see `docs/QA-02-REPORT.md`)  

### QA-AUTH-002 — Password reset API exists; no Customer Web/Mobile UI
- **Area:** Auth parity · **Severity:** HIGH · **Priority:** P1  
- **Evidence:** `POST /auth/reset-password` routed; no `reset-password` page/screen in nursery-web or nursery_app  
- **Root cause:** PARITY / FRONTEND MISSING  
- **Fix (QA-02):** Web `/reset-password`, Mobile `/reset-password`; `ResetPassword::createUrlUsing` → `CUSTOMER_WEB_URL`; forgot UI links to reset  
- **Test added:** forgot→reset→login in `Qa02AuthMessagesTest`  
- **Status:** **FIXED** (UI present + API flow VERIFIED; email delivery depends on `MAIL_*`)  

### QA-CHK-001 — Web checkout displays cart merchandise total if preview fails
- **Area:** Checkout · **App:** Customer Web  
- **Severity:** HIGH · **Priority:** P1  
- **Evidence:** `checkout/page.tsx` historically used `preview?.grand_total ?? cart?.grand_total`; cart `shipping_total` is always `0` (`CartService::present`)  
- **Expected:** Block place-order until preview succeeds (Flutter already does)  
- **Actual (pre-fix):** CTA amount can omit shipping; confusing / understated total  
- **Root cause:** FRONTEND state / API contract misunderstanding  
- **Fix (QA-04):** `checkoutPayableTotal` / `canPlaceOrder`; stale preview invalidation; force fresh preview before place; Mobile `CheckoutPreviewRules`  
- **Test added:** Web `qa-unit-checks`; Flutter `checkout_preview_rules_test`; `Qa04CheckoutCodTest`  
- **Live:** Device COD place PASS; API COD + admin visibility PASS  
- **Status:** **FIXED** / **CLOSED** (QA-04 COMPLETE)  

### QA-CART-001 — Free-delivery qualification differs cart vs checkout
- **Area:** Cart/Checkout · **Severity:** HIGH · **Priority:** P1  
- **Evidence:** Cart threshold vs **raw subtotal**; Checkout vs **subtotal − discount** (`CartService` vs `CheckoutService::buildTotals`)  
- **Root cause:** BACKEND business-rule inconsistency  
- **Fix (QA-03):** Cart `present()` uses merchandise after discount; Checkout reuses `CartService::freeDeliveryMeta`; coupon usage limits shared on apply  
- **Test added:** `Qa03CartTotalsTest` (agree with coupon + threshold + remove coupon)  
- **Live smoke:** Cart ↔ preview FD/remaining agree after WELCOME10; Web Playwright + vivo real-device cart smoke PASS  
- **Status:** **FIXED** / **CLOSED** (QA-03 COMPLETE)  

### QA-PAR-001 — Customer Mobile missing Returns account flow
- **Area:** Returns · **Severity:** HIGH · **Priority:** P1  
- **Evidence:** Web `/account/returns`; Mobile historically no account list/detail; API customer returns endpoints exist  
- **Root cause:** PARITY / MOBILE  
- **Fix (QA-07):** `ReturnsScreen` + `ReturnDetailScreen`; Account “My returns”; routes `/account/returns`, `/account/returns/:id`; reuse `GET /customer/returns` + `GET /returns/{id}`; request remains order-detail `POST /orders/{id}/returns`  
- **Test added:** `Qa07CustomerReturnsReviewsTest` + `customer_account_mapping_test.dart`  
- **Live:** Device `2d3714f` list + detail PASS; API Asha returns PASS  
- **Status:** **FIXED** / **CLOSED** (QA-07 COMPLETE)  

### QA-10 note — Cross-platform integration (2026-08-12)
- **Phase status:** **COMPLETE** — see `docs/QA-10-REPORT.md`
- **Production defects found:** none requiring code fix
- **Carry-forward unchanged:** QA-SEC-001 OPEN · QA-ADM-002 intentional PARTIAL · Razorpay live UNVERIFIED

### QA-SEC-001 — Customer/Admin Web JWTs stored in `localStorage`
- **Area:** Security · **Severity:** HIGH · **Priority:** P1  
- **Evidence:** Phase 21 audit; historically `gl_*` / `gl_admin_*` in localStorage  
- **Root cause:** SECURITY / FRONTEND architecture  
- **QA-11 decision:** OPTION C deferred full cookie migration  
- **QA-32 mitigation:** CSP headers (blast-radius only)  
- **QA-33 fix:** Same-origin Next.js BFF (`/api/bff/*`) + HttpOnly `gl_web_*` / `gl_admin_web_*` cookies; CSRF double-submit; tokens stripped from browser JSON; Mobile Bearer unchanged  
- **Evidence:** `docs/QA-33-SECURITY-REPORT.md` · `scripts/qa33_bff_smoke.sh` · unit cookie policy  
- **Status:** **FIXED / CLOSED** (QA-33)

### QA-SEC-002 — `users.manage` could assign `super_admin` (privilege escalation)
- **Area:** Security · Admin · RBAC · **Severity:** HIGH · **Priority:** P0  
- **Evidence:** `AdminUserController` store/update synced any existing role slug  
- **Fix (QA-11):** Only actors with `super_admin` role may assign `super_admin`  
- **Test:** `Qa11SecurityTest::test_users_manage_cannot_assign_super_admin`  
- **Status:** **FIXED** / **CLOSED**

### QA-SEC-003 — Admin Web login `?next=` open redirect
- **Area:** Security · Admin · UX · **Severity:** MEDIUM  
- **Fix (QA-11):** `sanitizeAdminNext`  
- **Test:** Admin `qa-unit-checks`  
- **Status:** **FIXED** / **CLOSED**

### QA-SEC-004 — Customer Web refresh-fail left in-memory `user`
- **Area:** Security · Frontend · **Severity:** MEDIUM  
- **Fix (QA-11):** `setUnauthorizedHandler` + `clearSession` (Admin Web parity)  
- **Test:** Customer `qa-unit-checks` + code path  
- **Status:** **FIXED** / **CLOSED**

### QA-11 note — Security phase (2026-08-12)
- **Phase status:** **COMPLETE** — `docs/QA-11-REPORT.md`  
- **QA-SEC-001:** OPEN (Option C)  
- **Carry-forward:** Razorpay UNVERIFIED · QA-ADM-002 intentional PARTIAL  

### QA-12 note — Performance + reliability (2026-08-12)
- **Phase status:** **COMPLETE** — `docs/QA-12-REPORT.md` · closeout · test matrix  
- **HIGH fixed:** QA-PERF-001…009 (see MEDIUM section for PERF register entries)  
- **Still OPEN:** QA-SEC-001 · QA-PERF-010 · Razorpay live UNVERIFIED  

### QA-13 note — Automated regression (2026-08-12)
- **Phase status:** **COMPLETE** — `docs/QA-13-REPORT.md` · closeout · test matrix  
- **Fixed:** QA-PERF-011 · QA-13-001  
- **Suite:** QA+Phase **160 passed** (732 assertions)  
- **Carry-forward:** QA-SEC-001 · QA-PERF-010 · Razorpay · device fulfillment mutations  

### QA-14 note — E2E release gate (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-14-REPORT.md`  
- **Live golden:** COD + pick/pack/ship/OFD/deliver → customer DELIVERED PASS  
- **Regression:** 162 passed / 754 assertions  
- **UNVERIFIED:** Razorpay live · interactive mobile/browser UI · load  
- **OPEN:** QA-SEC-001 · QA-PERF-010 · QA-ADM-002 intentional  

### QA-15 note — Final acceptance (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-15-REPORT.md`  
- **Release:** **YELLOW — CONDITIONAL RELEASE** (`docs/QA_FINAL_RELEASE_REPORT.md`)  
- **Paid Razorpay:** BLOCKED until live verification  
- **QA-SEC-001:** remains OPEN (HIGH RISK deferred epic)  
- **Handover:** conditional YES — `docs/QA_CLIENT_HANDOVER_CHECKLIST.md`  

### QA-16 note — Production hardening (2026-08-12)
- **Phase status:** **PARTIAL / BLOCKED for GREEN** — `docs/QA-16-REPORT.md`  
- **Added:** `nursery:production-readiness` + Qa16ProductionHardeningTest (5 PASS)  
- **Regression:** 167 passed / 767 assertions  
- **Still BLOCKED:** Razorpay live · prod-host HTTPS/DEBUG/CORS evidence · backup restore  
- **QA-SEC-001:** remains OPEN  

### QA-17 note — Go-live validation (2026-08-12)
- **Phase status:** **PARTIAL / BLOCKED for GREEN** — `docs/QA-17-REPORT.md`  
- **Release:** **YELLOW** unchanged for GREEN criteria  
- **Fixed:** `--strict` now requires `APP_ENV=production` (local misleading exit 0 removed)  
- **Local backup/restore drill:** **PASS** (isolated DB; prod still UNVERIFIED) — `QA-17-BACKUP-RESTORE-DRILL.md`  
- **Regression:** 168 passed / 768 assertions  
- **Still BLOCKED:** Razorpay live + webhook  
- **Still UNVERIFIED:** prod `--strict`, HTTPS/DEBUG on prod host, device interactive UI, Playwright, load, prod rollback  
- **QA-SEC-001:** remains OPEN  

### QA-18 note — Payment + ops readiness (2026-08-12)
- **Phase status:** **PARTIAL / BLOCKED for GREEN** — `docs/QA-18-REPORT.md`  
- **Added:** `Qa18ProductionPaymentTest` (11 PASS) — stub/signed webhook/idempotency/amount/COD/fulfill/auth  
- **Regression:** 179 passed / 873 assertions  
- **Razorpay LIVE:** still **BLOCKED** (keys empty) — do not claim LIVE PASS from stub tests  
- **Prod `--strict` / HTTPS / device UI / prod backup:** still **UNVERIFIED**  
- **QA-SEC-001:** remains OPEN  

### QA-19 note — Live payment + staging readiness (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-19-REPORT.md`  
- **Added:** `RAZORPAY_TEST_KEY_IN_PRODUCTION` readiness P0; `Qa19LivePaymentReadinessTest` (6 PASS)  
- **Regression:** 186 passed / 918 assertions  
- **LIVE payment / webhook / UPI device:** still **BLOCKED** (keys empty)  
- **QA-SEC-001:** remains OPEN (HttpOnly/BFF not implemented)  
- **GREEN:** **NO**  

### QA-20 note — Live payment + production release gate (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-20-REPORT.md`  
- **Baseline:** 186 / 918 reconfirmed PASS  
- **Added:** `Qa20ProductionReleaseGateTest` (7 PASS) — secret leakage, webhook-after-verify, IDOR verify, retry-after-fail, staging vs prod test keys, admin cannot force verify  
- **Regression:** 193 / 966 PASS; Qa11 security PASS  
- **LIVE payment/webhook:** still **BLOCKED** (keys empty)  
- **QA-SEC-001:** OPEN — risk acceptance required for public Web GREEN  
- **GREEN:** **NO**  

### QA-21 note — UPI Dynamic QR + Intent + server verification (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-21-REPORT.md` · `docs/QA-21-CLOSEOUT-REPORT.md`  
- **Built:** UPI channel on Razorpay (`dynamic_qr` / `upi_intent`); amount authority; Web QR + poll; Mobile intent + poll; admin payment txn fields  
- **Tests:** `Qa21UpiPaymentTest` (5); regression **198 / 1011** PASS  
- **LIVE UPI / webhook:** still **BLOCKED** (keys empty) — simulation ≠ LIVE  
- **QA-SEC-001:** OPEN (UPI does not fix)  
- **GREEN:** **NO**  

### QA-22 note — Push + order status notifications (2026-08-12)
- **Phase status:** **PARTIAL** — `docs/QA-22-REPORT.md` · `docs/QA-22-CLOSEOUT-REPORT.md`  
- **Built:** `OrderNotificationDispatcher`; staff `new_order`; customer status/payment/return/refund notifies; deep links; device register paths  
- **Tests:** `Qa22NotificationsTest` (9); regression **207 / 1045** PASS  
- **LIVE FCM:** **BLOCKED** (`FCM_SERVER_KEY` empty; no Firebase client project)  
- **QA-SEC-001:** OPEN  
- **GREEN:** **NO**  

### QA-23 note — Firebase FCM LIVE push gate (2026-08-12)
- **Phase status:** **PARTIAL / BLOCKED** — `docs/QA-23-REPORT.md` · `docs/QA-23-FCM-SETUP.md`  
- **Reused:** QA-22 NotificationService / dispatcher / queue / devices  
- **Gateway:** HTTP v1 (`FIREBASE_CREDENTIALS`) + legacy `FCM_SERVER_KEY` + local_stub  
- **Tests:** `Qa23FcmLiveReadinessTest` (6 PASS)  
- **Credentials / google-services.json:** **EMPTY / MISSING** → REAL FCM **BLOCKED**  
- **Web push:** DEFERRED  
- **Razorpay TEST gate** preserved in `docs/QA-23-*-RAZORPAY.md`  
- **QA-SEC-001:** OPEN · **GREEN:** **NO**  

### QA-24 note — Real Razorpay TEST payment gate (2026-08-12)
- **Phase status:** **BLOCKED** — `docs/QA-24-REPORT.md` · `docs/QA_RAZORPAY_TEST_PAYMENT_REPORT.md`  
- **Credentials:** KEY/SECRET/WEBHOOK **EMPTY** → real TEST stopped per policy  
- **Added:** `Qa24RealTestPaymentGateTest`  
- **Regression:** **224 / 1100 PASS**  
- **No payment redesign**; QA-22 notifications intact  
- **GREEN / LIVE:** **NO**  

### QA-25 note — Real Razorpay TEST + UPI E2E / Web UI (2026-08-13)
- **Phase status:** **PARTIAL** — `docs/QA-25-REPORT.md` · `docs/QA-25-UI-CHECKOUT-REPORT.md`  
- **Credentials:** **SET** (`rzp_test_*`); unsigned webhooks **false**  
- **Customer Web:** UPI / Razorpay option + Checkout / QR / Intent modes (existing API)  
- **Provider createOrder smoke:** **PASS**  
- **Full browser TEST charge + webhook finalize:** **UNVERIFIED** (operator)  
- **Regression:** **229 tests / 1108 assertions** (227 pass + 2 skipped empty-gate)  
- **No payment redesign**; QA-22 notifications intact  
- **GREEN / LIVE:** **NO**  

### QA-26 note — Real Razorpay TEST E2E (2026-08-13)
- **Phase status:** **COMPLETE** (TEST only) — `docs/QA-26-CLOSEOUT-REPORT.md`  
- **Evidence:** Payment **5526** / Order **9062** / `pay_TPBCfrCIzFjCKz` / `order_TPBCV5HS098qBW` / ₹1697  
- **Webhook + verify + CONFIRMED + inventory sale×1 + notifications:** **PASS**  
- **Regression:** **233 / 1120** (231 pass + 2 skipped)  
- **GREEN / LIVE:** **NO**  

### QA-27 note — LIVE Razorpay readiness gate (2026-08-13)
- **Phase status:** **COMPLETE** (readiness) — `docs/QA-27-REPORT.md`  
- **LIVE payment:** **BLOCKED** · **LIVE readiness:** **NOT READY**  
- **`--strict` on local:** EXIT **1** (expected; `APP_ENV=local`)  
- **LIVE credentials on this host:** **NOT CONFIGURED** (local has `rzp_test_*` only)  
- **Added:** `Qa27LiveReadinessGateTest` (7 PASS)  
- **Regression:** **240 / 1133** (238 pass + 2 skipped)  
- **QA-SEC-001:** **OPEN**  
- **GREEN:** **NO**  

---

## MEDIUM

### QA-CART-002 — Cart item `variant_id` vs Web type `product_variant_id`
- **Evidence:** API response `variant_id`; `types.ts` CartItem historically `product_variant_id`  
- **Severity:** MEDIUM · **Root cause:** API CONTRACT / FRONTEND  
- **Fix (QA-03):** Web types/services use `variant_id`; API accepts legacy `product_variant_id` on add; DB column unchanged  
- **Test added:** `Qa03CartTotalsTest::test_cart_add_accepts_variant_id_alias_and_returns_variant_id` + Flutter `cart_mapping_test`  
- **Status:** **FIXED** / **CLOSED** (QA-03 COMPLETE)  

### QA-AUTH-003 — Register UI password rules weaker than API
- **Evidence:** API `Password::min(8)->letters()->mixedCase()->numbers()`; Web/Mobile often length-only  
- **Severity:** MEDIUM · **Root cause:** UX / VALIDATION  
- **Fix (QA-02):** Shared client rules (`password-rules.ts`, `password_rules.dart`) on register + reset  
- **Status:** **FIXED**  

### QA-AUTH-004 — Admin Mobile refresh failure does not clear tokens
- **Evidence:** `nursery_admin_mobile` ApiClient `_refreshTokens` returns false without `clearTokens`  
- **Severity:** MEDIUM · **Root cause:** MOBILE / STATE  
- **Fix (QA-02):** Clear tokens + `AuthProvider.clearLocalSession` via `onSessionInvalid` → router sends user to login  
- **Status:** **FIXED** (code + unit message tests; interactive refresh UI NOT AVAILABLE)  

### QA-PAR-002 — Customer Mobile missing dedicated My Reviews page
- **Severity:** MEDIUM · Reviews historically on PDP only  
- **Fix (QA-07):** `MyReviewsScreen`; Account “My reviews”; route `/account/reviews`; reuse `GET /customer/reviews` (read-only; no customer edit/delete in API)  
- **Test added:** `Qa07CustomerReturnsReviewsTest` + mapping unit tests  
- **Live:** Device shows approved review history PASS  
- **Status:** **FIXED** / **CLOSED** (QA-07 COMPLETE)  

### QA-ADM-001 — Admin Web Users & Roles UI is Coming Soon
- **Evidence:** `navigation.ts` historically → `/coming-soon`; API user manage existed  
- **Severity:** MEDIUM · **Root cause:** ADMIN PARITY  
- **Fix (QA-08):** `/users`, `/users/new`, `/users/[id]`; nav `/users`; additive `GET /admin/roles`; user show `permissions` + `role_permissions`  
- **Test added:** `Qa08AdminUsersRolesTest` + Admin `qa-unit-checks`  
- **Status:** **FIXED** / **CLOSED** (QA-08 COMPLETE)  

### QA-ADM-002 — Admin Mobile lacks catalog/marketing/returns/analytics
- **Severity:** MEDIUM (by design for ops app, but gaps vs Phase 19 acceptance breadth)  
- **QA-09:** Confirmed intentional ops scope (Dashboard, Orders, Inventory, Scan, Fulfillment, PO, Suppliers, Warehouses, Notifications). Do not force Admin Web parity.  
- **QA-28:** Reconfirmed INTENTIONAL. Return notification deep links now fall back to `/orders` (no Admin Mobile returns module).  
- **Status:** **CLARIFIED** / intentional PARTIAL (not a defect)

### QA-28-001 — Customer Web return deep link 404
- **Severity:** HIGH · **Status:** **FIXED** (QA-28) — `/account/returns/[id]` + list links  
- **Detail:** `docs/QA-28-BUG-REGISTER.md`

### QA-28-002 — Customer Mobile refresh left stale in-memory user
- **Severity:** HIGH · **Status:** **FIXED** (QA-28) — `ApiClient.onSessionExpired` → `clearLocalSession`

### QA-28-003 — Customer order status label inconsistency
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-28) — shared labels Web + Mobile list

### QA-28-004 — Admin Mobile ungated order status buttons
- **Severity:** HIGH · **Status:** **FIXED** (QA-28) — `order_transitions.dart` UX gates

### QA-28-005 — Admin Mobile return deep link missing route
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-28) — deep link to order/list

### QA-28-006 — Admin Mobile Transfers tile misleading
- **Severity:** LOW · **Status:** **FIXED** (QA-28)

### QA-28-007 — Admin Mobile incomplete order status filters
- **Severity:** LOW · **Status:** **FIXED** (QA-28)

### QA-28-008 — Admin Mobile payment block type error (`Text(Column)`)
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-28)

### QA-29-001 — Debug Android cleartext blocked by network_security_config
- **Severity:** CRITICAL · **Status:** **FIXED** (QA-29) — debug/profile NSC overlays  
- **Detail:** `docs/QA-29-BUG-REGISTER.md`

### QA-29-002 — Admin Mobile payment blank after status update
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-29) — reload detail after status POST

### QA-29-003 — Customer Mobile wishlist add failed on device
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-30) — soft-delete restore in `WishlistService`  
- **Test:** `Qa30WishlistAndImagesTest` · Device add/remove PASS

### QA-29-004 — Tulsi product shows mismatched plant image
- **Severity:** LOW · **Status:** **FIXED** (QA-30) — distinct Tulsi image URL + repair script/sample SQL

### QA-30-001 — payment.failed then payment.captured left order PAYMENT_FAILED
- **Severity:** HIGH · **Area:** PAYMENT / BACKEND  
- **Status:** **FIXED** (QA-30) — recover confirm + inventory commit; state machine allows PAYMENT_FAILED→CONFIRMED  
- **Evidence:** Order **9066** / `pay_TPDwnubVyTYAiG` · `Qa30PaymentFailedThenCapturedRecoveryTest`

### QA-30-002 — UPI Intent launch blocked Checkout fallback
- **Severity:** MEDIUM · **Area:** MOBILE  
- **Status:** **FIXED** (QA-30) — `shouldPollAfterUpiIntentLaunch` · `upi_payment_test.dart`

### QA-31-001 — Client amount override accepted on pending payment reuse
- **Severity:** HIGH · **Area:** PAYMENT / BACKEND  
- **Status:** **FIXED** (QA-31) — amount check before pending reuse  
- **Test:** `Qa31AmountAuthorityAndIdempotencyTest`

### QA-31-002 — Mobile UPI label lagged Web “UPI / Razorpay”
- **Severity:** LOW · **Area:** UX / PARITY  
- **Status:** **FIXED** (QA-31) — Customer Mobile payment option copy aligned

### QA-32-001 — Admin Web/Mobile raw order status codes in UI
- **Severity:** LOW–MEDIUM · **Area:** UX / PARITY  
- **Status:** **FIXED** (QA-32) — `orderStatusLabel` / `opsStatusLabel` + unit coverage

### QA-32-002 — Customer Mobile order detail missing return/refund labels
- **Severity:** LOW · **Area:** UX / PARITY  
- **Status:** **FIXED** (QA-32) — detail `_labels` aligned with list + Web

### QA-32 note — Final TEST hardening (2026-08-13)
- **Phase status:** **PARTIAL** — `docs/QA-32-CLOSEOUT-REPORT.md`
- **Regression:** **240 / 1170** (unchanged vs QA-31)
- **Lifecycle re-proof:** COD order **9069** → REFUNDED
- **Still OPEN then:** QA-SEC-001 · QA-ADM-002 intentional
- **Still BLOCKED/UNVERIFIED:** true UPI settle · QR settle · mobile-data w/o reverse
- **GREEN:** **NO**

### QA-33 note — SEC-001 HttpOnly BFF (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-33-CLOSEOUT-REPORT.md`
- **QA-SEC-001:** **CLOSED**
- **Regression:** **242 / 1184** (+2 tests / +14 asserts vs QA-32)
- **Still OPEN:** QA-ADM-002 intentional
- **GREEN:** **NO** (LIVE / prod / FCM still out of scope)

### QA-34-001 — Staging/production env examples omitted BFF deploy contract
- **Severity:** MEDIUM · **Area:** CONFIGURATION  
- **Status:** **FIXED** (QA-34) — `API_PROXY_TARGET` + `COOKIE_SECURE` in Web/Admin env examples; `qa34_bff_readiness.sh`

### QA-34 note — BFF HTTPS/deploy readiness (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-34-CLOSEOUT-REPORT.md`
- **Regression:** **242 / 1184** (unchanged vs QA-33)
- **HTTPS Secure on live staging host:** **UNVERIFIED** (policy unit-tested)
- **Still OPEN:** QA-ADM-002 intentional
- **GREEN:** **NO**

### QA-35-001 — Admin Mobile return notification deep link 404
- **Severity:** HIGH · **Status:** **FIXED** (QA-35) — remap `/returns*` → order/list

### QA-35-002 — Customer PENDING_PAYMENT copy split
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-35) — “Order placed” Web/Mobile/API timeline

### QA-35-003 — Admin transition UI raw status codes
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-35)

### QA-35-004 — Customer Web `paid=1` success without CONFIRMED
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-35)

### QA-35-005 — Raw payment status enums in UI
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-35)

### QA-35-006 — Customer Web returns errors shown as empty list
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-35)

### QA-35 note — Comprehensive TEST audit (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-35-CLOSEOUT-REPORT.md`
- **Regression:** **243 / 1190** (+1 / +6 vs QA-34)
- **Still OPEN:** QA-ADM-002 intentional
- **GREEN:** **NO**

### QA-36-001 — Customer Mobile `paid=1` showed Order confirmed without CONFIRMED
- **Severity:** HIGH · **Status:** **FIXED** (QA-36) — status-gated banner + UPI poll requires `order_status=CONFIRMED`

### QA-36-002 — Customer Web `?pay=pending` unpaid banner without PENDING_PAYMENT
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-36)

### QA-36-003 — Invalid order id infinite skeleton (Customer Web)
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-36)

### QA-36-004 — Wishlist fetch error as empty + heart double-tap race
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-36)

### QA-36-005 — Customer deep link bare `/returns/*`
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-36) — remap to `/account/returns/*`

### QA-36-006 — Admin Web order header raw payment status
- **Severity:** LOW · **Status:** **FIXED** (QA-36)

### QA-36-007 — Customer Mobile bottom navigation missing on browse/account routes
- **Severity:** HIGH · **Status:** **FIXED** (QA-36) — shell branch restructure; `shell_nav_routes_test.dart`

### QA-36-008 — Wishlist removal skeleton flicker (stale UI)
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-36) — optimistic list + provider; Web store optimistic DELETE

### QA-36 note — Final TEST stability + feature parity audit (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-36-CLOSEOUT-REPORT.md` · `docs/QA-36-FEATURE-PARITY-MATRIX.md`
- **Regression:** **253 / 1209** (251 pass + 2 skipped); Customer Flutter **38** tests
- **Still OPEN:** QA-ADM-002 intentional
- **PARTIAL:** Variant PDP picker missing on both customer clients
- **BLOCKED/UNVERIFIED:** UPI settle · QR settle · mobile-data w/o reverse · live HTTPS cookie jar
- **GREEN:** **NO**

### QA-37-001 — Mobile session expiry left wishlist hearts stale
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-37) — `onSessionExpired` clears wishlist

### QA-37-002 — Cart qty/remove race (Web + Mobile)
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-37) — mutating single-flight + UI disable

### QA-37-003 — PDP wishlist double-tap (Web + Mobile)
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-37)

### QA-37-004 — Web cart fetch error shown as empty cart
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-37)

### QA-37-005 — Web wishlist Remove/Move busy + error toast
- **Severity:** LOW · **Status:** **FIXED** (QA-37)

### QA-37 note — Customer Web↔Mobile parity (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-37-CLOSEOUT-REPORT.md` · `QA-37-PARITY-MATRIX.md` · `QA-37-NAVIGATION-MATRIX.md`
- **Regression:** PHPUnit **253 / 1209**; Customer Flutter **41** (+3)
- **Bottom nav / wishlist flicker:** reconfirmed PASS (QA-36-007/008)
- **Still OPEN:** QA-ADM-002 intentional
- **GREEN:** **NO**

### QA-37-006 — Token refresh network failure forced logout
- **Severity:** CRITICAL · **Status:** **FIXED** (QA-37) — clear session only on auth rejection

### QA-37-007 — Missing offline / API-unavailable banner UX
- **Severity:** HIGH · **Status:** **FIXED** (QA-37) — NetworkStatus + banners Web/Mobile

### QA-37-008 — Customer Web ops-facing network toasts
- **Severity:** MEDIUM · **Status:** **FIXED** (QA-37)

### QA-37-009 — BFF upstream fetch without timeout
- **Severity:** HIGH · **Status:** **FIXED** (QA-37) — 30s AbortSignal

### QA-37 note — Network resilience (2026-08-13)
- **Phase status:** **COMPLETE** — `docs/QA-37-NETWORK-RESILIENCE.md` · `QA-37-AUDIT.md`
- **Regression:** PHPUnit **253 / 1209**; Customer Flutter **45**
- **Still OPEN:** QA-ADM-002 intentional
- **UNVERIFIED:** exhaustive Wi-Fi matrix · mobile-data w/o reverse · HTTPS cookie jar
- **GREEN:** **NO**

### QA-37-010…013 — Offline-first mock catalog / local cart-wishlist / no fake pay
- **Severity:** HIGH–CRITICAL (prevented) · **Status:** **FIXED** (QA-37)
- **Docs:** `QA-37-OFFLINE-ARCHITECTURE.md` · `QA-37-MOCK-DATA-CONTRACT.md`

### QA-37 note — Offline-first Customer Mobile (2026-08-13)
- **Phase status:** **COMPLETE** (TEST) · Vivo physical Wi‑Fi matrix **UNVERIFIED**
- **Regression:** PHPUnit **253 / 1209**; Customer Flutter **52**
- **GREEN:** **NO**

### QA-38-001…006 — Cache-first sync, API wins, reconnect, wishlist flicker
- **Severity:** CRITICAL–MEDIUM · **Status:** **FIXED** (QA-38 offline sync)
- **Docs:** `QA-38-OFFLINE-SYNC-REPORT.md`

### QA-38-007…020 — UI contrast, wishlist, toast/snackbar overlap, buttons
- **Severity:** CRITICAL–LOW · **Status:** **FIXED** (QA-38 UI polish; Vivo UNVERIFIED)
- **Docs:** `QA-38-REPORT.md` · `QA-38-BUG-REGISTER.md`

### QA-38 note — Offline sync + UI polish
- Offline (2026-08-13) + UI accessibility (2026-08-15)
- **GREEN:** NO · Device UI matrix UNVERIFIED
- **Phase status:** **COMPLETE** (TEST) · Vivo radio matrix **PARTIAL/UNVERIFIED**
- **Regression:** PHPUnit **253 / 1209**; Customer Flutter **58**
- **GREEN:** **NO**

### QA-39-001…005 — Vivo snackbar truncation, shell policy, wishlist bootstrap race
- **Severity:** P1–P2 · **Status:** **FIXED** (device evidence for 001; others code-verified)
- **Docs:** `QA-39-REPORT.md` · `QA-39-DEVICE-REPORT.md` · `QA-39-BUG-REGISTER.md`

### QA-39 note — Real device UX (2026-08-15)
- Vivo 1951 connected · adb reverse · Customer Mobile matrix PARTIAL
- **GREEN:** **NO** · LIVE OUT OF SCOPE
- Open: Admin Mobile full walk · COD/Razorpay TEST · wishlist flicker re-observe

### QA-40-001…017 — UI polish, render stability, image/cache soft refresh
- **Severity:** P1–P2 · **Status:** **FIXED** (code + unit; Vivo partial evidence in `docs/qa40/`)
- **Docs:** `QA-40-REPORT.md` · `QA-40-CLOSEOUT-REPORT.md` · `QA-40-BUG-REGISTER.md` · `QA-40-TEST-MATRIX.md`
- **Open:** QA-40-018…025 (admin list flicker, catalog remount, mock thumbs, device COD/Admin/web walk)

### QA-40 note — UI polish + resilience (2026-08-15)
- Checkout loading contrast root cause: Material disabled styles on `AppButton` loading
- Pixel sample on Vivo cart CTA: primaryDeep fill `(15,61,40)` + white-ish glyphs
- Regression: PHPUnit **253 / 1209**; Customer Flutter **63**; Admin Flutter **27**; Web unit **PASS**
- **GREEN:** **NO**

### QA-40-M-001…007 — Mobile pull-to-refresh / soft FutureBuilder / cart soft fetch
- **Severity:** P0–P2 · **Status:** **FIXED** (code + unit; Vivo PARTIAL evidence in `docs/qa40-mobile/`)
- **Docs:** `QA-40-MOBILE-REFRESH-REPORT.md` · `QA-40-MOBILE-BUG-REGISTER.md` · `QA-40-MOBILE-CLOSEOUT-REPORT.md`
- **Open:** Admin full device matrix · remaining admin hard-load screens

### QA-40-M note — Mobile refresh (2026-08-15)
- Root “refresh does nothing”: Addresses `onRefresh` did not await Future
- Soft FutureBuilder pattern + syncGeneration fan-out + cart `fetch(soft:)`
- Customer Flutter **66** · Admin Flutter **27** · PHPUnit **253 / 1209**
- **GREEN:** **NO**

### QA-41-001…007 — Mobile stability (images, Retry, soft FutureBuilder, catalog remount, admin soft-load)
- **Severity:** P0–P2 · **Status:** **FIXED** (code + unit; Vivo PARTIAL in `docs/qa41-mobile/`)
- **Docs:** `QA-41-REPORT.md` · `QA-41-CLOSEOUT-REPORT.md` · `QA-41-BUG-REGISTER.md` · `QA-41-TEST-MATRIX.md`
- **Open / carry-forward:** QA-40-M-008 residual · QA-40-M-010 admin matrix · QA-40-M-012 COD/Razorpay TEST · LIVE BLOCKED

### QA-41 note — Mobile stability (2026-08-15)
- Screenshot root causes: DEBUG FAB over categories; null category `image_url`; physical device `10.0.2.2` default
- Customer Flutter **68** · Admin Flutter **28** · PHPUnit **253 / 1209**
- **GREEN:** **NO**

### QA-42-001…005 — Soft-error keep-data, checkout/reviews soft load, Admin Products clarification
- **Severity:** P1–P2 · **Status:** **FIXED** / INTENTIONAL (no mobile `/products`)
- **Docs:** `QA-42-REPORT.md` · CLOSEOUT · TEST-MATRIX · BUG-REGISTER · `docs/qa42-mobile/`
- **Carry:** Admin post-login Vivo matrix UNVERIFIED · Razorpay TEST Checkout E2E UNVERIFIED · LIVE BLOCKED

### QA-42 note — Matrix + payment smoke (2026-08-15)
- COD API smoke PASS; Razorpay initiate PASS; stub verify correctly rejected under TEST keys
- Customer Flutter **68** · Admin Flutter **29** · PHPUnit **253 / 1209** · Web unit PASS
- **GREEN:** **NO**

### QA-42A-001…003 — Normal refresh must not look like offline
- **Severity:** P0 · **Status:** **FIXED** (unit + Vivo Home PTR evidence)
- **Root:** cache peek → `servingLocal` → saved-data banner on every pull-to-refresh
- **Docs:** `QA-42A-REFRESH-UX-REPORT.md` · TEST-MATRIX · BUG-REGISTER · `docs/qa42a-mobile/`
- Customer Flutter **73** · Admin Flutter **29** · PHPUnit **253 / 1209**
- **GREEN:** **NO**

### QA-09-001 — EnsurePermission only evaluated first slug of comma-separated middleware
- **Severity:** HIGH · **Area:** API RBAC  
- **Evidence:** `permission:inventory.adjust,purchase_orders.view,...` — Laravel splits args; old `handle(..., string $permission)` kept only first → `purchase_orders.view`-only staff got 403 on PO list  
- **Fix:** Variadic `string ...$permissionArgs` OR across all args  
- **Test:** `Qa09AdminOpsMobileTest::test_purchase_orders_view_alone_is_enough_without_inventory_adjust`  
- **Status:** **FIXED** / **CLOSED**  

### QA-PAY-002 — Payment/refund `local_stub` still available when keys empty (non-prod)
- **Severity:** MEDIUM · Acceptable for local; must never be on in production env  
- **Fix (QA-04):** Confirmed API production gate + pending-reuse refuse; local still allowed intentionally  
- **QA-08:** Admin refund UI production gate (`refund-safety.ts`); PHPUnit production refund 503; Returns page stub actions gated  
- **Status:** **VERIFIED** / **CLOSED** for production refusal (ops: keep production keys set; local stub remains for non-prod)  

### QA-05-001 — Expired reservation release used wrong movement reference
- **Area:** Inventory · **Severity:** HIGH · **Priority:** P1  
- **Evidence:** `ReleaseExpiredReservationsCommand` called `release(..., 'order_expired', $orderId)` while place/cancel/payment-fail use `reference_type=order`  
- **Impact:** After cron expiry → `PAYMENT_FAILED`, a customer cancel could release again under `order` and reduce another order’s `qty_reserved`  
- **Root cause:** BACKEND idempotency key mismatch  
- **Fix (QA-05):** Release with `order` reference; transition via `OrderStateMachine`; DB transaction + `lockForUpdate`  
- **Test added:** `Qa05InventoryFulfillmentTest::test_expired_reservation_uses_order_reference_so_cancel_cannot_over_release`  
- **Status:** **FIXED** / **CLOSED**  

### QA-06-001 — Customer Web search suggestions can apply stale responses
- **Area:** Customer Web · Search · **Severity:** MEDIUM · **Priority:** P2  
- **Evidence:** `SearchBox` debounced `/search/suggestions` without generation guard  
- **Fix:** `searchGenRef` + `shouldApplySearchResult`  
- **Test:** `qa-unit-checks` search generation asserts  
- **Status:** **FIXED** / **CLOSED**  

### QA-06-002 — Dead Unsplash seed URLs cause Next.js Image upstream 404
- **Area:** Customer Web / seed data · **Severity:** MEDIUM · **Priority:** P2  
- **Evidence:** Terminal `upstream image response failed … 404` for removed Unsplash photo IDs in `product_images` / banners / campaigns  
- **Fix:** `scripts/qa06_repair_product_images.php` + `SafeImage` onError placeholder  
- **Status:** **FIXED** / **CLOSED** (local DB repaired; re-run script after reseed)  

### QA-06-003 — Product cards/gallery lacked image error fallback
- **Area:** Customer Web · **Severity:** LOW · **Priority:** P3  
- **Fix:** `SafeImage` component on ProductCard + ProductGallery  
- **Status:** **FIXED** / **CLOSED**  

### QA-MSG-001 — Offers pages show “Coming soon”
- **Severity:** MEDIUM/LOW · Marketing surface incomplete  
- **QA-06 finding:** “Coming soon” on `/offers` is the **upcoming campaigns** section when API returns `upcoming_campaigns` — not a blank page. Live featured/sale/coupons render when present.  
- **Status:** **CLARIFIED** / intentional (not a Customer Web defect)  

### QA-PERF-001 — Admin inventory list PHP-paginated full table
- **Area:** Inventory · API · **Severity:** HIGH · **Priority:** P1  
- **Fix (QA-12):** SQL `paginate` + sellable filters in `InventoryService::list`  
- **Test:** `Qa12PerformanceTest::test_inventory_list_paginates_in_sql_not_full_table_php`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-002 — Cart present N+1 sellableQty
- **Area:** Cart · API · **Severity:** HIGH  
- **Fix (QA-12):** `sellableQtyMap` batch in `CartService::present`  
- **Test:** `test_sellable_qty_map_batches_inventory_lookups`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-003 — Order list canReorder exists() N+1
- **Area:** Orders · API · **Severity:** HIGH  
- **Fix (QA-12):** `withCount('items')` + `canReorder` uses count; items limit 1 for preview  
- **Test:** `test_can_reorder_uses_items_count_without_extra_exists_query`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-004 — Admin coupons unbounded + redemption COUNT N+1
- **Area:** Admin · Coupons · **Severity:** HIGH  
- **Fix (QA-12):** paginate + `withCount('redemptions')`  
- **Test:** `test_admin_coupons_are_paginated_with_meta`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-005 — Dashboard low-stock full inventory hydrate
- **Area:** Admin dashboard · **Severity:** MEDIUM  
- **Fix (QA-12):** SQL count with sellable expression  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-006 — Missing stock_movements / payments indexes
- **Area:** MySQL · **Severity:** HIGH  
- **Fix (QA-12):** migration `2026_08_12_180000_qa12_performance_indexes`  
- **Proposal:** `docs/QA_DATABASE_CHANGE_PROPOSAL.md`  
- **Status:** **FIXED** / **CLOSED** (local migrate Ran)

### QA-PERF-007 — Expired reservation cron skipped empty-item orders
- **Area:** Cron · Inventory · **Severity:** HIGH  
- **Fix (QA-12):** transition empty PENDING_PAYMENT → PAYMENT_FAILED; `--limit`; `withoutOverlapping(55)`  
- **Test:** `test_expired_reservation_command_transitions_empty_item_orders`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-008 — Customer Mobile token refresh storm
- **Area:** Mobile · Auth · **Severity:** HIGH  
- **Fix (QA-12):** `TokenRefreshCoordinator` single-flight  
- **Test:** `token_refresh_coordinator_test.dart`  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-009 — Admin Web live-search request storms
- **Area:** Admin Web · **Severity:** MEDIUM  
- **Fix (QA-12):** `useDebouncedValue` on returns/notifications/reviews/loyalty/subscriptions/picking/shipments/reconciliation  
- **Status:** **FIXED** / **CLOSED**

### QA-PERF-010 — Analytics inventory report loads all rows
- **Area:** Admin analytics · **Severity:** MEDIUM · **Priority:** P2  
- **Evidence:** `AnalyticsService` `InventoryItem::…->get()` then PHP sort/take(100)  
- **QA-13 assessment:** Live local **376ms** with **56** sku_locations; rows capped ≤100; correctness OK  
- **Decision:** **Accepted at current scale** — no code change; remain OPEN for production growth  
- **Test:** `Qa13RegressionTest::test_analytics_inventory_rows_are_capped_at_100`  
- **Status:** **OPEN** (formally assessed)

### QA-PERF-011 — Checkout preview client request races
- **Area:** Customer Web / Mobile · **Severity:** MEDIUM · **Priority:** P1  
- **Fix (QA-13):** Generation guards — Web `checkout-preview.ts` + checkout page; Flutter `CheckoutPreviewRules` + checkout screen  
- **Test:** Web `qa-unit-checks`; Flutter `checkout_preview_rules_test`  
- **Status:** **FIXED** / **CLOSED**

### QA-13-001 — Phase5 campaigns analytics assertion stale
- **Area:** Tests · Analytics · **Severity:** LOW  
- **Evidence:** Phase5 expected `supported=false`; Phase17+ returns `supported=true` when `orders.campaign_id` exists  
- **Fix:** Update Phase5 + Qa13 structure assert  
- **Status:** **FIXED** / **CLOSED**

---

## LOW

### QA-DOC-001 — Phase reports mark COMPLETE while runtime gaps remain
- **Severity:** LOW · Documentation risk  
- **Status:** OPEN  

### QA-UX-001 — Demo credential hints in development UIs
- **Severity:** LOW · Gated by `NODE_ENV===development`  
- **Status:** OPEN (acceptable for local)  

### QA-TYPE-001 — Web ShippingMethod type omits ETA fields
- **Severity:** LOW · Display gap  
- **Status:** OPEN  

### QA-OPS-001 — Production backups/monitoring not verified on host
- **Severity:** LOW for code; CRITICAL for launch ops (see PRODUCTION_ISSUE_BACKLOG)  
- **QA-17:** Local isolated mysqldump→restore **PASS**; production host drill still **UNVERIFIED**; monitoring/alerting still **UNVERIFIED**  
- **Status:** OPEN (partial local evidence only)  

---

## Not a bug (false leads)

| Claim | Finding |
|-------|---------|
| Login body uses wrong field (`username`) | **False** — all clients use `email`/`password` |
| Clients expect `data.token` | **False** — all use `access_token` |
| Place order hits wrong URL `/checkout` | **False** — both use `POST /orders` |
| Guest checkout | **N/A** — auth required by design |

---

## Counts (QA-00)

| Severity | Count |
|----------|-------|
| CRITICAL | 2 |
| HIGH | 6 |
| MEDIUM | 8 |
| LOW | 4 |
| False leads documented | 4 |
