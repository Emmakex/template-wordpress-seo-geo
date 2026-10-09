#!/usr/bin/env bash
set -euo pipefail

# Preserve the comprehensive self-contained harness while adapting only its
# setup-ready preset cardinality contract. Research is the sixth setup-ready
# preset now that explicit Person identity validation/persistence is complete.
BASE_SCRIPT="scripts/ci/self-contained-theme-smoke-base.sh"
TMP_SCRIPT="$(mktemp)"
trap 'rm -f "$TMP_SCRIPT"' EXIT

OLD_PRESETS='["corporate","local-business","publisher","ecommerce","saas-digital-product"]'
NEW_PRESETS='["corporate","local-business","publisher","ecommerce","saas-digital-product","research"]'

if ! grep -Fq "$OLD_PRESETS" "$BASE_SCRIPT"; then
  echo "[self-contained] Preset compatibility guard is stale: legacy preset assertion not found." >&2
  exit 1
fi

if ! grep -Fq 'read-only five-preset native setup plan' "$BASE_SCRIPT"; then
  echo "[self-contained] Preset compatibility guard is stale: legacy diagnostic not found." >&2
  exit 1
fi

if ! grep -Fq 'five presets available' "$BASE_SCRIPT"; then
  echo "[self-contained] Preset compatibility guard is stale: legacy success message not found." >&2
  exit 1
fi

sed \
  -e "s|$OLD_PRESETS|$NEW_PRESETS|g" \
  -e 's/read-only five-preset native setup plan/read-only six-preset native setup plan/g' \
  -e 's/five presets available/six presets available/g' \
  "$BASE_SCRIPT" > "$TMP_SCRIPT"

if ! grep -Fq "$NEW_PRESETS" "$TMP_SCRIPT"; then
  echo "[self-contained] Research preset assertion was not applied." >&2
  exit 1
fi

exec bash "$TMP_SCRIPT" "$@"
