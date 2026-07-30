# AGCP Phase 11 Completion Report

## Delivered

- Tenant tax profiles and customer billing/tax identities
- Effective-date tax rates for physical, digital and service items
- Inclusive and exclusive tax calculation foundation
- Concurrency-safe invoice numbering
- One immutable invoice per order
- Invoice line, tax, seller and buyer snapshots
- SHA-256 invoice integrity hashes
- Customer HTML invoice documents
- Invoice void audit events
- Orders, invoices, payments, deposits and tax-summary CSV exports
- Export row counts, file sizes, checksums and retention metadata
- Daily, weekly and monthly scheduled reports
- Report-run history and captured metrics
- Customer invoice center and admin reporting dashboard
- Phase 11 feature tests and Phase 1–10 regression verification script

## Security and data safety

- All queries are tenant scoped.
- Customer invoice access is owner scoped.
- Admin endpoints require verified sessions, administrator 2FA and reporting permissions.
- Export files are stored on the private filesystem disk.
- Upgrade scripts do not run `migrate:fresh`, `db:wipe` or `docker compose down -v`.
- Existing users, balances, orders, payments and operational records remain intact.

## Runtime note

Static PHP, JSON, YAML, XML and artifact integrity validation is performed during packaging. Live Docker migration, MySQL execution, Laravel feature tests and Next.js type checking must be run in the user's verified Docker environment with `scripts/verify-phase11.ps1`.
