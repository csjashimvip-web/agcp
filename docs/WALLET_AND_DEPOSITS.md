# AGCP Phase 3 — Wallet and Deposit Architecture

## Accounting model

AGCP stores monetary values as integer minor units. A value of `1250` in USD means `12.50 USD`. Each posted transaction contains two or more entries, and total debits must equal total credits in one currency.

Customer wallets are liability accounts with a normal credit balance. System clearing accounts are assets with a normal debit balance. Approving a deposit debits the clearing asset and credits the customer wallet liability.

## Concurrency controls

Ledger accounts are selected in deterministic ID order and locked with `SELECT ... FOR UPDATE` inside a MySQL transaction. This prevents simultaneous approvals or adjustments from overwriting one another. Laravel's transaction retry count is set to five for deadlock recovery.

## Immutability

Posted ledger transactions and entries cannot be updated or deleted through Eloquent. Corrections must be represented by new reversing journal entries in a future workflow.

## Deposit workflow

1. Customer submits a pending request.
2. An administrator independently verifies the payment.
3. Approval locks the request and ledger accounts.
4. A balanced journal is posted.
5. The deposit is marked approved in the same database transaction.
6. An outbox event and audit record are written.

## Manual adjustments

Balance adjustments use maker-checker separation. One administrator requests the adjustment and a different administrator must approve it.
