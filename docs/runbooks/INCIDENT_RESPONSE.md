# Incident Response Runbook

**Security contact (placeholder):** security@wabantu.local  
**On-call (placeholder):** ops@wabantu.local  

## Severity

| Sev | Examples | Response |
|-----|----------|----------|
| SEV1 | Data breach, ransomware, payment diversion | Immediate contain + counsel + notify decision |
| SEV2 | Auth outage, mass 5xx, queue backlog critical | Stabilize within 1h |
| SEV3 | Single-tenant degradation, sandbox misconfig | Next business day |

## Immediate containment

1. Rotate compromised API tokens / revoke Sanctum tokens (`personal_access_tokens`)  
2. Disable suspect integrations (`status=DISABLED`)  
3. Suspend SaaS tenant if abuse (`POST /platform/tenants/{id}/suspend`) — preserve customer record access policy  
4. Preserve audit_events (append-only) — do not delete  

## Evidence

- Application logs: `storage/logs/laravel.log`  
- Audit table: `audit_events`  
- Integration runs: `integration_sync_runs`  
- Correlation ID header: `X-Correlation-ID` when present  

## Notification

PDPC / contractual notifications require **legal decision** — do not auto-notify from software alone. Use ASSUMPTIONS_AND_BLOCKERS and COMPLIANCE_DECISION_REGISTER.

## Post-incident

1. Timeline + root cause  
2. Customer impact (tenants / policyholders)  
3. Remediation PR + regression test  
4. Update this runbook  

## Test exercise

Schedule tabletop exercise before production go-live; log date here: _______________
