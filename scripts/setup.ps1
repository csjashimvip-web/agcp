$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [Parameter(Mandatory = $true)][string]$FailureMessage
    )

    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$FailureMessage (exit code: $LASTEXITCODE)"
    }
}

function Test-DockerEngine {
    & docker info --format '{{.ServerVersion}}' *> $null
    return ($LASTEXITCODE -eq 0)
}

function Get-DockerOsType {
    $osType = & docker info --format '{{.OSType}}' 2>$null
    if ($LASTEXITCODE -ne 0) { return $null }
    return ($osType | Out-String).Trim().ToLowerInvariant()
}

function Start-DockerEngine {
    if (Test-DockerEngine) { return }

    Write-Host "Docker engine is not running. Attempting to start Docker Desktop..." -ForegroundColor Yellow
    $desktopLaunchAttempted = $false
    & docker desktop start *> $null
    if ($LASTEXITCODE -eq 0) { $desktopLaunchAttempted = $true }

    if (-not $desktopLaunchAttempted) {
        $candidatePaths = @(
            (Join-Path $Env:ProgramFiles "Docker\Docker\Docker Desktop.exe"),
            (Join-Path $Env:LOCALAPPDATA "Docker\Docker Desktop.exe")
        )
        foreach ($candidate in $candidatePaths) {
            if (Test-Path $candidate) {
                Start-Process -FilePath $candidate | Out-Null
                $desktopLaunchAttempted = $true
                break
            }
        }
    }

    if (-not $desktopLaunchAttempted) {
        throw "Docker Desktop could not be started automatically. Open Docker Desktop with Linux containers and run this script again."
    }

    for ($attempt = 1; $attempt -le 90; $attempt++) {
        Start-Sleep -Seconds 2
        if (Test-DockerEngine) {
            Write-Host "Docker engine is ready." -ForegroundColor Green
            return
        }
    }

    throw "Docker Desktop opened, but its Linux engine did not become ready."
}

function New-RandomBytes([int]$Length) {
    $bytes = New-Object byte[] $Length
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return $bytes
}

function New-Base64UrlSecret([int]$Length) {
    return [Convert]::ToBase64String((New-RandomBytes $Length)).TrimEnd('=').Replace('+', '-').Replace('/', '_')
}

function Get-EnvValue([string]$Key) {
    if (-not (Test-Path ".env")) { return $null }
    $escaped = [regex]::Escape($Key)
    $line = Get-Content ".env" | Where-Object { $_ -match "^$escaped=" } | Select-Object -Last 1
    if ($null -eq $line) { return $null }
    return ($line -replace "^$escaped=", '').Trim('"')
}

function Set-EnvValue([string]$Key, [string]$Value, [switch]$OnlyIfMissing) {
    $escaped = [regex]::Escape($Key)
    $content = Get-Content ".env" -Raw
    $exists = $content -match "(?m)^$escaped="
    if ($OnlyIfMissing -and $exists) { return }

    $line = "$Key=$Value"
    if ($exists) {
        $content = [regex]::Replace($content, "(?m)^$escaped=.*$", $line)
    } else {
        $content = $content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
    }
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) ".env"), $content, (New-Object System.Text.UTF8Encoding($false)))
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop is not installed or the docker command is not in PATH."
}

Invoke-CheckedCommand -Command "docker" -Arguments @("compose", "version") -FailureMessage "Docker Compose V2 is required"
Start-DockerEngine
if ((Get-DockerOsType) -ne "linux") {
    throw "AGCP requires Linux containers. Switch Docker Desktop to Linux containers."
}

if (-not (Test-Path ".env.example")) {
    throw ".env.example was not found."
}

