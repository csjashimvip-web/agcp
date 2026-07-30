$ErrorActionPreference = 'Stop'

$root = (Get-Location).Path
$nginxPath = Join-Path $root 'infrastructure\nginx\default.conf'
$devComposePath = Join-Path $root 'docker-compose.dev.yml'

if (-not (Test-Path $nginxPath) -or -not (Test-Path $devComposePath)) {
    throw 'Run this script from the AGCP project root, for example: C:\Projects\agcp'
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
Copy-Item $nginxPath "$nginxPath.backup-$stamp" -Force
Copy-Item $devComposePath "$devComposePath.backup-$stamp" -Force

$nginx = @'
limit_req_zone $binary_remote_addr zone=agcp_api:10m rate=30r/s;

map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

upstream agcp_frontend { server frontend:3000; keepalive 32; }
upstream agcp_backend { server backend:9000; keepalive 16; }

server {
    listen 80 default_server;
    server_name _;
    server_tokens off;
    client_max_body_size 10m;

    location = /nginx-health {
        access_log off;
        default_type text/plain;
        return 200 "ok\n";
    }

    location ^~ /api/ {
        limit_req zone=agcp_api burst=60 nodelay;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_param HTTP_PROXY "";
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $http_host;
        fastcgi_pass agcp_backend;
        fastcgi_read_timeout 120s;
        fastcgi_hide_header X-Powered-By;
    }

    location = /sanctum/csrf-cookie {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $http_host;
        fastcgi_pass agcp_backend;
    }

    location ^~ /passkeys/ {
        limit_req zone=agcp_api burst=20 nodelay;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $http_host;
        fastcgi_pass agcp_backend;
    }

    location ^~ /user/passkeys {
        limit_req zone=agcp_api burst=20 nodelay;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
        fastcgi_param HTTP_X_FORWARDED_HOST $http_host;
        fastcgi_pass agcp_backend;
    }

    location = /up {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_pass agcp_backend;
        access_log off;
    }

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Request-ID $request_id;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
        proxy_pass http://agcp_frontend;
    }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
}
'@

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($nginxPath, $nginx, $utf8NoBom)

$dev = [System.IO.File]::ReadAllText($devComposePath)
$old = 'command: ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]'
$new = 'command: ["npm", "run", "dev", "--", "--hostname", "0.0.0.0", "--webpack"]'
if ($dev.Contains($old)) {
    $dev = $dev.Replace($old, $new)
    [System.IO.File]::WriteAllText($devComposePath, $dev, $utf8NoBom)
}

$compose = @('-f', 'docker-compose.yml', '-f', 'docker-compose.dev.yml')

Write-Host 'Clearing stale Next.js build cache...' -ForegroundColor Cyan
try {
    & docker compose @compose exec frontend sh -lc 'rm -rf /app/.next/*'
} catch {
    Write-Warning 'Frontend cache could not be cleared through the existing container; recreation will continue.'
}

Write-Host 'Rebuilding and recreating frontend and nginx...' -ForegroundColor Cyan
& docker compose @compose up -d --build --force-recreate frontend nginx
if ($LASTEXITCODE -ne 0) { throw 'Docker rebuild failed.' }

Write-Host ''
Write-Host 'Frontend hydration hotfix applied.' -ForegroundColor Green
Write-Host 'Open http://localhost:8080/login in a new Incognito window after 20-30 seconds.'
Write-Host 'Do not use 127.0.0.1 for this test.'
Write-Host ''
& docker compose @compose ps frontend nginx
