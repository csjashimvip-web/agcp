#!/usr/bin/env sh
set -eu

cd /var/www/html

seed_vendor_from_image() {
    if [ ! -f /opt/agcp/vendor/autoload.php ]; then
        echo "AGCP dependency seed is missing from the image." >&2
        return 1
    fi

    echo "Synchronizing Laravel dependencies from the built image..."
    mkdir -p vendor
    find vendor -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a /opt/agcp/vendor/. vendor/
}

EXPECTED_HASH=""
CURRENT_HASH=""
if [ -f /opt/agcp/vendor/.agcp-dependency-hash ]; then
    EXPECTED_HASH=$(cat /opt/agcp/vendor/.agcp-dependency-hash)
fi
if [ -f vendor/.agcp-dependency-hash ]; then
    CURRENT_HASH=$(cat vendor/.agcp-dependency-hash)
fi

if [ -d /opt/agcp/vendor ]; then
    if [ ! -f vendor/autoload.php ] || [ -z "$EXPECTED_HASH" ] || [ "$CURRENT_HASH" != "$EXPECTED_HASH" ]; then
        seed_vendor_from_image
    fi
fi

if [ ! -f vendor/autoload.php ]; then
    echo "Laravel vendor/autoload.php is still missing after dependency synchronization." >&2
    exit 1
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Rebuild package manifests whenever dependencies changed or the shared cache is empty.
if [ ! -f bootstrap/cache/packages.php ] || [ ! -f bootstrap/cache/services.php ]; then
    echo "Generating Laravel package discovery manifests..."
    php artisan package:discover --ansi --no-interaction
fi

exec "$@"
