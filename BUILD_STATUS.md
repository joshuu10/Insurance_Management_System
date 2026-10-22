# BUILD_STATUS

**Updated:** 2026-10-19  
**Current phase:** Customer portal CUS-01…31 coverage (sandbox where conditional)  
**Edition default:** `standalone` (`APP_EDITION=saas` enables platform APIs)

## Completed

- [x] Phases 0–6 agency/brokerage MVP
- [x] Role-aware staff web UI
- [x] Customer portal baseline (login, policies, FNOL)
- [x] **CUS-\* customer journeys** — register, OTP sandbox, password reset, profile, products/enquiries, documents, premiums/payments sandbox, claims evidence + timeline, renewals, support tickets, notifications/communications

## Customer portal

- Login: `customer@demo.wabantu.local` / `Password1!` → `/portal`
- Register: `/portal/register` · Forgot password: `/portal/forgot-password`
- Checklist: `docs/CUS_PORTAL_CHECKLIST.md`
- Payments/OTP/document binaries are **sandbox / metadata** — not live insurer or SMS providers

## Tests

```
42 passed (200 assertions)
Web build: succeeded
```

## Demo credentials

| Scope | Email | Password |
|-------|-------|----------|
| Customer portal | `customer@demo.wabantu.local` | `Password1!` |
| Tenant admin | `admin@demo.wabantu.local` | `Password1!` |
| Tenant agent | `agent@demo.wabantu.local` | `Password1!` |
| Platform | `platform@wabantu.local` | `Password1!` (when `APP_EDITION=saas`) |

## Local note

If MySQL is stopped, start it then run `php artisan migrate:fresh --seed` in `apps/api` before using the UI against the API.

## Next

Optional: real payment gateway + object storage for documents, live SMS/email OTP, native mobile customer parity.
