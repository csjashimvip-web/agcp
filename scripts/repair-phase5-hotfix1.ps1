$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

function Assert-LastExitCode([string] $Message) {
    if ($LASTEXITCODE -ne 0) {
        throw "$Message (exit code: $LASTEXITCODE)"
    }
}

Write-Host "Applying AGCP Phase 5 Hotfix 1..." -ForegroundColor Cyan
Write-Host "Fix: map OrderStatusHistory to the existing order_status_history table."

& docker compose @compose ps
Assert-LastExitCode "Unable to read the AGCP container status"

& docker compose @compose exec -T backend php artisan optimize:clear
Assert-LastExitCode "Laravel cache reset failed"

# Development OPcache does not validate source timestamps, so the PHP
# processes must be restarted after replacing the model file.
& docker compose @compose restart backend queue-critical queue-default scheduler
Assert-LastExitCode "Unable to restart the PHP services"

$backendId = (& docker compose @compose ps -q backend).Trim()
if ([string]::IsNullOrWhiteSpace($backendId)) {
    throw "The backend container is not running."
}

$healthy = $false
for ($attempt = 1; $attempt -le 30; $attempt++) {
    $status = (& docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' $backendId).Trim()
    if ($status -eq "healthy") {
        $healthy = $true
        break
    }
    if ($status -eq "unhealthy" -or $status -eq "exited" -or $status -eq "dead") {
        break
    }
    Start-Sleep -Seconds 2
}

if (-not $healthy) {
    & docker compose @compose logs --tail=200 backend
    throw "The backend did not become healthy after the hotfix."
}

& docker compose @compose exec -T backend php artisan test --filter=SupplierEngineTest
Assert-LastExitCode "Supplier engine tests still fail"

& docker compose @compose exec -T backend php artisan test --filter=CommerceCoreTest
Assert-LastExitCode "Commerce regression tests failed"

& docker compose @compose ps
Assert-LastExitCode "Unable to read the final container status"

Write-Host "AGCP Phase 5 Hotfix 1 completed successfully." -ForegroundColor Green
Write-Host "Run .\scripts\verify-phase5.ps1 for the complete Phase 5 verification."
