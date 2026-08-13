# QA-20 REPORT — Live Payment + Production Release Gate

**Date:** 2026-08-12  
**Phase:** QA-20  
**Status:** **PARTIAL**  
**Release recommendation:** **YELLOW — CONDITIONAL RELEASE**  
**GREEN RELEASE:** **NO**

---

## 1. Status

**PARTIAL**

QA-20 re-confirmed the QA-19 baseline (**186 / 918 PASS**), re-probed Razorpay credentials (**still EMPTY**), added release-gate edge tests (`Qa20ProductionReleaseGateTest`, 7 PASS), re-ran security (Qa11) and full regression (**193 / 966 PASS**).

LIVE payment, LIVE webhook, production `--strict`, and production backup/rollback remain **BLOCKED / UNVERIFIED**. Automated simulation is **not** LIVE PASS.

---

## 2. Bugs fixed

No new production commerce defects required fixes. Coverage expanded for release-gate edge cases.

---

## 3. New bugs

None. Environment blockers unchanged (credentials, prod host).

---

## 4. Payment verification

| Item | Result |
|------|--------|
| Credentials | **EMPTY** |
| LIVE payment | **BLOCKED** |
| LIVE webhook | **BLOCKED** |
| Automated simulation (Qa18/19/20) | **PASS** |
| COD | **PASS** |

Qa20 simulation additions: secret not in initiate payload; webhook-after-verify idempotent; IDOR verify; invalid payment id; retry-after-fail; staging vs production test-key policy; admin cannot force customer verify.

---

## 5. Customer Web

Unit **PASS**; real gateway journey **BLOCKED**.

---

## 6. Customer Mobile

Flutter tests **PASS**; device attached; UPI/LIVE payment **BLOCKED**.

---

## 7. Admin Web

Unit **PASS**; live payment inspection **UNVERIFIED**.

---

## 8. Admin Mobile

Unit **PASS**; live payment/order UI **UNVERIFIED**.

---

## 9. Production readiness

| Item | Result |
|------|--------|
| This host | `APP_ENV=local`, `APP_DEBUG=true` |
| `--strict` | exit **1** (correct) |
| Real staging/prod `--strict` | **UNVERIFIED** |

---

## 10. Backup / restore

Local prior drill **PASS**; production-like **UNVERIFIED**.

---

## 11. Rollback

Documented; live drill **UNVERIFIED**.

---

## 12. Security

| Item | Result |
|------|--------|
| QA-SEC-001 | **OPEN** (localStorage JWT; HttpOnly/BFF not implemented) |
| Qa11SecurityTest | **PASS** 11 / 41 assertions |
| composer audit | **PASS** |
| npm audit (nursery-web omit=dev) | **PASS** 0 vulnerabilities |
| Risk acceptance required for public Web GREEN | **YES** until SEC-001 fixed |

### QA-SEC-001 risk acceptance (required for GREEN Web)

| Field | Value |
|-------|-------|
| Risk | XSS can steal Web access+refresh tokens from localStorage |
| Mitigation | CSP/XSS hygiene; Mobile secure storage; API RBAC; rate limits |
| Residual | HIGH for Customer/Admin Web until HttpOnly/BFF |
| Client acceptance | ☐ YES / ☐ NO — signature required before public Web GREEN |

---

## 13. Unit tests

**PASS** — Qa20 (7) + client units.

---

## 14. Integration tests

**PASS** — automated simulation only.

---

## 15. Regression tests

| Run | Result |
|-----|--------|
| Baseline QA-02…19 | **PASS** 186 / 918 |
| Final QA-02…20 + Phase | **PASS** 193 / 966 |
| Qa11 security | **PASS** 11 / 41 |

---

## 16. Build / lint / analyze

| Check | Result |
|-------|--------|
| PHPUnit | **PASS** |
| composer audit | **PASS** |
| Web/Admin unit | **PASS** |
| npm audit (web) | **PASS** 0 |
| Flutter tests | **PASS** |
| Web build / flutter analyze | Not re-run (no client code change this phase) |
| Pre-existing Web lint debt | Unchanged |

---

## 17. Database changes

**NONE**

---

## 18. API contract changes

**NONE**

---

## 19. PASS

- Baseline + final regression  
- Qa20 edge matrix (simulation)  
- Qa11 security regression  
- Client units; composer/npm audit  
- Local `--strict` correctly fails  

---

## 20. UNVERIFIED

Prod HTTPS/DEBUG/CORS/workers/cron/cache/storage · prod backup/rollback · monitoring · Playwright · interactive Admin payment · load 10/25/50 · large EXPLAIN · app-restart during LIVE payment  

---

## 21. BLOCKED

Razorpay KEY/SECRET/WEBHOOK empty · LIVE payment · LIVE webhook · UPI device payment · Customer Web real gateway · GREEN  

---

## 22. Remaining risks

1. Paid go-live without LIVE evidence  
2. QA-SEC-001 XSS token theft  
3. Unverified prod ops (backup/workers/alerting)  

---

## 23. QA-21 readiness

**YES** — when Razorpay TEST/LIVE credentials + staging/production host are available for LIVE matrix, `--strict` exit 0, backup/rollback drills, device/Web payment sign-off, and SEC-001 fix or signed acceptance.

Suggested QA-21: **Credentialed staging cutover + GREEN decision**.

---

## 24. Final release recommendation

**YELLOW — CONDITIONAL RELEASE** for COD/UAT with disclosures.  
**GREEN RELEASE: NO.**
