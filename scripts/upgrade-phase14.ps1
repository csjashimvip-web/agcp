$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
. ".\scripts\lib\agcp-phase-tools.ps1"
Assert-AgcpProjectRoot

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
Backup-AgcpFile ".env" "phase13-backup-$stamp"
Backup-AgcpFile "apps\web\package.json" "phase13-backup-$stamp"

Set-AgcpEnvValue ".env" "APP_VERSION" "14.0.0-phase14"
Set-AgcpEnvValue ".env" "RELEASE_CHANNEL" "release-candidate"
Set-AgcpWebVersion "14.0.0-phase14"

Write-Host "Phase 14 security, performance, and UAT assets installed." -ForegroundColor Green
Write-Host "Run: .\scripts\verify-phase14.ps1"
