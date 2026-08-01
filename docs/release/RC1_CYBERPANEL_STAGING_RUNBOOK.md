# AGCP RC1 CyberPanel Staging Runbook

This runbook intentionally separates **staging acceptance** from
**production cutover**.

## Recommended staging topology

Use two staging hostnames:

- `staging.example.com` -> Next.js process on `127.0.0.1:3000`
- `api-staging.example.com` -> Laravel `apps/backend/public`

The exact domain names are operator inputs. Replace all examples before use.

## Server requirements

- PHP 8.4 CLI and CyberPanel/OpenLiteSpeed PHP runtime
- Composer
- Node.js 22 and npm
- MariaDB/MySQL
- Redis
- Git
- Supervisor for long-running queue/Next.js processes
- cron for `php artisan schedule:run`
- TLS certificates for both staging hostnames

## Files to provision manually

Copy, then edit:

- `infra/cyberpanel/staging/backend.env.example`
  -> `apps/backend/.env`
- `infra/cyberpanel/staging/web.env.example`
  -> `apps/web/.env.production`

Never commit the real environment files.

## OpenLiteSpeed/CyberPanel routing

### API staging site

Document root must target:

`/home/agcp/agcp-2026-2027/apps/backend/public`

Enable the PHP runtime through CyberPanel/OpenLiteSpeed and point the domain
`api-staging.example.com` at this site.

### Web staging site

Run Next.js under Supervisor on loopback port `3000`.

Configure the staging web hostname as a reverse proxy to:

`http://127.0.0.1:3000`

Do not expose port 3000 publicly.

## Supervisor

Templates:

- `infra/cyberpanel/staging/supervisor/agcp-queue-staging.conf`
- `infra/cyberpanel/staging/supervisor/agcp-next-staging.conf`

Review `/usr/bin/php`, `/usr/bin/npm`, Linux user, project path, and log paths
before installing them.

Then typically:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Scheduler

Install the line from:

`infra/cyberpanel/staging/cron/agcp-scheduler.cron`

for the AGCP deployment user.

There must be only one effective scheduler for this staging installation.

## External delivery safety

Keep:

`AGCP_EXTERNAL_DELIVERY_ENABLED=false`

during initial staging deployment.

Enable real outbound webhook/email delivery only after:

- destination ownership is verified;
- signing secrets/provider credentials are staged;
- monitoring exists;
- test destinations are used.

## Deploy exact RC1 commit

On the staging server:

```bash
export AGCP_ROOT=/home/agcp/agcp-2026-2027
export AGCP_STAGING_COMMIT=<EXACT_GIT_COMMIT>
export AGCP_STAGING_API_URL=https://api-staging.example.com
export AGCP_STAGING_WEB_URL=https://staging.example.com

bash scripts/server/DEPLOY_AGCP_STAGING_RC1.sh
```

The deployment script intentionally performs `git reset --hard` and `git clean`
inside `AGCP_ROOT`. Do not store secrets or uploads as untracked files inside
the repository tree.

## Runtime verification

```bash
bash scripts/server/VERIFY_AGCP_STAGING_RC1.sh
```

Confirm:

- database/cache readiness;
- migration status;
- queue worker;
- scheduler;
- API contract;
- web/API HTTPS access.

## Staging acceptance

```bash
bash scripts/server/RUN_AGCP_STAGING_ACCEPTANCE_RC1.sh
```

Acceptance is not the same as production approval.

## Required manual staging tests

Before production cutover, test with staging-only accounts/credentials:

1. Login and tenant authorization.
2. Product/catalog visibility.
3. Wallet/deposit workflow.
4. Checkout idempotency.
5. Supplier order submission and reconciliation.
6. Safe failed/cancelled order compensation.
7. Payment webhook idempotency with sandbox/staging provider.
8. Reseller API authentication, rate limiting, idempotent order creation.
9. Admin payout hold/approve/reject flow without real money transfer.
10. Webhook signing using a controlled staging receiver.
11. Email pipeline using a controlled staging recipient.
12. Support/privacy/export operations.
13. Queue restart and scheduler continuity after deployment.

## Backup and restore

Before production cutover, create a fresh production-equivalent backup and run
a restore drill into an isolated database. A backup file alone is not evidence
of recoverability.

## Production boundary

Do not open production traffic until the production cutover model reports:

`traffic_open_allowed=true`

and every critical manual check has evidence.