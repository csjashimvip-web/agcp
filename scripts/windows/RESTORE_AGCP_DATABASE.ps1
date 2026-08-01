param(
    [Parameter(Mandatory = $true)]
    [string]$SqlPath,

    [string]$ProjectRoot = "C:\Projects\agcp-2026-2027",

    [string]$MySqlPath = "C:\xampp\mysql\bin\mysql.exe",

    [switch]$IUnderstandThisWillReplaceDatabase
)

$ErrorActionPreference = "Stop"

if (-not $IUnderstandThisWillReplaceDatabase) {
    throw "Refusing restore. Re-run with -IUnderstandThisWillReplaceDatabase."
}

if (-not (Test-Path -LiteralPath $SqlPath)) {
    throw "SQL backup not found: $SqlPath"
}

if (-not (Test-Path -LiteralPath $MySqlPath)) {
    throw "mysql client not found: $MySqlPath"
}

$Backend = Join-Path $ProjectRoot "apps\backend"
$EnvPath = Join-Path $Backend ".env"

function EnvValue([string]$Name) {
    $line = Get-Content -LiteralPath $EnvPath |
        Where-Object { $_ -match "^$([regex]::Escape($Name))=" } |
        Select-Object -First 1

    if (-not $line) { return "" }

    return ($line -split "=", 2)[1].Trim().Trim('"')
}

$DbHost = EnvValue "DB_HOST"
$DbPort = EnvValue "DB_PORT"
$DbName = EnvValue "DB_DATABASE"
$DbUser = EnvValue "DB_USERNAME"
$DbPassword = EnvValue "DB_PASSWORD"

if (-not $DbHost) { $DbHost = "127.0.0.1" }
if (-not $DbPort) { $DbPort = "3306" }

$manifestPath = Join-Path (Split-Path $SqlPath -Parent) "manifest.json"

if (Test-Path -LiteralPath $manifestPath) {
    $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
    $actual = (Get-FileHash -LiteralPath $SqlPath -Algorithm SHA256).Hash.ToLowerInvariant()

    if ($actual -ne ([string]$manifest.sha256).ToLowerInvariant()) {
        throw "Backup hash verification FAILED."
    }

    Write-Host "[PASS] Backup SHA-256 verified" -ForegroundColor Green
}
else {
    Write-Warning "No manifest.json found. SHA-256 cannot be verified automatically."
}

Write-Host ""
Write-Host "TARGET DATABASE: $DbName" -ForegroundColor Yellow
Write-Host "This operation replaces data in the target database." -ForegroundColor Yellow

$previousPassword = $env:MYSQL_PWD

try {
    $env:MYSQL_PWD = $DbPassword

    $command = '"' + $MySqlPath + '" ' +
        '--host="' + $DbHost + '" ' +
        '--port="' + $DbPort + '" ' +
        '--user="' + $DbUser + '" ' +
        '"' + $DbName + '" < "' + $SqlPath + '"'

    & cmd.exe /d /s /c $command

    if ($LASTEXITCODE -ne 0) {
        throw "Database restore failed with exit code $LASTEXITCODE"
    }
}
finally {
    $env:MYSQL_PWD = $previousPassword
}

Write-Host ""
Write-Host "AGCP DATABASE RESTORE COMPLETE" -ForegroundColor Green
Write-Host "Run application migrations/status checks before opening traffic."