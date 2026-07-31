$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = "C:\Projects\agcp"
Set-Location $ProjectRoot

$Utf8Bom = New-Object System.Text.UTF8Encoding($true)

Get-ChildItem -Path ".\scripts" -Filter "*.ps1" -Recurse | ForEach-Object {
    $Path = $_.FullName
    $Text = [System.IO.File]::ReadAllText($Path, [System.Text.Encoding]::UTF8)

    $Text = $Text.Replace([char]0x2013, "-")
    $Text = $Text.Replace([char]0x2014, "-")
    $Text = $Text.Replace([char]0x2018, "'")
    $Text = $Text.Replace([char]0x2019, "'")
    $Text = $Text.Replace([char]0x201C, '"')
    $Text = $Text.Replace([char]0x201D, '"')

    [System.IO.File]::WriteAllText($Path, $Text, $Utf8Bom)
    Write-Host "Fixed: $Path" -ForegroundColor Green
}

Write-Host "PowerShell encoding repair completed." -ForegroundColor Cyan
Write-Host "Now run: .\scripts\upgrade-phase13-15.ps1" -ForegroundColor Yellow
