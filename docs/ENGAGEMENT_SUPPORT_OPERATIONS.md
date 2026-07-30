# AGCP Phase 10 — Engagement, Support, Webhooks & Operations

## Scope
Phase 10 turns domain events into customer communication and partner integration while giving administrators an auditable operations center.

## Notification guarantees
- Versioned tenant templates.
- Per-user, per-event preferences.
- In-app messages and queued email/SMS/web-push provider contracts.
- Delivery attempts, provider IDs, failures and retry timestamps are persisted.
- Domain-event deduplication prevents duplicate in-app notifications.
- Local development uses a log provider and does not claim live email/SMS delivery.

## Outbound webhook guarantees
- Subscription by exact event or wildcard.
- Stable event IDs and one delivery per endpoint/event.
- HMAC-SHA256 signature over `timestamp.payload`.
- HTTPS-only external endpoints; localhost/private destinations are rejected to reduce SSRF exposure.
- `log://` is the explicit offline development sink.
- Exponential retry and dead-letter state retain evidence.

## Support desk
- Customer portal and tenant-admin queue.
- Public replies and internal notes.
- Priority-specific first-response and resolution deadlines.
- Status, assignment and priority transitions are append-only events.
- Support replies can produce customer notifications.

## Operations center
Snapshots include database/Redis checks, queue depth, failed jobs, outbox state, notification/webhook delivery, support SLA, payment reconciliation mismatches and supplier health. Incidents use deterministic fingerprints, so repeated failures update one incident instead of creating alert storms.

## Deliberate limits
Live SMTP/SMS/push providers and external webhook egress depend on production credentials and network policy. Phase 10 provides secure contracts, persistence, queues, sandbox/log providers and admin workflows without pretending those external services are connected.
