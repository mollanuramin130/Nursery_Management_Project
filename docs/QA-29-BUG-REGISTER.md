# QA-29 BUG REGISTER

**Date:** 2026-08-13 · TEST-only device QA  

---

## FIXED

### QA-29-001 — Physical device cannot call cleartext local API (debug)
- **Severity:** CRITICAL (blocks all device UI against local API)
- **Component:** Customer Mobile + Admin Mobile (Android)
- **Steps:** Install debug APK with `http://127.0.0.1` or LAN URL → Home “Unable to load home”
- **Expected:** Debug/profile allow cleartext to local API
- **Actual:** NSC `cleartextTrafficPermitted=false` blocked Dio
- **Fix:** `android/app/src/{debug,profile}/res/xml/network_security_config.xml` cleartext true (both apps)
- **Regression:** Device home/catalog load PASS after reinstall; release config unchanged
- **Status:** **FIXED**

### QA-29-002 — Admin Mobile payment line blank after status update
- **Severity:** MEDIUM
- **Component:** Admin Mobile
- **Steps:** Open order (shows `Payment: COD · —`) → Update status → Payment becomes `— · —`
- **Expected:** Payment method/status remain visible
- **Actual:** Status POST body omits payment fields; UI replaced detail wholesale
- **Fix:** `OrdersProvider.updateStatus` reloads full detail via `loadDetail`
- **Regression:** Admin unit tests PASS; rebuild installed (re-verify on next CONFIRMED order recommended)
- **Status:** **FIXED** (code); device re-verify of payment line after transition **PARTIAL**

---

## OPEN / NEW (not fixed this phase)

### QA-29-003 — Wishlist add failed on device (`Unable to update wishlist`)
- **Severity:** MEDIUM
- **Component:** Customer Mobile
- **Evidence:** Product detail snackbar during QA-29 smoke
- **Status:** **OPEN** (not root-caused; may be transient/API)

### QA-29-004 — Product image mismatch (Tulsi titled, succulent image)
- **Severity:** LOW
- **Component:** Catalog/seed assets
- **Status:** **OPEN** (data/asset; not architecture)

### QA-SEC-001 — Web JWT in localStorage
- **Status:** **OPEN** (unchanged)

### QA-ADM-002 — Admin Mobile intentional ops subset
- **Status:** **INTENTIONAL** (unchanged)

---

## NOT bugs / accepted

| Item | Note |
|------|------|
| Wi‑Fi LAN host unreachable | Environment; USB `adb reverse` used |
| Unpaid order blocks new place | Business rule; cancelled `9059` then COD succeeded |
| Mobile UPI modes vs Web Checkout/QR picker | ACCEPTED DIFFERENCE (TEST) |
| LIVE FCM | OUT OF SCOPE / BLOCKED |
