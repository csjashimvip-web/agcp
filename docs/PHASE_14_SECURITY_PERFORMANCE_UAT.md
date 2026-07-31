# Phase 14 — Security, Performance & UAT

## Security verification

Local audit:

```powershell
.\scripts\security-audit.ps1 -EnvironmentFile .env
```

Production audit:

```powershell
.\scripts\security-audit.ps1 -EnvironmentFile .env.production -Production
```

Production audit requires:

- `APP_DEBUG=false`
- HTTPS application URL
- secure cookies
- encrypted sessions
- configured application and backup keys
- real mail transport
- `.env` excluded from Git

## Regression suite

```powershell
.\scripts\verify-phase14.ps1
```

It runs:

- complete Laravel test suite
- Next.js lint
- TypeScript typecheck
- public route smoke tests
- authentication-boundary test
- lightweight health endpoint load smoke

## Manual UAT areas

Test with separate customer, tenant-admin, and platform-admin accounts:

1. Registration, login, logout, reset, verification and 2FA
2. Tenant isolation and role boundaries
3. Wallet credit/debit and deposit approval
4. Catalog, cart, checkout and order lifecycle
5. Supplier routing and retry
6. Fraud review and pricing rules
7. SaaS plans and tenant entitlements
8. Payment initiation, webhook, reconciliation and refund
9. Notifications, support, invoices and reports
10. Backup creation, verification and readiness checks

## Browser and device matrix

- Current Chrome
- Current Edge
- Current Firefox
- Android mobile viewport
- iPhone mobile viewport
- Keyboard-only navigation for critical forms

## Performance acceptance baseline

The included load smoke is not a full load test. Before launch, define production targets for:

- p95 API latency
- maximum error rate
- concurrent users
- checkout throughput
- wallet/payment race conditions
- queue processing delay
- database CPU and slow queries

Use a dedicated load-testing environment with anonymized or synthetic data.
