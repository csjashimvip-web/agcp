# AGCP Disaster Recovery Runbook

## Required recovery materials

Store separately from the server:

- verified encrypted database backup
- exact `BACKUP_ENCRYPTION_KEY`
- production environment secrets
- Git release tag
- domain/DNS access
- container volume inventory
- recovery contact list

Without the exact backup encryption key, encrypted backups cannot be recovered.

## Recovery sequence

1. Declare the incident and stop writes when necessary.
2. Preserve logs and the affected server state.
3. Provision a clean server.
4. install Docker, Compose, Git and Curl.
5. Check out the last known stable Git tag.
6. Restore `.env.production` securely.
7. Start MySQL and Redis.
8. Decrypt and validate the selected backup using the Phase 12 recovery tooling.
9. Restore into an isolated database first.
10. Validate row counts, tenant records, users, wallets, orders and payments.
11. Start backend, queues, scheduler, frontend and Nginx.
12. Run migrations only after confirming the backup's release version.
13. Run readiness and smoke tests.
14. Switch traffic.
15. Monitor closely and document the incident.

## Safety

Never overwrite the only available production database during a restore drill. Restore into an isolated database or recovery environment first.
