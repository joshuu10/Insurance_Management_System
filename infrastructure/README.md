# Infrastructure

PostgreSQL, Redis, MinIO, and Docker Compose are **deferred** for this workstation (stakeholder: do not install Postgres/Docker for Phase 0/1).

Local development uses:

- SQLite (`apps/api/database/database.sqlite`)
- `QUEUE_CONNECTION=sync`
- `CACHE_STORE=file`

When Docker/Postgres become available, add compose services here and switch `DB_CONNECTION=pgsql` without rewriting domain logic.
