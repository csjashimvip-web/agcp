# AGCP Phase 6 — Rules, Fraud and Dynamic Pricing

## Rule engine
Rules are tenant-scoped, versioned and deterministic. A published version contains an `all` or `any` condition group and a list of actions. Supported condition operators include equality, numeric comparison, list membership, text containment, ranges and presence checks. Every evaluation writes a `rule_executions` record with its input and result snapshot.

## Dynamic pricing
The base catalog price remains the source of truth. Published pricing rules may apply percentage or fixed discounts and surcharges, or set an explicit final unit price. Checkout recalculates the price server-side and stores the base price, adjustment, matched rules and breakdown on each order item.

## Fraud scoring
The deterministic risk engine combines built-in signals with published fraud rules. Built-in signals cover untrusted devices, new accounts, high-value orders, critical-value orders, repeated rejected deposits and optional network reputation. Decisions are `allow`, `step_up`, `review` or `block`.

Orders with a `review` decision are paid but held from supplier fulfillment. An authorized reviewer can approve and release fulfillment, or reject and refund the order. A `block` decision creates an assessment but prevents checkout.

## Data structures
- `rules`
- `rule_versions`
- `rule_executions`
- `fraud_risk_assessments`
- `fraud_signals`
- `price_quotes`

## Security controls
- Tenant isolation
- Admin 2FA and granular permissions
- Versioned, checksum-protected rule definitions
- Immutable execution and risk-signal history
- Server-side price revalidation
- Database transactions and row locking
- Fraud review maker workflow
- Supplier fulfillment hold until approval
