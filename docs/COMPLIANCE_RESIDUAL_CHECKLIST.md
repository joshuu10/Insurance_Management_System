# Compliance Residual Checklist

Items **not** satisfied by software alone. Owner must complete before production.

| ID | Item | Status | Owner |
|----|------|--------|-------|
| C1 | Tanzania Insurance Act Cap. 394 — licence class & appointments | Open | Counsel + intermediary |
| C2 | s.72 premium routing — confirm no broker float in live config | Soft-enforced in code; counsel confirm | Compliance |
| C3 | s.71 retention ≥ 6 years — policy signed | Config baseline present | Compliance |
| C4 | TIRA-MIS authorization | OFF | Regulatory |
| C5 | PDPC registration & DPIA | Open | DPO |
| C6 | Cross-border transfer permit (if any) | Open | DPO |
| C7 | Insurer tariff / commission schedules — real contracts | Seed fictional only | Commercial |
| C8 | TRA/EFD if agency invoices issued | Adapter OFF | Finance |
| C9 | Accessibility WCAG 2.2 AA full audit | Partial (semantic MUI); audit pending | UX |
| C10 | Penetration test | Not run in Phase 6 | Security |
| C11 | Load test evidence vs SLOs | Not run; targets in PERFORMANCE.md | SRE |

Reference: `docs/ASSUMPTIONS_AND_BLOCKERS.md`, `docs/COMPLIANCE_DECISION_REGISTER.md`.
