$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

Write-Host "Upgrading the verified AGCP Phase 1 runtime to Phase 2 Identity and Access..." -ForegroundColor Cyan

if (-not (Test-Path ".env")) {
    Write-Host "No existing .env was found. Phase 2 will perform a fresh secure setup." -ForegroundColor Yellow
} else {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $backup = ".env.phase1-backup-$stamp"
    Copy-Item ".env" $backup -Force
    Write-Host "Backed up the existing environment to $backup" -ForegroundColor Green
}

& (Join-Path $PSScriptRoot "setup.ps1")

Write-Host ""
Write-Host "Phase 2 verification commands:" -ForegroundColor Cyan
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan migrate:status
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan route:list --path=api/v1/auth
