# ADR 0002: Transactional outbox for durable business events

**Status:** Accepted
**Date:** 2026-07-30

## Decision

Persist business state and event envelopes in the same MySQL transaction. Publish pending outbox records asynchronously and require idempotent consumers.

## Rationale

Directly publishing to a queue before or after a database commit can lose events or create inconsistent state.

## Consequences

- Event payloads require stable schemas and versioning.
- Publishers and consumers must handle retries.
- Monitoring must alert on old or repeatedly failing outbox records.
