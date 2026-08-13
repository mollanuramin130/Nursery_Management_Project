# QA-29 REPORT — Real Device UI / Cross-Client (TEST-only)

**Date:** 2026-08-13  
**Status:** **COMPLETE** (device QA with honest UNVERIFIED payment modes)  
**GREEN / LIVE / LIVE FCM:** **NO / OUT OF SCOPE / OUT OF SCOPE**

---

## Summary

Physical **vivo 1951** used for Customer + Admin Mobile interactive smoke.  
LAN Wi‑Fi to Mac API failed; USB **`adb reverse`** + `http://127.0.0.1:8000/api/v1` used (documented).  

Critical cleartext NSC bug fixed (**QA-29-001**).  
COD order **9063 / ORD-20260813-00007** placed on device and driven to **DELIVERED** on Admin Mobile with Customer timeline + in-app notifications verified.

Razorpay **TEST** financial charge on device was **not** re-executed; QA-26 remains baseline. UPI method is selectable on Customer Mobile checkout.

---

## Evidence anchors

- Device report: `docs/QA-29-DEVICE-REPORT.md`
- Bugs: `docs/QA-29-BUG-REGISTER.md`
- Matrix: `docs/QA-29-TEST-MATRIX.md`
- COD: id **9063**, ₹199, cod/cod, final DELIVERED
- Regression: **244 tests / 1143 assertions** (unchanged vs QA-28)

---

## Fixes

1. Debug/profile Android cleartext NSC overlays (customer + admin)  
2. Admin Mobile reload order detail after status update (payment line)

---

## Explicit non-claims

- No LIVE Razorpay  
- No LIVE FCM push delivery  
- No GREEN production  
- No fabrication of unpaid device payment modes  

---

## QA-30 recommendation

1. Device Razorpay TEST path(s) on Customer Mobile (Intent first; Checkout/QR if product supports)  
2. Close QA-29-003 wishlist failure  
3. Optional Web UI parity click-through for order **9063**  
4. Keep LIVE blocked until QA-27 production gates  
