#!/usr/bin/env bash
set -euo pipefail

OUTPUT_DIR="${1:-dist/seo-geo-theme}"
THEME_SOURCE="packages/seo-geo-theme"
CORE_SOURCE="packages/seo-geo-core/src"
PRESET_SOURCE="presets"
BUNDLED_CORE_DIR="${OUTPUT_DIR}/inc/seo-geo-core"
CORPORATE_V5_RUNTIME="${OUTPUT_DIR}/assets/css/presets/corporate-v5-runtime.css"
CORPORATE_V5_MARKER="${OUTPUT_DIR}/assets/css/presets/corporate-v5-bundled.marker"
CORPORATE_V5_SOURCES=(
  "${THEME_SOURCE}/style.css"
  "${THEME_SOURCE}/assets/css/presets/corporate-v2.css"
  "${THEME_SOURCE}/assets/css/presets/corporate-v5-runtime.css"
)

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

for source in "${CORPORATE_V5_SOURCES[@]}"; do
  if [[ ! -f "$source" ]]; then
    printf 'Corporate v5 CSS source missing: %s\n' "$source" >&2
    exit 1
  fi
done

CORPORATE_V5_TMP="${CORPORATE_V5_RUNTIME}.tmp"
{
  printf '/* Corporate v5 client bundle: foundation + visual system + strategic canvas. */\n'
  for source in "${CORPORATE_V5_SOURCES[@]}"; do
    cat "$source"
    printf '\n'
  done
} >"$CORPORATE_V5_TMP"
mv "$CORPORATE_V5_TMP" "$CORPORATE_V5_RUNTIME"
printf 'corporate-v5-css-bundle-v1\n' >"$CORPORATE_V5_MARKER"

if [[ ! -s "$CORPORATE_V5_RUNTIME" || ! -s "$CORPORATE_V5_MARKER" ]]; then
  printf 'Corporate v5 client CSS bundle was not generated.\n' >&2
  exit 1
fi

printf 'Self-contained theme assembled at %s\n' "$OUTPUT_DIR"