# AGCP Phase 14 Admin Token and Phase 15 Readiness Hotfix

Run from PowerShell:

```powershell
Copy-Item "$env:USERPROFILE\Downloads\fix-agcp-phase14-admin-token-and-readiness.ps1" "C:\Projects\agcp\" -Force
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\fix-agcp-phase14-admin-token-and-readiness.ps1
```

Then:

```powershell
.\scripts\verify-phase14.ps1
.\scripts\verify-phase15.ps1
```
