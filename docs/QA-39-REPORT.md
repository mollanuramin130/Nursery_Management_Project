# QA-39 REPORT — Real Device UX, UI Consistency & Interaction Hardening

**Date:** 2026-08-15  
**Scope:** TEST / LOCAL ONLY  
**GREEN:** **NO** · **LIVE:** **OUT OF SCOPE**

---

## Environment (Phase 1) — VERIFIED

| Field | Value |
|-------|-------|
| DEVICE | Vivo physical USB |
| MODEL | vivo 1951 |
| ANDROID VERSION | 11 (API 30) |
| ADB ID | `2d3714f` |
| FLUTTER DEVICE | `vivo 1951 (mobile) • 2d3714f • android-arm64` |
| API BASE URL | `http://127.0.0.1:8000/api/v1` (via adb reverse) |
| NETWORK MODE | USB reverse tunnel to Mac API |
| ADB REVERSE | `tcp:8000 → tcp:8000` |
| API HEALTH | `{"status":"ok","database":"healthy","cache":"healthy"}` |
| Customer Web | `http://127.0.0.1:3000` → 200 |
| Admin Web | `http://127.0.0.1:3001` → 200 |
| STATUS | Ready for device audit |

Evidence screenshots: `docs/qa39/vivo_*.png`

---

## Customer Mobile (Vivo) — PARTIAL PASS

### Verified PASS on device
- Launch + Impeller Vulkan
- Bottom nav labels: **Home · Shop · Cart · Orders · Account**
- Home product grid, Sale red badge, inactive wishlist heart **neutral** (not brand green)
- Shop/Categories grid + active Shop tab highlight
- Cart empty state + Continue shopping CTA
- Account guest gate (Sign in / Create account)
- PDP fullscreen (no shell tabs) + sticky Add / Buy now
- Add to cart success snackbar appears
- Cart with item + Checkout sticky + Cart tab badge
- Offline/saved-data banner observed (`Showing your saved GreenLeaf data`)

### Fixed from device evidence
| ID | Issue | Fix |
|----|-------|-----|
| QA-39-001 | Snackbar action **"View Cart" truncated to "View Ca"** | Short action **Open** + label length guard |
| QA-39-002 | Debug FAB overlapped snackbar | FAB `bottom: 200` |
| QA-39-003 | Dual bottom chrome on order detail | In-body actions; `ShellNavPolicy` |
| QA-39-004 | Wishlist remove flash via post-reload bootstrap | Removed `_reload`→`bootstrap`; silent bootstrap option |
| QA-39-005 | Snackbar clearance ignored fullscreen vs shell | Path-aware clearance via `ShellNavPolicy` |

### Still OPEN / PARTIAL
| ID | Issue | Status |
|----|-------|--------|
| QA-39-006 | Cart sticky Checkout + shell tab bar both visible | **INTENTIONAL** (tabs kept); compact sticky padding |
| QA-39-007 | Debug FAB can still compete with content | **LOW** DEBUG-only |
| QA-39-008 | Sample image mismatches (content) | **PRE-EXISTING** data |
| QA-39-009 | Wishlist remove micro-flicker on device after fix | **UNVERIFIED** (needs re-observe after reinstall of latest clearance fix) |
| QA-39-010 | COD / Razorpay TEST full path on Vivo | **UNVERIFIED** this pass |
| QA-39-011 | Admin Mobile full UX matrix | **PARTIAL** (launch attempted; not fully walked) |

---

## Admin Mobile — PARTIAL

- Package present: `com.greenleaf.nursery_admin_mobile`
- Theme snackbar inset + login spinner from QA-38 retained
- Full Vivo walkthrough **not completed** in this session (customer app occupied device for primary matrix)

---

## Customer / Admin Web — PARTIAL

- Customer Web health/BFF responding (200)
- Admin Web responding (200)
- QA-38 toast/wishlist/button contrast fixes retained
- Full responsive matrix (mobile/tablet/desktop) **not fully re-walked** on device browser this pass → **PARTIAL / UNVERIFIED** for new regressions

---

## Shell navigation rule

`apps/nursery_app/lib/core/shell_nav_policy.dart`

- Shell tabs: Home/Shop/Cart/Orders/Account (+ browse/account subroutes)
- Fullscreen (no tabs): product, checkout, auth, address edit
- Order detail: prefer in-body actions (no second bottom bar)

---

## Regression (this pass)

| Suite | Result |
|-------|--------|
| PHPUnit | **253 / 1209** (251 pass, 2 skip) |
| Customer Web qa-unit-checks | **PASS** |
| Customer Flutter | See closeout (expect ≥58; shell policy tests added) |
| Admin Flutter | Expect **27** (unchanged suite) |

---

## Security / payments

- No LIVE Razorpay · No LIVE payments · BFF/HttpOnly unchanged · Offline cannot fake order/payment

---

## GREEN recommendation

**Do NOT claim GREEN.**  
Next: finish Admin Mobile Vivo matrix + wishlist flicker re-observe + COD/Razorpay TEST smoke on device.
