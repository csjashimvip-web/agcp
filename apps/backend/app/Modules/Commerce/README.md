# Commerce Module

Phase 4 owns the tenant catalog, variants, price lists, inventory, carts, wallet checkout and order lifecycle.

## Boundary rules

- Commerce reads wallet availability through the Wallet module and posts money only through `LedgerService`.
- Commerce never writes `ledger_accounts.balance_minor` directly.
- Supplier submission and automated fulfillment are intentionally deferred to Phase 5.
- Every catalog and order query is tenant-scoped.
- Prices and product names are snapshotted into order items.
- Tracked inventory is reserved before the wallet journal is posted; the surrounding database transaction rolls back both on failure.
