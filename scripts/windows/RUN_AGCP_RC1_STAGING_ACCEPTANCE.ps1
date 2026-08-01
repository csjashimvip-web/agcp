param(
    [string]$ProjectRoot = "C:\Projects\agcp-2026-2027",
    [string]$Environment = "staging"
)

$ErrorActionPreference = "Stop"

$Backend = Join-Path $ProjectRoot "apps\backend"
$Web = Join-Path $ProjectRoot "apps\web"

Push-Location $ProjectRoot
try {
    $Commit = (& git rev-parse HEAD).Trim()
}
finally {
    Pop-Location
}

Write-Host "AGCP RC1 STAGING ACCEPTANCE" -ForegroundColor Cyan
Write-Host "Commit: $Commit"
Write-Host "Environment: $Environment"

Push-Location $Backend
try {
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw "optimize:clear failed" }

    & php artisan migrate:status
    if ($LASTEXITCODE -ne 0) { throw "migration status failed" }

    & php artisan test
    if ($LASTEXITCODE -ne 0) { throw "Laravel tests failed" }

    & php artisan agcp:api-contract-audit
    if ($LASTEXITCODE -ne 0) { throw "API contract audit failed" }

    & php artisan agcp:security-audit `
        --environment=$Environment `
        --git-commit=$Commit

    if ($LASTEXITCODE -ne 0) {
        throw "Security audit failed"
    }
}
finally {
    Pop-Location
}

Push-Location $Web
try {
    & npx tsc --noEmit
    if ($LASTEXITCODE -ne 0) { throw "TypeScript failed" }

    & npm run build
    if ($LASTEXITCODE -ne 0) { throw "Next.js build failed" }
}
finally {
    Pop-Location
}

Push-Location $Backend
try {
    & php artisan agcp:performance-baseline `
        --environment=$Environment `
        --samples=25

    if ($LASTEXITCODE -ne 0) {
        throw "Performance baseline failed"
    }

    & php artisan agcp:staging-acceptance `
        --environment=$Environment `
        --git-commit=$Commit

    if ($LASTEXITCODE -ne 0) {
        throw "Staging acceptance blocked"
    }
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "AGCP RC1 STAGING ACCEPTANCE: PASS" -ForegroundColor Green