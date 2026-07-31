$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
. ".\scripts\lib\agcp-phase-tools.ps1"
Assert-AgcpProjectRoot

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
Backup-AgcpFile ".env" "phase14-backup-$stamp"
Backup-AgcpFile "apps\web\package.json" "phase14-backup-$stamp"

Set-AgcpEnvValue ".env" "APP_VERSION" "15.0.0-phase15"
Set-AgcpEnvValue ".env" "RELEASE_VERSION" "1.0.0"
Set-AgcpEnvValue ".env" "RELEASE_CHANNEL" "stable"
Set-AgcpWebVersion "15.0.0-phase15"
[System.IO.File]::WriteAllText((Join-Path (Get-Location) "VERSION"), "1.0.0`n", [System.Text.UTF8Encoding]::new($false))

Write-Host "Phase 15 launch, documentation, and handover assets installed." -ForegroundColor Green
Write-Host "Run: .\scripts\verify-phase15.ps1"
