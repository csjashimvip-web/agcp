# ADR 0003: Tenant-aware from Phase 1

**Status:** Accepted
**Date:** 2026-07-30

## Decision

Add tenant identity, domains, and request context from the foundation while operating in single-tenant mode initially.

## Rationale

Retrofitting tenant isolation after commerce data exists is risky and expensive.

## Consequences

- Tenant-owned tables must include and index `tenant_id`.
- Global tables must be explicitly classified.
- Authorization must validate both user permission and tenant membership.
