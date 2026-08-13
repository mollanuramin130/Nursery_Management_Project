# QA-38 Bug Register

**Date:** 2026-08-13 · **GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED

### QA-38-001 — Cache lacked metadata / stale distinction
- **Severity:** HIGH · **Fix:** `CacheEnvelope` (cachedAt, source, version)  
- **Status:** **FIXED**

### QA-38-002 — Mock JSON could overwrite fresher API cache
- **Severity:** CRITICAL · **Fix:** `CacheEnvelope.mayWrite` blocks mock→API  
- **Status:** **FIXED**

### QA-38-003 — Reads were API-first (blank wait) not cache-first
- **Severity:** HIGH · **Fix:** `peekHome` / catalog peek + soft Updating  
- **Status:** **FIXED**

### QA-38-004 — Reconnect did not soft-refresh screens
- **Severity:** HIGH · **Fix:** `onReconnected` → syncGeneration + `syncAfterReconnect`  
- **Status:** **FIXED**

### QA-38-005 — Wishlist remove brief old-list flash
- **Severity:** MEDIUM · **Cause:** reload/bootstrap re-seed + skeleton  
- **Fix:** list epoch, skip reload while busy, snapshot empty ≠ reseed  
- **Status:** **FIXED**

### QA-38-006 — DEBUG missing reconnect control
- **Severity:** LOW · **Fix:** `NetworkSimulation.reconnect`  
- **Status:** **FIXED**

---

## OPEN / UNVERIFIED / BLOCKED

| Item | Class |
|------|-------|
| Vivo physical Wi‑Fi/API matrix | **UNVERIFIED** |
| Mobile-data w/o adb reverse | **UNVERIFIED** |
| UPI-app settle | **BLOCKED** |
| QA-ADM-002 | **OPEN** intentional |
| Razorpay SDK overlay hang | **PRE-EXISTING** |
