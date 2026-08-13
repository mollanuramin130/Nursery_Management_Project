# QA-37 REPORT — Offline-first Mobile + Network Resilience + Parity

**Date:** 2026-08-13  
**Verdict:** **COMPLETE** (TEST/local) with device matrix **PARTIAL**  
**LIVE / GREEN:** **OUT OF SCOPE / NO**  
**Device:** CONNECTED — `2d3714f` / vivo 1951 (hooks + DEBUG sim; exhaustive Wi‑Fi matrix UNVERIFIED this session)

---

## Scorecard

| Area | Result |
|------|--------|
| Offline UI (same widgets + mock) | **PASS** |
| Mock data (API contract) | **PASS** |
| CatalogRepository remote→cache→mock | **PASS** |
| Cart / wishlist local persistence | **PASS** |
| Place order / payment offline | **PASS** (blocked, no fake success) |
| Network banner + DEBUG sim | **PASS** |
| Bottom navigation | **PASS** |
| BFF / SEC-001 / Razorpay TEST | **PASS** / **CLOSED** / **PASS** |
| PHPUnit | **253 / 1209** |
| Customer Flutter | **52** (+7 vs prior 45) |

---

## What shipped (offline-first)

1. Audit → `QA-37-OFFLINE-ARCHITECTURE.md` · `QA-37-MOCK-DATA-CONTRACT.md`
2. `assets/mock_data/*` realistic nursery JSON (Money Plant, Snake Plant, Monstera, …)
3. `CatalogRepository` + `MockDataMode` + `OfflineController`
4. Home / Shop / PDP / Search / Categories / Wishlist / Cart / Orders fallback
5. `ResilientNetworkImage` + local placeholder asset
6. Local cart/wishlist mutations when offline; **no** fake orders/payments
7. DEBUG floating network simulation panel (`kDebugMode` only)
8. Prior parity + refresh false-logout fixes retained (QA-37-001…009)

---

## Acceptance (honest)

| Criterion | Status |
|-----------|--------|
| App remains GreenLeaf UI offline via mock | **PASS** (AUTO/MOCK_ONLY) |
| Same ProductCard for remote/mock | **PASS** |
| No fake payment / place order | **PASS** |
| Exhaustive Vivo Wi‑Fi OFF physical matrix | **UNVERIFIED** (use DEBUG sim / mockOnly) |
| Mobile-data without adb reverse | **UNVERIFIED** |

---

## Next

QA-38 — LIVE gates; optional Admin Mobile read-only cache; optional Web catalog cache parity.
