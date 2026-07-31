$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
. ".\scripts\lib\agcp-phase-tools.ps1"
Assert-AgcpProjectRoot

Write-Host "Applying AGCP Phases 13-15 completion bundle..." -ForegroundColor Cyan

& ".\scripts\upgrade-phase13.ps1"
& ".\scripts\upgrade-phase14.ps1"
& ".\scripts\upgrade-phase15.ps1"

Write-Host "Rebuilding local development stack without deleting volumes..." -ForegroundColor Cyan
Invoke-AgcpCompose build backend queue-critical queue-default scheduler frontend
Invoke-AgcpCompose up -d
Invoke-AgcpCompose exec -T backend php artisan migrate --force
Invoke-AgcpCompose exec -T backend php artisan optimize:clear
Invoke-AgcpCompose exec -T backend php artisan queue:restart

if (Test-AgcpArtisanCommand "reliability:heartbeat") {
    Invoke-AgcpCompose exec -T backend php artisan reliability:heartbeat scheduler
}

if (Test-AgcpArtisanCommand "reliability:backup") {
    Invoke-AgcpCompose exec -T backend php artisan reliability:backup
}

if (Test-AgcpArtisanCommand "reliability:verify-backup") {
    Invoke-AgcpCompose exec -T backend php artisan reliability:verify-backup --latest
}

Invoke-AgcpCompose ps

Write-Host "Running final verification..." -ForegroundColor Cyan
& ".\scripts\verify-phase13-15.ps1"

Write-Host "AGCP core roadmap Phases 1-15 is code-complete." -ForegroundColor Green
Write-Host "Actual production launch still requires a real domain, HTTPS, SMTP, payment credentials, and server deployment." -ForegroundColor Yellow
