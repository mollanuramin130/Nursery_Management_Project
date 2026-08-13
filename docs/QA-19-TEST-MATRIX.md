# QA-19 TEST MATRIX

**Date:** 2026-08-12

| Area | Classification | Evidence |
|------|----------------|----------|
| Razorpay LIVE UPI | **BLOCKED** | Keys empty |
| Razorpay LIVE webhook | **BLOCKED** | Keys empty |
| Automated payment simulation (Qa18) | **PASS** | 11 tests |
| Qa19 readiness gaps | **PASS** | 6 tests |
| Test key blocked in production profile | **PASS** | Checker + tests |
| COD | **PASS** | Regression |
| Inventory single commit on pay | **PASS** | Qa19 |
| Duplicate initiate / already paid / cancel / wrong order id | **PASS** | Qa19 |
| Customer Web real payment UI | **BLOCKED** | No keys |
| Customer Mobile UPI device | **BLOCKED** | No keys; device attached |
| Admin interactive payment UI | **UNVERIFIED** | |
| Prod `--strict` | **UNVERIFIED** | Local exit 1 |
| Backup/restore prod | **UNVERIFIED** | |
| Rollback live | **UNVERIFIED** | |
| QA-SEC-001 | **OPEN** | |
| Full regression | **PASS** | 186 / 918 |
| Web unit | **PASS** | |
| Flutter Customer/Admin | **PASS** | |
| composer audit | **PASS** | |

Label for stub/signed tests: **AUTOMATED PROVIDER SIMULATION** — not LIVE PAYMENT PASS.
