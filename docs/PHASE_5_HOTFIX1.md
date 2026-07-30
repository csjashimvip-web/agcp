# AGCP Phase 5 Hotfix 1

## Problem

The Phase 4 commerce migration created the append-only order lifecycle table as
`order_status_history`. The `OrderStatusHistory` Eloquent model did not declare
that non-conventional table name, so Eloquent inferred `order_status_histories`.
Supplier feature tests therefore failed during checkout before supplier routing
could begin.

## Resolution

`OrderStatusHistory::$table` now explicitly points to `order_status_history`.
No migration, table rename, data copy, volume reset, or database rebuild is
required.

## Apply

Copy the hotfix patch over the project and run:

```powershell
.\scripts\repair-phase5-hotfix1.ps1
```

The repair script clears Laravel caches, restarts PHP services to invalidate
OPcache, runs the supplier feature suite, and runs commerce regression tests.
