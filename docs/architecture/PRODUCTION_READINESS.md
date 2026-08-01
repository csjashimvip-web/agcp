# AGCP Production Readiness

## Runtime processes

Production requires independent web, queue, and scheduler processes:

- Laravel/PHP runtime
- Next.js runtime
- Redis
- MariaDB/MySQL
- queue worker
- Laravel scheduler
- OpenLiteSpeed/CyberPanel reverse proxy and TLS

## Release gate

Before traffic is opened:

1. Database backup exists and its SHA-256 is recorded.
2. `composer install --no-dev --optimize-autoloader` succeeds.
3. `php artisan migrate --force` succeeds.
4. Laravel caches build successfully.
5. Full Laravel test suite passes.
6. `npm ci` and `npm run build` pass.
7. AGCP deployment readiness returns `ready`.
8. Queue worker and scheduler are running.
9. `/api/v1/platform/readiness` returns HTTP 200.

## Backup and disaster recovery

Windows development utilities are in:

- `scripts/windows/BACKUP_AGCP_DATABASE.ps1`
- `scripts/windows/RESTORE_AGCP_DATABASE.ps1`

Production should use an equivalent server-side scheduled backup with:

- encrypted off-server copies;
- SHA-256 verification;
- retention policy;
- restore drills;
- documented RPO/RTO.

A backup is not considered proven until a restore drill has succeeded.

## Privacy and retention

AGCP does not automatically erase financial ledger, payment, audit, or other
records that may require legal/accounting retention. Deletion requests are
review workflows, not blind cascades.

## Secrets

Never commit:

- `.env`
- payment credentials
- supplier API keys
- reseller API secrets
- self-hosted license secrets
- private backup archives

## Rollback

Application-code rollback may use Git/release rollback. Database schema rollback
must be treated separately. Do not automatically run `migrate:rollback` in
production after a failed release; restore/forward-fix decisions require a
review of data changes.