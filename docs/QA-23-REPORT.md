# QA-23 REPORT — Firebase FCM LIVE Push + Real Device Verification

**Date:** 2026-08-12  
**Status:** **PARTIAL / BLOCKED**  
**GREEN:** **NO**

**Note:** Razorpay TEST-config evidence remains in `docs/QA-23-*-RAZORPAY.md` and `docs/RAZORPAY-TEST-SETUP.md`. This report is the **FCM LIVE** gate requested after QA-22 notifications.

---

## 1. Status

**PARTIAL / BLOCKED for real FCM delivery**

QA-22 notification architecture was **reused** (no redesign of NotificationService / OrderNotificationDispatcher / inbox).

| Credential / config | State |
|---------------------|-------|
| `FIREBASE_CREDENTIALS` | **EMPTY** |
| `FCM_SERVER_KEY` | **EMPTY** |
| Customer `google-services.json` | **MISSING** |
| Admin `google-services.json` | **MISSING** |
| Flutter `firebase_messaging` | **not added** (would break builds without JSON) |

Therefore: **REAL FCM = BLOCKED**. Automated/stub ≠ LIVE.

---

## 2. Bugs fixed

- `FcmPushGateway` now supports **Firebase HTTP v1** via service-account JSON (`FIREBASE_CREDENTIALS`), with legacy `FCM_SERVER_KEY` fallback and non-prod `local_stub`.

---

## 3. Bugs still open / blockers

| Item | Status |
|------|--------|
| Firebase project + client JSON | **BLOCKED** (operator) |
| Server FCM credentials | **EMPTY** |
| Real-device FG/BG/terminated push | **BLOCKED / UNVERIFIED** |
| Queue worker on this host for LIVE push | **UNVERIFIED** (config `database`; worker must be running) |
| QA-SEC-001 | **OPEN** |
| Razorpay LIVE | **BLOCKED** (separate) |

---

## 4–16. Results summary

| Area | Result |
|------|--------|
| Architecture reuse | PASS |
| Token APIs | PASS (existing + Qa22) |
| Gateway HTTP v1 / legacy / stub modes | PASS (`Qa23FcmLiveReadinessTest`) |
| Invalid token deactivation | PASS (automated Http::fake) |
| Payload no secrets | PASS |
| Customer→Admin real push | **BLOCKED** |
| Admin→Customer real push | **BLOCKED** |
| Foreground / background / terminated | **BLOCKED** |
| Multi-device / logout | Code PASS · LIVE **BLOCKED** |
| Web push | **DEFERRED / OUT OF SCOPE** |
| Unit / integration (automated) | PASS |
| Real device matrix | **BLOCKED** |

---

## 17–20. Tests / build / regression

| Suite | Result |
|-------|--------|
| `Qa23FcmLiveReadinessTest` | **6 PASS** |
| Prior Qa22 notifications | remains valid |
| Full regression | recorded in closeout |

Debug APK / physical LIVE push: **not claimed** (no Firebase client config).

---

## 21. Real device matrix

All items **BLOCKED** until Firebase credentials + `google-services.json` + packages + worker.

---

## 22. Payment vs FCM

Razorpay LIVE remains **BLOCKED**. Do not treat FCM work as payment LIVE.

---

## 23. Docs

- `docs/QA-23-FCM-SETUP.md`
- This report + closeout + test matrix
- Razorpay preserved: `docs/QA-23-*-RAZORPAY.md`

---

## 24–29. Changes / next

**Database:** NONE  
**API:** NONE (additive gateway behavior only)  

**Files:** `FcmPushGateway.php`, `Qa23FcmLiveReadinessTest.php`, `.env.example`, gitignores, `google-services.json.example` ×2, FCM docs  

**Next:** Operator creates Firebase project → place SA JSON + client JSON → add Flutter Firebase packages → run queue worker → execute device matrix → re-open QA-23 for LIVE evidence.
