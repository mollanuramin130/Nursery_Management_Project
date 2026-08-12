# PHASE 12 — Pre-Implementation Audit Summary

## Status

**Audit complete → hardening implemented → docs published.**  
See `PHASE_12_FINAL_REPORT.md`, `PHASE_12_SECURITY_AUDIT.md`, `PHASE_12_PERFORMANCE_REPORT.md`, `PHASE_12_DATABASE_CHANGES.md`, `PRODUCTION_RUNBOOK.md`.

## CRITICAL (ops / production blockers)

| ID | Finding | Action |
|----|---------|--------|
| C1 | Live deploy with `APP_DEBUG=true` | Checklist + config defaults already false |
| C2 | Missing Razorpay keys/webhook in prod | Document; Phase 3 gates already refuse stubs |
| C3 | Multi-node JWT blacklist on `CACHE_STORE=file` | Document Redis/shared cache |

## HIGH

| ID | Finding | Fix in Phase 12 |
|----|---------|-----------------|
| H1 | PlantFinder loads all plants | Cap + SQL filters |
| H2 | Reviews missing `(product_id,status)` indexes | Migration |
| H3 | Next.js localhost API default in prod | Build-time warning / docs |
| H4 | CORS localhost in prod env | Docs + .env.example |

## MEDIUM

| ID | Finding | Fix |
|----|---------|-----|
| M1 | No global API throttle | `throttleApi` |
| M2 | Weak password (`min:8` only) | Stronger Password rule |
| M3 | Privileged `$fillable` residual | Narrow User fillable |
| M4 | Image URLs loosely validated | Stricter URL rules |
| M5 | Queue `after_commit` false | Enable default |
| M6 | No app-level Cache::remember | Cache home categories briefly |
| M7 | Wishlist unbounded | Soft pagination |

## Already good (do not rebuild)

Phase 3: payment HMAC, webhook gates, auth throttles, JSON errors, request IDs, health, stub refusal.  
IDOR: Order/Address/Wishlist/Loyalty/Subscription/Notification ownership OK.  
Android release cleartext assert OK.
