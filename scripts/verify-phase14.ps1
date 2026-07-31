$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

& ".\scripts\security-audit.ps1" -EnvironmentFile ".env"

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
$testExec = @(
    "exec", "-T",
    "-e", "APP_ENV=testing",
    "-e", "DB_CONNECTION=sqlite",
    "-e", "DB_DATABASE=:memory:",
    "-e", "CACHE_STORE=array",
    "-e", "SESSION_DRIVER=array",
    "-e", "QUEUE_CONNECTION=sync",
    "-e", "MAIL_MAILER=array",
    "backend"
)

& docker compose @compose @testExec php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw "Testing config clear failed." }

& docker compose @compose @testExec php artisan test
if ($LASTEXITCODE -ne 0) { throw "Backend regression suite failed." }

# Restore the long-running development PHP-FPM process after isolated tests.
& docker compose @compose exec -T backend php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Development cache restore failed." }

& docker compose @compose restart backend nginx
if ($LASTEXITCODE -ne 0) { throw "Backend/Nginx restart failed after tests." }

$live = $false
$lastError = $null

for ($attempt = 1; $attempt -le 30; $attempt++) {
    Start-Sleep -Seconds 3

    try {
        $response = Invoke-WebRequest `
            "http://localhost:8080/api/v1/health/live" `
            -UseBasicParsing `
            -Headers @{Accept="application/json"} `
            -TimeoutSec 20

        if ([int]$response.StatusCode -eq 200) {
            $live = $true
            break
        }

        $lastError = "HTTP $([int]$response.StatusCode)"
    } catch {
        $lastError = $_.Exception.Message
    }
}

if (-not $live) {
    & docker compose @compose ps
    & docker compose @compose logs --tail=150 backend nginx
    & docker compose @compose exec -T backend sh -lc "tail -n 150 storage/logs/laravel.log 2>/dev/null || true"
    throw "Liveness did not recover after the test environment. Last error: $lastError"
}

& docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler
if ($LASTEXITCODE -ne 0) {
    throw "Scheduler heartbeat refresh failed."
}

& docker compose @compose exec -T frontend npm run lint
if ($LASTEXITCODE -ne 0) { throw "Frontend lint failed." }

& docker compose @compose exec -T frontend npm run typecheck
if ($LASTEXITCODE -ne 0) { throw "Frontend typecheck failed." }

& ".\scripts\uat-smoke.ps1"
& ".\scripts\load-smoke.ps1" -Requests 30

Write-Host "Phase 14 verification passed." -ForegroundColor Green