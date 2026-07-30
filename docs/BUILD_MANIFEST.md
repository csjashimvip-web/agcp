# Phase 4 Build Manifest

- Build date: 2026-07-30
- Platform: Araabi Global Commerce Platform (AGCP)
- Phase: 4 — Commerce Core
- Upgrade base: Phase 3 Hotfix 1 Enterprise Wallet package
- Runtime baseline: Laravel 13 / PHP 8.4 / Next.js 16 / React 19.2 / Node.js 22 / MySQL 8.4 / Redis / Nginx

## Static validation completed

- All backend PHP files passed `php -l`.
- JSON documents parsed successfully.
- YAML documents parsed successfully.
- Shell scripts passed `bash -n`.
- TypeScript and TSX source files passed compiler-based syntactic parsing.
- Catalog, pricing, inventory, checkout, ledger integration and order transition paths received manual code review.

## Automated tests included

The package includes feature tests for:

- wallet-funded checkout and balanced ledger debit;
- inventory reservation;
- checkout idempotency;
- cancellation refund and inventory release;
- required service configuration;
- all prior identity and wallet tests.

## Runtime validation required on the receiving machine

Docker is unavailable inside the artifact build environment. Therefore, the following are intentionally left for the user's verified Docker Desktop environment:

- full Docker image build;
- Composer dependency boot;
- MySQL migration execution;
- Laravel feature-test execution;
- Next.js full typecheck and production build.

Run `scripts/upgrade-phase4.ps1`, then `scripts/verify-phase4.ps1`.
