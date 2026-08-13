# QA-31 — UPI / Dynamic QR Evidence (TEST only)

**Date:** 2026-08-13  
**Device:** vivo 1951 / `2d3714f`  
**Mode:** Razorpay `rzp_test_*` only · **LIVE:** OUT OF SCOPE  

No secrets are recorded below.

---

## A. Web Dynamic QR (generation)

| Field | Value |
|-------|--------|
| GreenLeaf Order ID | **9067** |
| Order number | **ORD-20260813-00011** |
| Amount | **₹49** (4900 paise in payload) |
| Payment ID | **5531** |
| Razorpay Order ID | **order_TPEFNfrp4b71ni** |
| Mode | **dynamic_qr** |
| `qr_data` present | **YES** (`upi://pay?pa=…`) |
| Provider `qr_image_url` | **NO** (local encode fallback available) |
| `expires_at` | set (~15 min) |
| Secret in payload | **NO** |
| Key prefix | `rzp_test` |

**Settlement via scanning QR:** **NOT COMPLETED** — no verified TEST QR capture in this phase.  
**Classification:** Dynamic QR generation **PASS** · true QR payment **UNVERIFIED**.

Order **9067** was cancelled after evidence capture (left unpaid).

---

## B. Mobile UPI Intent (true UPI-app path)

| Field | Value |
|-------|--------|
| GreenLeaf Order ID | **9068** |
| Order number | **ORD-20260813-00012** |
| Amount | **₹49** |
| Payment ID | **5532** (pending) |
| Razorpay Order ID | **order_TPEJAzsUwOAe1F** |
| Intent launch | **PASS** — Android “Open with” PhonePe / PNB ONE / GPay |
| GPay handoff | **PASS** — shows “Paying GreenLeaf Nursery API”, **₹49**, **ORD-20260813-00012** |
| Settlement | **BLOCKED** — GPay: “Cannot pay with this QR code / No payment account registered on Google Pay” |

**TRUE UPI-APP SETTLEMENT:** **BLOCKED** (device/account limitation, not architecture change).

Order **9068** cancelled after evidence (unpaid).

---

## C. Authoritative paid baseline (unchanged from QA-30)

Still the last successful TEST charge for cross-client parity:

| Field | Value |
|-------|--------|
| Order | **9066** / `ORD-20260813-00010` |
| Payment | **5530** success |
| Razorpay | `order_TPDlYGn0iAIT1e` / `pay_TPDwnubVyTYAiG` |
| Status | **CONFIRMED** |
| Inventory sales | **1** |

In-app notifications for 9066 (stub/local): `payment_confirmed`, `order_confirmed`, `new_order` (+ earlier `payment_failed` from failed-then-captured race, then recovery).

---

## D. Amount authority (live API)

Client `amount: 1` on pending reuse for order **9067** → **HTTP 409** `Payment amount mismatch` after QA-31-001 fix.
