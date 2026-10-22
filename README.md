# Wabantu Insurance Ecosystem

Tanzania insurance **agency & brokerage** management platform by **Wabantu Technologies**.  
Web (React + MUI) · Android/iOS (Expo) · Laravel API · Standalone + SaaS (one codebase).

**Phase status:** Phase 0 foundation + Phase 1 identity/RBAC vertical slice. See [`BUILD_STATUS.md`](BUILD_STATUS.md).

## Local prerequisites (this machine)

- PHP 8.3+, Composer, Node 22+
- **SQLite** (default) — PostgreSQL and Docker are **not** required for Phase 0/1
- Production target remains PostgreSQL + Redis + private object storage (documented; not installed here)

## Quick start

```powershell
# API
cd "apps\api"
composer install
copy .env.example .env   # if needed
php artisan key:generate
# ensure database\database.sqlite exists
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000

# Web (new terminal)
cd "apps\web"
npm install
npm run dev

# Mobile (new terminal)
cd "apps\mobile"
npm install
npx expo start
```

### Demo credentials (fictional seed)

| User | Email | Password | Role |
|------|-------|----------|------|
| Admin | `admin@demo.wabantu.local` | `Password1!` | Managing Director |
| Agent | `agent@demo.wabantu.local` | `Password1!` | Agent (no tenant.configure) |

## Editions

- `APP_EDITION=standalone` (default) — single intermediary
- `APP_EDITION=saas` — SaaS control plane (stubs in Phase 1; full lifecycle later)

## Compliance defaults

- Premium collection: **insurer-directed only** (`ALLOW_AGENT_PREMIUM_COLLECTION=false`)
- TIRA / insurer / payment integrations: **NOT_CONFIGURED**
- Illustrative carrier seed is labeled inactive/non-partner

## Tests

```powershell
cd apps\api
php artisan test
```

## Repository layout

```
apps/api       Laravel 13 API
apps/web       React 19 + MUI 9
apps/mobile    Expo SDK 57
packages/*     design tokens, shared i18n, api-client stub
docs/          ADRs, assumptions, OpenAPI
```
