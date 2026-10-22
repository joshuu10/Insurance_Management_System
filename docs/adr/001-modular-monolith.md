# ADR 001 — Modular monolith (not microservices)

## Status
Accepted — 2026-10-15

## Context
The ecosystem spans web, Android, iOS, and dual edition (standalone + SaaS). Premature microservices would multiply auth, tenancy, and deployment complexity.

## Decision
Ship a **Laravel modular monolith** with domain modules (Identity, Tenant, Organization, CRM, …). Clients consume versioned `/api/v1`. Split services only after proven scaling or team boundaries.

## Consequences
+ Single deployable API, shared transactions, simpler Phase 0–4  
− Must enforce module boundaries via folders/namespaces and tests  
