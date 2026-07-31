# AGCP Operations Runbook

## Daily

- Check `/api/v1/health/ready`.
- Review failed queue jobs.
- Review payment reconciliation exceptions.
- Review supplier failures and webhook retries.
- Confirm scheduler heartbeat.
- Confirm latest backup status.

## Weekly

- Verify an encrypted backup.
- Review slow database queries.
- Review admin audit events.
- Review locked/suspended accounts.
- Review disk usage and retained exports.
- Review dependency and security alerts.

## Monthly

- Perform a restore integrity drill.
- Review permissions and admin memberships.
- Rotate provider secrets where supported.
- Test incident contacts.
- Review capacity trends.
- Apply tested operating-system and container updates.

## Useful production commands

```bash
COMPOSE="docker compose --env-file .env.production -f docker-compose.production.yml"

$COMPOSE ps
$COMPOSE logs --tail=200 backend
$COMPOSE logs --tail=200 queue-critical queue-default
$COMPOSE exec -T backend php artisan queue:failed
$COMPOSE exec -T backend php artisan reliability:check --persist
$COMPOSE exec -T backend php artisan reliability:backup
$COMPOSE exec -T backend php artisan reliability:verify-backup --latest
```

## Incident priorities

- **P1:** payment integrity, data exposure, full outage
- **P2:** checkout unavailable, queue backlog, tenant access failure
- **P3:** non-critical module failure or degraded performance
- **P4:** cosmetic or low-impact issue
