$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$required = @(
    "VERSION",
    "CHANGELOG.md",
    "docs\PHASE_13_PRODUCTION_DEPLOYMENT.md",
    "docs\PHASE_14_SECURITY_PERFORMANCE_UAT.md",
    "docs\PHASE_15_LAUNCH_AND_HANDOVER.md",
    "docs\OPERATIONS_RUNBOOK.md",
    "docs\DISASTER_RECOVERY_RUNBOOK.md",
    "docs\ADMIN_OPERATIONS_GUIDE.md"
)

foreach ($path in $required) {
    if (-not (Test-Path $path)) {
        throw "Required handover file is missing: $path"
    }
}

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

& docker compose @compose restart backend nginx
if ($LASTEXITCODE -ne 0) {
    throw "Backend and Nginx restart failed."
}

$ready = $null
$lastError = $null

for ($attempt = 1; $attempt -le 30; $attempt++) {
    Start-Sleep -Seconds 3

    try {
        & docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler *> $null
        $candidate = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health/ready" -TimeoutSec 20

        if ($candidate.status -eq "passed") {
            $ready = $candidate
            break
        }

        $lastError = "Readiness returned status: $($candidate.status)"
    } catch {
        $lastError = $_.Exception.Message
    }
}

if ($null -eq $ready) {
    & docker compose @compose ps
    & docker compose @compose logs --tail=120 backend nginx
    throw "Final readiness endpoint did not pass. Last error: $lastError"
}

$version = (Get-Content "VERSION" -Raw).Trim()
if ($version -eq "") {
    throw "VERSION is empty."
}

Write-Host "Phase 15 verification passed for release $version." -ForegroundColor Green