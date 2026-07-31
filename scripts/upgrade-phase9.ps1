$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 8 to Phase 9 Payment Orchestration and Financial Reconciliation..." -ForegroundColor Cyan

if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase8-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=9.0.0-phase9" } else { $envContent += "`nAPP_VERSION=9.0.0-phase9`n" }
    $defaults = @{
        PAYMENT_INTENT_EXPIRY_MINUTES="30"
        PAYMENT_WEBHOOK_TOLERANCE_SECONDS="300"
        PAYMENT_RECONCILIATION_WINDOW_DAYS="30"
        PAYMENT_RECONCILIATION_TIME="03:10"
    }
    foreach ($entry in $defaults.GetEnumerator()) {
        if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" }
    }
    if ($envContent -notmatch "(?m)^SANDBOX_PAYMENT_WEBHOOK_SECRET=" -or $envContent -match "(?m)^SANDBOX_PAYMENT_WEBHOOK_SECRET=CHANGE_ME") {
        $bytes = New-Object byte[] 48
        [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
        $secret = [Convert]::ToBase64String($bytes)
        if ($envContent -match "(?m)^SANDBOX_PAYMENT_WEBHOOK_SECRET=") { $envContent = $envContent -replace "(?m)^SANDBOX_PAYMENT_WEBHOOK_SECRET=.*$", "SANDBOX_PAYMENT_WEBHOOK_SECRET=$secret" } else { $envContent += "SANDBOX_PAYMENT_WEBHOOK_SECRET=$secret`n" }
    }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and secure Phase 9 payment settings added." -ForegroundColor Green
}

$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 9 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 9 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 9 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 9 permissions and sandbox provider failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/payments; if($LASTEXITCODE-ne 0){throw "Customer payment route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/payments; if($LASTEXITCODE-ne 0){throw "Admin payment route verification failed."}
& docker compose @compose exec -T backend php artisan payments:reconcile --tenant=araabi-global; if($LASTEXITCODE-ne 0){throw "Initial payment reconciliation failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 9 upgrade completed." -ForegroundColor Green
Write-Host "Customer payments: http://localhost:8080/payments"
Write-Host "Payment administration: http://localhost:8080/admin/payments"
