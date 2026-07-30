# Apply AGCP Phase 12

This patch upgrades the verified Phase 11 project to Phase 12: Reliability, Encrypted Backups and Production Readiness.

## Apply

1. Make a copy of the current project folder.
2. Extract this patch over `C:\Projects\agcp` and allow replacement of matching files.
3. Open PowerShell:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\upgrade-phase12.ps1
```

## Verify

```powershell
.\scripts\verify-phase12.ps1
```

## Open

- Reliability administration: `http://localhost:8080/admin/reliability`
- Liveness: `http://localhost:8080/api/v1/health/live`
- Readiness: `http://localhost:8080/api/v1/health/ready`

## Safety

The upgrade does not wipe MySQL, delete Docker volumes or import a restore into the running database. It creates one initial encrypted backup and verifies it non-destructively.
