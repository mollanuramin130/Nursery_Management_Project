# QA-30 Bug Register

**Date:** 2026-08-13 · **Scope:** TEST-only device payment + remaining QA-29 defects  
**GREEN:** NO · **LIVE Razorpay / LIVE FCM / production:** OUT OF SCOPE

---

## FIXED this phase

### QA-29-003 — Wishlist add returned opaque 500 after soft-delete
- **Classification:** BUG (API)
- **Root cause:** Soft-deleted wishlist rows still blocked unique `(user_id, product_id)` inserts; clients only treated **409** as “already wishlisted”.
- **Fix:** `WishlistService` uses `withTrashed()`, restores trashed rows, race-safe unique handling.
- **Tests:** `Qa30WishlistAndImagesTest`
- **Device:** Add / remove / re-add Tulsi **PASS**
- **Status:** **FIXED**

### QA-29-004 — Tulsi image matched Aloe asset
- **Classification:** BUG (catalog data / prior QA-06 remap)
- **Root cause:** Dead Tulsi Unsplash URL remapped onto Aloe’s succulent asset.
- **Fix:** Distinct Tulsi URL in DB + `qa06_repair_product_images.php` + sample SQL.
- **Status:** **FIXED** (data + repair script)

### QA-30-001 — Successful capture left order PAYMENT_FAILED
- **Classification:** BUG (PAYMENT / BACKEND) · **Severity:** HIGH
- **Root cause:** `payment.failed` webhook transitioned order to `PAYMENT_FAILED` during multi-method Checkout; later `payment.captured` marked payment success but `finalizeSuccess` only confirmed from `PENDING_PAYMENT`. State machine also blocked `PAYMENT_FAILED` → `CONFIRMED`.
- **Fix:** Confirm from `PAYMENT_FAILED` (re-reserve + commit); allow state transition; ignore stale failures when sibling success / CONFIRMED; clear failure fields on success.
- **Tests:** `Qa30PaymentFailedThenCapturedRecoveryTest`
- **Device evidence:** Order **9066** / `pay_TPDwnubVyTYAiG` → **CONFIRMED** after fix
- **Status:** **FIXED**

### QA-30-002 — UPI Intent launch success blocked Checkout fallback
- **Classification:** BUG (MOBILE) · **Severity:** MEDIUM
- **Root cause:** Checkout always polled after `launchUrl` returned true; if chooser opened but user did not pay, Checkout never opened.
- **Fix:** Poll only when intent launch succeeds; otherwise Razorpay Checkout fallback (`shouldPollAfterUpiIntentLaunch`).
- **Tests:** `upi_payment_test.dart`
- **Status:** **FIXED** (device still completed via Order Detail Pay → Checkout)

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-SEC-001 | Web JWT in localStorage | **OPEN** |
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** (intentional) |

---

## Not bugs / accepted

| Item | Notes |
|------|--------|
| Dynamic QR on Customer Mobile | Not exposed — **ACCEPTED DIFFERENCE** vs Web |
| International TEST Visa on India merchant | Razorpay rejects — use Netbanking TEST Success |
| Pure mobile-data without `adb reverse` | LAN AP isolation — **UNVERIFIED**, not product defect |
| adb IME password length corruption | Test harness issue, not app auth bug |
