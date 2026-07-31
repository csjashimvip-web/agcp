$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/notifications; if($LASTEXITCODE-ne 0){throw "Notification routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/support; if($LASTEXITCODE-ne 0){throw "Customer support routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/support; if($LASTEXITCODE-ne 0){throw "Admin support routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/webhooks; if($LASTEXITCODE-ne 0){throw "Webhook routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/operations; if($LASTEXITCODE-ne 0){throw "Operations routes failed."}
& docker compose @compose exec -T backend php artisan ops:snapshot --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Operations snapshot command failed."}
& docker compose @compose exec -T backend php artisan test --filter=EngagementOperationsTest; if($LASTEXITCODE-ne 0){throw "Phase 10 engagement and operations tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=PaymentOrchestrationTest; if($LASTEXITCODE-ne 0){throw "Payments regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Rules and fraud regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "SaaS regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=AnalyticsAiTest; if($LASTEXITCODE-ne 0){throw "Analytics regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=CommerceCoreTest; if($LASTEXITCODE-ne 0){throw "Commerce regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=WalletDepositTest; if($LASTEXITCODE-ne 0){throw "Wallet regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$health=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
Write-Host "Phase 10 verification completed successfully." -ForegroundColor Green
@{health=$health;notifications="http://localhost:8080/notifications";support="http://localhost:8080/support";operations="http://localhost:8080/admin/operations";webhooks="http://localhost:8080/admin/webhooks"} | ConvertTo-Json -Depth 8
