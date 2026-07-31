param(
    [string]$Url = "http://localhost:8080/api/v1/health/live",
    [ValidateRange(5, 500)][int]$Requests = 50
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$times = New-Object System.Collections.Generic.List[double]
$failures = 0

for ($i = 1; $i -le $Requests; $i++) {
    $watch = [System.Diagnostics.Stopwatch]::StartNew()
    try {
        $response = Invoke-WebRequest $Url -UseBasicParsing -TimeoutSec 20
        if ([int]$response.StatusCode -ne 200) { $failures++ }
    } catch {
        $failures++
    } finally {
        $watch.Stop()
        $times.Add($watch.Elapsed.TotalMilliseconds)
    }
}

$sorted = $times | Sort-Object
$index = [Math]::Ceiling($sorted.Count * 0.95) - 1
if ($index -lt 0) { $index = 0 }

$result = [pscustomobject]@{
    url = $Url
    requests = $Requests
    failures = $failures
    average_ms = [Math]::Round(($times | Measure-Object -Average).Average, 2)
    p95_ms = [Math]::Round($sorted[$index], 2)
    maximum_ms = [Math]::Round(($times | Measure-Object -Maximum).Maximum, 2)
}

$result | Format-List
New-Item -ItemType Directory -Path "dist" -Force | Out-Null
$result | ConvertTo-Json | Set-Content "dist\phase14-load-smoke.json" -Encoding UTF8

if ($failures -gt 0) {
    throw "$failures load-smoke requests failed."
}
