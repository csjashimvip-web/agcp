#!/usr/bin/env sh
set -eu
ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT_DIR"
command -v docker >/dev/null 2>&1 || { echo "Docker is required." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "Docker Compose v2 is required." >&2; exit 1; }

random_b64() { head -c "$1" /dev/urandom | base64 | tr -d '\r\n'; }
random_url() { head -c "$1" /dev/urandom | base64 | tr '+/' '-_' | tr -d '=\r\n'; }
env_has() { grep -q "^$1=" .env 2>/dev/null; }
env_set() {
  key=$1 value=$2
  if env_has "$key"; then
    tmp=$(mktemp)
    awk -v k="$key" -v v="$value" 'BEGIN{FS="="} $1==k{print k"="v; next} {print}' .env > "$tmp"
    mv "$tmp" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

ADMIN_GENERATED=""
if [ ! -f .env ]; then
  ADMIN_GENERATED=$(random_url 24)
  sed \
    -e "s|CHANGE_ME_APP_KEY|base64:$(random_b64 32)|g" \
    -e "s|CHANGE_ME_DB_PASSWORD|$(random_url 32)|g" \
    -e "s|CHANGE_ME_MYSQL_ROOT_PASSWORD|$(random_url 40)|g" \
    -e "s|CHANGE_ME_REDIS_PASSWORD|$(random_url 40)|g" \
    -e "s|CHANGE_ME_PASSKEYS_SECRET|$(random_url 48)|g" \
    -e "s|CHANGE_ME_INITIAL_ADMIN_PASSWORD|$ADMIN_GENERATED|g" \
    .env.example > .env
  chmod 600 .env 2>/dev/null || true
  echo "Generated secure local .env file."
else
  echo "Using and upgrading the existing .env file."
  env_set APP_VERSION 4.0.0-phase4
  env_has SANCTUM_STATEFUL_DOMAINS || env_set SANCTUM_STATEFUL_DOMAINS 'localhost:8080,localhost,127.0.0.1:8080,127.0.0.1'
  env_has FORTIFY_PREFIX || env_set FORTIFY_PREFIX 'api/v1/auth'
  env_has PASSKEYS_ALLOWED_ORIGINS || env_set PASSKEYS_ALLOWED_ORIGINS 'http://localhost:8080'
  passkey_secret=$(grep '^PASSKEYS_USER_HANDLE_SECRET=' .env 2>/dev/null | tail -1 | cut -d= -f2- || true)
  case "$passkey_secret" in ''|CHANGE_ME_*) env_set PASSKEYS_USER_HANDLE_SECRET "$(random_url 48)" ;; esac
  env_has SESSION_ENCRYPT || env_set SESSION_ENCRYPT true
  env_has INITIAL_ADMIN_NAME || env_set INITIAL_ADMIN_NAME '"AGCP Administrator"'
  env_has INITIAL_ADMIN_EMAIL || env_set INITIAL_ADMIN_EMAIL 'admin@localhost.test'
  admin_password=$(grep '^INITIAL_ADMIN_PASSWORD=' .env 2>/dev/null | tail -1 | cut -d= -f2- || true)
  case "$admin_password" in
    ''|CHANGE_ME_*) ADMIN_GENERATED=$(random_url 24); env_set INITIAL_ADMIN_PASSWORD "$ADMIN_GENERATED" ;;
  esac
fi

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.dev.yml"
$COMPOSE config --quiet
$COMPOSE up -d --build --wait --wait-timeout 900
$COMPOSE exec -T backend php artisan migrate --seed --force
$COMPOSE exec -T backend php artisan route:list --path=api/v1/auth --except-vendor >/dev/null
$COMPOSE ps

echo "AGCP Phase 3 is available at http://localhost:8080"
if [ -n "$ADMIN_GENERATED" ]; then
  echo "Initial admin email: $(grep '^INITIAL_ADMIN_EMAIL=' .env | tail -1 | cut -d= -f2-)"
  echo "Initial admin password: $ADMIN_GENERATED"
  echo "Enable two-factor authentication before opening /admin."
fi
