$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/analytics; if($LASTEXITCODE-ne 0){throw "Analytics routes failed."}
& docker compose @compose exec -T backend php artisan analytics:refresh --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Analytics command failed."}
& docker compose @compose exec -T backend php artisan test --filter=AnalyticsAiTest; if($LASTEXITCODE-ne 0){throw "Phase 8 analytics tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "Phase 7 regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Rules and fraud regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$health=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
Write-Host "Phase 8 verification completed successfully." -ForegroundColor Green
@{health=$health;analytics_url="http://localhost:8080/admin/analytics"} | ConvertTo-Json -Depth 8
