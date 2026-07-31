#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$ROOT_DIR"

COMPOSE="docker compose --env-file .env.production -f docker-compose.production.yml"
APP_PORT_VALUE=$(sed -n 's/^APP_PORT=//p' .env.production | tail -n 1)
[ -n "$APP_PORT_VALUE" ] || APP_PORT_VALUE=8080
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p deployment-history

./scripts/server/preflight.sh

echo "[$STAMP] Starting AGCP production deployment."

if $COMPOSE ps --status running backend >/dev/null 2>&1; then
  if $COMPOSE exec -T backend php artisan list --raw | grep -q '^reliability:backup '; then
    echo "Creating encrypted pre-deployment backup..."
    $COMPOSE exec -T backend php artisan reliability:backup
    $COMPOSE exec -T backend php artisan reliability:verify-backup --latest
  fi
fi

git rev-parse HEAD > "deployment-history/$STAMP.commit" 2>/dev/null || true

$COMPOSE build --pull backend queue-critical queue-default scheduler frontend
$COMPOSE up -d mysql redis
$COMPOSE up -d backend
$COMPOSE exec -T backend php artisan migrate --force
$COMPOSE exec -T backend php artisan optimize:clear
$COMPOSE exec -T backend php artisan config:cache
$COMPOSE exec -T backend php artisan event:cache
$COMPOSE exec -T backend php artisan route:cache
$COMPOSE up -d queue-critical queue-default scheduler frontend nginx
$COMPOSE exec -T backend php artisan queue:restart

if $COMPOSE exec -T backend php artisan list --raw | grep -q '^reliability:heartbeat '; then
  $COMPOSE exec -T backend php artisan reliability:heartbeat scheduler
fi

attempt=1
while [ "$attempt" -le 30 ]; do
  if curl -fsS "http://127.0.0.1:${APP_PORT_VALUE}/api/v1/health/ready" >/dev/null 2>&1; then
    echo "[PASSED] AGCP production deployment is ready."
    $COMPOSE ps
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 5
done

$COMPOSE logs --tail=200 backend frontend nginx
echo "[FAILED] Readiness endpoint did not pass after deployment." >&2
exit 1
