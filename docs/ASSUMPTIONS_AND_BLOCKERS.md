# Assumptions & Blocker Register

**Product:** Wabantu Technologies — Tanzania Insurance Agency & Brokerage Management Ecosystem  
**Last reviewed:** 2026-10-15  
**Edition default:** `standalone` (`APP_EDITION=standalone|saas`)

## Safe defaults (proceed without legal sign-off)

| ID | Topic | Assumption | Owner | Risk if wrong |
|----|-------|------------|-------|---------------|
| A01 | Intermediary type | Seed as **brokerage**; type is configurable and audited | Product | Agent-mode appointment rules differ |
| A02 | Premium routing | Insurer-directed only (Insurance Act Cap. 394 s.72). Broker float collection feature flag **OFF** | Compliance | Illegal float handling |
| A03 | Retention | Configurable ≥ 6 years for broker records (s.71); no hard-delete of issued/financial/audit | Compliance | Premature erasure |
| A04 | Currency/locale | TZS, `en-TZ` + `sw-TZ`, `Africa/Dar_es_Salaam`, E.164 +255 | Product | Zanzibar exceptions need config |
| A05 | Rating | No invented tariffs; quotes may be “awaiting insurer” | Domain | Fake premiums |
| A06 | Integrations | TIRA / insurer / payment / NIDA adapters exist as ports; state `NOT_CONFIGURED` | Integrations | Fabricated confirmations |
| A07 | Local data store | **SQLite** for Phase 0/1 local + tests (no Postgres/Docker on this machine). Production target remains PostgreSQL | Engineering | Feature drift vs Postgres |
| A08 | Queues/cache | `sync` queue + `file`/`array` cache until Redis available | Engineering | No Horizon locally |
| A09 | Carrier seed | Illustrative inactive “Alliance (example)” row — **not a partnership** | Legal | Brand misuse |

## Blockers (cannot invent; block production only)

| ID | Blocker | Needed for | Status |
|----|---------|------------|--------|
| B01 | Licensed intermediary classification & TIRA registration | Go-live, appointment enforcement | Open |
| B02 | Official insurer API credentials / contracts | Live quotes, policy bind, claim updates | Open |
| B03 | Regulated premium payment beneficiary accounts | Live premium checkout | Open |
| B04 | Authorized TIRA-MIS access | e-sticker / verification | Open — adapter OFF |
| B05 | PDPC registration & cross-border permit | Non-TZ hosting/transfers | Open |
| B06 | Production infra (TLS, Postgres, Redis, object storage, backups) | Managed deploy | Deferred (local SQLite) |
| B07 | Apple Developer / Google Play signing | Store releases | Open |

## Jurisdiction references (verify before go-live)

- Tanzania Insurance Act, Cap. 394 R.E. 2023
- TIRA rules / TIRA-MIS (authorized access only)
- Personal Data Protection Act, 2022 and regulations, 2023
- AML/CFT, TRA/EFD where applicable

**Counsel must validate** jurisdictional settings before production activation.
