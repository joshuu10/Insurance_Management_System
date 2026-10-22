# UAT Script — Wabantu Insurance (Phases 0–5)

**Environment:** staging or local with seeded demo data  
**Edition under test:** ☐ standalone  ☐ saas  
**Tester / date:** _______________

Use fictional seed only. Do **not** treat sandbox statuses as live insurer/TIRA confirmations.

## Preconditions

1. `php artisan migrate:fresh --seed` (or staging equivalent)
2. API up on configured URL; web `npm run dev`; mobile optional
3. Demo logins work: `admin@demo.wabantu.local` / `Password1!`

## Golden journeys

| # | Journey | Steps | Pass? | Notes |
|---|---------|-------|-------|-------|
| 1 | Login + RBAC | Admin login → shell loads; agent cannot open Finance overview successfully | ☐ | |
| 2 | Onboarding | Admin completes org licence fields; audit event exists | ☐ | Counsel still pending |
| 3 | CRM | Create customer + lead | ☐ | |
| 4 | Quote sandbox | Create quote on appointed carrier → sandbox submit → premium still null/awaiting | ☐ | |
| 5 | Unappointed block | Quote against unappointed carrier fails | ☐ | |
| 6 | Policy intake | Intake insurer-issued policy; verification `not_checked` | ☐ | |
| 7 | Renewal | Upcoming renewals lists demo policy; open renewal case | ☐ | No auto-charge |
| 8 | Premium instruction | Create insurer-directed instruction; agency float `routes_to_insurer=false` blocked | ☐ | |
| 9 | FNOL | Agent drafts/submits claim; admin acknowledges; insurer update needs evidence | ☐ | |
| 10 | Commission journal | Expect → confirm → receive; journal balanced; cannot delete journal | ☐ | |
| 11 | Integrations | Settings → enable sandbox + probe; `fabricated_confirmation=false` | ☐ | |
| 12 | SaaS (if edition=saas) | Platform login → provision tenant → software invoice ledger=`platform_software` → suspend | ☐ | |
| 13 | Isolation | Tenant token cannot list platform tenants; X-Tenant-ID ignored on `/me` | ☐ | |
| 14 | i18n | Toggle EN/SW on web shell | ☐ | |
| 15 | CORS/login | Web from Vite origin can login | ☐ | |

## Sign-off

- Product: _____________  
- Compliance (interim): _____________  
- Engineering: _____________  

**Blockers remaining:** TIRA credentials, insurer contracts, payment beneficiary, PDPC, pen-test (see ASSUMPTIONS_AND_BLOCKERS.md).
