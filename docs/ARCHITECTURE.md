# AGCP Architecture Blueprint

## Architecture style

AGCP is an event-driven modular monolith with explicit extraction boundaries. Phase 3 activates the Wallet module while preserving the Phase 2 identity controls and the Phase 1 tenancy, audit, outbox, provider-contract, and observability foundations.

## Runtime request path

```text
Browser / Mobile / Partner API
             |
        Nginx Gateway
       /             \
Next.js Web          Laravel API
                         |
       Tenant + Identity assurance middleware
                         |
            Modular Domain Application
                         |
        MySQL 8.4 + Redis + Queue Workers
```

## Backend modules

```text
Shared          Cross-module contracts and infrastructure
Tenancy         Tenant identity and request context
Identity        Authentication, membership, roles, sessions, devices, tokens
Wallet          Ledger and account boundary (Phase 3)
Payments        Provider-neutral payment boundary
Products        Physical and digital catalogue boundary
Orders          Checkout and lifecycle boundary
Suppliers       Supplier adapters, scoring, and failover boundary
Notifications   Email, SMS, push, and webhook boundary
Rules           Versioned condition/action boundary
Fraud           Risk-signal and decision boundary
Audit           Append-only security and business audit boundary
Observability   Health, correlation, metrics, and tracing boundary
```

## Identity dependency direction

```text
Next.js UI / API controllers
             ↓
Fortify actions and Identity application services
             ↓
User, membership, role, permission, session, and device models
             ↓
MySQL persistence, audit events, mail, and external authenticators
```

## Authentication separation

- Stateful browser requests use Sanctum's first-party SPA/session mode.
- Personal tokens are for bounded headless integrations.
- Human administration requires an interactive browser session with verified email and confirmed 2FA.
- Passkeys use dedicated WebAuthn routes and are authorized against the current account and tenant after verification.

## Event flow

```text
Business/security action
   ├── state change
   ├── append-only audit event
   └── optional outbox message
             ↓
Queue consumers and notifications
```

## Data rules

- MySQL 8.4 with `utf8mb4`.
- UUIDs for externally visible platform records.
- Global users and permissions; tenant-aware memberships and roles.
- Raw passwords, raw sessions, raw device IDs, recovery codes, and plain API tokens are never stored in recoverable form.
- Monetary values in later phases use integer minor units plus an ISO currency code.
- Ledger and audit records are append-only.

## Phase 4 commerce boundary

The Commerce module now owns catalog, variants, price lists, inventory, carts and orders. It calls the Wallet module through application services for balanced payment and refund journals. Supplier submission remains outside this boundary until Phase 5. See `COMMERCE_CORE.md`.
