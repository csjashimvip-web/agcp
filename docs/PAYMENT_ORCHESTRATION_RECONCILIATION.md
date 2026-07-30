# Payment Orchestration and Financial Reconciliation

## Trust boundary

A browser redirect, client-side callback or customer screenshot never credits a wallet. A credit is posted only after the backend accepts a signed provider event inside the replay window and maps it to the exact tenant, provider account, payment reference, amount and currency.

```text
Customer creates payment intent
        ↓
Provider checkout initialized
        ↓
Provider sends signed event
        ↓
Signature + timestamp + event ID verified
        ↓
Payment intent amount/currency matched
        ↓
Gateway deposit created and approved automatically
        ↓
Balanced ledger journal posted
        ↓
Wallet credited + outbox + audit record
```

## Data model

- `payment_provider_accounts`: tenant provider configuration, encrypted credentials and encrypted webhook secret.
- `payment_intents`: customer request, provider reference, fee, total, state and expiry.
- `payment_attempts`: immutable operational attempt history.
- `payment_webhooks`: encrypted, deduplicated event inbox with verification outcome.
- `payment_refunds`: provider refund and matching wallet reversal.
- `payment_reconciliation_runs` and `payment_reconciliation_items`: stored financial consistency evidence.
- `deposit_requests.payment_intent_id`: one-to-one link between the verified payment and wallet deposit.

## Idempotency

Customer create and admin refund endpoints require a 16–128 character `Idempotency-Key`. The database stores only a SHA-256 hash and a request hash. Reusing a key with different parameters is rejected. Provider event IDs are unique per provider account, preventing duplicate delivery from posting a second wallet credit.

## Webhook security

The sandbox adapter signs `timestamp.payload` with HMAC-SHA256. The receiver:

1. Resolves the tenant and exact active provider account from the account-specific webhook path (or the compatibility header).
2. Rejects timestamps outside the configured tolerance.
3. Compares signatures with `hash_equals`.
4. Verifies required event fields.
5. Stores signature and payload hashes.
6. Deduplicates the external event ID.
7. Rejects amount, currency and provider-payment-ID mismatches.

Production adapters must implement their provider's official signature format inside the same contract and should preserve the same replay, idempotency and amount-matching invariants.

## Refund safety

An external refund is allowed only for a completed or partially refunded payment. Completed refunds cannot exceed the original wallet credit. The customer wallet must still have enough available balance. A wallet hold reserves the refundable balance before the provider call. Provider confirmation happens before the ledger reversal:

```text
Customer wallet liability    Debit
Gateway clearing asset       Credit
```

If the customer spent the balance, the automated refund is blocked instead of creating a negative wallet.

## Reconciliation

The reconciliation service reports:

- Completed payment without a deposit
- Deposit not approved
- Amount or currency mismatch
- Approved deposit without ledger transaction
- Stale pending intent after expiry
- Refund overage
- Orphan approved gateway deposit
- Completed refund without reversal ledger
- Provider-confirmed refund still waiting for local ledger settlement

It stores each mismatch with severity, expected/actual values and evidence. The default job runs daily and can be triggered manually.

## Provider readiness

Phase 9 includes a complete offline sandbox provider. Approved live adapters can be added behind `PaymentProvider` and `RefundablePaymentProvider`. Live rollout additionally requires merchant onboarding, provider-specific certificates/secrets, verified callback domains, TLS and settlement-file/API reconciliation.
