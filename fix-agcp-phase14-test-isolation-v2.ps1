$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = "C:\Projects\agcp"
Set-Location $ProjectRoot

$MiddlewarePath = Join-Path $ProjectRoot "apps\backend\app\Modules\Identity\Http\Middleware\EnsureAdminTwoFactor.php"
$TestPath = Join-Path $ProjectRoot "apps\backend\tests\Feature\IdentityAccessTest.php"
$PhpunitPath = Join-Path $ProjectRoot "apps\backend\phpunit.xml"
$Verify14Path = Join-Path $ProjectRoot "scripts\verify-phase14.ps1"
$Verify15Path = Join-Path $ProjectRoot "scripts\verify-phase15.ps1"
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$Utf8Bom = New-Object System.Text.UTF8Encoding($true)

foreach ($Path in @($MiddlewarePath, $TestPath, $PhpunitPath, $Verify14Path, $Verify15Path)) {
    if (-not (Test-Path $Path)) {
        throw "Required file was not found: $Path"
    }
    Copy-Item $Path "$Path.phase14-v2-backup-$Stamp" -Force
}

# 1. Correct browser-session vs bearer-token detection.
$Middleware = @'
<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        /*
         * Administrative APIs are browser-session only.
         * Sanctum SPA cookie authentication does not send an Authorization bearer header.
         */
        if ($request->bearerToken() !== null) {
            return response()->json([
                'message' => 'Administrative access requires an interactive browser session.',
                'code' => 'ADMIN_BROWSER_SESSION_REQUIRED',
            ], 403);
        }

        if ($user->two_factor_confirmed_at === null) {
            return response()->json([
                'message' => 'Two-factor authentication is required for administrative access.',
                'code' => 'ADMIN_TWO_FACTOR_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
'@
[System.IO.File]::WriteAllText($MiddlewarePath, $Middleware, $Utf8NoBom)

# 2. Make the bearer-token test send a real Authorization header.
$TestText = [System.IO.File]::ReadAllText($TestPath, [System.Text.Encoding]::UTF8)
$TestText = $TestText.Replace("use Laravel\Sanctum\Sanctum;`r`n", "")
$TestText = $TestText.Replace("use Laravel\Sanctum\Sanctum;`n", "")

$BearerTest = @'
it('rejects bearer-token access to interactive administration', function (): void {
    $tenant = identityTenant();
    $adminAccess = identityPermission('identity.admin.access');
    $role = identityRole($tenant, 'tenant-admin', [$adminAccess], true);
    $admin = identityUser($tenant, ['two_factor_confirmed_at' => now()]);
    $admin->roles()->attach($role);

    $plainTextToken = $admin
        ->createToken('identity-admin-test', ['identity.admin.access'])
        ->plainTextToken;

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/admin/users')
        ->assertForbidden()
        ->assertJsonPath('code', 'ADMIN_BROWSER_SESSION_REQUIRED');
});
'@

$Pattern = "(?s)it\('rejects bearer-token access to interactive administration'.*\z"
if (-not [regex]::IsMatch($TestText, $Pattern)) {
    throw "Bearer-token test block was not found."
}
$TestText = [regex]::Replace($TestText, $Pattern, $BearerTest)
[System.IO.File]::WriteAllText($TestPath, $TestText, $Utf8NoBom)

# 3. Force PHPUnit to use testing + SQLite even when Docker injects local .env variables.
[xml]$Phpunit = Get-Content $PhpunitPath -Raw

$RequiredEnv = @{
    APP_ENV = "testing"
    APP_KEY = "base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA="
    CACHE_STORE = "array"
    DB_CONNECTION = "sqlite"
    DB_DATABASE = ":memory:"
    QUEUE_CONNECTION = "sync"
    SESSION_DRIVER = "array"
    MAIL_MAILER = "array"
}

$PhpNode = $Phpunit.phpunit.php
foreach ($Name in $RequiredEnv.Keys) {
    $Node = @($PhpNode.env | Where-Object { $_.name -eq $Name }) | Select-Object -First 1

    if ($null -eq $Node) {
        $Node = $Phpunit.CreateElement("env")
        $Node.SetAttribute("name", $Name)
        [void]$PhpNode.AppendChild($Node)
    }

    $Node.SetAttribute("value", $RequiredEnv[$Name])
    $Node.SetAttribute("force", "true")
}

foreach ($Node in @($PhpNode.env)) {
    $Node.SetAttribute("force", "true")
}

$XmlSettings = New-Object System.Xml.XmlWriterSettings
$XmlSettings.Encoding = $Utf8NoBom
$XmlSettings.Indent = $true
$XmlSettings.OmitXmlDeclaration = $false
$Writer = [System.Xml.XmlWriter]::Create($PhpunitPath, $XmlSettings)
try {
    $Phpunit.Save($Writer)
} finally {
    $Writer.Dispose()
}

# 4. Run Phase 14 backend tests with explicit isolated test variables.
$Verify14 = @'
$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

& ".\scripts\security-audit.ps1" -EnvironmentFile ".env"

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")
$testExec = @(
    "exec", "-T",
    "-e", "APP_ENV=testing",
    "-e", "DB_CONNECTION=sqlite",
    "-e", "DB_DATABASE=:memory:",
    "-e", "CACHE_STORE=array",
    "-e", "SESSION_DRIVER=array",
    "-e", "QUEUE_CONNECTION=sync",
    "-e", "MAIL_MAILER=array",
    "backend"
)

& docker compose @compose @testExec php artisan config:clear
if ($LASTEXITCODE -ne 0) { throw "Testing config clear failed." }

& docker compose @compose @testExec php artisan test
if ($LASTEXITCODE -ne 0) { throw "Backend regression suite failed." }

& docker compose @compose exec -T backend php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "Development cache restore failed." }

& docker compose @compose exec -T frontend npm run lint
if ($LASTEXITCODE -ne 0) { throw "Frontend lint failed." }

& docker compose @compose exec -T frontend npm run typecheck
if ($LASTEXITCODE -ne 0) { throw "Frontend typecheck failed." }

& ".\scripts\uat-smoke.ps1"
& ".\scripts\load-smoke.ps1" -Requests 30

Write-Host "Phase 14 verification passed." -ForegroundColor Green
'@
[System.IO.File]::WriteAllText($Verify14Path, $Verify14, $Utf8Bom)

# 5. Add startup retries to Phase 15 readiness.
$Verify15 = @'
$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))

