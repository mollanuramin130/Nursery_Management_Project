# QA Testing Policy (Mandatory — All Phases)

This policy applies to every QA remediation phase (QA-01 onward).

A phase is **not COMPLETE** because code compiles or the UI “looks fine.”

## Required loop for every changed flow

1. Understand existing implementation (code is source of truth).
2. Smallest correct fix.
3. Write/update automated tests for changed logic.
4. Run those tests; fix failures.
5. Run related regression tests (including prior QA phases).
6. Verify API contracts for any client↔Laravel change.
7. Verify real user/business flow where environment allows.
8. Document results honestly in `docs/QA-{PHASE}-REPORT.md`.

## Pyramid

| Level | When mandatory |
|-------|----------------|
| Unit | Every business-logic / helper / mapping change |
| API / Integration | Controllers, services, DB, authz, cart, checkout, payments |
| Frontend | Next.js/React components, stores, API clients, forms |
| Flutter | Any Customer/Admin Mobile change (unit + widget as applicable) |
| E2E / Smoke | Business-critical journeys |

## Status vocabulary

Use only: **PASS** · **FAIL** · **PARTIAL** · **UNVERIFIED** · **BLOCKED**

Never mark an untested flow **PASS**.

## Final status

Exactly one of: **COMPLETE** · **PARTIAL** · **BLOCKED**

**COMPLETE** requires all applicable automated tests for the change to have been executed and passed, plus documented smoke for critical flows (or explicit UNVERIFIED with environment limitation — which forces PARTIAL, not COMPLETE).

## Forbidden

- Deleting/weakening tests to force green
- Fake success / bypassing validation or authz
- Claiming zero bugs or COMPLETE without evidence

See also phase reports under `docs/QA-*-REPORT.md`.
