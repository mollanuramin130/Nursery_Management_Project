# PHASE 3 — Production Readiness Audit (Updated)

**Date:** 2026-08-11  
**After:** Phase 3 hardening implementation

Initial audit: see git history / earlier section content in conversation. This file is the **post-hardening residual risk register**.

---

## CRITICAL — remaining

| ID | Issue | Impact | Recommended solution | Blocking production? |
|----|-------|--------|----------------------|----------------------|
| C2 | Razorpay production keys/webhook must be configured by ops | Online pay unavailable/stub | Set secrets; E2E verify | **YES** (online pay) |
| C3 | Android `key.properties` / Play signing absent | Cannot ship Play release | Create upload keystore; CI secrets | **YES** (Play) |
| C1′ | Gateway refunds still not implemented | No automated money refunds | Integrate Razorpay refunds in future phase | **YES** (auto refunds); offline ops OK |

**Resolved in Phase 3:** fake “processed” refunds in production path (now refused); non-prod no longer marks orders REFUNDED on stub.

---

## HIGH — remaining

| ID | Issue | Impact | Solution | Blocking? |
|----|-------|--------|----------|-----------|
| H7 | JWT in `localStorage` (web/admin) | XSS session theft | Future httpOnly/BFF cookies | Soft — mitigate XSS |
| H8 | Clients default to localhost HTTP if env unset | Mis-deploy risk | Checklist + force env in hosting | **YES** if misconfigured |
| H3′ | Broader payment/checkout Feature suite still thin | Coverage gaps | Expand tests in CI | Soft |

**Resolved in Phase 3:** H1 active.user on Admin; H2 JWT invalidate on logout; H4 unsigned webhook gate; H5 rate limits expanded; H6 Admin prefills gated to development.

---

## MEDIUM — remaining

| ID | Issue | Blocking? |
|----|-------|-----------|
| M7 | Production CORS must replace localhost list | Config task |
| M8 | Next image remotePatterns may need CDN hosts | Config task |
| M10 | No Docker/CI in repo | Process risk |
| M3′ | DB unique on `orders.request_id` deferred | Soft |

**Resolved:** M1 pagination caps; M2 refund payment status lookup; M4 Razorpay client leak; M5 indexes; M6 health endpoint.

---

## LOW — remaining

L1 TTL naming confusion, L2 sample docs passwords, L3 stale PROJECT_AUDIT narrative, L4 no iOS — unchanged / non-blocking.

---

## Counts

| Severity | Remaining blocking for “full production” | Remaining soft |
|----------|------------------------------------------|----------------|
| CRITICAL | 3 (keys, keystore, gateway refunds) | 0 |
| HIGH | 1 (HTTPS env misconfig if ignored) | 2 |
| MEDIUM | 0 | several config/process |
| LOW | 0 | several |

---

## Already solid (reaffirmed)

Server-authoritative money totals, order state machine, inventory locking, payment verify HMAC, permission middleware, JSON error envelope, Flutter secure storage + release cleartext guards.

---

## Go / No-Go

| Goal | Verdict |
|------|---------|
| Staging / UAT on HTTPS with COD | Conditionally ready after env checklist |
| Production online Razorpay | **NO-GO** until keys + webhook E2E |
| Play Store release | **NO-GO** until signed release keystore |
| Production automated refunds | **NO-GO** until PSP refund integration |
