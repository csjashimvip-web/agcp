$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
. ".\scripts\lib\agcp-phase-tools.ps1"
Assert-AgcpProjectRoot

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
& docker compose @compose config --quiet
if ($LASTEXITCODE -ne 0) { throw "Development Compose configuration is invalid." }

$previousProductionEnvFile = $env:PRODUCTION_ENV_FILE
$env:PRODUCTION_ENV_FILE = ".env.production.example"
try {
    & docker compose --env-file .env.production.example -f docker-compose.production.yml config --quiet
    if ($LASTEXITCODE -ne 0) { throw "Production Compose configuration is invalid." }
} finally {
    if ($null -eq $previousProductionEnvFile) {
        Remove-Item Env:PRODUCTION_ENV_FILE -ErrorAction SilentlyContinue
    } else {
        $env:PRODUCTION_ENV_FILE = $previousProductionEnvFile
    }
}

& docker compose @compose ps
if ($LASTEXITCODE -ne 0) { throw "Unable to read service status." }

& docker compose @compose exec -T backend php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw "Migration status failed." }

& docker compose @compose exec -T backend php artisan route:list --path=api/v1/health
if ($LASTEXITCODE -ne 0) { throw "Health routes are unavailable." }

& docker compose @compose exec -T frontend npm run typecheck
if ($LASTEXITCODE -ne 0) { throw "Frontend typecheck failed." }

Write-Host "Phase 13 verification passed." -ForegroundColor Green
