# QA CLIENT HANDOVER CHECKLIST — GreenLeaf Nursery

**Date:** 2026-08-12 · Through QA-21 · Companion to `QA_FINAL_RELEASE_REPORT.md`  
**Release mode:** YELLOW — CONDITIONAL (not GREEN)  
**Never put real secrets in this document.**

---

## A. Source & apps

- [ ] Git repository access (branch/tag for release candidate)
- [ ] `apps/nursery-api` Laravel API
- [ ] `apps/nursery-web` Customer Web
- [ ] `apps/nursery-admin` Admin Web
- [ ] `apps/nursery_app` Customer Mobile
- [ ] `apps/nursery_admin_mobile` Admin Mobile
- [ ] `docs/` QA + deploy docs

## B. Runtime docs

- [ ] `RUN.txt` / `docs/RUN_DEPLOY_AND_CUSTOMIZE.md`
- [ ] `docs/ENVIRONMENT_SETUP.md`
- [ ] `docs/QA_TESTING_POLICY.md`
- [ ] `docs/QA-11-SECURITY-DESIGN.md` (SEC-001 options)

## C. Environment variables (provide via secure vault — not chat)

| Area | Keys (names only) |
|------|-------------------|
| API | `APP_KEY`, `APP_URL`, `DB_*`, `JWT_SECRET`, `CORS_ALLOWED_ORIGINS`, mail, queue |
| Payments | `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, `UPI_PAYEE_VPA` (deep-link payee; optional) |
| Web | `NEXT_PUBLIC_API_BASE_URL` (HTTPS production) |
| Mobile | `--dart-define=API_BASE_URL=https://…/api/v1` (never localhost/10.0.2.2 on devices) |

## D. Deployment

- [ ] PHP/MySQL/Node/Flutter toolchain versions recorded
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `php artisan config:cache` / `route:cache` (prod)
- [ ] Queue worker running
- [ ] Scheduler: expired reservation release + other crons
- [ ] HTTPS certificates
- [ ] Health: `/api/v1/health/ready`

## E. Accounts (rotate after handover)

- [ ] Process for creating production `super_admin` (do not reuse demo passwords)
- [ ] Demo/local only: `admin@nursery.test` / `asha@example.com` — **not for production**

## F. Payment

- [ ] Razorpay dashboard app created
- [ ] Webhook URL pointed at production verify endpoint
- [ ] Live/test matrix signed off (currently **BLOCKED** — keys empty on QA host)
- [x] Confirm `local_stub` cannot succeed in production (Qa04 + Qa18)
- [x] Confirm production readiness rejects `rzp_test_` keys (QA-19)
- [x] Automated payment/webhook/idempotency suite (Qa18/Qa19/Qa20) — **AUTOMATED PROVIDER SIMULATION only**, not LIVE PASS
- [x] UPI Dynamic QR + Intent code path (QA-21) — **CODE PASS**; LIVE UPI **BLOCKED** until credentials
- [ ] Docs: `docs/UPI-PAYMENT-DESIGN.md` reviewed by ops

## G. Backup / monitoring

- [ ] DB backup schedule + retention
- [x] Restore drill documented — local isolated drill PASS (QA-17); **production drill still required**
- [ ] App/log monitoring
- [x] Rollback procedure documented (`docs/QA-16-ROLLBACK-PLAN.md` / `QA-17-ROLLBACK-REPORT.md`) — live prod drill still required

## H. Known limitations (client must acknowledge)

- [ ] QA-SEC-001 Web JWT localStorage — OPEN (risk acceptance required for public Web GREEN — see QA-20 report)
- [ ] Razorpay / UPI live — BLOCKED / paid BLOCKED until credentials + LIVE matrix
- [ ] Admin Mobile intentional ops scope (QA-ADM-002)
- [ ] Interactive device UI / LIVE UPI payment sign-off still required
- [ ] Load test / large EXPLAIN UNVERIFIED
- [ ] Production `--strict`, backup, rollback on real host UNVERIFIED

## I. Acceptance sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Client product owner | | | |
| Client technical | | | |
| Vendor delivery lead | | | |

**Accepted release mode:** ☐ COD soft-launch only · ☐ Full paid (requires Razorpay PASS)  
**SEC-001 risk accepted:** ☐ YES · ☐ NO (block public Web until cookie/BFF)
