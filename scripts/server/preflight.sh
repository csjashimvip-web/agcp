#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
cd "$ROOT_DIR"

fail() {
  echo "[FAILED] $1" >&2
  exit 1
}

command -v docker >/dev/null 2>&1 || fail "Docker is not installed."
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is not available."
[ -f .env.production ] || fail ".env.production is missing."
[ -f docker-compose.production.yml ] || fail "docker-compose.production.yml is missing."

if grep -Eq '(^|=)CHANGE_ME_' .env.production; then
  fail ".env.production still contains CHANGE_ME placeholders."
fi

if ! grep -Eq '^APP_ENV=production$' .env.production; then
  fail "APP_ENV must be production."
fi

if ! grep -Eq '^APP_DEBUG=false$' .env.production; then
  fail "APP_DEBUG must be false."
fi

if ! grep -Eq '^APP_URL=https://' .env.production; then
  fail "APP_URL must use HTTPS."
fi

docker compose --env-file .env.production -f docker-compose.production.yml config --quiet

echo "[PASSED] Production preflight completed."
