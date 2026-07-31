param(
    [string]$BaseUrl = "http://localhost:8080"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Assert-Status {
    param([string]$Name, [scriptblock]$Request, [int[]]$Expected)

    try {
        $response = & $Request
        $status = [int]$response.StatusCode
    } catch {
        if ($_.Exception.Response -ne $null) {
            $status = [int]$_.Exception.Response.StatusCode
        } else {
            throw
        }
    }

    if ($Expected -notcontains $status) {
        throw "$Name returned HTTP $status; expected $($Expected -join ', ')."
    }

    Write-Host "[PASSED] $Name - HTTP $status" -ForegroundColor Green
}

Assert-Status "Login page" { Invoke-WebRequest "$BaseUrl/login" -UseBasicParsing -TimeoutSec 20 } @(200)
Assert-Status "Liveness" { Invoke-WebRequest "$BaseUrl/api/v1/health/live" -UseBasicParsing -Headers @{Accept="application/json"} -TimeoutSec 20 } @(200)
Assert-Status "Readiness" { Invoke-WebRequest "$BaseUrl/api/v1/health/ready" -UseBasicParsing -Headers @{Accept="application/json"} -TimeoutSec 20 } @(200)
Assert-Status "Unauthenticated profile protection" { Invoke-WebRequest "$BaseUrl/api/v1/auth/me" -UseBasicParsing -Headers @{Accept="application/json"} -TimeoutSec 20 } @(401)

Write-Host "Public and authentication boundary smoke tests passed." -ForegroundColor Green
