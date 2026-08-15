# QA-38 REPORT — UI Visual Consistency & Accessibility Polish

**Date:** 2026-08-15  
**Scope:** Customer Web · Customer Mobile · Admin Web · Admin Mobile  
**Prior QA-38 work:** Offline sync hardening (2026-08-13) — preserved; see `QA-38-OFFLINE-SYNC-REPORT.md`  
**GREEN:** **NO** · **LIVE:** **OUT OF SCOPE**

---

## Objective

Visual quality + UX + accessibility hardening only. No API/schema/payment architecture changes. No full redesign.

---

## Audit method

1. Read prior QA-33…38 docs + `RUN.txt`  
2. Source-of-truth inspection of theme tokens and shared UI kits  
3. Systematic contrast / button / wishlist / toast / bottom-nav review  
4. Targeted fixes in shared components first  

---

## Colour system

| Client | Tokens |
|--------|--------|
| Customer Web | `globals.css` — added wishlist + sticky-cta + banner z + darker muted/rating |
| Customer Mobile | `tokens.dart` — `wishlistActive` / `wishlistInactive` |
| Admin Web | existing `--admin-*` (toast contrast hardened) |
| Admin Mobile | `OpsColors` + snackBar / disabled button theme |

---

## Findings fixed this pass

| ID | Severity | Platform | Problem | Fix |
|----|----------|----------|---------|-----|
| QA-38-007 | CRITICAL | Customer Web | Toasts overlapped sticky Buy/Cart / Place Order | `--sticky-cta-h` + toast offset; set on PDP/checkout |
| QA-38-008 | HIGH | Customer Web | Network banner covered sticky header | Banner `relative` + lower z; darker warning text |
| QA-38-009 | HIGH | Customer Web | Wishlist heart ink-only when saved | Red active / muted inactive tokens + ring chip |
| QA-38-010 | HIGH | Customer Web | Outline/ghost disabled looked enabled | Shared `disabled:opacity-50` on all Button variants |
| QA-38-011 | HIGH | Customer Mobile | Inactive heart brand green | Neutral `wishlistInactive` |
| QA-38-012 | HIGH | Customer Mobile | Snackbar cleared only 16px — covered bottom nav | Margin = safe inset + 72 |
| QA-38-013 | HIGH | Admin Web | Soft tonal toasts low contrast | Solid success/error/warning/info fills + raised bottom |
| QA-38-014 | HIGH | Admin Mobile | Snackbars flush on NavigationBar | `snackBarTheme` insetPadding 88 |
| QA-38-015 | MEDIUM | Web + Mobile | Sale badge used warning amber | `sale` tone (red) |
| QA-38-016 | MEDIUM | Customer Mobile | Busy wishlist greys filled red | `busy` keeps colour, soft alpha |
| QA-38-017 | MEDIUM | Customer Web | Rating star used ink | `--color-rating` gold |
| QA-38-018 | MEDIUM | Admin Mobile | Login loading text-only disabled | White spinner on disabled fill |
| QA-38-019 | LOW | Customer Mobile | Debug FAB over nav | Raise FAB `bottom: 160` |
| QA-38-020 | LOW | Customer Web | Wishlist count green-on-green | Red count chip |

---

## Intentionally unchanged

| Item | Reason |
|------|--------|
| Cart/order-detail dual bottom chrome (Mobile) | Needs IA decision (hide shell nav vs compact sticky); not a silent polish |
| Offline banner absent on full-screen PDP/checkout | Structural mount; defer without breaking QA-37 |
| Web bottom nav includes Saved vs Mobile Orders | Platform IA difference — documented, not forced equal |
| Dark mode | Apps are light-first; no dark theme to break |
| Pixel-identical Web↔Mobile layouts | Brand semantics aligned; layouts remain platform-native |

---

## Regression

| Suite | Result |
|-------|--------|
| PHPUnit | **253 tests / 1209 assertions** (251 pass, 2 skip) |
| Customer Flutter | **58** All tests passed |
| Admin Flutter | **27** All tests passed |
| Customer Web `qa-unit-checks` | **PASS** |

Device (Vivo): **UNVERIFIED** this pass (no attached device in session).

---

## Non-claims

No GREEN · No LIVE · No production readiness · Offline payment/order still blocked correctly.
