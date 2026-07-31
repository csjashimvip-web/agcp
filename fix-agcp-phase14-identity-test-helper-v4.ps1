$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ProjectRoot = "C:\Projects\agcp"
Set-Location $ProjectRoot

$TestPath = Join-Path $ProjectRoot "apps\backend\tests\Feature\IdentityAccessTest.php"
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

if (-not (Test-Path $TestPath)) {
    throw "IdentityAccessTest.php was not found: $TestPath"
}

Copy-Item $TestPath "$TestPath.phase14-v4-backup-$Stamp" -Force
$Text = [System.IO.File]::ReadAllText($TestPath, [System.Text.Encoding]::UTF8)

if ($Text.Contains("'two_factor_confirmed_at' => `$twoFactorConfirmedAt") -and $Text.Contains("return `$user->refresh();")) {
    Write-Host "Identity test helper is already fixed." -ForegroundColor Green
} else {
    $NewHelper = @'
function identityUser(Tenant $tenant, array $attributes = []): User
{
    // These security timestamps are intentionally not mass assignable.
    $emailVerifiedAt = array_key_exists('email_verified_at', $attributes)
        ? $attributes['email_verified_at']
        : now();

    $twoFactorConfirmedAt = array_key_exists('two_factor_confirmed_at', $attributes)
        ? $attributes['two_factor_confirmed_at']
        : null;

    unset($attributes['email_verified_at'], $attributes['two_factor_confirmed_at']);

    $user = User::query()->create(array_merge([
        'name' => 'Identity User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'Correct-Horse-Battery-Staple!42',
        'status' => 'active',
        'locale' => 'en',
        'timezone' => 'UTC',
    ], $attributes));

    $user->forceFill([
        'email_verified_at' => $emailVerifiedAt,
        'two_factor_confirmed_at' => $twoFactorConfirmedAt,
    ])->save();

    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $user->refresh();
}
'@

    # Single-quoted pattern is required so PowerShell does not expand PHP variables.
    $Pattern = '(?s)function identityUser\(Tenant \$tenant, array \$attributes = \[\]\): User\s*\{.*?\r?\n\}\r?\n(?=\r?\nit\(''registers a customer inside the resolved tenant'')'

    if (-not [regex]::IsMatch($Text, $Pattern)) {
        throw "The identityUser helper block was not found. No file was changed."
    }

    $Text = [regex]::Replace($Text, $Pattern, $NewHelper + "`r`n", 1)
    [System.IO.File]::WriteAllText($TestPath, $Text, $Utf8NoBom)
    Write-Host "Identity test helper patched." -ForegroundColor Green
}

Write-Host "Checking PHP syntax..." -ForegroundColor Cyan
& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php -l tests/Feature/IdentityAccessTest.php
if ($LASTEXITCODE -ne 0) {
    throw "IdentityAccessTest.php syntax check failed."
}

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

& docker compose -f docker-compose.yml -f docker-compose.dev.yml exec -T backend php artisan optimize:clear
& docker compose -f docker-compose.yml -f docker-compose.dev.yml restart backend nginx

Write-Host "Phase 14 identity fix V4 completed." -ForegroundColor Green
Write-Host "Next: .\scripts\verify-phase14.ps1" -ForegroundColor Yellow
