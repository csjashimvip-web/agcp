# Reliability & Production Readiness

Phase 12 adds platform-scoped encrypted MySQL backups, authenticated integrity verification, non-destructive restore drills, runtime heartbeats, liveness/readiness probes and persistent release checks.

## Safety boundaries

- Backup artifacts are private, gzip-compressed and encrypted with XChaCha20-Poly1305 secretstream.
- The encryption key is never stored in the database or returned by the API.
- Restore drills verify checksum, authenticated decryption and full gzip readability without importing into the live database.
- No browser endpoint can download a full database backup or execute a production restore.
- Expiration removes private artifacts but retains operational metadata.
- Reliability administration is platform-super-admin only, with verified browser session and confirmed 2FA inherited from the admin route group.
