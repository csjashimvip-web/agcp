#!/usr/bin/env sh
set -eu

cd /app

EXPECTED_HASH=""
CURRENT_HASH=""
if [ -f /opt/agcp/node_modules/.agcp-dependency-hash ]; then
    EXPECTED_HASH=$(cat /opt/agcp/node_modules/.agcp-dependency-hash)
fi
if [ -f node_modules/.agcp-dependency-hash ]; then
    CURRENT_HASH=$(cat node_modules/.agcp-dependency-hash)
fi

if [ ! -d node_modules/next ] || [ -z "$EXPECTED_HASH" ] || [ "$CURRENT_HASH" != "$EXPECTED_HASH" ]; then
    if [ ! -d /opt/agcp/node_modules/next ]; then
        echo "AGCP frontend dependency seed is missing from the image." >&2
        exit 1
    fi

    echo "Synchronizing Next.js dependencies from the built image..."
    mkdir -p node_modules
    find node_modules -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a /opt/agcp/node_modules/. node_modules/
fi

if [ ! -d node_modules/next ]; then
    echo "Next.js dependencies are still missing after dependency synchronization." >&2
    exit 1
fi

exec "$@"
