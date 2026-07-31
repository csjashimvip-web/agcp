$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
Set-Location (Resolve-Path (Join-Path $PSScriptRoot ".."))
Write-Host "Upgrading AGCP Phase 11 to Phase 12 Reliability and Production Readiness..." -ForegroundColor Cyan

if (Test-Path ".env") {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item ".env" ".env.phase11-backup-$stamp" -Force
    $envContent = Get-Content ".env" -Raw
    if ($envContent -match "(?m)^APP_VERSION=") { $envContent = $envContent -replace "(?m)^APP_VERSION=.*$", "APP_VERSION=12.0.0-phase12" } else { $envContent += "`nAPP_VERSION=12.0.0-phase12`n" }
    $defaults = [ordered]@{
        BACKUP_ENABLED="true"
        BACKUP_DISK="local"
        BACKUP_DIRECTORY="backups/database"
        BACKUP_RETENTION_DAYS="14"
        BACKUP_DAILY_TIME="01:30"
        BACKUP_MAX_AGE_HOURS="36"
        BACKUP_COMMAND_TIMEOUT_SECONDS="1800"
        BACKUP_ENCRYPTION_CHUNK_BYTES="1048576"
        MYSQLDUMP_BINARY="mysqldump"
        RUNTIME_HEARTBEAT_TTL_MINUTES="3"
    }
    foreach ($entry in $defaults.GetEnumerator()) {
        if ($envContent -notmatch "(?m)^$($entry.Key)=") { $envContent += "$($entry.Key)=$($entry.Value)`n" }
    }
    if ($envContent -notmatch "(?m)^BACKUP_ENCRYPTION_KEY=" -or $envContent -match "(?m)^BACKUP_ENCRYPTION_KEY=(CHANGE_ME.*)?$") {
        $bytes = New-Object byte[] 32
        $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        $rng.GetBytes($bytes)
        $rng.Dispose()
        $key = [Convert]::ToBase64String($bytes)
        if ($envContent -match "(?m)^BACKUP_ENCRYPTION_KEY=") { $envContent = $envContent -replace "(?m)^BACKUP_ENCRYPTION_KEY=.*$", "BACKUP_ENCRYPTION_KEY=$key" } else { $envContent += "BACKUP_ENCRYPTION_KEY=$key`n" }
        Write-Host "Generated a private backup encryption key. Preserve .env and keep an offline recovery copy." -ForegroundColor Yellow
    }
    [System.IO.File]::WriteAllText((Resolve-Path ".env"), $envContent, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Environment backup created and Phase 12 settings added." -ForegroundColor Green
}

$compose=@("-f","docker-compose.yml","-f","docker-compose.dev.yml")
& docker compose @compose down --remove-orphans; if($LASTEXITCODE-ne 0){throw "Unable to stop current stack."}
& docker compose @compose build backend queue-critical queue-default scheduler frontend; if($LASTEXITCODE-ne 0){throw "Phase 12 images failed to build."}
& docker compose @compose up -d; if($LASTEXITCODE-ne 0){throw "Phase 12 containers failed to start."}
& docker compose @compose exec -T backend php artisan migrate --force; if($LASTEXITCODE-ne 0){throw "Phase 12 migrations failed."}
& docker compose @compose exec -T backend php artisan db:seed --class=IdentitySeeder --force; if($LASTEXITCODE-ne 0){throw "Phase 12 reliability permissions failed to seed."}
& docker compose @compose exec -T backend php artisan optimize:clear; if($LASTEXITCODE-ne 0){throw "Laravel cache reset failed."}
& docker compose @compose exec -T backend php artisan queue:restart; if($LASTEXITCODE-ne 0){throw "Queue restart failed."}
& docker compose @compose exec -T backend php artisan reliability:heartbeat scheduler; if($LASTEXITCODE-ne 0){throw "Initial scheduler heartbeat failed."}
& docker compose @compose exec -T backend php artisan reliability:backup; if($LASTEXITCODE-ne 0){throw "Initial encrypted database backup failed."}
& docker compose @compose exec -T backend php artisan reliability:verify-backup --latest; if($LASTEXITCODE-ne 0){throw "Initial backup integrity drill failed."}
& docker compose @compose exec -T backend php artisan reliability:check --persist; if($LASTEXITCODE-ne 0){throw "Phase 12 readiness check failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/health; if($LASTEXITCODE-ne 0){throw "Health route verification failed."}
& docker compose @compose exec -T backend php artisan route:list --path=api/v1/admin/reliability; if($LASTEXITCODE-ne 0){throw "Reliability administration routes failed."}
& docker compose @compose ps
Write-Host "AGCP Phase 12 upgrade completed." -ForegroundColor Green
Write-Host "Reliability administration: http://localhost:8080/admin/reliability"
Write-Host "Liveness: http://localhost:8080/api/v1/health/live"
Write-Host "Readiness: http://localhost:8080/api/v1/health/ready"
