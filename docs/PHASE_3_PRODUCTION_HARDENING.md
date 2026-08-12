# PHASE 3 — Production Hardening

**Date:** 2026-08-11  
**Status:** PARTIALLY COMPLETE (hardening done; external production blockers remain)

---

## 1. Security improvements

- Admin + inventory Admin routes now require `active.user` (blocked staff rejected)
- Logout invalidates access JWT via blacklist in addition to refresh revoke
- Webhooks refuse unsigned payloads unless `PAYMENT_ALLOW_UNSIGNED_WEBHOOKS=true` (never implied by non-prod alone)
- Razorpay error bodies no longer returned to API clients
- Admin login sample credentials only in `NODE_ENV=development`
- `.env.example` hardened defaults (logging off, CORS without example.com)

## 2. Authentication improvements

- JWT invalidate on logout
- Register throttled (`5/min`)
- Existing login/refresh/forgot throttles retained

## 3. Authorization improvements

- Customer → Admin APIs still 403 (covered by Feature test)
- Inactive Admin → 403 via `active.user` (Feature test)
- Unauthenticated Admin → 401 (Feature test)

## 4. Payment improvements

- Unsigned webhook gate tightened
- Refunds: production refuses stub; non-prod records `recorded_local` **without** faking order REFUNDED / PSP payout
- Payment lookup for refunds accepts `success` (and `captured`)
- Provider HTTP errors sanitized

## 5. Inventory improvements

- Existing locking preserved
- Feature test for negative on-hand rejection
- History indexes added for movements

## 6. Database improvements

- See `PHASE_3_DATABASE_CHANGES.md` (indexes only)
- SQLite-compatible migration tweaks for CI/tests

## 7. API improvements

- `GET /api/v1/health` (app + DB status, no secrets)
- Admin products/orders `per_page` capped at 100
- Throttles on checkout/order mutations, payments, webhooks

## 8. Performance improvements

- Targeted indexes for coupons, stock movements, orders, audit logs
- Client HTTP timeouts (30s) on Website + Admin axios

## 9. Logging improvements

- Example config disables verbose request logging by default
- Payment create failures logged server-side without leaking body to clients

## 10. Testing improvements

- `tests/Feature/Phase3HardeningTest.php` (7 tests) — health, authz, pagination cap, state machine, inventory adjust

## 11. Deployment improvements

- `PRODUCTION_DEPLOYMENT_CHECKLIST.md`
- `.env.example` production guidance

## 12. Backup plan

- `PRODUCTION_BACKUP_PLAN.md` (operator-owned; not pretended as automated in-repo)

## 13. Monitoring plan

- `PRODUCTION_MONITORING_PLAN.md` + health endpoint for uptime checks

## 14. Remaining risks / production blockers

| Item | Blocking? |
|------|-----------|
| Live Razorpay keys + webhook secret in prod | YES for online pay |
| Gateway refund integration | YES for automated money refunds |
| Android Play upload keystore | YES for Play Store |
| Production HTTPS API URLs on all clients | YES for go-live |
| localStorage JWT XSS residual risk | Accepted for now / future httpOnly |
| No Docker/CI in repo | Operational risk, not code blocker |

## 15. Production blockers (explicit)

1. Configure real payment provider secrets and verify webhook signatures end-to-end  
2. Do not enable Admin refunds as money movement until PSP refund API exists  
3. Create Android release keystore + signed AAB with HTTPS `--dart-define`  
4. Point Website/Admin `NEXT_PUBLIC_API_BASE_URL` to HTTPS production API  
5. Replace CORS allowlist with production origins; `APP_DEBUG=false`

---

## Files touched (high level)

- API routes/middleware/auth/payment/refund/health/indexes/tests/env example  
- Admin login prefills + refunds notice + axios timeout  
- Website axios timeout  
- Docs listed above + updated production audit
