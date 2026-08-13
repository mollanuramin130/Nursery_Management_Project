# QA-29 DEVICE REPORT

**Date:** 2026-08-13  
**Device:** vivo 1951 (`2d3714f`) · Android 11 (API 30) · USB authorized  
**Flutter:** recognizes device · install/launch PASS after rebuild  

---

## Network / API configuration

| Item | Value |
|------|--------|
| Mac LAN IP (session) | `192.168.1.7` |
| Device Wi‑Fi IP | `192.168.1.5` |
| Direct LAN to Mac `:8000` | **FAIL** (host unreachable / AP isolation across bands) |
| Working path | **`adb reverse tcp:8000 tcp:8000`** + app `API_BASE_URL=http://127.0.0.1:8000/api/v1` |
| Secrets | Not exposed; Razorpay remains TEST-only in API `.env` |

`127.0.0.1` is valid **only** with USB `adb reverse` for this session. Pure Wi‑Fi LAN to Mac was not usable.

---

## Pre-flight

| Check | Result |
|-------|--------|
| `adb devices` authorized | PASS |
| Flutter device list | PASS (`vivo 1951`) |
| Install Customer APK | PASS |
| Install Admin APK | PASS |
| Launch / no crash | PASS (after cleartext fix) |
| Catalog images load | PASS |
| Auth (customer + admin) | PASS |

---

## Blocking defect found & fixed (local TEST)

**QA-29-001 — Debug cleartext blocked by `network_security_config`**  
Main config set `cleartextTrafficPermitted="false"` while debug only set `usesCleartextTraffic=true`. Android NSC wins → Flutter Dio could not reach `http://…` API even when `curl` on device worked via reverse.  

**Fix:** debug/profile `network_security_config.xml` overlays with cleartext permitted (Customer + Admin Mobile). Release remains HTTPS-only.

---

## COD order (device-placed)

| Field | Value |
|-------|--------|
| GreenLeaf Order ID | **9063** |
| Order number | **ORD-20260813-00007** |
| Status (final) | **DELIVERED** |
| Payment method | **cod** |
| Payment status | **cod** |
| Amount | **₹199** |
| Item | Tulsi (Holy Basil) × 1 |
| Customer | asha@example.com |

Placed on Customer Mobile → visible Admin Mobile dashboard → status transitions on Admin Mobile → Customer order detail timeline updated → in-app notifications for each step.

---

## Admin Mobile lifecycle (device)

CONFIRMED → PROCESSING → PACKED → SHIPPED → OUT_FOR_DELIVERY → DELIVERED  

Invalid buttons gated (QA-28 transitions). Customer timeline labels: Order confirmed / Preparing / Packed / Order shipped / Out for delivery / Delivered.

---

## Screenshots (local evidence paths)

Under `/tmp/qa29_*.png` on the operator Mac (not committed): home, product, cart, checkout, COD result, admin dashboard/order, customer delivered, notification center, deep link.
