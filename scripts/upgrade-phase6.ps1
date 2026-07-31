$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 5 to Phase 6 Rules, Fraud and Dynamic Pricing..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase5-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=6.0.0-phase6" } else { $envContent += "`nAPP_VERSION=6.0.0-phase6`n" }
    $defaults = @{ FRAUD_REVIEW_SCORE="60"; FRAUD_BLOCK_SCORE="80"; FRAUD_HIGH_VALUE_MINOR="50000"; FRAUD_CRITICAL_VALUE_MINOR="200000"; DYNAMIC_PRICE_QUOTE_TTL="300" }
    foreach ($entry in $defaults.GetEnumerator()) { if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" } }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 6 settings added." -ForegroundColor Green
}
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 6 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 6 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 6 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 6 rules and permissions failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/rules; if($LASTEXITCODE-ne 0){throw "Rule route verification failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 6 upgrade completed." -ForegroundColor Green
Write-Host "Rules and fraud administration: http://localhost:8080/admin/rules"
Write-Host "Dynamic catalog: http://localhost:8080/catalog"
Write-Host "Customer orders: http://localhost:8080/orders"
