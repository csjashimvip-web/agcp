# Araabi Global Commerce Platform — Phase 7

AGCP is a modular, tenant-aware digital and physical commerce platform. Phase 7 adds the SaaS control plane and approved plugin marketplace on top of Identity, Wallet, Commerce, Supplier, Rules, Fraud and Dynamic Pricing foundations.

## Phase 7 capabilities

- Multi-tenant plan catalog and subscription lifecycle
- Nested feature entitlements and numeric quotas
- Locked monthly usage counters
- Tenant provisioning with owner membership and tenant-admin role
- White-label branding profiles
- Custom-domain verification lifecycle
- Public tenant configuration API for headless clients
- Approved manifest-based plugin marketplace
- Encrypted tenant plugin configuration
- Install, configure, enable and disable lifecycle events
- Platform-only tenant and subscription controls
- Tenant-scoped branding, domains and plugin administration

## Security model

Phase 7 does not accept arbitrary PHP, JavaScript, Composer, NPM or ZIP plugin uploads. Marketplace records point only to approved provider keys already reviewed and shipped with AGCP. Secret configuration values use Laravel encrypted casts and are never returned by the API.

## Upgrade Phase 6 → Phase 7

Extract `agcp-phase7-upgrade-patch.zip` into `C:\Projects\agcp`, choose **Replace All**, preserve the existing `.env`, then run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase7.ps1
```

Verify:

```powershell
.\scripts\verify-phase7.ps1
```

## URLs

- SaaS and plugin administration: `http://localhost:8080/admin/saas`
- Tenant configuration API: `http://localhost:8080/api/v1/tenant/configuration`
- Identity administration: `http://localhost:8080/admin`
- Commerce administration: `http://localhost:8080/admin/commerce`
- Supplier administration: `http://localhost:8080/admin/suppliers`
- Rules and fraud: `http://localhost:8080/admin/rules`

## Seeded plans

- Starter
- Growth
- Enterprise

The existing `araabi-global` tenant receives the Enterprise plan so all previously delivered Phase 1–6 capabilities remain available after upgrade.

See `docs/SAAS_AND_PLUGIN_MARKETPLACE.md` and `docs/PHASE_7_REPORT.md`.
