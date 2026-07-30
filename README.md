# AGCP Phase 11 Payment Hotfix Script Fix

This patch replaces only `scripts/repair-phase11-payment-hotfix1.ps1`.

It fixes the Windows PowerShell parser error caused by placing a colon immediately after `$LASTEXITCODE` inside an interpolated string.

Apply the file over the existing project and run:

```powershell
cd C:\Projects\agcp
Set-ExecutionPolicy -Scope Process Bypass -Force
.\scripts\repair-phase11-payment-hotfix1.ps1
```
