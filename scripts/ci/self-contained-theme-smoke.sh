#!/usr/bin/env bash
set -euo pipefail

# Preserve the comprehensive self-contained harness while adapting only its
# setup-ready preset cardinality contract. Research is the sixth setup-ready
# preset now that explicit Person identity validation/persistence is complete.
BASE_SCRIPT="scripts/ci/self-contained-theme-smoke-base.sh"
TMP_SCRIPT="$(mktemp)"
trap 'rm -f "$TMP_SCRIPT"' EXIT

python3 - "$BASE_SCRIPT" "$TMP_SCRIPT" <<'PY'
from pathlib import Path
import sys

source = Path(sys.argv[1]).read_text(encoding="utf-8")
old_presets = '["corporate","local-business","publisher","ecommerce","saas-digital-product"]'
new_presets = '["corporate","local-business","publisher","ecommerce","saas-digital-product","research"]'
replacements = {
    old_presets: new_presets,
    'read-only five-preset native setup plan': 'read-only six-preset native setup plan',
    'five presets available': 'six presets available',
}

for old in replacements:
    if old not in source:
        raise SystemExit(f"[self-contained] Preset compatibility guard is stale: {old!r} not found.")

for old, new in replacements.items():
    source = source.replace(old, new)

if new_presets not in source:
    raise SystemExit("[self-contained] Research preset assertion was not applied.")

Path(sys.argv[2]).write_text(source, encoding="utf-8")
PY

exec bash "$TMP_SCRIPT" "$@"
