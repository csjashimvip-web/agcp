$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
& docker compose @compose ps
if ($LASTEXITCODE -ne 0) { throw "Unable to read container status." }
& docker compose @compose exec -T backend php artisan migrate:status
if ($LASTEXITCODE -ne 0) { throw "Migration verification failed." }
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/wallets
if ($LASTEXITCODE -ne 0) { throw "Wallet route verification failed." }
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/deposits
if ($LASTEXITCODE -ne 0) { throw "Deposit route verification failed." }
& docker compose @compose exec -T backend php artisan test --filter=WalletDepositTest
if ($LASTEXITCODE -ne 0) { throw "Wallet feature tests failed." }
& docker compose @compose exec -T frontend npm run typecheck
if ($LASTEXITCODE -ne 0) { throw "Frontend type checking failed." }
$health = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
Write-Host "Phase 3 verification completed successfully." -ForegroundColor Green
$health | ConvertTo-Json -Depth 6
