# Backup & Disaster Recovery Runbook

**Status:** Operational targets documented; restore drills not yet evidenced on this workstation.  
**Data stores:** MySQL (local/dev) or PostgreSQL (production target); local file storage for documents (metadata-first).

## Targets (until tested, treat as aspirations)

| Metric | Target |
|--------|--------|
| RPO | ≤ 15 minutes (managed DB PITR preferred) |
| RTO | ≤ 4 hours |
| Backup encryption | Required in production |
| Restore drill | Quarterly minimum |

## Local / current environment

```powershell
# MySQL dump example (XAMPP / local)
cd "C:\xampp\mysql\bin"
.\mysqldump.exe -u root wabantu_insurance > C:\Backups\wabantu_%date:~-4,4%%date:~-10,2%%date:~-7,2%.sql
```

Application files: back up `apps/api/storage`, `.env` (secrets vault — never commit), and repo tag.

## Production expectations

1. Automated encrypted DB backups + retention policy  
2. Point-in-time recovery if provider supports  
3. Object-storage versioning for documents when binary uploads go live  
4. Per-tenant restore procedure for SaaS (must not overwrite other tenants)  
5. Never clone production PII into public demos  

## Restore drill checklist

1. Provision empty DB  
2. Restore backup  
3. Run `php artisan migrate --force` only if backup predates schema (prefer backup = schema+data)  
4. Smoke: `/api/v1/health`, `/api/v1/ready`, login, one policy list  
5. Record time-to-recover and gaps in BUILD_STATUS / incident log  

## Failover notes

- Standalone: single business DB  
- SaaS: landlord metadata + tenant business data — restore tenant DB independently when dedicated DBs exist (`db_connection_name`)
