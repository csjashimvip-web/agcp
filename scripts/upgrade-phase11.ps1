$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 10 to Phase 11 Enterprise Reporting and Invoicing..." -ForegroundColor Cyan

if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase10-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=11.0.0-phase11" } else { $envContent += "`nAPP_VERSION=11.0.0-phase11`n" }
    $defaults = @{
        REPORT_EXPORT_DISK="local"
        REPORT_EXPORT_DIRECTORY="exports"
        REPORT_EXPORT_RETENTION_DAYS="30"
        REPORT_SCHEDULE_BATCH_SIZE="50"
        INVOICE_DUE_DAYS="0"
    }
    foreach ($entry in $defaults.GetEnumerator()) {
        if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" }
    }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 11 reporting settings added." -ForegroundColor Green
}

$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 11 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 11 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 11 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=DatabaseSeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 11 permissions and reporting defaults failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan invoices:generate-missing --tenant=araabi-global --limit=500; if($LASTEXITCODE-ne 0){throw "Existing paid-order invoice generation failed."}
& docker compose @compose exec -T backend php artisan reports:run-due --tenant=araabi-global --limit=50; if($LASTEXITCODE-ne 0){throw "Initial scheduled report processing failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/invoices; if($LASTEXITCODE-ne 0){throw "Customer invoice route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/reports; if($LASTEXITCODE-ne 0){throw "Reporting administration route verification failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 11 upgrade completed." -ForegroundColor Green
Write-Host "Customer invoices: http://localhost:8080/invoices"
Write-Host "Reporting administration: http://localhost:8080/admin/reports"
