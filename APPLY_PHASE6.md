# AGCP Phase 6 Upgrade Patch

Copy every file and folder in this patch into the root of your existing Phase 5 Hotfix 1 project (`C:\Projects\agcp`) and choose **Replace All**. Keep the existing `.env` file.

Then run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\upgrade-phase6.ps1
.\scripts\verify-phase6.ps1
```

The script does not run `migrate:fresh`, `db:wipe`, or `docker compose down -v`; MySQL and Redis data volumes remain intact.
