# Production Readiness and Recovery

## Health endpoints

- `GET /api/v1/health/live` confirms the PHP application process can respond.
- `GET /api/v1/health/ready` returns sanitized component readiness and responds with HTTP 503 only when a critical check fails.
- `GET /api/v1/health` retains dependency latency diagnostics for controlled operational use.

## Backup lifecycle

1. `reliability:backup` creates a consistent MySQL logical dump with `--single-transaction`.
2. The SQL stream is gzip-compressed.
3. The archive is encrypted using XChaCha20-Poly1305 secretstream with an environment-managed 32-byte key.
4. The encrypted artifact receives a SHA-256 checksum and private retention metadata.
5. `reliability:verify-backup --latest` validates checksum, authenticated decryption and complete gzip readability without importing data.
6. `reliability:purge-backups` deletes expired artifacts and preserves metadata.

## Important operational requirements

- Store `BACKUP_ENCRYPTION_KEY` in a managed secret store and keep an offline recovery copy.
- Replicate encrypted backups to an independently controlled off-site destination.
- Do not rotate or delete the backup key until all backups encrypted by that key have expired or been re-encrypted.
- Run a full restoration into an isolated MySQL instance before production launch and at a regular documented cadence.
- Never test a restore by importing into the live production database.

## Commands

```bash
php artisan reliability:heartbeat
php artisan reliability:check --persist
php artisan reliability:backup
php artisan reliability:verify-backup --latest
php artisan reliability:purge-backups
```
