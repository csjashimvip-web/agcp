param(
    [string]$ProjectRoot = "C:\Projects\agcp-2026-2027",
    [string]$MySqlDumpPath = "C:\xampp\mysql\bin\mysqldump.exe",
    [string]$DestinationRoot = "C:\Projects\agcp-data-backups"
)

$ErrorActionPreference = "Stop"

$Backend = Join-Path $ProjectRoot "apps\backend"
$EnvPath = Join-Path $Backend ".env"

if (-not (Test-Path -LiteralPath $EnvPath)) {
    throw "Laravel .env not found."
}

if (-not (Test-Path -LiteralPath $MySqlDumpPath)) {
    throw "mysqldump not found: $MySqlDumpPath"
}

function EnvValue([string]$Name) {
    $line = Get-Content -LiteralPath $EnvPath |
        Where-Object { $_ -match "^$([regex]::Escape($Name))=" } |
        Select-Object -First 1

    if (-not $line) {
        return ""
    }

    return ($line -split "=", 2)[1].Trim().Trim('"')
}

$DbHost = EnvValue "DB_HOST"
$DbPort = EnvValue "DB_PORT"
$DbName = EnvValue "DB_DATABASE"
$DbUser = EnvValue "DB_USERNAME"
$DbPassword = EnvValue "DB_PASSWORD"

if (-not $DbHost) { $DbHost = "127.0.0.1" }
if (-not $DbPort) { $DbPort = "3306" }

if (-not $DbName -or -not $DbUser) {
    throw "DB_DATABASE or DB_USERNAME is missing from .env"
}

$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$Folder = Join-Path $DestinationRoot $Stamp
New-Item -ItemType Directory -Force -Path $Folder | Out-Null

$SqlPath = Join-Path $Folder "agcp-$Stamp.sql"
$ManifestPath = Join-Path $Folder "manifest.json"

$previousPassword = $env:MYSQL_PWD

try {
    $env:MYSQL_PWD = $DbPassword

    $args = @(
        "--host=$DbHost",
        "--port=$DbPort",
        "--user=$DbUser",
        "--single-transaction",
        "--routines",
        "--events",
        "--triggers",
        "--hex-blob",
        "--default-character-set=utf8mb4",
        $DbName
    )

    $process = Start-Process `
        -FilePath $MySqlDumpPath `
        -ArgumentList $args `
        -RedirectStandardOutput $SqlPath `
        -NoNewWindow `
        -Wait `
        -PassThru

    if ($process.ExitCode -ne 0) {
        throw "mysqldump failed with exit code $($process.ExitCode)"
    }
}
finally {
    $env:MYSQL_PWD = $previousPassword
}

$file = Get-Item -LiteralPath $SqlPath
$sha = (Get-FileHash -LiteralPath $SqlPath -Algorithm SHA256).Hash.ToLowerInvariant()

$manifest = [ordered]@{
    version = 1
    created_at = (Get-Date).ToString("o")
    database = $DbName
    sql_file = $file.Name
    size_bytes = $file.Length
    sha256 = $sha
}

$manifest | ConvertTo-Json -Depth 4 |
    Set-Content -LiteralPath $ManifestPath -Encoding UTF8

Push-Location $Backend
try {
    & php artisan agcp:backup-register $SqlPath $sha $file.Length

    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Backup succeeded, but catalog registration failed."
    }
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "AGCP DATABASE BACKUP COMPLETE" -ForegroundColor Green
Write-Host "SQL:      $SqlPath"
Write-Host "Manifest: $ManifestPath"
Write-Host "SHA-256:  $sha"