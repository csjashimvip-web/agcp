# AGCP Phase 7 — Multi-Tenant SaaS and Plugin Marketplace

## Architecture

Phase 7 turns the existing tenant-aware modular monolith into an operable SaaS control plane. Tenant data remains in the shared MySQL schema with mandatory `tenant_id` boundaries. The design is extraction-ready but deliberately avoids premature database-per-tenant complexity.

### Subscription and entitlement model

- Global versionable plan catalog
- One current active/trial subscription selected per tenant
- Nested feature flags and numeric usage limits
- Expiring tenant-specific feature overrides
- Monthly locked usage counters with quota snapshots
- Public tenant configuration endpoint for white-label clients

### White-label tenant configuration

Each tenant receives a dedicated branding profile with display name, legal name, colors, logo URLs, support contacts, locale and controlled custom CSS. Custom domains retain explicit verification and SSL lifecycle metadata. The local development verifier is manual; production DNS/HTTP verification must be connected before real domain activation.

### Plugin marketplace security model

The marketplace is manifest-based. It does **not** accept arbitrary PHP, JavaScript, ZIP or Composer package uploads. A plugin record identifies an approved provider key already shipped and reviewed in the application. Tenant configuration is encrypted at rest using Laravel's encrypted model cast.

Lifecycle:

`available → installed → configured → enabled → disabled`

Every lifecycle action is recorded in `plugin_installation_events` and in the append-only audit log. Enabling requires the tenant's subscription to include `plugins.marketplace` and all required configuration values.

### Included manifests

- Sandbox Supplier Adapter (core, enabled)
- bKash Payment Gateway (manifest only)
- Stripe Payment Gateway (manifest only)
- WhatsApp Notifications (manifest only)

The non-core manifests provide secure configuration and lifecycle foundations only. They do not claim live provider integration.

## Core tables

- `subscription_plans`
- `tenant_subscriptions`
- `tenant_branding_profiles`
- `tenant_feature_overrides`
- `tenant_usage_counters`
- `plugins`
- `plugin_installations`
- `plugin_installation_events`

## APIs

- `GET /api/v1/tenant/configuration`
- `GET /api/v1/admin/saas`
- `POST /api/v1/admin/saas/plans`
- `POST /api/v1/admin/saas/tenants`
- `PUT /api/v1/admin/saas/tenants/{tenant}/subscription`
- `GET|PATCH /api/v1/admin/tenant-profile`
- `GET|POST /api/v1/admin/tenant-domains`
- `POST /api/v1/admin/tenant-domains/{domain}/verify`
- `POST /api/v1/admin/tenant-domains/{domain}/primary`
- `GET /api/v1/admin/plugins`
- Plugin install, configure, enable and disable endpoints
