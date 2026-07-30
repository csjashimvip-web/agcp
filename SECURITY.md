# Security Policy

## Reporting

Report suspected vulnerabilities privately to the repository owner. Do not publish credentials, tokens, customer data, or exploit details in public issues.

## Phase 2 identity controls

- Passwords use Laravel's configured adaptive password hashing.
- Registration and password authentication are tenant-aware.
- Suspended or locked accounts are rejected on every protected request.
- First-party browser authentication uses encrypted, HTTP-only session cookies and CSRF protection.
- Login and sensitive actions have dedicated rate limits.
- Email verification is required for administration.
- Administrative access requires confirmed TOTP 2FA.
- Personal API tokens cannot be used for interactive administration.
- API token abilities are enforced in addition to database permissions.
- Passkey login is blocked for inactive accounts and users outside the resolved tenant.
- Device fingerprints are server-keyed hashes; raw client identifiers are not stored.
- Sessions may be individually revoked, and suspension revokes active sessions and tokens.
- Platform roles cannot be assigned through tenant administration APIs.
- Tenant role assignment requires an active tenant membership.
- Identity security events are written to the append-only audit foundation.


## Phase 3 wallet controls

- Money is stored as integer minor units; floating-point values are never posted to the ledger.
- Every posted journal must balance debits and credits in one currency.
- Ledger account rows are locked in deterministic order inside database transactions.
- Posted ledger transactions and entries are append-only and cannot be updated or deleted through Eloquent.
- Customer deposits remain pending until an authorized reviewer approves or rejects them.
- A deposit request is locked before approval so concurrent reviews cannot credit it twice.
- Manual adjustments require maker-checker separation; the requester cannot approve or reject the same request.
- Financial approvals write both an audit event and a transactional outbox event.
- Wallet and ledger records are tenant-scoped at query and authorization boundaries.

## Secret handling

- Never commit `.env` or generated administrator credentials.
- Preserve `APP_PREVIOUS_KEYS` during controlled application-key rotation.
- Keep `PASSKEYS_USER_HANDLE_SECRET` stable. Changing it can break passkey user-handle continuity.
- Replace local secrets with a managed secret store before production.
- Rotate the initial administrator password immediately and store recovery codes offline.

## Production requirements

Before public deployment:

1. Use HTTPS end to end; passkeys require a valid secure origin outside localhost.
2. Configure trusted proxy handling and a WAF/CDN.
3. Replace the log mailer with a verified transactional email provider.
4. Configure secure cookie settings and the production domain/origin allowlists.
5. Add centralized security logs, alerts, metrics, and trace export.
6. Test backup restoration and account-recovery procedures.
7. Complete threat modeling and independent penetration testing.
8. Review privacy, consumer-protection, payment, stored-value, KYC, and data-retention obligations in each operating country.

## Supported versions

Security fixes target the active main branch. Runtime and dependency versions are pinned in the phase package and must be reviewed regularly before production releases.
