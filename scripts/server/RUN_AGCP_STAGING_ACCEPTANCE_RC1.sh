#!/usr/bin/env bash
set -euo pipefail

ROOT="${AGCP_ROOT:-/home/agcp/agcp-2026-2027}"
BACKEND="$ROOT/apps/backend"
WEB="$ROOT/apps/web"
ENVIRONMENT="${AGCP_ACCEPTANCE_ENVIRONMENT:-staging}"

cd "$ROOT"
COMMIT="$(git rev-parse HEAD)"

echo "AGCP RC1 STAGING ACCEPTANCE"
echo "Commit: $COMMIT"
echo "Environment: $ENVIRONMENT"

cd "$BACKEND"

php artisan optimize:clear
php artisan migrate:status
php artisan test
php artisan agcp:api-contract-audit
php artisan agcp:security-audit \
  --environment="$ENVIRONMENT" \
  --git-commit="$COMMIT"

cd "$WEB"

npx tsc --noEmit
npm run build

cd "$BACKEND"

php artisan agcp:performance-baseline \
  --environment="$ENVIRONMENT" \
  --samples=25

php artisan agcp:staging-acceptance \
  --environment="$ENVIRONMENT" \
  --git-commit="$COMMIT"

echo
echo "AGCP RC1 STAGING ACCEPTANCE: PASS"
echo "Do not create a production cutover run until backup/restore verification and all manual staging checks are complete."