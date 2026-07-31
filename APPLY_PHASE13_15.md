# Apply AGCP Phases 13–15

## Prerequisites

- Phase 12 files are already installed.
- Docker Desktop is running.
- Current local data is backed up.
- The project is at `C:\Projects\agcp`.

## Apply

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\upgrade-phase13-15.ps1
```

The script:

1. Backs up `.env`, frontend `package.json`, and Nginx configuration.
2. Sets the local code version to `15.0.0-phase15`.
3. Preserves the current database and Docker volumes.
4. Rebuilds the local development stack.
5. Runs migrations without resetting data.
6. Refreshes queues and Phase 12 reliability heartbeat.
7. Creates and verifies an encrypted backup when Phase 12 commands exist.
8. Runs Phase 13–15 verification.

## Verify

```powershell
.\scripts\verify-phase13-15.ps1
```

## Production

Do not use the local `.env` in production. Create `.env.production` from:

```text
.env.production.example
```

Then follow:

```text
docs/PHASE_13_PRODUCTION_DEPLOYMENT.md
```
