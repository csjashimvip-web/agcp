# AGCP 2026â€“2027 Master Architecture

## Governing rule

AGCP is architecture-first. The system is not developed as isolated Phase A / Phase B rebuilds.
All major capabilities exist in the target architecture from the beginning. Implementation proceeds
through dependency-aware workstreams without changing the governing architecture.

## Runtime topology

Client / Admin / Supplier / Mobile / API Clients
                    |
             Edge / Reverse Proxy
                    |
             Next.js 16 / React
                    |
           Versioned Laravel API
                    |
       Modular Monolith + Domain Events
                    |
    +---------------+----------------+
    |               |                |
 MariaDB/MySQL    Redis          Object Storage
    |          cache/session/       |
    |          queue/rate-limit     |
    +---------------+----------------+
                    |
         Workers / Scheduler / Outbox
                    |
 Suppliers / Payments / Webhooks / AI

## Master domains

- Platform & Tenancy
- Identity, RBAC, Passkeys, 2FA
- Licensing & Entitlements
- Smart API Gateway
- Enterprise Wallet / Double-entry Ledger
- Catalog, Inventory, Checkout, Orders
- Supplier Orchestration / Dhru-compatible adapters
- Payment Orchestration / Reconciliation
- Pricing, Rules, Fraud
- SaaS, Plugins, Marketplace
- Automation
- Analytics
- Notifications & Support
- Reporting / Invoice / Tax
- Mobile Ecosystem
- AI Commerce Engine
- Reliability / Backup / Observability / Security

## Non-negotiable engineering rules

1. Money is ledger-based, immutable, idempotent and reconcilable.
2. Orders and supplier submissions are idempotent.
3. Tenant boundaries are explicit at every data/API layer.
4. Provider integrations use adapters; no provider owns AGCP core logic.
5. Outbox/events are used for reliable cross-domain side effects.
6. Redis is infrastructure, not the source of financial truth.
7. Production deployment targets CyberPanel/OpenLiteSpeed initially,
   but application architecture remains portable.
8. Browser code contains no sensitive business secrets.
9. Self-hosted commercial distribution is entitlement/license-ready;
   sensitive PHP modules may later be encoded for distribution.
10. AI features are governed, auditable and introduced only on reliable domain data.

## CyberPanel target

- OpenLiteSpeed: TLS + reverse proxy
- PHP 8.4: Laravel application
- Node.js 22: Next.js production process
- MariaDB/MySQL: relational source of truth
- Redis/Valkey-compatible server: cache/session/queues/rate limits
- Supervisor/systemd/PM2-equivalent process supervision
- Laravel queue workers
- Laravel scheduler
- automated backups
- centralized logs/metrics/readiness checks