# QA-33 Bug Register

**Date:** 2026-08-13 · **Scope:** QA-SEC-001 Web auth hardening  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-SEC-001 — Customer/Admin Web JWTs stored in `localStorage`
- **Classification:** SECURITY · **Severity:** HIGH · **Priority:** P1
- **Root cause:** Browser JS could read access + refresh tokens from localStorage (XSS → token theft).
- **Fix:** Same-origin Next.js BFF stores JWTs in **HttpOnly** cookies; browser never receives tokens in JSON; CSRF double-submit on mutating BFF calls; legacy keys wiped on bootstrap.
- **Apps:** Customer Web, Admin Web  
- **Mobile:** Unchanged (Bearer + secure storage) — intentional  
- **Tests:** Web/Admin unit cookie policy; `qa33_bff_smoke.sh`; `Qa33BearerMobileCompatTest`; full QA PHPUnit filter  
- **Status:** **FIXED / CLOSED**

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** (intentional — not a defect) |

---

## Not defects / environment

| Item | Notes |
|------|--------|
| Cookie `Secure=false` on local HTTP | Required for `http://localhost` TEST; prod sets Secure |
| Same-origin XSS session riding | Mitigated by CSP; cannot exfiltrate HttpOnly JWT |
| Login rate limit during smoke | Cleared via `php artisan cache:clear`; not a product bug |
| UPI/QR settle | Unrelated; still BLOCKED/UNVERIFIED from QA-31/32 |
