# Araabi Global Commerce Platform — Phase 8

AGCP is a modular, tenant-aware digital and physical commerce platform. Phase 8 adds explainable AI-assisted analytics on top of Identity, Wallet, Commerce, Supplier, Rules, Fraud, Dynamic Pricing, SaaS and Plugin foundations.

## Phase 8 capabilities

- Tenant-isolated KPI snapshots
- Sales forecasting with basis window, confidence and daily points
- RFM-style customer segmentation
- Explainable supplier recommendations
- AI-assisted operational insights with stored evidence
- Model-run monitoring and failure records
- Synchronous, queued and scheduled analytics refresh
- Admin analytics dashboard
- Analytics permissions and SaaS entitlements

The default provider is local and deterministic. No tenant data is sent to an external AI service.

## Upgrade Phase 7 → Phase 8

Extract `agcp-phase8-upgrade-patch.zip` into `C:\Projects\agcp`, choose **Replace All**, preserve the existing `.env`, then run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase8.ps1
```

Verify:

```powershell
.\scripts\verify-phase8.ps1
```

## URLs

- AI and analytics administration: `http://localhost:8080/admin/analytics`
- SaaS and plugin administration: `http://localhost:8080/admin/saas`
- Commerce administration: `http://localhost:8080/admin/commerce`
- Supplier administration: `http://localhost:8080/admin/suppliers`
- Rules and fraud: `http://localhost:8080/admin/rules`

## Manual refresh

```powershell
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan analytics:refresh --tenant=araabi-global
```

See `docs/AI_ANALYTICS_AUTOMATION.md` and `docs/PHASE_8_REPORT.md`.
