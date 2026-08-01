#!/usr/bin/env bash
set -euo pipefail

ROOT="${AGCP_ROOT:-/home/agcp/agcp-2026-2027}"
BACKEND="$ROOT/apps/backend"
API_URL="${AGCP_STAGING_API_URL:-https://api-staging.example.com}"
WEB_URL="${AGCP_STAGING_WEB_URL:-https://staging.example.com}"

echo "==> Git"
cd "$ROOT"
git status --short
git rev-parse HEAD

echo "==> Laravel readiness"
cd "$BACKEND"
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan agcp:api-contract-audit

echo "==> Queue/cache/database readiness"
php artisan agcp:reliability-snapshot

echo "==> HTTP readiness"
curl --fail --silent --show-error "$API_URL/api/v1/platform/readiness"
echo
curl --fail --silent --show-error --head "$WEB_URL" >/dev/null

echo "==> Supervisor"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl status || true
else
  echo "supervisorctl not found; verify process supervision manually."
fi

echo
echo "AGCP RC1 STAGING RUNTIME VERIFY: PASS"