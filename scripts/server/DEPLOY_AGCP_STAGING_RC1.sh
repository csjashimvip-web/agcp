#!/usr/bin/env bash
set -euo pipefail

ROOT="${AGCP_ROOT:-/home/agcp/agcp-2026-2027}"
BACKEND="$ROOT/apps/backend"
WEB="$ROOT/apps/web"
EXPECTED_COMMIT="${AGCP_STAGING_COMMIT:-}"
API_URL="${AGCP_STAGING_API_URL:-https://api-staging.example.com}"
WEB_URL="${AGCP_STAGING_WEB_URL:-https://staging.example.com}"
AUDIT_DIR="$ROOT/.agcp-staging-audit"

if [[ -z "$EXPECTED_COMMIT" ]]; then
  echo "AGCP_STAGING_COMMIT is required."
  exit 1
fi

mkdir -p "$AUDIT_DIR"

echo "==> Validating toolchain"
php -v
composer --version
node --version
npm --version
git --version

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"

if [[ "$PHP_VERSION" != "8.4" ]]; then
  echo "Expected PHP 8.4 CLI, got $PHP_VERSION"
  exit 1
fi

if [[ "$NODE_MAJOR" != "22" ]]; then
  echo "Expected Node.js 22, got major $NODE_MAJOR"
  exit 1
fi

echo "==> Fetching exact RC1 commit"
cd "$ROOT"
git fetch --all --prune

if ! git cat-file -e "${EXPECTED_COMMIT}^{commit}" 2>/dev/null; then
  echo "Commit not found after fetch: $EXPECTED_COMMIT"
  exit 1
fi

git reset --hard "$EXPECTED_COMMIT"
git clean -fd

ACTUAL_COMMIT="$(git rev-parse HEAD)"
if [[ "$ACTUAL_COMMIT" != "$EXPECTED_COMMIT" ]]; then
  echo "Commit mismatch: expected $EXPECTED_COMMIT, got $ACTUAL_COMMIT"
  exit 1
fi

echo "==> Verifying staging environment files"
if [[ ! -f "$BACKEND/.env" ]]; then
  echo "Missing $BACKEND/.env"
  exit 1
fi

if [[ ! -f "$WEB/.env.production" && ! -f "$WEB/.env.local" ]]; then
  echo "Missing Next.js staging environment file."
  exit 1
fi

echo "==> Composer install and audit"
cd "$BACKEND"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer audit --locked --format=json > "$AUDIT_DIR/composer-audit.json"

echo "==> Laravel maintenance and migration"
php artisan down --retry=30 || true

cleanup() {
  cd "$BACKEND"
  php artisan up || true
}
trap cleanup EXIT

php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Laravel regression"
php artisan test
php artisan agcp:api-contract-audit

echo "==> Next.js clean install/build"
cd "$WEB"
npm ci
npm audit --json > "$AUDIT_DIR/npm-audit.json" || true

node - "$AUDIT_DIR/npm-audit.json" <<'NODE'
const fs = require("fs");
const path = process.argv[2];
const audit = JSON.parse(fs.readFileSync(path, "utf8"));
const v = audit?.metadata?.vulnerabilities ?? {};
const critical = Number(v.critical ?? 0);
const high = Number(v.high ?? 0);

console.log(JSON.stringify({
  critical,
  high,
  moderate: Number(v.moderate ?? 0),
  low: Number(v.low ?? 0),
  total: Number(v.total ?? 0)
}, null, 2));

if (critical + high > 0) {
  process.exit(2);
}
NODE

npm run build

echo "==> Recording staging dependency audit"
cd "$BACKEND"

NPM_COUNTS="$(
node - "$AUDIT_DIR/npm-audit.json" <<'NODE'
const fs = require("fs");
const audit = JSON.parse(fs.readFileSync(process.argv[2], "utf8"));
const v = audit?.metadata?.vulnerabilities ?? {};
console.log([
  Number(v.critical ?? 0),
  Number(v.high ?? 0),
  Number(v.moderate ?? 0),
  Number(v.low ?? 0)
].join(" "));
NODE
)"

read -r NPM_CRITICAL NPM_HIGH NPM_MODERATE NPM_LOW <<< "$NPM_COUNTS"
NPM_SHA="$(sha256sum "$AUDIT_DIR/npm-audit.json" | awk '{print $1}')"

php artisan agcp:dependency-audit-record \
  npm \
  "$NPM_CRITICAL" \
  "$NPM_HIGH" \
  "$NPM_MODERATE" \
  "$NPM_LOW" \
  --environment=staging \
  --path="$AUDIT_DIR/npm-audit.json" \
  --sha256="$NPM_SHA"

COMPOSER_SHA="$(sha256sum "$AUDIT_DIR/composer-audit.json" | awk '{print $1}')"

php artisan agcp:dependency-audit-record \
  composer \
  0 0 0 0 \
  --environment=staging \
  --path="$AUDIT_DIR/composer-audit.json" \
  --sha256="$COMPOSER_SHA"

echo "==> Restarting Laravel queues"
php artisan queue:restart || true

echo "==> Leaving maintenance mode"
php artisan up
trap - EXIT

echo "==> HTTP readiness checks"
curl --fail --silent --show-error "$API_URL/api/v1/platform/readiness"
echo
curl --fail --silent --show-error --head "$WEB_URL" >/dev/null

echo
echo "AGCP RC1 STAGING DEPLOYMENT: COMPLETE"
echo "Commit: $ACTUAL_COMMIT"
echo "Next: run scripts/server/VERIFY_AGCP_STAGING_RC1.sh"