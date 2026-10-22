# ADR 002 — Dual edition + dedicated tenant DBs (SaaS)

## Status
Accepted — 2026-10-15

## Context
One codebase must support dedicated enterprise installs and multi-tenant SaaS without forking.

## Decision
- `APP_EDITION=standalone|saas` selects runtime profile.
- SaaS: landlord DB for tenants/subscriptions; **dedicated business DB per tenant**.
- Standalone: single business DB; no platform billing routes.
- Tenant resolved from verified host + membership — never trust raw client tenant headers alone.

## Local Phase 0/1 note
SQLite single-file DB simulates standalone. SaaS multi-DB isolation tests use separate SQLite files when edition=saas in tests.

## Consequences
+ Strong isolation path for production SaaS  
− Local SQLite ≠ production Postgres; migrations stay driver-portable via Laravel schema builder  
