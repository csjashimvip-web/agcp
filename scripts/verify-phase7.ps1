$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose ps; if($LASTEXITCODE-ne 0){throw "Unable to read container status."}
& docker compose @compose exec -T backend php artisan migrate:status; if($LASTEXITCODE-ne 0){throw "Migration verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/saas; if($LASTEXITCODE-ne 0){throw "SaaS routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/plugins; if($LASTEXITCODE-ne 0){throw "Plugin routes failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/tenant/configuration; if($LASTEXITCODE-ne 0){throw "Tenant configuration route failed."}
& docker compose @compose exec -T backend php artisan test --filter=SaasPluginPlatformTest; if($LASTEXITCODE-ne 0){throw "Phase 7 feature tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=RulesFraudPricingTest; if($LASTEXITCODE-ne 0){throw "Phase 6 regression tests failed."}
& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest; if($LASTEXITCODE-ne 0){throw "Supplier regression tests failed."}
& docker compose @compose exec -T frontend npm run typecheck; if($LASTEXITCODE-ne 0){throw "Frontend typecheck failed."}
$health=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
$config=Invoke-RestMethod -Uri "http://localhost:8080/api/v1/tenant/configuration" -TimeoutSec 20
Write-Host "Phase 7 verification completed successfully." -ForegroundColor Green
@{health=$health;tenant=$config.data.tenant;plan=$config.data.entitlements.plan} | ConvertTo-Json -Depth 8
