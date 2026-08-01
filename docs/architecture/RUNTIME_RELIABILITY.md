# AGCP Runtime Reliability

## Required long-running processes

AGCP transactional request handling and background automation are deliberately
separated.

### Web/API
OpenLiteSpeed/CyberPanel serves Laravel HTTP traffic in production.

### Queue workers
Redis-backed Laravel workers process:
- supplier fulfillment
- supplier reconciliation
- default background jobs

### Scheduler
Laravel `schedule:work` drives:
- supplier-order polling
- transactional outbox publication

## Financial safety

Order cancellation is allowed automatically only while no supplier item has
entered submitted, processing, or completed fulfillment.

When an eligible wallet-funded order is cancelled:
1. the original commerce debit is compensated through a new double-entry
   ledger transaction;
2. the wallet balance is restored;
3. tracked inventory reservations are released;
4. a financial compensation record is stored;
5. an outbox event is emitted.

No balance is edited without a ledger transaction.

## Outbox

HTTP transactions write integration events into `outbox_events`.
The scheduled publisher records publication in `event_publications`, creates
eligible in-app notifications, then marks the outbox row published.

This keeps business commits independent from future external brokers.