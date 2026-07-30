# AGCP Smart Supplier Engine

## Purpose

Phase 5 adds an isolated supplier automation module to the existing commerce, wallet and outbox foundations. Checkout remains supplier-agnostic. A published `commerce.order.placed` outbox event creates supplier work only for catalog items whose `fulfillment_mode` is `supplier_api`.

## Processing flow

```text
Wallet checkout committed
        ↓
commerce.order.placed stored in transactional outbox
        ↓
Outbox publisher emits the committed event
        ↓
Supplier order created per automated order item
        ↓
Supplier queue selects an eligible mapping
        ↓
Provider adapter submits the request
        ↓
Completed immediately OR scheduled status polling
        ↓
Order/item status synchronized
```

If a submission fails, the failed supplier is excluded and the next eligible mapping is scored. When no eligible supplier remains, the item is refunded through a balanced ledger journal.

## Module boundaries

```text
Modules/Suppliers
├── Domain
│   ├── Contracts/SupplierProvider.php
│   └── Enums
├── Application
│   ├── Jobs
│   └── Services
├── Infrastructure
│   ├── Console
│   ├── Listeners
│   ├── Models
│   └── Providers
└── Http
    ├── Controllers/Admin
    └── Resources
```

## Provider isolation

Every external integration implements `SupplierProvider`. Core fulfillment code only calls:

- `health`
- `submit`
- `status`
- `cancel`

Credentials are stored with Laravel's encrypted array cast and are never returned by API resources. Phase 5 includes an offline sandbox provider for deterministic local tests. Real providers can be registered in `SupplierServiceProvider` without editing checkout logic.

## Routing strategies

A tenant routing profile supports:

- Balanced
- Cheapest
- Fastest
- Highest success rate
- Priority

The balanced score combines cost, observed success rate, latency, health score and priority. Consecutive failures add a penalty. Every routing decision persists the full candidate snapshot and human-readable reason.

## Health and circuit protection

Live requests update:

- total requests
- successful and failed requests
- success rate
- average latency
- consecutive failures
- last success/failure timestamps

Three consecutive failures temporarily disable routing to the supplier. The scheduled `supplier:health-check` command also probes provider health every five minutes.

## Queue and polling

Supplier submission and polling use the existing `supplier` critical queue. Jobs use overlap locks per supplier order. Scheduled commands are:

```text
supplier:poll
supplier:health-check
```

## Automatic refund

A terminal supplier failure posts:

```text
Commerce sales revenue   Debit
Customer wallet          Credit
```

The refund is item-level, references the supplier order, and stores the resulting immutable ledger transaction on `supplier_orders.refund_ledger_transaction_id`.

## Seeded demonstration

The IMEI Status Check is changed to `supplier_api` fulfillment. Two sandbox mappings are seeded:

- Sandbox Fast Supplier
- Sandbox Economy Supplier

The default balanced profile selects using stored operational evidence. Admins can inspect suppliers and supplier orders at `/admin/suppliers`.

## Cancellation consistency

Supplier work creation and commerce cancellation both lock the parent order. Once a supplier-order record exists, the normal commerce cancellation and manual status-transition paths are blocked. This prevents a customer refund from racing with an external supplier submission. Orders canceled before outbox processing are ignored by the supplier listener. A dedicated supplier-cancellation workflow can be added later for providers that support reliable remote cancellation.
