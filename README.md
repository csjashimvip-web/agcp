# Araabi Global Commerce Platform — Phase 5

AGCP Phase 5 adds the Smart Supplier Engine on top of the verified Identity, Enterprise Wallet and Commerce Core foundations.

## Capabilities

- Tenant-scoped supplier accounts and encrypted credentials
- Supplier-to-catalog service mappings
- Balanced, cheapest, fastest, highest-success and priority routing
- Auditable candidate score snapshots and selection reasons
- Provider adapter registry
- Offline sandbox provider for local verification
- Queue-based automatic submission
- Scheduled supplier status polling
- Health probes, latency and success-rate metrics
- Consecutive-failure circuit protection
- Automatic supplier failover
- Item-level automatic wallet refund after terminal failure
- Supplier administration dashboard

## Upgrade an existing Phase 4 project

Extract the Phase 5 upgrade patch and copy all files into `C:\Projects\agcp`, choosing **Replace All**. Keep the existing `.env`.

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase5.ps1
```

Verify:

```powershell
.\scripts\verify-phase5.ps1
```

## URLs

- Home: `http://localhost:8080`
- Catalog: `http://localhost:8080/catalog`
- Orders: `http://localhost:8080/orders`
- Supplier administration: `http://localhost:8080/admin/suppliers`
- Commerce administration: `http://localhost:8080/admin/commerce`
- API health: `http://localhost:8080/api/v1/health`

## Demonstration flow

1. Sign in as the administrator and confirm 2FA.
2. Open Supplier administration and inspect the seeded Fast and Economy sandbox suppliers.
3. Run a health check from the supplier dashboard.
4. Approve customer balance from Wallet administration.
5. Sign in as the customer and purchase **IMEI Status Check**.
6. The committed order is published through the transactional outbox and queued for supplier routing.
7. Open Orders and Supplier administration to inspect fulfillment status, routing evidence and supplier reference.

To test failover, set `sandbox_fail_submissions` to `true` in one sandbox supplier's metadata through code or an API client. To test automatic refund, configure every eligible sandbox supplier to fail.

See `docs/SMART_SUPPLIER_ENGINE.md` and `docs/PHASE_5_REPORT.md`.
