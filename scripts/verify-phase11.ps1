$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/invoices; if($LASTEXITCODE-ne 0){throw "Customer invoice routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/tax-profile; if($LASTEXITCODE-ne 0){throw "Customer tax-profile routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/reports; if($LASTEXITCODE-ne 0){throw "Reporting administration routes failed."}
& docker compose @compose exec -T backend php artisan invoices:generate-missing --tenant=araabi-global --limit=500; if($LASTEXITCODE-ne 0){throw "Invoice generation command failed."}
& docker compose @compose exec -T backend php artisan reports:run-due --tenant=araabi-global --limit=50; if($LASTEXITCODE-ne 0){throw "Scheduled reporting command failed."}
& docker compose @compose exec -T backend php artisan test --filter=ReportingInvoicingTest; if($LASTEXITCODE-ne 0){throw "Phase 11 reporting and invoicing tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=EngagementOperationsTest; if($LASTEXITCODE-ne 0){throw "Phase 10 regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=PaymentOrchestrationTest; if($LASTEXITCODE-ne 0){throw "Payments regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Rules and fraud regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "SaaS regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=AnalyticsAiTest; if($LASTEXITCODE-ne 0){throw "Analytics regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=CommerceCoreTest; if($LASTEXITCODE-ne 0){throw "Commerce regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=WalletDepositTest; if($LASTEXITCODE-ne 0){throw "Wallet regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$health=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
Write-Host "Phase 11 verification completed successfully." -ForegroundColor Green
@{health=$health;invoices="http://localhost:8080/invoices";reports="http://localhost:8080/admin/reports"} | ConvertTo-Json -Depth 8
