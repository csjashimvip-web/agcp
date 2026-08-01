$ErrorActionPreference = "SilentlyContinue"

$targets = @(
    "AGCP Backend",
    "AGCP Queue",
    "AGCP Scheduler",
    "AGCP Web"
)

Get-Process powershell | Where-Object {
    $targets -contains $_.MainWindowTitle
} | ForEach-Object {
    Write-Host "Stopping $($_.MainWindowTitle)..."
    Stop-Process -Id $_.Id -Force
}

Write-Host "AGCP labeled runtime windows stopped." -ForegroundColor Green