$required = @(
    "VERSION",
    "CHANGELOG.md",
    "docs\PHASE_13_PRODUCTION_DEPLOYMENT.md",
    "docs\PHASE_14_SECURITY_PERFORMANCE_UAT.md",
    "docs\PHASE_15_LAUNCH_AND_HANDOVER.md",
    "docs\OPERATIONS_RUNBOOK.md",
    "docs\DISASTER_RECOVERY_RUNBOOK.md",
    "docs\ADMIN_OPERATIONS_GUIDE.md"
)

foreach ($path in $required) {
    if (-not (Test-Path $path)) {
        throw "Required handover file is missing: $path"
    }
}

$compose = @("-f", "docker-compose.yml", "-f", "docker-compose.dev.yml")

& docker compose @compose restart backend nginx
if ($LASTEXITCODE -ne 0) {
    throw "Backend and Nginx restart failed."
}

$ready = $null
$lastError = $null

for ($attempt = 1; $attempt -le 30; $attempt++) {
    Start-Sleep -Seconds 3

    try {
        & docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler *> $null
        $candidate = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health/ready" -TimeoutSec 20

        if ($candidate.status -eq "passed") {
            $ready = $candidate
            break
        }

        $lastError = "Readiness returned status: $($candidate.status)"
    } catch {
        $lastError = $_.Exception.Message
    }
}

if ($null -eq $ready) {
    & docker compose @compose ps
    & docker compose @compose logs --tail=120 backend nginx
    throw "Final readiness endpoint did not pass. Last error: $lastError"
}

$version = (Get-Content "VERSION" -Raw).Trim()
if ($version -eq "") {
    throw "VERSION is empty."
}

Write-Host "Phase 15 verification passed for release $version." -ForegroundColor Green
'@
[System.IO.File]::WriteAllText($Verify15Path, $Verify15, $Utf8Bom)

Write-Host "Checking syntax..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php -l app/Modules/Identity/Http/Middleware/EnsureAdminTwoFactor.php
if ($LASTEXITCODE -ne 0) { throw "Middleware syntax check failed." }

& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php -l tests/Feature/IdentityAccessTest.php
if ($LASTEXITCODE -ne 0) { throw "Identity test syntax check failed." }

Write-Host "Running isolated IdentityAccessTest..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T `
    -e APP_ENV=testing `
    -e DB_CONNECTION=sqlite `
    -e "DB_DATABASE=:memory:" `
    -e CACHE_STORE=array `
    -e SESSION_DRIVER=array `
    -e QUEUE_CONNECTION=sync `
    -e MAIL_MAILER=array `
    backend php artisan test tests/Feature/IdentityAccessTest.php

if ($LASTEXITCODE -ne 0) {
    throw "IdentityAccessTest still failed."
}

Write-Host "Restarting development backend and Nginx..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan optimize:clear
& docker compose -f docker-compose.yml -f docker-compose.dev.yml restart backend nginx
Start-Sleep -Seconds 15

Write-Host "Current development database counts:" -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan tinker --execute="dump(['tenants'=>\Modules\Tenancy\Infrastructure\Models\Tenant::count(),'users'=>\App\Models\User::count()]);"

Write-Host "Phase 14 test-isolation hotfix completed." -ForegroundColor Green
Write-Host "Next: .\scripts\verify-phase14.ps1" -ForegroundColor Yellow
