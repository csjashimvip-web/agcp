$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

Write-Host "Stopping the failed AGCP stack..." -ForegroundColor Cyan
& docker compose @compose down --remove-orphans
if ($LASTEXITCODE -ne 0) { throw "Unable to stop the existing AGCP stack." }

Write-Host "Removing only the backend dependency/cache volumes..." -ForegroundColor Cyan
$projectName = "agcp"
if (Test-Path ".env") {
    $line = Get-Content ".env" | Where-Object { $_ -match '^COMPOSE_PROJECT_NAME=' } | Select-Object -First 1
    if ($line) { $projectName = ($line -split '=', 2)[1].Trim().Trim('"') }
}

@(
    "${projectName}_backend_vendor",
    "${projectName}_backend_cache"
) | ForEach-Object {
    & docker volume rm $_ 2>$null | Out-Null
}

Write-Host "Rebuilding and starting AGCP..." -ForegroundColor Cyan
& docker compose @compose up -d --build --wait --wait-timeout 600
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "Backend diagnostics:" -ForegroundColor Yellow
    & docker compose @compose logs --tail 250 backend
    throw "AGCP did not become healthy."
}

Write-Host "Running migrations..." -ForegroundColor Cyan
& docker compose @compose exec -T backend php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw "Database migration or seeding failed." }

& docker compose @compose ps
Write-Host ""
Write-Host "AGCP is ready at http://localhost:8080" -ForegroundColor Green
