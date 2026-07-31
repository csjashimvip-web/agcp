$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$compose = @('-f', 'docker-compose.yml', '-f', 'docker-compose.dev.yml')

function Invoke-DockerCompose {
    param(
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    & docker compose @compose @Arguments
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        $commandText = $Arguments -join ' '
        throw "docker compose command failed with exit code ${exitCode}: ${commandText}"
    }
}

Write-Host 'Applying AGCP Phase 11 payment relationship hotfix...' -ForegroundColor Cyan

Invoke-DockerCompose -Arguments @('exec', 'backend', 'php', 'artisan', 'optimize:clear')
Invoke-DockerCompose -Arguments @('restart', 'backend', 'queue-critical', 'queue-default', 'scheduler')

Write-Host 'Waiting for backend health...' -ForegroundColor Yellow
$healthy = $false

for ($attempt = 1; $attempt -le 30; $attempt++) {
    Start-Sleep -Seconds 2
    $status = & docker inspect --format='{{.State.Health.Status}}' agcp-backend-1 2>$null
    $inspectExitCode = $LASTEXITCODE

    if ($inspectExitCode -eq 0 -and $status -eq 'healthy') {
        $healthy = $true
        break
    }
}

if (-not $healthy) {
    & docker compose @compose logs --tail=200 backend
    throw 'Backend did not become healthy after the payment hotfix restart.'
}

Invoke-DockerCompose -Arguments @(
    'exec',
    'backend',
    'php',
    'artisan',
    'test',
    'tests/Feature/PaymentOrchestrationTest.php',
    '--stop-on-failure'
)

Write-Host ''
Write-Host 'Payment relationship hotfix completed successfully.' -ForegroundColor Green
Write-Host 'Now run: .\scripts\verify-phase11.ps1'