$generatedAdminPassword = $null
if (-not (Test-Path ".env")) {
    $content = Get-Content ".env.example" -Raw
    $appKey = "base64:" + [Convert]::ToBase64String((New-RandomBytes 32))
    $generatedAdminPassword = New-Base64UrlSecret 24
    $content = $content.Replace("CHANGE_ME_APP_KEY", $appKey)
    $content = $content.Replace("CHANGE_ME_DB_PASSWORD", (New-Base64UrlSecret 32))
    $content = $content.Replace("CHANGE_ME_MYSQL_ROOT_PASSWORD", (New-Base64UrlSecret 40))
    $content = $content.Replace("CHANGE_ME_REDIS_PASSWORD", (New-Base64UrlSecret 40))
    $content = $content.Replace("CHANGE_ME_PASSKEYS_SECRET", (New-Base64UrlSecret 48))
    $content = $content.Replace("CHANGE_ME_INITIAL_ADMIN_PASSWORD", $generatedAdminPassword)
    [System.IO.File]::WriteAllText((Join-Path (Get-Location) ".env"), $content, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host "Generated secure local .env file." -ForegroundColor Green
} else {
    Write-Host "Using and upgrading the existing .env file." -ForegroundColor Cyan
    Set-EnvValue "APP_VERSION" "6.0.0-phase6"
    Set-EnvValue "SANCTUM_STATEFUL_DOMAINS" "localhost:8080,localhost,127.0.0.1:8080,127.0.0.1" -OnlyIfMissing
    Set-EnvValue "FORTIFY_PREFIX" "api/v1/auth" -OnlyIfMissing
    Set-EnvValue "PASSKEYS_ALLOWED_ORIGINS" "http://localhost:8080" -OnlyIfMissing
    Set-EnvValue "SESSION_ENCRYPT" "true" -OnlyIfMissing
    Set-EnvValue "SESSION_SAME_SITE" "lax" -OnlyIfMissing
    Set-EnvValue "MAIL_MAILER" "log" -OnlyIfMissing
    Set-EnvValue "MAIL_FROM_ADDRESS" "noreply@localhost.test" -OnlyIfMissing
    Set-EnvValue "MAIL_FROM_NAME" '"Araabi Global"' -OnlyIfMissing
    Set-EnvValue "SUPPLIER_POLL_BATCH_SIZE" "100" -OnlyIfMissing
    Set-EnvValue "INITIAL_ADMIN_NAME" '"AGCP Administrator"' -OnlyIfMissing
    Set-EnvValue "INITIAL_ADMIN_EMAIL" "admin@localhost.test" -OnlyIfMissing

    $passkeySecret = Get-EnvValue "PASSKEYS_USER_HANDLE_SECRET"
    if ([string]::IsNullOrWhiteSpace($passkeySecret) -or $passkeySecret.StartsWith("CHANGE_ME_")) {
        Set-EnvValue "PASSKEYS_USER_HANDLE_SECRET" (New-Base64UrlSecret 48)
    }

    $adminPassword = Get-EnvValue "INITIAL_ADMIN_PASSWORD"
    if ([string]::IsNullOrWhiteSpace($adminPassword) -or $adminPassword.StartsWith("CHANGE_ME_")) {
        $generatedAdminPassword = New-Base64UrlSecret 24
        Set-EnvValue "INITIAL_ADMIN_PASSWORD" $generatedAdminPassword
    }
}

$composeFiles = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("config", "--quiet")) -FailureMessage "Docker Compose configuration validation failed"

Write-Host "Building and starting AGCP Phase 5 containers..." -ForegroundColor Cyan
try {
    Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("up", "-d", "--build", "--wait", "--wait-timeout", "900")) -FailureMessage "AGCP containers failed to build or become healthy"
} catch {
    Write-Host "AGCP startup diagnostics:" -ForegroundColor Yellow
    & docker compose @composeFiles ps
    & docker compose @composeFiles logs --tail 200 backend frontend nginx
    throw
}

Write-Host "Running Phase 5 migrations and seeders..." -ForegroundColor Cyan
Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("exec", "-T", "backend", "php", "artisan", "migrate", "--seed", "--force")) -FailureMessage "Database migration or seeding failed"

Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("ps")) -FailureMessage "Unable to read AGCP container status"

Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("exec", "-T", "backend", "php", "artisan", "route:list", "--path=api/v1/auth", "--except-vendor")) -FailureMessage "Identity routes could not be verified"
Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("exec", "-T", "backend", "php", "artisan", "route:list", "--path=api/v1/admin/suppliers", "--except-vendor")) -FailureMessage "Supplier routes could not be verified"
Invoke-CheckedCommand -Command "docker" -Arguments (@("compose") + $composeFiles + @("exec", "-T", "backend", "php", "artisan", "supplier:health-check")) -FailureMessage "Supplier health check failed"

try {
    $response = Invoke-WebRequest -Uri "http://localhost:8080/api/v1/health" -UseBasicParsing -TimeoutSec 20
    if ($response.StatusCode -ne 200) { throw "Unexpected health response." }
} catch {
    throw "Containers started, but the AGCP API health check failed: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "AGCP Phase 5 setup completed successfully." -ForegroundColor Green
Write-Host "Website:        http://localhost:8080"
Write-Host "Register:       http://localhost:8080/register"
Write-Host "Login:          http://localhost:8080/login"
Write-Host "Security:       http://localhost:8080/security"
Write-Host "Admin:          http://localhost:8080/admin"
Write-Host "Suppliers:      http://localhost:8080/admin/suppliers"
Write-Host "Backend health: http://localhost:8080/api/v1/health"

if ($null -ne $generatedAdminPassword) {
    Write-Host ""
    Write-Host "Initial administrator credentials (save securely):" -ForegroundColor Yellow
    Write-Host "Email:    $(Get-EnvValue 'INITIAL_ADMIN_EMAIL')"
    Write-Host "Password: $generatedAdminPassword"
    Write-Host "Administrative pages require enabling two-factor authentication first."
}
