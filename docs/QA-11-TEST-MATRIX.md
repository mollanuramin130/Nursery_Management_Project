# QA-11 Test Matrix — Security

**Date:** 2026-08-12 · **Phase:** QA-11

Legend: **PASS** · **FAIL** · **PARTIAL** · **UNVERIFIED** · **BLOCKED** · **N/A**

---

## A. Architecture / SEC-001

| Item | Result |
|------|--------|
| Security design document | PASS |
| QA-SEC-001 Option C OPEN | PASS (decision) |
| Cookie migration implemented | N/A (deferred) |

---

## B. `Qa11SecurityTest` (PHPUnit)

| Test | Result |
|------|--------|
| Customer cannot access admin API | PASS |
| Permission denials and allows | PASS |
| Super-admin bypass | PASS |
| Order/address/return IDOR | PASS |
| Profile mass-assignment | PASS |
| users.manage cannot assign super_admin | PASS |
| Super-admin can assign super_admin | PASS |
| Login failure + me no password | PASS |
| Logout invalidates refresh | PASS |
| Forgot-password non-enumeration | PASS |
| Security headers on API | PASS |
| **Suite** | **PASS 11/11** |

---

## C. Regression

| Filter | Result |
|--------|--------|
| `Qa11\|Qa10\|…\|Qa02` | **PASS** 60 tests / 279 assertions |

---

## D. Web / Mobile unit

| Suite | Result |
|-------|--------|
| Customer Web `npm run test:unit` | PASS |
| Admin Web `npm run test:unit` | PASS |
| Customer Mobile widget + auth_messages | PASS |
| Admin Mobile `session_refresh_test` | PASS |

---

## E. Audits

| Item | Result |
|------|--------|
| composer audit | PASS (0 advisories) |
| npm audit (web + admin) | PASS (0 vulns) |
| .env gitignore | PASS |
| Live secret literals | PASS (placeholders only) |

---

## F. Device / live

| Item | Result |
|------|--------|
| API health LAN | PASS |
| Interactive device auth | UNVERIFIED |
| Razorpay live | UNVERIFIED |

---

## G. Database

| Item | Result |
|------|--------|
| Schema change | None |
