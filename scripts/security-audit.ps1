param(
    [string]$EnvironmentFile = ".env",
    [switch]$Production
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

function Read-DotEnv {
    param([string]$Path)
    $values = @{}
    if (-not (Test-Path $Path)) { return $values }

    foreach ($line in Get-Content $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#") -or -not $trimmed.Contains("=")) { continue }
        $parts = $trimmed.Split("=", 2)
        $values[$parts[0].Trim()] = $parts[1].Trim().Trim('"')
    }
    return $values
}

$envValues = Read-DotEnv $EnvironmentFile
$results = New-Object System.Collections.Generic.List[object]
$failed = $false

function Add-Check {
    param([string]$Name, [bool]$Passed, [string]$Message, [bool]$Required)
    $status = if ($Passed) { "passed" } elseif ($Required) { "failed" } else { "warning" }
    if (-not $Passed -and $Required) { $script:failed = $true }
    $results.Add([pscustomobject]@{
        name = $Name
        status = $status
        message = $Message
    })
    $color = if ($status -eq "passed") { "Green" } elseif ($status -eq "failed") { "Red" } else { "Yellow" }
    Write-Host "[$($status.ToUpper())] $Name - $Message" -ForegroundColor $color
}

$debugOff = ($envValues.ContainsKey("APP_DEBUG") -and $envValues["APP_DEBUG"].ToLowerInvariant() -eq "false")
Add-Check "debug_disabled" $debugOff "APP_DEBUG should be false for production." $Production.IsPresent

$https = ($envValues.ContainsKey("APP_URL") -and $envValues["APP_URL"].StartsWith("https://"))
Add-Check "https_origin" $https "APP_URL should use HTTPS for production." $Production.IsPresent

$secureCookie = ($envValues.ContainsKey("SESSION_SECURE_COOKIE") -and $envValues["SESSION_SECURE_COOKIE"].ToLowerInvariant() -eq "true")
Add-Check "secure_cookie" $secureCookie "Secure session cookies should be enabled for production." $Production.IsPresent

$sessionEncrypted = ($envValues.ContainsKey("SESSION_ENCRYPT") -and $envValues["SESSION_ENCRYPT"].ToLowerInvariant() -eq "true")
Add-Check "encrypted_session" $sessionEncrypted "Redis sessions should be encrypted." $true

$appKeySafe = ($envValues.ContainsKey("APP_KEY") -and $envValues["APP_KEY"] -ne "" -and -not $envValues["APP_KEY"].StartsWith("CHANGE_ME"))
Add-Check "app_key" $appKeySafe "Application encryption key must be configured." $true

$backupKeySafe = ($envValues.ContainsKey("BACKUP_ENCRYPTION_KEY") -and $envValues["BACKUP_ENCRYPTION_KEY"] -ne "" -and -not $envValues["BACKUP_ENCRYPTION_KEY"].StartsWith("CHANGE_ME"))
Add-Check "backup_key" $backupKeySafe "Backup encryption key must be configured." $true

$trackedFiles = @(& git ls-files --cached -- .env)
$trackedEnv = $trackedFiles -contains ".env"
Add-Check "env_not_tracked" (-not $trackedEnv) ".env must not be tracked by Git." $true

$mailProduction = ($envValues.ContainsKey("MAIL_MAILER") -and $envValues["MAIL_MAILER"] -notin @("log", "array"))
Add-Check "production_mailer" $mailProduction "A real mail transport is required for production." $Production.IsPresent

$dist = Join-Path (Get-Location) "dist"
New-Item -ItemType Directory -Path $dist -Force | Out-Null
$report = [pscustomobject]@{
    generated_at = (Get-Date).ToUniversalTime().ToString("o")
    environment_file = $EnvironmentFile
    production_mode = $Production.IsPresent
    checks = $results
}
$report | ConvertTo-Json -Depth 6 | Set-Content (Join-Path $dist "phase14-security-audit.json") -Encoding UTF8

if ($failed) {
    throw "Security audit has required failures."
}
