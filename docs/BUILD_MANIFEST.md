# Phase 5 Build Manifest

- Build date: 2026-07-30
- Platform: Araabi Global Commerce Platform (AGCP)
- Phase: 5 — Smart Supplier Engine
- Upgrade base: Phase 4 Commerce Core package
- Runtime baseline: Laravel 13 / PHP 8.4 / Next.js 16 / React 19.2 / Node.js 22 / MySQL 8.4 / Redis / Nginx

## Static validation completed

- All backend PHP files passed `php -l`.
- JSON documents parsed successfully.
- YAML documents parsed successfully.
- Shell scripts passed `bash -n`.
- TypeScript and TSX source files passed compiler-based syntactic parsing.
- Migration foreign-key order and tenant-scoping paths received manual code review.
- Routing, failover, polling, health scoring and refund paths received manual code review.

## Automated tests included

The package includes feature tests for:

- successful supplier routing and completion;
- automatic failover after a submission failure;
- item-level wallet refund when all suppliers fail;
- cancellation-race protection after supplier work is created;
- all prior commerce, wallet and identity tests.

## Runtime validation required on the receiving machine

The artifact environment does not include the project's Composer dependencies or Docker daemon. Its internal npm mirror also does not contain `@laravel/passkeys`. Therefore, these checks are intentionally delegated to the user's verified Docker Desktop environment:

- full Docker image build;
- Composer dependency boot;
- MySQL migration execution;
- Laravel feature-test execution;
- Next.js full typecheck and production build.

Run `scripts/upgrade-phase5.ps1`, then `scripts/verify-phase5.ps1`.
