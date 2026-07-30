# Wallet Module

Phase 3 implements tenant-scoped, multi-currency wallets backed by an immutable double-entry ledger.

## Invariants

- Money is stored as integer minor units; floating-point values never reach the ledger.
- Every posted journal has equal debits and credits in one currency.
- Ledger entries and posted transactions cannot be updated or deleted.
- Account rows are locked inside database transactions before balances change.
- Customer deposits are pending until an authorized reviewer approves them.
- Manual balance adjustments use maker-checker separation: the requester cannot approve their own request.
