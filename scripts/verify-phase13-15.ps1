$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

& ".\scripts\verify-phase13.ps1"
& ".\scripts\verify-phase14.ps1"
& ".\scripts\verify-phase15.ps1"

Write-Host "AGCP Phases 13-15 verification completed successfully." -ForegroundColor Green
