# QA-38 Test Matrix

**Date:** 2026-08-13 · Device `2d3714f`

| # | Case | Result | Evidence |
|---|------|--------|----------|
| A | Cache → API → fresh cache | **PASS** | CatalogRepository + peek |
| B | API unavailable → cache | **PASS** | fallback path |
| C | No cache + offline → mock | **PASS** | mockOnly tests |
| D | Cache + API 500 → keep cache | **PASS** | architecture |
| E | Mock → API recovery → API wins | **PASS** | qa38_sync_priority_test |
| F | Offline cart local | **PASS** | offline_local_cart_test |
| G | Offline wishlist local | **PASS** | offline_local_cart_test |
| H | Offline payment blocked | **PASS** | checkout localOnly guard |
| I | Reconnect auto refresh | **PASS** | syncGeneration + onReconnected |
| J | Home /home coalescing | **PASS** | `_homeInFlight` |
| K | Wishlist remove no flash | **PASS** | epoch + busy guard |
| L | Image fallback | **PASS** | ResilientNetworkImage |
| M | Temp network ≠ logout | **PASS** | QA-37-006 retained |
| N | 401 ≠ offline | **PASS** | classify + refresh rules |
| O | 500 safe fallback | **PASS** | eligible fallback |
| P | Timeout fallback | **PASS** | network_errors_test |
| Q | Envelope JSON | **PASS** | cache_envelope_test |
| — | PHPUnit regression | **PASS** | **253 / 1209** |
| — | Flutter suite | **PASS** | **58** |
| — | Vivo Wi‑Fi OFF physical | **UNVERIFIED** | DEBUG sim available |
| — | LIVE / GREEN | **NO** | |
