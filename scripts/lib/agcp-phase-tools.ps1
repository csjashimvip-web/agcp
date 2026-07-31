Set-StrictMode -Version Latest

function Assert-AgcpProjectRoot {
    if (-not (Test-Path "docker-compose.yml")) {
        throw "Run this script from the AGCP project root."
    }
    if (-not (Test-Path "apps\backend\artisan")) {
        throw "Laravel backend was not found."
    }
    if (-not (Test-Path "apps\web\package.json")) {
        throw "Next.js frontend was not found."
    }
}

function Set-AgcpEnvValue {
    param(
        [Parameter(Mandatory=$true)][string]$Path,
        [Parameter(Mandatory=$true)][string]$Name,
        [Parameter(Mandatory=$true)][string]$Value
    )

    if (-not (Test-Path $Path)) {
        return
    }

    $content = [System.IO.File]::ReadAllText((Resolve-Path $Path))
    $escaped = [regex]::Escape($Name)
    if ($content -match "(?m)^$escaped=") {
        $content = [regex]::Replace($content, "(?m)^$escaped=.*$", "$Name=$Value")
    } else {
        if (-not $content.EndsWith("`n")) { $content += "`n" }
        $content += "$Name=$Value`n"
    }
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $content, [System.Text.UTF8Encoding]::new($false))
}

function Set-AgcpWebVersion {
    param([Parameter(Mandatory=$true)][string]$Version)

    $path = "apps\web\package.json"
    $content = [System.IO.File]::ReadAllText((Resolve-Path $path))
    $content = [regex]::Replace($content, '"version"\s*:\s*"[^"]+"', '"version": "' + $Version + '"', 1)
    [System.IO.File]::WriteAllText((Resolve-Path $path), $content, [System.Text.UTF8Encoding]::new($false))
}

function Backup-AgcpFile {
    param([Parameter(Mandatory=$true)][string]$Path, [Parameter(Mandatory=$true)][string]$Suffix)

    if (Test-Path $Path) {
        Copy-Item $Path "$Path.$Suffix" -Force
    }
}

function Invoke-AgcpCompose {
    param([Parameter(ValueFromRemainingArguments=$true)][string[]]$Arguments)

    $compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
    & docker compose @compose @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose command failed: $($Arguments -join ' ')"
    }
}

function Test-AgcpArtisanCommand {
    param([Parameter(Mandatory=$true)][string]$Name)

    $output = & docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan list --raw 2>$null
    return ($output -match "(?m)^$([regex]::Escape($Name))\s")
}
