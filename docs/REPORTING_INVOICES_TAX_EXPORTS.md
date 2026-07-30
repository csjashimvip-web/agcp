# AGCP Enterprise Reporting, Invoices, Tax and Exports

Phase 11 introduces a tenant-scoped financial-document and business-reporting boundary. It does not replace the double-entry wallet ledger; invoices and exports are evidence views derived from commerce, wallet and payment records.

## Invoice integrity

An invoice is generated once per order. The seller profile, buyer profile, line descriptions, SKU, quantity, price, tax rule and totals are copied into immutable snapshots. A SHA-256 content hash is stored and printed in the HTML document. Replaying invoice generation returns the existing invoice instead of allocating a second number.

Invoice numbers are allocated while the tenant tax profile row is locked:

```text
AGCP-2026-000001
AGCP-2026-000002
```

Voiding an invoice records an append-only event and reason. Existing invoice lines are not rewritten.

## Tax foundation

Tax rates are tenant scoped and effective-date aware. Rules can target all items or physical, digital and service items. Inclusive calculation extracts tax from an already-paid gross price. Exclusive calculation records the additional tax as invoice amount due. Customer tax exemptions and exemption references are snapshot into the invoice.

This is a configurable tax foundation, not jurisdiction-specific legal or accounting advice. Before production use, a qualified local tax professional must approve rates, invoice wording, registration numbers, rounding and filing treatment.

## Exports

Supported checksummed CSV exports:

- orders
- invoices
- payments
- deposits
- tax summary

Every export stores status, requested period, row count, file size, SHA-256 checksum, storage path and expiry. Exports are tenant isolated and downloaded only through authenticated administration routes.

## Scheduled reports

Daily, weekly and monthly schedules run through Laravel Scheduler. Each run records its exact period, metrics, export and failure evidence. The scheduler command is:

```bash
php artisan reports:run-due --tenant=araabi-global
```

Missing paid-order invoices can be generated idempotently:

```bash
php artisan invoices:generate-missing --tenant=araabi-global
```

## Pages

- Customer invoices: `/invoices`
- Reporting administration: `/admin/reports`
