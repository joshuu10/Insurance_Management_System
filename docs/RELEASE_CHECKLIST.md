# Release Checklist

## Build & quality

- [ ] `cd apps/api && php artisan test` — all green  
- [ ] `cd apps/web && npm run build` — succeeds  
- [ ] Mobile starts (`npx expo start`) on representative device/simulator  
- [ ] OpenAPI stub reviewed (`docs/openapi/`)  
- [ ] `BUILD_STATUS.md` updated with evidence  

## Configuration

- [ ] `APP_KEY` set; debug off in production  
- [ ] `APP_EDITION` correct (`standalone` or `saas`)  
- [ ] `CORS_ALLOWED_ORIGINS` lists real frontends only  
- [ ] `ALLOW_AGENT_PREMIUM_COLLECTION=false` unless counsel-approved  
- [ ] DB backups configured  
- [ ] No production secrets in git  

## Security

- [ ] HTTPS/TLS + HSTS at edge  
- [ ] Rate limits enabled on auth routes  
- [ ] Dependency audit reviewed (`composer audit` / `npm audit`) — record date  
- [ ] Pen-test scheduled or completed (not claimed here)  

## Compliance / partners (blockers)

- [ ] Intermediary licence classification confirmed  
- [ ] Insurer appointments & APIs authorized  
- [ ] Premium beneficiary accounts verified  
- [ ] TIRA-MIS access authorized or explicitly OFF  
- [ ] PDPC registration / cross-border assessed  
- [ ] UAT signed (`docs/UAT_SCRIPT.md`)  

## Rollback

1. Redeploy previous release artifact / git tag  
2. Restore DB only if migration is backward-incompatible and drill-tested  
3. Disable new integrations via status flags  
4. Communicate status to operators  

## Store releases (mobile)

- [ ] Apple Developer / Google Play accounts available  
- [ ] Privacy nutrition labels / data safety forms  
- [ ] EAS/local signing configured  
- [ ] Do not claim store submission without proof  
