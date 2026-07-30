# Phase 3 Seeder and Wallet Test Hotfix

## Symptoms

1. `upgrade-phase3.ps1` failed with `ModelNotFoundException` because it invoked `IdentitySeeder` directly while the default tenant had not yet been created.
2. The balanced-journal feature test compared SQLite's aggregate result string (`"5000"`) with an integer (`5000`) using Pest's strict `toBe` assertion.

## Corrections

- The upgrade now runs `DatabaseSeeder`, which creates the default tenant and localhost tenant domain before invoking `IdentitySeeder`.
- The ledger aggregate is explicitly converted to an integer before the strict assertion. Eloquent model casts do not apply to query-builder aggregate results.
- `scripts/repair-phase3-hotfix1.ps1` repairs an already-running installation without deleting MySQL or Redis volumes.

## Data safety

The repair script is idempotent. It runs existing migrations, idempotent seeders, cache clearing, focused wallet tests, frontend type checking, and the live health check. It does not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or remove persistent data volumes.
