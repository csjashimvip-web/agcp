# Phase 7 Build Manifest

- Build date: 2026-07-30
- Platform: Araabi Global Commerce Platform (AGCP)
- Phase: 7 — Multi-Tenant SaaS and Plugin Marketplace
- Upgrade base: Phase 6 Rules, Fraud and Dynamic Pricing
- Runtime baseline: Laravel 13 / PHP 8.4 / Next.js 16 / React 19.2 / Node.js 22 / MySQL 8.4 / Redis / Nginx

## Static validation completed

- All backend PHP files passed `php -l`.
- Local application class references were resolved against declared classes.
- JSON and YAML documents parsed successfully.
- Shell scripts passed `bash -n` where applicable.
- TypeScript and TSX source files passed compiler-based syntactic parsing.
- Git diff whitespace validation passed.
- Full and patch ZIP integrity checks are performed during artifact packaging.

## Phase 7 automated tests included

- subscription entitlement and limit resolution;
- locked monthly quota enforcement;
- encrypted plugin secrets and lifecycle events;
- isolated tenant provisioning with owner membership and tenant roles;
- Phase 6 Rules/Fraud regression verification;
- Phase 5 Supplier Engine regression verification;
- frontend TypeScript verification in the Docker runtime.

## Runtime validation required on the receiving machine

The artifact environment does not include Docker or the project's Composer dependency tree. Its internal npm mirror also lacks some project packages. Therefore these checks are intentionally delegated to the user's verified Docker Desktop environment:

- full Docker image build;
- Composer dependency boot;
- MySQL migration execution;
- Laravel feature-test execution;
- Next.js full typecheck and production build.

Run `scripts/upgrade-phase7.ps1`, then `scripts/verify-phase7.ps1`.
