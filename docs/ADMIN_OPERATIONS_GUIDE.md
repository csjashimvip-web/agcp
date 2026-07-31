# AGCP Admin Operations Guide

## Account security

- Use a unique administrator password.
- Confirm two-factor authentication.
- Keep recovery codes offline.
- Revoke unknown sessions and devices.
- Do not use personal API tokens for browser administration.

## Core operations

Platform administrators should regularly review:

- users, roles and permissions
- wallets, deposits and ledger entries
- orders, inventory and pricing
- suppliers and failed supplier orders
- fraud assessments and rule changes
- payment attempts, refunds and reconciliation
- notification templates and delivery failures
- support queues
- invoices, reports and exports
- reliability backups and readiness checks

## Change control

For sensitive changes:

1. Record the reason.
2. Confirm the correct tenant.
3. Use least-privilege access.
4. Capture approval where required.
5. Verify the result.
6. Review the audit trail.

## Backup operations

Before deployments and high-risk maintenance:

```bash
php artisan reliability:backup
php artisan reliability:verify-backup --latest
```

Do not expose backup files or encryption keys through the public web root.
