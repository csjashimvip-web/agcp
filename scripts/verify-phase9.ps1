$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/payments; if($LASTEXITCODE-ne 0){throw "Customer payment routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/payments; if($LASTEXITCODE-ne 0){throw "Admin payment routes failed."}
& docker compose @compose exec -T backend php artisan payments:reconcile --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Payment reconciliation command failed."}
& docker compose @compose exec -T backend php artisan test --filter=PaymentOrchestrationTest; if($LASTEXITCODE-ne 0){throw "Phase 9 payment tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=WalletDepositTest; if($LASTEXITCODE-ne 0){throw "Wallet regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Rules and fraud regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "SaaS regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=AnalyticsAiTest; if($LASTEXITCODE-ne 0){throw "Analytics regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$health=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
Write-Host "Phase 9 verification completed successfully." -ForegroundColor Green
@{health=$health;payments_url="http://localhost:8080/payments";admin_url="http://localhost:8080/admin/payments"} | ConvertTo-Json -Depth 8
