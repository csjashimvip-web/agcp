#!/usr/bin/env sh
set -eu
ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT_DIR"

echo "Validating PHP syntax..."
find apps/backend scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

echo "Validating shell syntax..."
find scripts apps -type f -name '*.sh' -print0 | xargs -0 -n1 sh -n

echo "Validating JSON..."
python3 - <<'PYCODE'
import json
from pathlib import Path
for path in Path('.').rglob('*.json'):
    if any(part in {'node_modules', 'vendor', '.git'} for part in path.parts):
        continue
    json.loads(path.read_text(encoding='utf-8'))
print('JSON OK')
PYCODE

echo "Validating YAML..."
python3 - <<'PYCODE'
from pathlib import Path
try:
    import yaml
except ImportError:
    print('PyYAML unavailable; YAML parse skipped.')
else:
    for pattern in ('*.yml', '*.yaml'):
        for path in Path('.').rglob(pattern):
            if '.git' not in path.parts:
                yaml.safe_load(path.read_text(encoding='utf-8'))
    print('YAML OK')
PYCODE

echo "Static verification passed."
