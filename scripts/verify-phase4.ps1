$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

& docker compose @compose ps
if ($LASTEXITCODE -ne 0) { throw "Unable to read container status." }
& docker compose @compose exec -T backend php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw "Migration verification failed." }
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/catalog
if ($LASTEXITCODE -ne 0) { throw "Catalog route verification failed." }
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/cart
if ($LASTEXITCODE -ne 0) { throw "Cart route verification failed." }
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/orders
if ($LASTEXITCODE -ne 0) { throw "Order route verification failed." }
& docker compose @compose exec -T backend php artisan test --filter=CommerceCoreTest
if ($LASTEXITCODE -ne 0) { throw "Commerce feature tests failed." }
& docker compose @compose exec -T frontend npm run typecheck
if ($LASTEXITCODE -ne 0) { throw "Frontend type checking failed." }
$health = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
$catalog = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/catalog" -TimeoutSec 20
if (-not $catalog.data) { throw "The seeded catalog is empty." }
Write-Host "Phase 4 verification completed successfully." -ForegroundColor Green
$health | ConvertTo-Json -Depth 6
