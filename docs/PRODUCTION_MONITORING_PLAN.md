# Production Monitoring Plan

Recommended observability for GreenLeaf without forcing a paid vendor in-repo.

---

## Minimum signals

| Signal | Source | Alert when |
|--------|--------|------------|
| API health | `GET /api/v1/health` + Laravel `/up` | database ≠ healthy or HTTP ≥ 500 for 2+ min |
| Error rate | Laravel `storage/logs/laravel.log` / host logs | spike in 5xx |
| Payment failures | `payment.*` log channels / Razorpay dashboard | verify/webhook failures |
| Webhook failures | invalid signature / 401s | sustained rejects |
| Queue depth | if queues enabled | backlog growth |
| Disk / DB size | host metrics | >80% |

---

## Suggested stack (pick one)

1. **Hostinger/VPS native** monitoring + log download (lowest friction)  
2. **Sentry** (or similar) for PHP + Next.js + Flutter crash/error capture  
3. **UptimeRobot / Better Stack** ping `/api/v1/health` every 1–5 minutes  

Do not log: passwords, JWT tokens, Razorpay secrets, full card data (none should exist).

---

## Application hooks already useful

- `X-Request-Id` on API responses  
- Audit logs for Admin mutating actions  
- Payment logs with order/payment ids only (provider bodies not returned to clients after Phase 3 sanitization)

---

## Metrics to add later (not required for Phase 3 close)

- p95 latency per route group  
- Checkout conversion funnel  
- Inventory adjust rate  
- Coupon redemption failures  

---

## On-call checklist

1. Check `/api/v1/health`  
2. Check last deploy + `.env` `APP_ENV`/`APP_DEBUG`  
3. Check Razorpay dashboard for payment anomalies  
4. Check `audit_logs` for unexpected Admin actions  
5. If DB restore needed → follow `PRODUCTION_BACKUP_PLAN.md`
