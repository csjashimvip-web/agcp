$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

Write-Host "Upgrading AGCP Phase 2 to Phase 3 Enterprise Wallet and Deposits..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase2-backup-$stamp" -Force
    Write-Host "Environment backup created." -ForegroundColor Green
}

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
& docker compose @compose down --remove-orphans
if ($LASTEXITCODE -ne 0) { throw "Unable to stop the current stack." }

& docker compose @compose build backend queue-critical queue-default scheduler frontend
if ($LASTEXITCODE -ne 0) { throw "Phase 3 images failed to build." }

& docker compose @compose up -d
if ($LASTEXITCODE -ne 0) { throw "Phase 3 containers failed to start." }

& docker compose @compose exec -T backend php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "Wallet migrations failed." }

& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force
if ($LASTEXITCODE -ne 0) { throw "Tenant, identity, and wallet permissions failed to seed." }

& docker compose @compose exec -T backend php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Laravel cache reset failed." }

& docker compose @compose ps
& docker compose @compose exec -T backend php artisan tinker --execute="throw_unless(\Modules\Tenancy\Infrastructure\Models\Tenant::query()->where('slug', 'araabi-global')->exists(), new RuntimeException('Default tenant was not seeded.'));"
if ($LASTEXITCODE -ne 0) { throw "Default tenant verification failed." }

Write-Host "AGCP Phase 3 upgrade completed." -ForegroundColor Green
Write-Host "Wallet: http://localhost:8080/wallet"
Write-Host "Deposits: http://localhost:8080/deposits"
Write-Host "Admin review: http://localhost:8080/admin/wallets"
