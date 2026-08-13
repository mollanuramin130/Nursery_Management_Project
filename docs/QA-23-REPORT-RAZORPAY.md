# QA-23 REPORT — Razorpay UPI TEST Configuration + Payment Verification Gate

**Date:** 2026-08-12  
**Status:** **PARTIAL / BLOCKED**  
**Note:** QA-22 remains the notifications phase. This command maps to **QA-23** (Razorpay TEST config). Do not overwrite QA-22 notification docs.

**GREEN RELEASE:** **NO**

---

## 1. Status

**PARTIAL / BLOCKED for real TEST payment**

Existing UPI/Razorpay architecture (QA-21) was audited and **not redesigned**. Automated/stub payment suites remain green. On this host:

| Credential | State |
|------------|-------|
| `RAZORPAY_KEY` | **EMPTY** |
| `RAZORPAY_SECRET` | **EMPTY** |
| `RAZORPAY_WEBHOOK_SECRET` | **EMPTY** |

Therefore:

- REAL Razorpay TEST payment = **BLOCKED**
- LIVE payment = **BLOCKED**
- LIVE webhook = **BLOCKED**
- Device UPI with real PSP = **BLOCKED**

Operator guide: `docs/RAZORPAY-TEST-SETUP.md`

---

## 2. Bugs fixed

None required in payment architecture. Configuration documentation and gate tests added.

---

## 3. Bugs still open

| ID | Status |
|----|--------|
| QA-SEC-001 | **OPEN** |
| Razorpay credentials EMPTY | **BLOCKED** (environment) |

---

## 4. Razorpay configuration status

| Item | Result |
|------|--------|
| Env names | `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` |
| `.env` gitignored | **PASS** |
| Secrets in frontend | **PASS** (none found) |
| Secrets in API initiate payload | **PASS** (Qa23) |
| Credentials on this host | **EMPTY** |

---

## 5–8. Dynamic QR / Intent / Verify / Webhook

| Item | Automated/stub | Real TEST | LIVE |
|------|----------------|-----------|------|
| Dynamic QR | PASS (Qa21) | **BLOCKED** | **BLOCKED** |
| UPI Intent | PASS (Qa21) | **BLOCKED** | **BLOCKED** |
| Server verification | PASS | **BLOCKED** | **BLOCKED** |
| Webhook logic | PASS (Qa18–20) | **BLOCKED** (no public webhook target + empty secret) | **BLOCKED** |

Webhook route (existing): `POST /api/v1/payments/webhooks/razorpay`

---

## 9–12. Clients

Code paths from QA-21 unchanged. Real TEST UI journeys **BLOCKED** until keys SET.

---

## 13–18. Tests

| Suite | Result |
|-------|--------|
| `Qa23RazorpayTestConfigGateTest` | **5 PASS** |
| Qa18–21 payment | PASS |
| Qa11 security | included in broader filter |
| Full QA filter | see closeout |

---

## 19. Build/lint/analyze

Not re-run for payment redesign (no client payment redesign). Gate tests only.

---

## 20–22. Production / backup / rollback

| Item | Result |
|------|--------|
| Local `--strict` | exit **1** (correct; `APP_ENV=local`) |
| Staging/prod `--strict` | **UNVERIFIED** |
| Backup/restore | prior QA-17 local drill; prod **UNVERIFIED** |
| Rollback | documented historically; prod drill **UNVERIFIED** |

---

## 23. UNVERIFIED

Real Razorpay TEST QR/intent completion, reachable TEST webhook, staging HTTPS, device TEST payment after keys.

---

## 24. BLOCKED

Empty Razorpay credentials; LIVE payment; GREEN release.

---

## 25. Remaining risks

Operator must configure TEST keys + tunnel/staging webhook before claiming TEST payment PASS. Mixing `rzp_live_*` on local is forbidden.

---

## 26–27. Database / API

**NONE** / **NONE** (no contract change)

---

## 28. Files changed

- `apps/nursery-api/tests/Feature/Qa23RazorpayTestConfigGateTest.php` (new)
- `apps/nursery-api/.env.example` (comments only)
- `docs/RAZORPAY-TEST-SETUP.md` (new)
- `docs/QA-23-*.md` (new)
- `RUN.txt` (pointer to setup guide)
- Register / matrix / roadmap updates

---

## 29. Next-phase readiness

**YES** when operator sets TEST credentials → re-run this phase for real TEST evidence → then LIVE only on production with `--strict` PASS.
