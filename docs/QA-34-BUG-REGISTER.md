# QA-34 Bug Register

**Date:** 2026-08-13 · **Scope:** BFF HTTPS/deployment readiness + cross-client regression  
**GREEN:** NO · **LIVE:** OUT OF SCOPE

---

## FIXED this phase

### QA-34-001 — Staging/production env examples omitted BFF deploy contract
- **Classification:** CONFIGURATION / SECURITY hygiene · **Severity:** MEDIUM
- **Root cause:** Post–QA-33 examples still only listed `NEXT_PUBLIC_API_BASE_URL`, omitting server-only `API_PROXY_TARGET` and HTTPS `COOKIE_SECURE`.
- **Impact:** Operators could deploy Web without correct BFF upstream / Secure cookie settings.
- **Fix:** Updated Customer + Admin `.env.example`, `.env.staging.example`, `.env.production.example`; added `qa34_bff_readiness.sh` contract checks; unit tests for `resolveCookieSecureFlag`.
- **Status:** **FIXED**

---

## Verified still PASS

| ID | Notes |
|----|--------|
| QA-SEC-001 | Remains **CLOSED** — BFF HttpOnly re-verified |
| QA-33 BFF smoke | PASS inside readiness script |

---

## OPEN (carry-forward)

| ID | Summary | Status |
|----|---------|--------|
| QA-ADM-002 | Admin Mobile ops subset | **OPEN** (intentional) |

---

## Not defects / environment limits

| Item | Classification |
|------|----------------|
| Live HTTPS staging Secure cookie jar | **UNVERIFIED** — no staging HTTPS host this phase; policy unit-tested |
| True UPI-app settle | **BLOCKED** |
| Dynamic QR settle | **UNVERIFIED** |
| Mobile-data without reverse | **UNVERIFIED** |
| Login rate limit during heavy smoke | Ops noise — `php artisan cache:clear` |
