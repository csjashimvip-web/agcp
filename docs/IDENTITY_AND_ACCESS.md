# AGCP Identity and Access Architecture

## Authentication model

AGCP uses a first-party headless architecture:

```text
Next.js browser
   ↓ CSRF cookie + encrypted session cookie
Nginx gateway
   ↓
Laravel Fortify authentication endpoints
   ↓
Laravel Sanctum stateful authentication
   ↓
Tenant, account-state, session, verification, 2FA, and permission middleware
```

Passwords, TOTP 2FA, recovery codes, and WebAuthn passkeys are supported. The UI never changes account balance or authorization state directly; it calls versioned backend endpoints.

## Tenant-aware identity

A user is a global identity and may have memberships in one or more tenants. Access requires:

1. an active user account;
2. an active resolved tenant;
3. an active membership in that tenant, unless the user is a platform administrator;
4. the required role permission;
5. any route-specific assurance such as verified email or confirmed 2FA.

Tenant administration APIs may assign only roles owned by the active tenant. Global platform roles are seeded and cannot be granted through tenant role endpoints.

## Authorization tables

```text
users
permissions
roles
permission_role
role_user
tenant_memberships
```

Permissions are stable machine-readable slugs. Roles group permissions and are either platform-scoped (`tenant_id = null`) or tenant-scoped.

## Session and device controls

```text
user_devices
  └── keyed fingerprint hash
  └── observed browser/platform
  └── trust timestamp

auth_sessions
  └── HMAC of the framework session ID
  └── tenant and device references
  └── IP/user-agent metadata
  └── last activity and revocation timestamps
```

Raw session IDs and raw client device IDs are never persisted. Revoked sessions are rejected by middleware. Removing a device revokes its linked sessions.

## Administrative assurance

The admin route chain is:

```text
tenant
→ auth:sanctum
→ account.active
→ auth.session
→ verified
→ admin.2fa
→ permission:identity.admin.access
```

Administrative routes require an interactive browser session. Bearer tokens are intentionally rejected even when the user owns an administrative role.

## API tokens

Personal access tokens:

- are displayed only once;
- expire after a bounded number of days;
- can receive only abilities already available to the user in the active tenant;
- are checked against both token abilities and current role permissions;
- are deleted when the account is suspended or locked.

## Passkeys

Passkeys use WebAuthn. AGCP stores credential material in the `passkeys` table and exposes the official passkey login and registration routes through Nginx. A server-side authorization callback blocks inactive or tenant-ineligible users even after a cryptographically valid passkey assertion.

Localhost is suitable for development. Production passkeys require HTTPS, a stable relying-party domain, an exact allowed-origin list, and a stable user-handle secret.

## Initial roles

- `platform-super-admin`: full platform permissions; platform-scoped
- `tenant-admin`: tenant identity administration
- `customer`: profile, sessions, and own API-token management

The default customer role is automatically attached at registration.

## Local email verification

The development mailer writes verification and reset links to backend logs. This is deliberate for local development only. Production must use a real provider and domain-authenticated sender.
