# Production Launch Checklist — GreenLeaf Nursery

Use before any production cutover or soft launch.  
Mark **PASS** only with evidence. Otherwise **NOT VERIFIED** or **FAIL**.

Related: `PHASE_13_DEPLOYMENT_ARCHITECTURE.md`, `PRODUCTION_RUNBOOK.md`, `PRODUCTION_BACKUP_PLAN.md`, `PRODUCTION_MONITORING_PLAN.md`.

---

## INFRASTRUCTURE

- [ ] Hosting ready (API + MySQL + website + admin) — **NOT VERIFIED** until host provisioned
- [ ] DNS ready (www / api / admin) — **NOT VERIFIED**
- [ ] HTTPS ready + HTTP redirect — **NOT VERIFIED**
- [ ] Database ready (prod DB ≠ staging ≠ local)
- [ ] Storage ready (durable media strategy)
- [ ] Cache ready (`redis` if multi-node)
- [ ] Queue worker supervised (`docs/deploy/supervisor-nursery-api-worker.conf.example`)
- [ ] Scheduler cron (`docs/deploy/crontab-scheduler.example`)

## SECURITY

- [ ] Secrets only in host env / secret store (not git)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `CORS_ALLOWED_ORIGINS` = HTTPS shop + admin only
- [ ] Sample accounts (`*.nursery.test` / `Secret@123`) **not** in production DB
- [ ] RBAC verified on staging then prod smoke
- [ ] Rate limits active (global + auth/payment)
- [ ] Webhooks: `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=false` + webhook secret set

## PAYMENT

- [ ] Staging: Razorpay **test** keys + webhook tested
- [ ] Production: Razorpay **live** keys (only after staging E2E)
- [ ] Webhook URL HTTPS + signature verified
- [ ] Create → capture → order paid path verified
- [ ] Failure / duplicate webhook verified
- [ ] Refund path understood (PSP live refunds may still be limited — see Phase docs)

## WEBSITE

- [ ] `npm ci && npm run build` PASS (verified locally Phase 13)
- [ ] `NEXT_PUBLIC_API_BASE_URL` = production HTTPS `/api/v1`
- [ ] `NEXT_PUBLIC_SITE_ENV=production` (enables indexing)
- [ ] Staging uses `NEXT_PUBLIC_SITE_ENV=staging` (noindex) — code ready
- [ ] No localhost in production env
- [ ] HTTPS smoke
- [ ] Privacy / terms pages — **NOT VERIFIED / missing** (owner content)

## ADMIN

- [ ] `npm ci && npm run build` PASS (fixed + verified Phase 13)
- [ ] Production API URL
- [ ] Staff login; customer login rejected
- [ ] Demo password prefill off when `NODE_ENV=production` (code ready)
- [ ] HTTPS on admin host; not advertised as storefront

## ANDROID

- [ ] `applicationId` `com.greenleaf.nursery_app`
- [ ] Version / versionCode set for release
- [ ] `flutter build appbundle --release --dart-define=API_BASE_URL=https://…/api/v1`
- [ ] Release signing (`key.properties` + keystore) — **NOT VERIFIED** (absent locally)
- [ ] Cleartext disabled in release (code assert present)
- [ ] Play Store listing / privacy / data safety — **NOT VERIFIED** (owner)

## BACKUP

- [ ] Daily backup configured — **NOT VERIFIED**
- [ ] Off-host retention ≥ 30 days — **NOT VERIFIED**
- [ ] Restore tested on staging — **NOT VERIFIED** (mandatory before launch)

## MONITORING

- [ ] `GET /api/v1/health` + `/health/ready` monitored
- [ ] Logs writable; no secret logging
- [ ] Alerts: API down, DB down, queue stopped, payment failure spike — **NOT VERIFIED** wiring

## QA

- [ ] Automated API tests PASS (`php artisan test` — 68) ✓ local
- [ ] Staging smoke checklist complete — **NOT VERIFIED**
- [ ] Staging E2E journey — **NOT VERIFIED**
- [ ] Failure tests (payment fail, webhook dup, OOS) — **NOT VERIFIED** on staging
- [ ] Rollback rehearsed on staging — **NOT VERIFIED**

## SOFT LAUNCH GATES

Do **not** open unlimited traffic until:

1. All CRITICAL blockers cleared  
2. Staging smoke + payment test E2E PASS  
3. Production deploy + internal smoke PASS  
4. Backup restore PASS  
5. Small invite cohort monitored ≥ 24–72h  

---

## Customer smoke (manual)

- [ ] Website opens  
- [ ] Register / Login / Logout  
- [ ] Search / Category / PDP  
- [ ] Wishlist / Cart / Coupon  
- [ ] Address / Checkout  
- [ ] Payment (test or live per env)  
- [ ] Order confirmation / history / tracking  
- [ ] Return / Review / Loyalty / Subscription / Notifications  

## Admin smoke (manual)

- [ ] Login / Dashboard  
- [ ] Products / Categories / Inventory  
- [ ] Orders / Refund / Customers  
- [ ] Campaigns / Coupons / Reports  
- [ ] Notifications / Audit / Settings / Logout  
