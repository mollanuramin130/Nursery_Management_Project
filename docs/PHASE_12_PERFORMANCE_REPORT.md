# PHASE 12 — Performance Report

Baselines measured in local/CI (`sqlite` in-memory tests). **Do not treat as production SLAs.** Production p50/p95 require APM or load tools against staging with realistic data.

---

## API

| Endpoint / area | Finding | Action |
|-----------------|---------|--------|
| `GET /home` | Multiple catalog queries every hit | 60s `Cache::remember` + invalidation |
| PlantFinder | Loaded all plant products | Cap 300 |
| Wishlist | Unbounded get | Cap 100 |
| Reviews list | Missing composite indexes | Indexes added |
| Global API | No baseline throttle | 120 req/min |

---

## Database

- Reviews indexes: see `PHASE_12_DATABASE_CHANGES.md`.
- N+1: wishlist/home continue to eager-load `images`; PlantFinder eager-loads profile/tags/images within the cap.
- Do not cache inventory/payment/order state.

---

## Cache

| Key | TTL | Invalidate |
|-----|-----|------------|
| `catalog:home:feed:v1` | 60s | Product/Category/Banner/Campaign saved/deleted |

Production: prefer Redis for shared cache + JWT blacklist.

---

## Queue

- `after_commit => true` on database/redis/sqs/beanstalkd connections — jobs dispatch only after successful transactions (notifications, email, etc.).

---

## Website (Next.js)

- Prod localhost API default emits `console.error`.
- Prefer server fetch caching already used in `server-api` (`revalidate`).
- Images: keep remote CDN URLs; avoid shipping multi‑MB originals in cards (ops/CDN concern).

---

## Android

- Release: cleartext off; `API_BASE_URL` via `--dart-define` for production HTTPS.
- Default emulator URL (`10.0.2.2`) is **dev only**; release assert blocks insecure hosts.

---

## Admin

- Existing server pagination retained; no unbounded admin product dumps introduced.
- Destructive actions: rely on existing confirmation UX where present (no Phase 12 redesign).

---

## Load testing

Not run against production. Recommended staging scenarios (concurrent users, not destructive):

1. Homepage + catalog browse  
2. Search / PlantFinder  
3. Login + cart + checkout preview  
4. Admin orders list  

Document dataset size used (products/customers/orders) when executing.

---

## Memory / CPU

PlantFinder cap reduces peak memory for large catalogs. Home cache reduces repeated DB load. Long jobs: monitor `failed_jobs` and queue depth (see monitoring plan).

---

## Slow endpoints (watch list)

| Candidate | Why |
|-----------|-----|
| Admin reports / analytics | Heavy aggregates |
| PlantFinder | CPU scoring |
| Checkout | Locks + inventory |

Instrument with request logging sample rate + DB slow query log in staging.
