$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 7 to Phase 8 Explainable AI and Advanced Analytics..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase7-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=8.0.0-phase8" } else { $envContent += "`nAPP_VERSION=8.0.0-phase8`n" }
    $defaults = @{ ANALYTICS_WINDOW_DAYS="30"; ANALYTICS_FORECAST_HORIZON_DAYS="14"; ANALYTICS_PROVIDER="deterministic"; ANALYTICS_REFRESH_TIME="02:10" }
    foreach ($entry in $defaults.GetEnumerator()) { if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" } }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 8 settings added." -ForegroundColor Green
}
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 8 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 8 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 8 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 8 permissions and plan data failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan analytics:refresh --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Initial analytics refresh failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/analytics; if($LASTEXITCODE-ne 0){throw "Analytics route verification failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 8 upgrade completed." -ForegroundColor Green
Write-Host "AI and analytics administration: http://localhost:8080/admin/analytics"
Write-Host "Rules and risk administration: http://localhost:8080/admin/rules"
