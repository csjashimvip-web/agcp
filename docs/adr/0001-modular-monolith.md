# ADR 0001: Start with a modular monolith

**Status:** Accepted
**Date:** 2026-07-30

## Decision

Use one Laravel deployment with enforced domain modules instead of immediate microservices.

## Rationale

This minimizes distributed-system failure modes and operational cost while preserving extraction boundaries through contracts, events, versioned APIs, separate queues, and module-owned data access.

## Consequences

- Faster delivery and simpler transactions now.
- Architecture reviews must prevent cross-module coupling.
- Modules can be extracted when scale, ownership, or isolation requirements justify it.
