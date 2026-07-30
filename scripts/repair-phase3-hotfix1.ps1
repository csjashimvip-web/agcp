$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

function Invoke-CheckedDocker {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [Parameter(Mandatory = $true)][string]$FailureMessage
    )

    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$FailureMessage (exit code: $LASTEXITCODE)"
    }
}

Write-Host "Applying AGCP Phase 3 seeder and wallet-test hotfix..." -ForegroundColor Cyan

Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("ps")) -FailureMessage "Unable to read AGCP container status"
Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("exec", "-T", "backend", "php", "artisan", "migrate", "--force")) -FailureMessage "Phase 3 migrations failed"
Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("exec", "-T", "backend", "php", "artisan", "db:seed", "--class=DatabaseSeeder", "--force")) -FailureMessage "Tenant, identity, and wallet permission seeding failed"
Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("exec", "-T", "backend", "php", "artisan", "optimize:clear")) -FailureMessage "Laravel cache reset failed"
Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("exec", "-T", "backend", "php", "artisan", "test", "--filter=WalletDepositTest")) -FailureMessage "Wallet feature tests failed"
Invoke-CheckedDocker -Arguments (@("compose") + $compose + @("exec", "-T", "frontend", "npm", "run", "typecheck")) -FailureMessage "Frontend type checking failed"

try {
    $health = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 20
} catch {
    throw "AGCP health check failed: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "AGCP Phase 3 hotfix completed successfully." -ForegroundColor Green
Write-Host "Website: http://localhost:8080"
Write-Host "Wallet:  http://localhost:8080/wallet"
Write-Host "Deposit: http://localhost:8080/deposits"
$health | ConvertTo-Json -Depth 6
