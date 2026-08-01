param(
    [string]$Url = "http://127.0.0.1:8000/api/v1/platform/readiness",
    [int]$Requests = 100,
    [int]$Concurrency = 10
)

$ErrorActionPreference = "Stop"

if ($Requests -lt 1 -or $Requests -gt 5000) {
    throw "Requests must be between 1 and 5000."
}

if ($Concurrency -lt 1 -or $Concurrency -gt 100) {
    throw "Concurrency must be between 1 and 100."
}

$handler = [System.Net.Http.SocketsHttpHandler]::new()
$handler.MaxConnectionsPerServer = $Concurrency
$client = [System.Net.Http.HttpClient]::new($handler)
$client.Timeout = [TimeSpan]::FromSeconds(30)

$durations = [System.Collections.Concurrent.ConcurrentBag[double]]::new()
$failures = [System.Collections.Concurrent.ConcurrentBag[string]]::new()

$semaphore = [System.Threading.SemaphoreSlim]::new($Concurrency, $Concurrency)
$tasks = [System.Collections.Generic.List[System.Threading.Tasks.Task]]::new()

for ($i = 0; $i -lt $Requests; $i++) {
    $task = [System.Threading.Tasks.Task]::Run([Action]{
        $semaphore.Wait()

        try {
            $sw = [System.Diagnostics.Stopwatch]::StartNew()
            $response = $client.GetAsync($Url).GetAwaiter().GetResult()
            $sw.Stop()

            $durations.Add($sw.Elapsed.TotalMilliseconds)

            if (-not $response.IsSuccessStatusCode) {
                $failures.Add([string]$response.StatusCode)
            }
        }
        catch {
            $failures.Add($_.Exception.Message)
        }
        finally {
            $semaphore.Release() | Out-Null
        }
    })

    $tasks.Add($task)
}

[System.Threading.Tasks.Task]::WaitAll($tasks.ToArray())

$values = @($durations.ToArray() | Sort-Object)

if ($values.Count -eq 0) {
    throw "No successful timing samples were collected."
}

function Percentile([double[]]$Sorted, [double]$Ratio) {
    $index = [Math]::Floor(($Sorted.Count - 1) * $Ratio)
    return [Math]::Round($Sorted[$index], 2)
}

$result = [ordered]@{
    url = $Url
    requests = $Requests
    concurrency = $Concurrency
    completed = $values.Count
    failures = $failures.Count
    p50_ms = Percentile $values 0.50
    p95_ms = Percentile $values 0.95
    p99_ms = Percentile $values 0.99
    max_ms = [Math]::Round(($values | Measure-Object -Maximum).Maximum, 2)
}

$result | ConvertTo-Json -Depth 4

$client.Dispose()
$handler.Dispose()
$semaphore.Dispose()