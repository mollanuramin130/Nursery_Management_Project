# PHASE 13 — Staging QA Scripts (Manual)

Execute on **staging** only. Do not use production payment live keys here.

Seed staging with migrations + `database/nursery_sample_data.sql` **or** factories — never production customer dumps without approved anonymization.

Sample logins (staging only): `docs/SAMPLE_LOGIN_CREDENTIALS.md`.

---

## A. Smoke

Follow Customer + Admin sections in `PRODUCTION_LAUNCH_CHECKLIST.md`.

API:

```bash
curl -fsS "$API/health"
curl -fsS "$API/health/ready"
```

---

## B. End-to-end (happy path)

1. Register new customer (unique email)  
2. Login  
3. Browse shop + search + PDP  
4. Wishlist add/remove  
5. Cart add + coupon (valid/invalid)  
6. Address create  
7. Checkout preview → place order  
8. Pay with Razorpay **test** (or COD)  
9. Confirm webhook updates payment + order (if online)  
10. Inventory reserved/sold consistent  
11. In-app / email notification for order  
12. Admin: view order → pick/pack/ship → deliver  
13. Customer: tracking → review → loyalty balance  
14. Optional: subscription create + due cycle dry-run  

Record order IDs and request IDs.

---

## C. Failure tests

| Case | Expect |
|------|--------|
| Wrong card / payment fail | Order stays pending/failed; no false paid |
| Duplicate webhook | Idempotent; no double stock deduct |
| Expired JWT | 401; refresh or re-login |
| Invalid coupon | 422; totals unchanged |
| OOS product | Checkout blocked |
| Cancel eligible order | Status + inventory release rules hold |
| Queue worker stopped | Job accumulates; restart drains; no corrupt paid state |
| Unsigned webhook | Rejected when secret configured |

---

## D. Rollback rehearsal

1. Deploy build A to staging → smoke  
2. Deploy build B → smoke  
3. Roll back to A (app only) → smoke  
4. Separately: restore DB dump to alternate schema → smoke  

Mark PASS only if both app rollback and restore drill succeed.
