param(
    [string]$ProjectRoot = "C:\Projects\agcp-2026-2027"
)

$ErrorActionPreference = "Stop"

$Backend = Join-Path $ProjectRoot "apps\backend"
$Web = Join-Path $ProjectRoot "apps\web"

function Launch(
    [string]$Title,
    [string]$WorkingDirectory,
    [string]$Command
) {
    $escapedTitle = $Title.Replace("'", "''")
    $escapedCommand = $Command.Replace("'", "''")

    $payload = @"
`$Host.UI.RawUI.WindowTitle = '$escapedTitle'
Set-Location '$WorkingDirectory'
$escapedCommand
"@

    Start-Process powershell.exe -ArgumentList @(
        "-NoExit",
        "-ExecutionPolicy", "Bypass",
        "-Command", $payload
    )
}

if (-not (Test-Path (Join-Path $Backend "artisan"))) {
    throw "Backend not found."
}

if (-not (Test-Path (Join-Path $Web "package.json"))) {
    throw "Frontend not found."
}

$redis = Test-NetConnection 127.0.0.1 -Port 6379 -WarningAction SilentlyContinue

if (-not $redis.TcpTestSucceeded) {
    Write-Warning "Redis-compatible service is not listening on 127.0.0.1:6379."
    Write-Warning "Queue worker functionality will fail until Redis is running."
}

Launch "AGCP Backend" $Backend "php artisan serve --host=127.0.0.1 --port=8000"
Launch "AGCP Queue" $Backend "php artisan queue:work redis --queue=supplier,default --sleep=1 --tries=3 --timeout=120"
Launch "AGCP Scheduler" $Backend "php artisan schedule:work"
Launch "AGCP Web" $Web "npm run dev"

Write-Host ""
Write-Host "AGCP runtime windows launched." -ForegroundColor Green
Write-Host "Backend : http://127.0.0.1:8000"
Write-Host "Frontend: http://localhost:3000"
Write-Host ""
Write-Host "Keep Queue and Scheduler windows running during development."