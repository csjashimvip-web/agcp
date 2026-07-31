param(
    [Parameter(Mandatory=$true)][string]$Version,
    [switch]$CreateTag
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$status = & git status --porcelain
if ($LASTEXITCODE -ne 0) { throw "Unable to read Git status." }
if ($status) {
    throw "Git worktree is not clean. Commit or stash changes before creating the final release."
}

& ".\scripts\verify-phase13-15.ps1"
& ".\scripts\create-release-package.ps1" -Version $Version

if ($CreateTag) {
    & git tag -a "v$Version" -m "AGCP Enterprise Platform stable release v$Version"
    if ($LASTEXITCODE -ne 0) { throw "Git tag creation failed." }
    Write-Host "Created tag v$Version. Push it with: git push origin v$Version" -ForegroundColor Yellow
}

Write-Host "AGCP release v$Version is ready." -ForegroundColor Green
