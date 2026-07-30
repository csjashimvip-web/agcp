# Phase 9 Completion Report

## Objective

Add a provider-neutral, replay-safe payment orchestration boundary that can credit customer wallets only after a verified event and can prove consistency between provider activity, deposits, ledger journals and refunds.

## Delivered

- Payment provider account model with encrypted secrets and credentials
- Provider registry and sandbox payment/refund adapter
- Payment intent and attempt lifecycle
- HMAC webhook verification and replay-window enforcement
- Encrypted webhook inbox and external-event deduplication
- Automated gateway deposit settlement through the Phase 3 double-entry ledger
- Customer cancellation before settlement
- Controlled full and partial refunds with wallet-balance reservation
- Financial reconciliation command, service, tables and admin action
- Payment permissions for customer and tenant administrators
- Customer and admin Next.js pages
- Upgrade and verification scripts
- Seven Phase 9 feature tests plus previous-phase regression commands

## Financial invariants

- Client callbacks never credit money.
- Verified captured amount and currency must equal the server-created intent.
- One payment intent can create only one deposit request.
- One provider event ID is processed only once per provider account.
- Wallet credit and approved deposit share a ledger reference.
- Refund amount cannot exceed remaining credited amount.
- Refund reversal cannot make the wallet negative.
- A provider-confirmed refund that has not settled locally remains held and is surfaced by reconciliation.

## Integration status

The sandbox provider is fully local and suitable for end-to-end testing. Live bKash, Stripe, PayPal or bank-gateway adapters are not represented as active integrations. They require official merchant credentials, provider-specific webhook rules and certification before activation.

## Migration

- `2026_07_30_800001_create_payment_orchestration_tables.php`

## Verification command

```powershell
.\scripts\verify-phase9.ps1
```

The package was statically validated in the artifact environment. Full Docker migration, Laravel feature tests and Next.js type checking are executed by the verification script in the user's running environment.
