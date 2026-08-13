# QA-17 TEST MATRIX

**Date:** 2026-08-12

Legend: **PASS** · **FAIL** · **UNVERIFIED** · **BLOCKED** · **N/A**

---

## A. Gates

| Gate | Result | Evidence |
|------|--------|----------|
| Razorpay live order/pay/verify/webhook | **BLOCKED** | Keys EMPTY |
| Production `--strict` on prod host | **UNVERIFIED** | Host is local; local `--strict` correctly exits 1 |
| Local backup/restore drill | **PASS** | `QA-17-BACKUP-RESTORE-DRILL.md` |
| Prod backup/restore | **UNVERIFIED** | |
| Deploy on prod | **UNVERIFIED** | Checklist exists |
| Rollback drill live | **UNVERIFIED** | Plan exists |
| Customer Mobile interactive UI | **UNVERIFIED** | Device attached; no interactive golden this phase |
| Admin Mobile interactive UI | **UNVERIFIED** | |
| Customer Web Playwright golden | **UNVERIFIED** | HTTP 200 smoke only |
| Admin Web Playwright golden | **UNVERIFIED** | HTTP 200 smoke only |
| QA-SEC-001 HttpOnly/BFF | **OPEN** | Not implemented |
| Workers on prod | **UNVERIFIED** | Schedule registered locally |
| Load 10/25/50 | **UNVERIFIED** | |
| Large EXPLAIN | **UNVERIFIED** | |

---

## B. Automated suites (executed)

| Suite | Result |
|-------|--------|
| PHPUnit Qa02–Qa16 + Phase filter | **PASS** 168 tests / 768 assertions |
| Qa16ProductionHardeningTest (incl. strict env reject) | **PASS** 6 tests |
| Customer Web `npm run test:unit` | **PASS** |
| Admin Web `npm run test:unit` | **PASS** |
| Customer Flutter `flutter test` | **PASS** |
| Admin Flutter `flutter test` | **PASS** |
| composer audit | **PASS** (no advisories) |
| API health local + LAN | **PASS** 200 |
| Web `:3000` / Admin `:3001` HTTP | **PASS** 200 |

---

## C. Payment matrix

| Case | Result |
|------|--------|
| COD regression (prior + suite) | **PASS** (automated / prior live) |
| Razorpay create/pay/verify | **BLOCKED** |
| Webhook / idempotency / invalid signature | **BLOCKED** (no live keys; prior unit coverage retained where present) |
| Production stub rejection | **PASS** (regression retained) |

---

## D. Device

| Check | Result |
|-------|--------|
| `adb devices` shows `2d3714f` | **PASS** |
| LAN health `192.168.1.3:8000` | **PASS** |
| Interactive Customer/Admin journeys | **UNVERIFIED** |

---

## E. Changed-flow tests (QA-17)

| Flow | Happy | Negative | Result |
|------|-------|----------|--------|
| `--strict` on non-production | N/A | Must fail | **PASS** (`test_strict_gate_rejects_non_production_env`) |
| Non-strict local readiness | Succeeds | — | **PASS** |
