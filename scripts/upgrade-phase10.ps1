$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 9 to Phase 10 Enterprise Engagement and Operations..." -ForegroundColor Cyan

if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase9-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=10.0.0-phase10" } else { $envContent += "`nAPP_VERSION=10.0.0-phase10`n" }
    $defaults = @{
        OPERATIONS_SNAPSHOT_INTERVAL_MINUTES="5"
        SUPPORT_DEFAULT_FIRST_RESPONSE_MINUTES="480"
        SUPPORT_DEFAULT_RESOLUTION_HOURS="48"
        WEBHOOK_MAX_RESPONSE_BYTES="10000"
        WEBHOOK_ALLOW_LOG_SINK="true"
        NOTIFICATION_DEFAULT_PROVIDER="log"
    }
    foreach ($entry in $defaults.GetEnumerator()) {
        if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" }
    }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 10 operational settings added." -ForegroundColor Green
}

$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 10 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 10 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 10 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 10 permissions, templates and sandbox webhook failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan ops:snapshot --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Initial operations snapshot failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/notifications; if($LASTEXITCODE-ne 0){throw "Notification route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/support; if($LASTEXITCODE-ne 0){throw "Customer support route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/operations; if($LASTEXITCODE-ne 0){throw "Operations route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/webhooks; if($LASTEXITCODE-ne 0){throw "Webhook route verification failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 10 upgrade completed." -ForegroundColor Green
Write-Host "Notifications: http://localhost:8080/notifications"
Write-Host "Customer support: http://localhost:8080/support"
Write-Host "Operations center: http://localhost:8080/admin/operations"
Write-Host "Support administration: http://localhost:8080/admin/support"
Write-Host "Outbound webhooks: http://localhost:8080/admin/webhooks"
