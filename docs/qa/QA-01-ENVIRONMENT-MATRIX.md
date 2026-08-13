# QA-01 Environment Matrix

**Date:** 2026-08-12  
**Rule:** Only evidence-based results. Never invent PASS.

Legend: **PASS** | **FAIL** | **UNVERIFIED** | **NOT APPLICABLE**

---

## Application ↔ API

| Application | Environment | API URL (expected) | Result | Evidence |
|-------------|-------------|--------------------|--------|----------|
| Laravel API | Local | `http://127.0.0.1:8000/api/v1` | **PASS** | `GET /health/ready` → 200, `database:healthy` |
| Customer Web | Local browser | `NEXT_PUBLIC_API_BASE_URL` → `…/api/v1` | **PASS** | `.env.local` present; health probe module; CORS Origin `http://localhost:3000` allow |
| Customer Mobile | Android emulator | default `http://10.0.2.2:8000/api/v1` | **UNVERIFIED** | Config + health probe code verified; device/emulator not run this session |
| Customer Mobile | Physical device | `http://<LAN_IP>:8000/api/v1` via dart-define | **UNVERIFIED** | Documented; requires operator LAN + `--host=0.0.0.0` |
| Admin Web | Local browser `:3001` | `NEXT_PUBLIC_API_BASE_URL` → `…/api/v1` | **PASS** | `.env.local` present; health probe; CORS Origin `http://localhost:3001` allow |
| Admin Mobile | Android emulator | default `http://10.0.2.2:8000/api/v1` | **UNVERIFIED** | Config + health probe code verified; emulator not run this session |
| Admin Mobile | Physical device | LAN dart-define | **UNVERIFIED** | Documented only |

---

## Infrastructure checks

| Check | Result | Evidence |
|-------|--------|----------|
| MySQL reachable via API | **PASS** | ready endpoint `database:healthy` |
| Laravel starts | **PASS** | Serving on `:8000` during verification |
| Migrations applied (core) | **PASS** | Core commerce tables Ran |
| Migrations pending (Phase 15–20) | **UNVERIFIED** / noted | `migrate:status` shows Pending for phase15–20 files — not a health blocker |
| Sample/seed accounts | **PASS** | `asha@example.com`, `admin@nursery.test` login 200 |
| Health JSON | **PASS** | `status/ok`, `database/healthy`, `cache/healthy` |
| CORS OPTIONS (Customer Web) | **PASS** | 204 + `Access-Control-Allow-Origin: http://localhost:3000` |
| CORS OPTIONS (Admin Web) | **PASS** | 204 + `Access-Control-Allow-Origin: http://localhost:3001` |
| CORS actual POST login (Customer Origin) | **PASS** | 200 + ACAO header + tokens present (not printed) |
| Products catalog smoke | **PASS** | `GET /products?per_page=1` success |
| Personal LAN IP hardcoded in source | **PASS** (absent) | Uses env / dart-define |
| Secrets committed | **PASS** (none observed in QA-01 docs) | Reports use placeholders |

---

## Login smoke

| Actor | Path | Result | Classification if fail |
|-------|------|--------|------------------------|
| Customer seeded | `POST /auth/login` | **PASS** | — |
| Admin seeded | `POST /auth/login` | **PASS** | — |
| Customer Web UI end-to-end click | Browser automation | **UNVERIFIED** | API+CORS verified; UI probe code present |
| Customer/Admin Mobile UI login | Emulator/device | **UNVERIFIED** | Same |

Transient **429** during rapid smoke = rate limit (CONFIGURATION/ops), not broken contract. Smoke script prints hint.

---

## QA-CFG-001 disposition (QA-01)

| Status | Meaning |
|--------|---------|
| **VERIFIED** (mitigated) | Connectivity class addressed with health probes, docs, CORS allow-list, smoke script; seeded API login works on this host |
| Residual | Operator misconfig / wrong mobile URL / throttle still possible — diagnostics exist |

Auth **message** quality (generic Unauthenticated) → **DEFERRED TO QA-02** (not fixed in QA-01).
