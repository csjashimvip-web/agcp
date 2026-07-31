param(
    [Parameter(Mandatory=$true)][string]$Version
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

if (-not (Test-Path ".git")) {
    throw "A Git repository is required to create a clean release archive."
}

New-Item -ItemType Directory -Path "dist" -Force | Out-Null
$output = "dist\agcp-v$Version-source.zip"
$checksum = "$output.sha256"

if (Test-Path $output) { Remove-Item $output -Force }
if (Test-Path $checksum) { Remove-Item $checksum -Force }

& git archive --format=zip --output=$output HEAD
if ($LASTEXITCODE -ne 0) { throw "Git release archive creation failed." }

$hash = (Get-FileHash $output -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  $(Split-Path $output -Leaf)" | Set-Content $checksum -Encoding ASCII

Write-Host "Release archive: $output" -ForegroundColor Green
Write-Host "Checksum: $checksum" -ForegroundColor Green
