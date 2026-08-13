# QA-16 TEST MATRIX

**Date:** 2026-08-12 · Status: PARTIAL / YELLOW

| ID | Case | Result |
|----|------|--------|
| T16-01 | Baseline audit written | PASS |
| T16-02 | ProductionReadinessChecker unit/feature cases | PASS (5) |
| T16-03 | `nursery:production-readiness` on local | PASS (no false P0 on local) |
| T16-04 | Production profile fails debug/razorpay/cors/https | PASS |
| T16-05 | Healthy production profile ready | PASS |
| T16-06 | Full regression Qa02–16 + Phase | PASS 167/767 |
| T16-07 | Web/Admin unit | PASS |
| T16-08 | Device attached | PASS |
| T16-09 | Razorpay live matrix | BLOCKED / UNVERIFIED (keys empty) |
| T16-10 | Prod host APP_DEBUG=false | UNVERIFIED |
| T16-11 | Backup/restore drill | UNVERIFIED |
| T16-12 | Interactive mobile UI | UNVERIFIED |
| T16-13 | Load / 4-UI / EXPLAIN | UNVERIFIED |
| T16-14 | QA-SEC-001 | OPEN |

## GREEN gate checklist (honest)

| Gate | Met? |
|------|------|
| Razorpay live/verify/webhook | NO |
| APP_DEBUG=false verified on prod | NO |
| HTTPS / prod CORS verified on prod | NO |
| Backup + restore | NO |
| Device UI goldens | NO |
| Load / 4-UI | NO |
| Full regression | YES |
| Unit tests for QA-16 changes | YES |
| No unresolved P0 **code** defects for COD | YES (within scope) |
| No unresolved P0 **release** blockers for GREEN | NO |
