$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$composeFiles = @(
    "-f", "docker-compose.yml",
    "-f", "docker-compose.dev.yml"
)

Write-Host "Stopping AGCP development containers..." -ForegroundColor Cyan
& docker compose @composeFiles down --remove-orphans
if ($LASTEXITCODE -ne 0) {
    throw "Unable to stop the AGCP development stack."
}

$projectName = "agcp"
if (Test-Path ".env") {
    $projectLine = Get-Content ".env" | Where-Object { $_ -match '^COMPOSE_PROJECT_NAME=' } | Select-Object -First 1
    if ($projectLine) {
        $projectName = ($projectLine -split '=', 2)[1].Trim()
    }
}

$dependencyVolumes = @(
    "${projectName}_backend_vendor",
    "${projectName}_frontend_node_modules",
    "${projectName}_frontend_next"
)

foreach ($volume in $dependencyVolumes) {
    & docker volume inspect $volume *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Removing dependency cache volume: $volume" -ForegroundColor Yellow
        & docker volume rm $volume
        if ($LASTEXITCODE -ne 0) {
            throw "Unable to remove dependency cache volume: $volume"
        }
    }
}

Write-Host "Development dependency volumes reset successfully." -ForegroundColor Green
Write-Host "MySQL and Redis data volumes were not removed."
