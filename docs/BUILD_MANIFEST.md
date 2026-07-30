# Phase 11 Build Manifest

## Runtime

- Laravel 13 / PHP 8.4 backend
- Next.js 16 / React 19 frontend
- MySQL 8.4
- Redis queues and cache
- Nginx gateway

## Phase 11 modules

- Tenant and customer tax profiles
- Effective tenant tax rates
- Immutable invoice snapshots and content hashes
- Private checksummed CSV exports
- Scheduled report execution and run history
- Customer invoices and reporting administration pages

## Phase 11 automated tests included

- `ReportingInvoicingTest`
- Phase 1–10 backend regression tests
- Frontend TypeScript validation

## Migration

- `2026_07_30_910001_create_reporting_invoice_tax_tables.php`
