$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

Write-Host "Upgrading AGCP Phase 4 to Phase 5 Smart Supplier Engine..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase4-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") {
        $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=5.0.0-phase5"
    } else {
        $envContent += "`nAPP_VERSION=5.0.0-phase5`n"
    }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and APP_VERSION updated." -ForegroundColor Green
}

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
& docker compose @compose down --remove-orphans
if ($LASTEXITCODE -ne 0) { throw "Unable to stop the current stack." }

& docker compose @compose build backend queue-critical queue-default scheduler frontend
if ($LASTEXITCODE -ne 0) { throw "Phase 5 images failed to build." }

& docker compose @compose up -d
if ($LASTEXITCODE -ne 0) { throw "Phase 5 containers failed to start." }

& docker compose @compose exec -T backend php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "Supplier engine migrations failed." }

& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force
if ($LASTEXITCODE -ne 0) { throw "Supplier permissions, routing profile and sandbox providers failed to seed." }

& docker compose @compose exec -T backend php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Laravel cache reset failed." }

& docker compose @compose exec -T backend php artisan supplier:health-check
if ($LASTEXITCODE -ne 0) { throw "Initial supplier health check failed." }

& docker compose @compose exec -T backend php artisan queue:restart
if ($LASTEXITCODE -ne 0) { throw "Queue worker restart failed." }

& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/suppliers
if ($LASTEXITCODE -ne 0) { throw "Supplier route verification failed." }

& docker compose @compose ps
Write-Host "AGCP Phase 5 upgrade completed." -ForegroundColor Green
Write-Host "Supplier administration: http://localhost:8080/admin/suppliers"
Write-Host "Automated demo service: http://localhost:8080/catalog"
Write-Host "Customer orders: http://localhost:8080/orders"
