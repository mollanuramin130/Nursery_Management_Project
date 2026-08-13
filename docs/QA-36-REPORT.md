# QA-36 REPORT — Stability + Cross-Platform Feature Parity Audit

**Date:** 2026-08-13  
**Verdict:** **COMPLETE**  
**LIVE / FCM / GREEN:** **OUT OF SCOPE / NO**  
**Device:** CONNECTED — `2d3714f` / vivo 1951  

Companion: `docs/QA-36-FEATURE-PARITY-MATRIX.md`

---

## Objectives covered

1. Canonical feature inventory (API + four clients)  
2. Customer Web ↔ Mobile feature/action/data/UI matrix  
3. Screen-by-screen comparison + L/E/E/S states  
4. Customer Mobile bottom navigation architecture audit + fix  
5. Wishlist removal flicker audit + fix  
6. Admin Web ↔ Mobile inventory (QA-ADM-002 preserved)  
7. Payment / order lifecycle parity (TEST only; no fabricated settlement)  
8. Regression vs QA-35 baseline  

---

## Phase 1 — Baseline

| Suite | Result |
|-------|--------|
| PHPUnit vs QA-35 | **243 / 1190** (session baseline) |
| Stability-pass final (pre-parity nav) | **253 / 1209** |
| Web/Admin unit + Flutter | **PASS** |

---

## User-reported issues

### Bottom navigation missing

**Confirmed defect → QA-36-007 FIXED.**  
Root cause: browse/account routes lived outside `StatefulShellRoute`. Fixed at shell/route config (no duplicated nav widgets). Intentional full-screen: auth, PDP, checkout, address forms.

### Wishlist remove flicker

**Confirmed defect → QA-36-008 FIXED.**  
Root cause: post-remove `_reload()` forced full skeleton via `FutureBuilder`. Fixed with optimistic list + failure restore (no artificial delay).

---

## Feature parity verdict

| Area | Verdict |
|------|---------|
| Customer Web ↔ Mobile commerce/account | **PASS** (Dynamic QR path **INTENTIONAL** Mobile gap) |
| Variant PDP picker | **PARTIAL** both clients |
| Admin Web ↔ Mobile | **INTENTIONAL** subset (QA-ADM-002) |
| API authority (price/stock/payment/order) | **PASS** |
| UI terminology | **PASS** (approved customer/admin pending-copy difference) |
| Navigation shell | **PASS** after QA-36-007 |
| Wishlist UX | **PASS** after QA-36-008 |

---

## Defects this phase (all FIXED)

| ID | Summary |
|----|---------|
| QA-36-001…006 | Stability / query-authority / deep-link / wishlist error (prior pass) |
| QA-36-007 | Mobile bottom nav shell inconsistency |
| QA-36-008 | Wishlist removal skeleton flicker |

---

## Final regression

| | Tests | Assertions | Passed | Skipped | Failed |
|--|------:|-----------:|-------:|--------:|-------:|
| QA-35 | 243 | 1190 | 241 | 2 | 0 |
| QA-36 PHPUnit | **253** | **1209** | **251** | **2** | **0** |

| Client suite | Result |
|--------------|--------|
| nursery-web unit | **PASS** |
| nursery-admin unit | **PASS** (prior) |
| nursery_app flutter test | **PASS** (38; + shell nav tests) |
| nursery_admin_mobile flutter test | **PASS** |
| BFF smoke | **PASS** (prior / reconfirmable) |

No tests weakened.

---

## Explicit non-claims

- No GREEN · No LIVE Razorpay · No LIVE FCM  
- No fabricated UPI-app / Dynamic QR settlement  
- No production deploy / `--strict`  

See `docs/QA-36-CLOSEOUT-REPORT.md`.
