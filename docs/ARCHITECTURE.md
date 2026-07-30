# AGCP Architecture Blueprint

## Architecture style

AGCP is an event-driven modular monolith with explicit extraction boundaries. Phase 5 activates the Smart Supplier Engine while preserving the Identity, Wallet, Commerce, tenancy, audit, outbox and observability foundations.

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
Suppliers       Provider adapters, scoring, health, queues, failover, polling, and refund orchestration
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

The Commerce module now owns catalog, variants, price lists, inventory, carts and orders. It calls the Wallet module through application services for balanced payment and refund journals. Supplier submission is now consumed by the Phase 5 Suppliers module through committed outbox events. See `COMMERCE_CORE.md` and `SMART_SUPPLIER_ENGINE.md`.

## Phase 5 supplier boundary

The Suppliers module now owns provider accounts, encrypted integration settings, catalog mappings, routing profiles, health observations, supplier orders, attempt history and routing decisions. Commerce publishes an order event but does not know supplier-specific APIs.

```text
Commerce checkout
      ↓ committed outbox event
Supplier event listener
      ↓
Supplier routing engine
      ↓
Provider contract / adapter
      ↓
External supplier
```

Terminal supplier failure calls the Wallet application services to post an item-level balanced refund. This preserves dependency direction: Suppliers may orchestrate Commerce and Wallet application services, while provider-specific code remains inside supplier adapters.
