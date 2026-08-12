# PHASE 9 — API Gaps

## Resolved in Phase 9

| Feature | Support |
|---------|---------|
| Admin review queue | `GET /admin/reviews`, `GET /admin/reviews/{id}`, dashboard |
| Review moderate + hide | `POST /admin/reviews/{id}/moderate` status `approved\|rejected\|hidden` |
| My reviews | `GET /customer/reviews` |
| Review image URLs | Optional `images[]` on create |
| Loyalty balance/history | `GET /customer/loyalty`, `GET /customer/loyalty/transactions` |
| Admin loyalty | dashboard, accounts, transactions, adjust |
| Earn on delivery / reverse on refund | Service hooks |
| RBAC | `reviews.*`, `loyalty.*` |

## Remaining gaps

### 1. Checkout points redemption

| Field | Value |
|-------|-------|
| Feature | Redeem points at checkout |
| Current | Ledger only; no cart/checkout apply |
| Missing | Preview/place fields + authoritative discount calc |
| Recommendation | Future phase after finance rules defined |

### 2. Store credit wallet

| Field | Value |
|-------|-------|
| Feature | Cash store credit |
| Current | None (refunds remain payment stub) |
| Recommendation | Do **not** invent wallet now; document until PSP refunds + accounting rules exist |

### 3. Referral program

| Field | Value |
|-------|-------|
| Feature | Referral codes + rewards |
| Current | None |
| Recommendation | Extension point only — no fake rewards |

### 4. Multipart review photo upload

| Field | Value |
|-------|-------|
| Feature | Binary upload |
| Current | URL attach only (same pattern as product images) |
| Recommendation | Shared media upload phase |

### 5. Customer edit/delete review

| Field | Value |
|-------|-------|
| Feature | Edit own review |
| Current | Create only |
| Recommendation | Future if policy allows |

### 6. Server recently viewed

| Field | Value |
|-------|-------|
| Feature | Cross-device history |
| Current | Client local storage |
| Recommendation | Only if sync required |

### 7. Helpful votes / Most helpful sort

| Field | Value |
|-------|-------|
| Current | Not implemented |
| Recommendation | Skip until vote table exists |

### 8. Engagement analytics pack

| Field | Value |
|-------|-------|
| Feature | Review/wishlist/loyalty KPIs in AnalyticsService |
| Current | Admin dashboards on Reviews/Loyalty pages only |
| Recommendation | Additive analytics endpoints later |
