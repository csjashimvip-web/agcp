$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = "C:\Projects\agcp"
Set-Location $ProjectRoot

$MiddlewarePath = Join-Path $ProjectRoot "apps\backend\app\Modules\Identity\Http\Middleware\EnsureAdminTwoFactor.php"
$TestPath = Join-Path $ProjectRoot "apps\backend\tests\Feature\IdentityAccessTest.php"
$Phase15Path = Join-Path $ProjectRoot "scripts\verify-phase15.ps1"
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$Utf8Bom = New-Object System.Text.UTF8Encoding($true)

foreach ($Path in @($MiddlewarePath, $TestPath, $Phase15Path)) {
    if (-not (Test-Path $Path)) {
        throw "Required file was not found: $Path"
    }
    Copy-Item $Path "$Path.phase14-hotfix-backup-$Stamp" -Force
}

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
         * A real Authorization: Bearer header is programmatic token access.
         * Sanctum SPA cookie authentication does not send a bearer header.
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

[System.IO.File]::WriteAllText($MiddlewarePath, $Middleware, $Utf8Bom)

$TestText = [System.IO.File]::ReadAllText($TestPath, [System.Text.Encoding]::UTF8)
$TestText = $TestText.Replace("use Laravel\Sanctum\Sanctum;`r`n", "")
$TestText = $TestText.Replace("use Laravel\Sanctum\Sanctum;`n", "")

$Replacement = @'
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
    throw "The bearer-token test block was not found in IdentityAccessTest.php."
}

$TestText = [regex]::Replace($TestText, $Pattern, $Replacement)
[System.IO.File]::WriteAllText($TestPath, $TestText, $Utf8Bom)

$Phase15 = @'
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

Start-Sleep -Seconds 12

& docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler
if ($LASTEXITCODE -ne 0) {
    Write-Host "Heartbeat refresh did not run; readiness retries will continue." -ForegroundColor Yellow
}

$ready = $null
$lastError = $null

for ($attempt = 1; $attempt -le 30; $attempt++) {
    try {
        $candidate = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health/ready" -TimeoutSec 20
        if ($candidate.status -eq "passed") {
            $ready = $candidate
            break
        }
        $lastError = "Readiness returned status: $($candidate.status)"
    } catch {
        $lastError = $_.Exception.Message
    }

    Start-Sleep -Seconds 3
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

[System.IO.File]::WriteAllText($Phase15Path, $Phase15, $Utf8Bom)

Write-Host "Checking PHP syntax..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php -l app/Modules/Identity/Http/Middleware/EnsureAdminTwoFactor.php
if ($LASTEXITCODE -ne 0) {
    throw "Middleware PHP syntax check failed."
}

Write-Host "Restarting backend and Nginx..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml restart backend nginx
if ($LASTEXITCODE -ne 0) {
    throw "Container restart failed."
}

Start-Sleep -Seconds 15

Write-Host "Running the Identity access test file..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan test tests/Feature/IdentityAccessTest.php
if ($LASTEXITCODE -ne 0) {
    throw "IdentityAccessTest.php still has a failure."
}

Write-Host "Hotfix completed. Run Phase 14 verification next." -ForegroundColor Green
Write-Host ".\scripts\verify-phase14.ps1" -ForegroundColor Yellow
