# Production Issue Backlog

Track real production/staging incidents and pre-launch blockers.  
Do **not** invent resolved issues. Update status with evidence.

| ID | Date | Area | Severity | Description | Reproduction | Root Cause | Status | Fix | Verification |
|----|------|------|----------|-------------|--------------|------------|--------|-----|--------------|
| P13-C1 | 2026-08-12 | Infra | CRITICAL | Staging host not verified live | N/A — Phase 13 audit | Hosting not provisioned / not tested | OPEN | Operator provision + `PHASE_13_STAGING_QA` | Health + smoke PASS |
| P13-C2 | 2026-08-12 | Infra | CRITICAL | Production DNS/HTTPS not verified | N/A | Domains/certs not evidenced | OPEN | Configure DNS + TLS | Browser HTTPS + API health |
| P13-C3 | 2026-08-12 | Backup | CRITICAL | Backup restore not tested | N/A | Restore drill not run | OPEN | Staging restore per runbook | Documented RTO evidence |
| P13-C4 | 2026-08-12 | Payments | CRITICAL | Razorpay staging E2E / live keys not verified | N/A | External credentials pending | OPEN | Test keys on staging then live | Webhook + order paid consistent |
| P13-C5 | 2026-08-12 | Legal | HIGH | Privacy/terms pages missing on website | Browse site | Content not authored | OPEN | Owner legal pages | Pages live + linked |
| P13-C6 | 2026-08-12 | Android | HIGH | Release signing + Play assets not verified | No keystore in repo | Operator-owned secrets | OPEN | key.properties + AAB + listing | Play internal track |
| P13-C7 | 2026-08-12 | Ops | HIGH | Queue worker + scheduler not verified on host | N/A | Supervisor/cron not confirmed | OPEN | Deploy examples in `docs/deploy/` | `queue:work` + schedule events |
| P13-C8 | 2026-08-12 | Monitoring | HIGH | Uptime/alerts not wired | N/A | External monitor not configured | OPEN | Ping `/health/ready` | Alert test |
| P14-I1 | 2026-08-12 | Launch | CRITICAL | Controlled soft launch not started — no production traffic baseline | Phase 14 | Blocked by P13-C* | OPEN | Clear CRITICAL blockers | Soft-launch Stage 0–1 evidence |

---

## Status legend

`OPEN` · `INVESTIGATING` · `MITIGATED` · `FIXED` · `VERIFIED` · `WONTFIX`
