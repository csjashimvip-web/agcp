#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <git-commit-or-tag>" >&2
  exit 1
fi

TARGET=$1
ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$ROOT_DIR"

COMPOSE="docker compose --env-file .env.production -f docker-compose.production.yml"

if $COMPOSE ps --status running backend >/dev/null 2>&1; then
  if $COMPOSE exec -T backend php artisan list --raw | grep -q '^reliability:backup '; then
    $COMPOSE exec -T backend php artisan reliability:backup
    $COMPOSE exec -T backend php artisan reliability:verify-backup --latest
  fi
fi

git fetch --all --tags
git checkout "$TARGET"

$COMPOSE build backend queue-critical queue-default scheduler frontend
$COMPOSE up -d
$COMPOSE exec -T backend php artisan migrate --force
$COMPOSE exec -T backend php artisan optimize:clear
$COMPOSE exec -T backend php artisan queue:restart

echo "Rollback deployment completed at $TARGET."
echo "Database migrations are forward-only; use the disaster recovery runbook for a database restore."
