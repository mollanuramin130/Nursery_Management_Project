# QA-40 REPORT — UI Polish + Render Stability + Data Resilience + Parity

**Date:** 2026-08-15  
**Device:** vivo 1951 (`2d3714f`) · API `http://127.0.0.1:8000/api/v1` via `adb reverse`  
**Baseline:** QA-39 PARTIAL  
**GREEN:** **NO** · **LIVE:** **OUT OF SCOPE**

---

## Executive status

**QA-40 STATUS: PARTIAL**

Cross-client audit completed. Highest-impact defects fixed with regression tests. Full Admin Mobile matrix, COD/Razorpay TEST device smoke, and exhaustive web responsive re-walk remain UNVERIFIED.

---

## Defect inventory → outcomes

| ID | Title | Status |
|----|-------|--------|
| QA-40-001 | Cart snackbar under-clears sticky + tab bar | **FIXED** |
| QA-40-002 | `FadeInUp` rebuild flash (new Future each build) | **FIXED** |
| QA-40-003 | Wishlist IndexedStack stale list after heart toggles | **FIXED** |
| QA-40-004 | Orders filter/refresh full-screen skeleton flash | **FIXED** |
| QA-40-005 | `getOrders` not cache-first / no `onImmediate` | **FIXED** |
| QA-40-006 | Raw `CachedNetworkImage` without resilient fallback | **FIXED** |
| QA-40-007 | AppButton loading uses Material disabled (dark-on-muted Checkout) | **FIXED** (device pixel sample PASS) |
| QA-40-008 | Categories FutureBuilder blanks on refresh | **FIXED** |
| QA-40-009 | Cart→wishlist loud `bootstrap` | **FIXED** (`silent: true`) |
| QA-40-010 | Wishlist heart semantic product name | **FIXED** |
| QA-40-011 | Web primary button hover lightens; disabled opacity wash | **FIXED** |
| QA-40-012 | Admin warning toast + badge warning/success AA | **FIXED** (token darken) |
| QA-40-013 | MiniCart Checkout not shared Button + SafeImage | **FIXED** |
| QA-40-014 | Admin AnalyticsShell blanks UI on refresh | **FIXED** |
| QA-40-015 | Account reviews/subscriptions treat error as empty | **FIXED** |
| QA-40-016 | Wishlist web first-load empty-grid flash | **FIXED** |
| QA-40-017 | Web cart/wishlist SafeImage adoption | **FIXED** |
| QA-40-018 | Admin list-page refresh flicker (non-analytics) | **OPEN** (pattern remains on many admin lists) |
| QA-40-019 | Catalog filter remount skeleton flash | **OPEN** |
| QA-40-020 | Mock JSON mostly null thumbnails | **OPEN** (ResilientImage placeholders mitigate) |
| QA-40-021 | Admin Mobile offline parity | **INTENTIONAL** (ops online-first) |
| QA-40-022 | Wishlist remove device re-observe | **UNVERIFIED** (carry QA-39-009) |
| QA-40-023 | COD / Razorpay TEST on Vivo | **UNVERIFIED** |
| QA-40-024 | Admin Mobile full matrix | **UNVERIFIED** |
| QA-40-025 | Web full responsive re-walk | **UNVERIFIED** |

---

## Root cause — Checkout “disabled looking” button (screenshot)

`AppButton` set `onPressed: null` while `loading == true`, so Material applied **disabled** colors (`borderStrong` + muted ink) even for primary CTAs. Cart sticky Checkout showed **Working…** with dark spinner/text on washed green-grey.

**Fix:** keep `primaryDeep` + white (and danger red + white) for the loading/disabled style override while non-interactive.

---

## Architecture preserved

- Cache → mock → API priority unchanged  
- No payment/signature/webhook/inventory changes  
- Offline banner + mock envelopes intact  
- Shell bottom nav: Home · Shop · Cart · Orders · Account  

---

## Regression

| Suite | Result |
|-------|--------|
| PHPUnit | **253 / 1209** (251 pass, 2 skip) |
| Customer Flutter | **63** (prior 61 + shell sticky + AppButton loading contrast) |
| Admin Flutter | **27** All passed |
| Customer Web `test:unit` | **PASS** |
| Flutter analyze (changed files) | exit 0 (pre-existing Radio deprecation infos) |

---

## Vivo evidence

`docs/qa40/vivo_*.png`

Observed:

- Bottom nav present (Home/Shop/Cart/Orders/Account)  
- Offline/cached banner + “Updating…” soft refresh (cache kept visible)  
- Cart sticky Checkout loading contrast defect reproduced pre-fix  
- Post-fix APK rebuild for contrast re-check: see CLOSEOUT  

---

## Explicit non-claims

No GREEN · No LIVE · No UPI/QR settlement completion · No fabricated payment success  
