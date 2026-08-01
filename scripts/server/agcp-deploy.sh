#!/usr/bin/env bash
set -euo pipefail

ROOT="${AGCP_ROOT:-/home/agcp/agcp-2026-2027}"
BACKEND="$ROOT/apps/backend"
WEB="$ROOT/apps/web"
ENVIRONMENT="${AGCP_ENVIRONMENT:-production}"

cd "$ROOT"

GIT_COMMIT="$(git rev-parse HEAD)"

echo "[1/8] Composer install"
cd "$BACKEND"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "[2/8] Laravel maintenance mode"
php artisan down --retry=30 || true

cleanup() {
  cd "$BACKEND"
  php artisan up || true
}
trap cleanup EXIT

echo "[3/8] Database migrations"
php artisan migrate --force

echo "[4/8] Laravel caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[5/8] Laravel tests"
php artisan test

echo "[6/8] Next.js install/build"
cd "$WEB"
npm ci
npm run build

echo "[7/8] Record release readiness"
cd "$BACKEND"
php artisan tinker --execute="
\$service = app(\App\Modules\Platform\Application\DeploymentReadinessService::class);
\$release = \$service->record('$ENVIRONMENT', '$GIT_COMMIT', null);
dump(['release' => \$release->release_uuid, 'status' => \$release->status]);
if (\$release->status !== 'ready') { throw new RuntimeException('deployment blocked'); }
"

echo "[8/8] Resume application"
php artisan up
trap - EXIT

echo "AGCP deployment readiness: PASS"