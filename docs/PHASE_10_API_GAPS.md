# PHASE 10 — API Gaps

## Implemented

| Feature | Endpoints |
|---------|-----------|
| Public plans on PDP | Product detail `subscription_plans` + `GET /products/{id}/subscription-plans` |
| Create / list / show | `POST/GET /subscriptions`, `GET /subscriptions/{id}` |
| Pause / resume / cancel | `POST .../pause\|resume\|cancel` |
| Change qty / address | `POST .../change-quantity`, `.../change-address` |
| Admin subscriptions | `GET /admin/subscriptions`, `/{id}`, dashboard, pause/resume/cancel |
| Admin plans | `GET/POST /admin/subscription-plans`, `PUT .../{id}` |
| Due processor | `subscriptions:process-due` |

## Remaining gaps

### 1. True recurring Razorpay mandates

| Field | Value |
|-------|-------|
| Current | Pay-per-cycle orders only |
| Missing | Customer tokens / mandates / auto-debit |
| Recommendation | Future payments phase — do not fake |

### 2. Change frequency mid-subscription

| Field | Value |
|-------|-------|
| Current | Frequency fixed to plan at create |
| Recommendation | Add plan-change API that schedules from next cycle |

### 3. Subscribe-from-cart

| Field | Value |
|-------|-------|
| Current | Direct subscribe from PDP |
| Recommendation | Optional later; keep cart one-time by default |

### 4. Admin “retry payment” shortcut

| Field | Value |
|-------|-------|
| Current | Customer uses existing order retry-payment |
| Recommendation | Admin deep-link to order is enough for MVP |

### 5. Analytics churn / MRR pack

| Field | Value |
|-------|-------|
| Current | Admin dashboard counters only |
| Recommendation | Extend AnalyticsService with lifecycle-based churn later |

### 6. Guest browse → subscribe

| Field | Value |
|-------|-------|
| Current | Auth required to create |
| Recommendation | Keep; document as intentional |
