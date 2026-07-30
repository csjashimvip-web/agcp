# AGCP Phase 2 Upgrade Patch

This patch upgrades the verified AGCP Phase 1 backend-startup-fix project to Phase 2 Identity and Access.

## Windows instructions

1. Back up `C:\Projects\agcp\.env`.
2. Copy every file and folder from this patch into `C:\Projects\agcp`.
3. Choose **Replace All** when Windows asks.
4. Open PowerShell in `C:\Projects\agcp` and run:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase2.ps1
```

The upgrade preserves MySQL and Redis data. Changed Composer and NPM dependency volumes are synchronized automatically from the newly built images.

See `docs/PHASE_2_REPORT.md` and `docs/IDENTITY_AND_ACCESS.md` after applying the patch.
