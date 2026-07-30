$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/health; if($LASTEXITCODE-ne 0){throw "Health routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/reliability; if($LASTEXITCODE-ne 0){throw "Reliability administration routes failed."}
& docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler; if($LASTEXITCODE-ne 0){throw "Heartbeat command failed."}
& docker compose @compose exec -T backend php artisan reliability:check --persist; if($LASTEXITCODE-ne 0){throw "Readiness check failed."}
& docker compose @compose exec -T backend php artisan reliability:verify-backup --latest; if($LASTEXITCODE-ne 0){throw "Backup verification failed."}
& docker compose @compose exec -T backend php artisan test --filter=ReliabilityProductionReadinessTest; if($LASTEXITCODE-ne 0){throw "Phase 12 reliability tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=ReportingInvoicingTest; if($LASTEXITCODE-ne 0){throw "Phase 11 regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=EngagementOperationsTest; if($LASTEXITCODE-ne 0){throw "Phase 10 regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=PaymentOrchestrationTest; if($LASTEXITCODE-ne 0){throw "Payments regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Rules and fraud regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "SaaS regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=AnalyticsAiTest; if($LASTEXITCODE-ne 0){throw "Analytics regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=CommerceCoreTest; if($LASTEXITCODE-ne 0){throw "Commerce regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=WalletDepositTest; if($LASTEXITCODE-ne 0){throw "Wallet regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$live=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health/live" -TimeoutSec 20
$ready=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health/ready" -TimeoutSec 20
Write-Host "Phase 12 verification completed successfully." -ForegroundColor Green
@{liveness=$live;readiness=$ready;reliability="http://localhost:8080/admin/reliability"} | ConvertTo-Json -Depth 8
