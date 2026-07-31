$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
. ".\scripts\lib\agcp-phase-tools.ps1"
Assert-AgcpProjectRoot

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
Backup-AgcpFile ".env" "phase12-backup-$stamp"
Backup-AgcpFile "apps\web\package.json" "phase12-backup-$stamp"
Backup-AgcpFile "infrastructure\nginx\default.conf" "phase12-backup-$stamp"

Set-AgcpEnvValue ".env" "APP_VERSION" "13.0.0-phase13"
Set-AgcpEnvValue ".env" "RELEASE_CHANNEL" "candidate"
Set-AgcpEnvValue ".env" "DEPLOYMENT_ENVIRONMENT" "development"
Set-AgcpWebVersion "13.0.0-phase13"

Write-Host "Phase 13 deployment and DevOps files installed." -ForegroundColor Green
Write-Host "Production template: .env.production.example"
Write-Host "Production Compose: docker-compose.production.yml"
Write-Host "Deployment guide: docs/PHASE_13_PRODUCTION_DEPLOYMENT.md"
