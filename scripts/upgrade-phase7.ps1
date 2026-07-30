$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 6 to Phase 7 Multi-Tenant SaaS and Plugin Marketplace..." -ForegroundColor Cyan
if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase6-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=7.0.0-phase7" } else { $envContent += "`nAPP_VERSION=7.0.0-phase7`n" }
    $defaults = @{ TENANT_DEFAULT_PLAN="enterprise"; DOMAIN_VERIFICATION_MODE="manual"; PLUGIN_ALLOW_UNAPPROVED="false"; SAAS_USAGE_PERIOD="monthly" }
    foreach ($entry in $defaults.GetEnumerator()) { if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" } }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 7 settings added." -ForegroundColor Green
}
$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 7 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 7 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 7 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 7 SaaS and plugin data failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/saas; if($LASTEXITCODE-ne 0){throw "SaaS route verification failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 7 upgrade completed." -ForegroundColor Green
Write-Host "SaaS administration: http://localhost:8080/admin/saas"
Write-Host "Tenant configuration API: http://localhost:8080/api/v1/tenant/configuration"
