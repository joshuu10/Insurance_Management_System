# ADR 003 — Local SQLite; defer Postgres/Docker install

## Status
Accepted — 2026-10-15 (per stakeholder direction)

## Context
Developer machine has PHP 8.3, Composer, Node 22; no Docker; no PostgreSQL. Stakeholder directed: do not install Postgres or Docker for Phase 0/1.

## Decision
- Use **SQLite** (`database/database.sqlite`) for local development and automated tests.
- Use `QUEUE_CONNECTION=sync`, `CACHE_STORE=file` (or `array` in tests).
- Document **PostgreSQL + Redis + private object storage** as production/staging targets.
- Keep schema portable (Laravel migrations; avoid Postgres-only SQL in Phase 0/1).

## Consequences
+ Immediate runnable stack  
− JSONB, RLS, concurrent write nuances differ; re-validate on Postgres before production  
