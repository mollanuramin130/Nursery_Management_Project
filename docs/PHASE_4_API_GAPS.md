# PHASE 4 — API Gaps

Additive work implemented in Phase 4 is listed under **Resolved**. Remaining items are deferred.

---

## Resolved in Phase 4 (additive)

| Feature | Change |
|---------|--------|
| Reviews | `verified_purchase`, rating `distribution` on list meta; `GET /products/{id}/review-eligibility` |
| Order detail | `can_return`, `actions.can_return`, `return_reasons`, `returns[]`, `refunds[]`, `reviewable_product_ids` |
| Returns | `GET /orders/{id}/returns`; `GET /customer/returns` |
| Preferences | `GET/PUT /customer/preferences` |
| Notifications | `active.user` middleware on notification routes |

---

## Remaining gaps (future)

### 1. Admin review moderation queue

| Field | Value |
|-------|-------|
| Feature | Review moderation |
| Existing | `POST /admin/reviews/{id}/moderate` |
| Missing | `GET /admin/reviews?status=pending` list/queue |
| Impact | Admin cannot discover pending reviews without DB |
| Recommended | Admin phase — list + filters |

### 2. Customer review update/delete

| Field | Value |
|-------|-------|
| Feature | Edit/delete own review |
| Existing | POST create only |
| Missing | PUT/DELETE `/reviews/{id}` |
| Recommended | Future if product policy allows edits |

### 3. Review image upload

| Field | Value |
|-------|-------|
| Feature | Photo reviews |
| Existing | `review_images` table; list returns URLs |
| Missing | Multipart upload on create |
| Recommended | Future media phase |

### 4. Admin return lifecycle

| Field | Value |
|-------|-------|
| Feature | Approve/reject/receive returns |
| Existing | Customer `POST /orders/{id}/returns` → `RETURN_REQUESTED` |
| Missing | Admin transition APIs to `RETURNED` / reject |
| Recommended | Next Admin operations phase |

### 5. Customer-initiated refunds

| Field | Value |
|-------|-------|
| Feature | Customer requests refund |
| Existing | Admin `POST /admin/refunds` (stub/PSP limited) |
| Missing | Customer initiate endpoint |
| Recommended | **Do not add** until real PSP refunds work; customers only **view** refunds |

### 6. Push notification send (FCM/APNs)

| Field | Value |
|-------|-------|
| Feature | Mobile push |
| Existing | `POST /devices/register` stores `push_token` |
| Missing | Provider config, send pipeline, deep-link payload contract |
| Recommended | Dedicated push phase; never put secrets in clients |

### 7. Server-side recently viewed

| Field | Value |
|-------|-------|
| Feature | Cross-device recently viewed |
| Existing | None |
| Missing | Table + `POST/GET /customer/recently-viewed` |
| Recommended | Only if local storage proves insufficient |

### 8. Loyalty / segmentation

| Field | Value |
|-------|-------|
| Feature | Points, tiers, segments |
| Missing | Entire subsystem |
| Recommended | Future retention phase |
