# QA-07 Forensic Audit — Customer Mobile Parity

**Date:** 2026-08-12

## Gap summary

| Feature | API | Web | Mobile (pre-QA-07) |
|---------|-----|-----|---------------------|
| Returns list | `GET /customer/returns` | `/account/returns` | **MISSING account screen** |
| Return request | `POST /orders/{id}/returns` | Order detail | Order detail **present** |
| Return detail | `GET /returns/{id}` | Via order | Partial (embedded on order) |
| My reviews | `GET /customer/reviews` | `/account/reviews` | **MISSING** (PDP only) |
| Review create | `POST /products/{id}/reviews` | PDP + order | PDP **present** |

## Decision

- Reuse existing APIs — **no schema / no new endpoints**.
- Add Flutter screens: My Returns, Return Detail, My Reviews.
- Wire Account navigation.
- Keep order-detail return submit as the request path (parity with Web).

## Out of scope

- Razorpay live (UNVERIFIED)
- Admin Mobile
- Edit/delete review (API does not support customer edit/delete)
