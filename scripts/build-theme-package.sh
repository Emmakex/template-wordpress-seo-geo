#!/usr/bin/env bash
set -euo pipefail

OUTPUT_DIR="${1:-dist/seo-geo-theme}"
THEME_SOURCE="packages/seo-geo-theme"
CORE_SOURCE="packages/seo-geo-core/src"
PRESET_SOURCE="presets"
BUNDLED_CORE_DIR="${OUTPUT_DIR}/inc/seo-geo-core"

if [[ ! -d "$THEME_SOURCE" ]]; then
  printf 'Theme source not found: %s\n' "$THEME_SOURCE" >&2
  exit 1
fi

if [[ ! -d "$CORE_SOURCE" ]]; then
  printf 'Core source not found: %s\n' "$CORE_SOURCE" >&2
  exit 1
fi

rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"
cp -R "${THEME_SOURCE}/." "$OUTPUT_DIR/"

if [[ -d "$PRESET_SOURCE" ]]; then
  rm -rf "${OUTPUT_DIR}/presets"
  cp -R "$PRESET_SOURCE" "${OUTPUT_DIR}/presets"
fi

mkdir -p "$BUNDLED_CORE_DIR"
rm -rf "${BUNDLED_CORE_DIR}/src"
cp -R "$CORE_SOURCE" "${BUNDLED_CORE_DIR}/src"

if [[ ! -f "${BUNDLED_CORE_DIR}/src/Runtime.php" ]]; then
  printf 'Bundled runtime missing after build.\n' >&2
  exit 1
fi

if [[ ! -f "${OUTPUT_DIR}/functions.php" || ! -f "${OUTPUT_DIR}/theme.json" || ! -f "${OUTPUT_DIR}/inc/presets.php" ]]; then
  printf 'Built theme is incomplete.\n' >&2
  exit 1
fi

printf 'Self-contained theme assembled at %s\n' "$OUTPUT_DIR"
