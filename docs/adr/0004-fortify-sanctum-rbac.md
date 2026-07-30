# ADR 0004: Fortify, Sanctum, and Tenant-Scoped RBAC

## Status

Accepted for Phase 2.

## Context

AGCP needs a headless authentication backend for Next.js, future mobile applications, reseller integrations, multi-tenant administration, and stronger authentication such as TOTP and passkeys.

## Decision

- Use Laravel Fortify for headless authentication workflows.
- Use Laravel Sanctum for first-party SPA sessions and bounded personal API tokens.
- Store permissions as stable global slugs.
- Store roles as platform-scoped or tenant-scoped records.
- Require active tenant membership for tenant role operations.
- Require verified email and confirmed TOTP 2FA for administrative browser sessions.
- Reject bearer-token access to interactive administration.
- Apply token abilities in addition to live RBAC permissions.

## Consequences

- Browser and API clients share one identity domain without exposing session internals.
- Permissions can be expanded without changing authentication contracts.
- Removing a role immediately affects token-authorized requests because live permissions are still evaluated.
- Administrative automation will require a separate service-account design rather than reusing human administrator tokens.
