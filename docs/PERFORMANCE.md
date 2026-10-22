# Performance Targets & How to Measure

**Evidence status:** Targets only — Phase 6 did **not** execute formal load tests on this workstation.

## Reference targets (from product spec)

| Area | Target |
|------|--------|
| API availability (managed SaaS) | 99.9% monthly aspiration |
| p95 authenticated read | ~300–500ms under representative load (excludes slow external APIs) |
| Web LCP | p75 &lt; 2.5s on defined profiles |
| Mobile nav | smooth ~60fps ordinary interactions |

## Suggested local smoke

```powershell
# Repeated health (not a load test)
1..20 | ForEach-Object { Measure-Command { Invoke-RestMethod http://127.0.0.1:8000/api/v1/health } | Select-Object TotalMilliseconds }
```

## Before production

1. Seed ≥ representative volume (spec mentions large-tenant policy counts for later)  
2. Run k6/Locust against staging with auth  
3. Capture p95, error rate, DB slow queries  
4. Attach results under `docs/evidence/` and link from BUILD_STATUS  

## App-level mitigations already in place

- Paginated list endpoints  
- Sync queue locally (upgrade to Redis/Horizon in prod)  
- Async intent for PDFs/uploads (binary upload still deferred)
