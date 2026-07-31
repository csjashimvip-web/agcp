$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

Write-Host "Upgrading AGCP Phase 3 to Phase 4 Commerce Core..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase3-backup-$stamp" -Force
    Write-Host "Environment backup created." -ForegroundColor Green
}

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
& docker compose @compose down --remove-orphans
if ($LASTEXITCODE -ne 0) { throw "Unable to stop the current stack." }

& docker compose @compose build backend queue-critical queue-default scheduler frontend
if ($LASTEXITCODE -ne 0) { throw "Phase 4 images failed to build." }

& docker compose @compose up -d
if ($LASTEXITCODE -ne 0) { throw "Phase 4 containers failed to start." }

& docker compose @compose exec -T backend php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "Commerce migrations failed." }

& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force
if ($LASTEXITCODE -ne 0) { throw "Commerce permissions and demonstration catalog failed to seed." }

& docker compose @compose exec -T backend php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Laravel cache reset failed." }

& docker compose @compose exec -T backend php artisan route:list --path=api/v1/catalog
if ($LASTEXITCODE -ne 0) { throw "Catalog route verification failed." }

& docker compose @compose ps
Write-Host "AGCP Phase 4 upgrade completed." -ForegroundColor Green
Write-Host "Catalog: http://localhost:8080/catalog"
Write-Host "Cart: http://localhost:8080/cart"
Write-Host "Orders: http://localhost:8080/orders"
Write-Host "Commerce admin: http://localhost:8080/admin/commerce"
