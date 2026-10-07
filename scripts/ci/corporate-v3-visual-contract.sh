#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v3-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
FUNCTIONS="packages/seo-geo-theme/functions.php"

fail() {
  printf '[corporate-v3] FAIL: %s\n' "$1" >&2
  exit 1
}

require_text() {
  local file="$1"
  local text="$2"
  local label="$3"
  grep -Fq -- "$text" "$file" || fail "$label"
}

[[ -f "$CSS" ]] || fail "master presentation stylesheet missing: $CSS"
[[ -f "$RUNTIME" ]] || fail "master runtime stylesheet missing: $RUNTIME"
[[ -f "$FOUNDATION" ]] || fail "Theme foundation stylesheet missing: $FOUNDATION"
[[ -f "$FUNCTIONS" ]] || fail "theme bootstrap missing: $FUNCTIONS"

printf '[corporate-v3] Checking master presentation ownership.\n'
require_text "$CSS" 'Corporate v3 master visual layer.' 'Corporate v3 ownership marker missing'
require_text "$FUNCTIONS" 'seo_geo_theme_corporate_master_home_layer' 'Corporate v3 Home detector missing'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v2.css" 'Corporate master presentation asset is not loaded'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v3-runtime.css" 'Corporate master runtime asset is not loaded'
require_text "$FUNCTIONS" "array( 'seo-geo-theme' )" 'Corporate v3 must load directly from the Theme foundation, not the legacy Corporate layer'
require_text "$FUNCTIONS" 'return;' 'Corporate v3 asset branch must exit before legacy preset enqueue'

printf '[corporate-v3] Checking approved responsive acceptance widths.\n'
require_text "$CSS" '@media (max-width: 1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width: 820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width: 600px)' '390-class responsive contract missing'
require_text "$RUNTIME" '@media (max-width: 1100px)' '1024-class navigation runtime missing'
require_text "$CSS" 'grid-template-columns: 1fr !important;' 'single-column mobile recomposition missing'
require_text "$CSS" 'word-break: normal;' 'long-title word-break guard missing'
require_text "$CSS" 'hyphens: none;' 'long-title hyphenation guard missing'

printf '[corporate-v3] Checking non-generic Home composition contracts.\n'
require_text "$CSS" 'grid-template-columns: repeat(12, minmax(0, 1fr));' '12-column Bento grid missing'
require_text "$CSS" '.seo-geo-corporate-card:nth-child(1)' 'featured capability composition missing'
require_text "$CSS" '.seo-geo-corporate-native-process' 'full-bleed process composition missing'
require_text "$CSS" '.seo-geo-corporate-native-insights .wp-block-post-template' 'editorial Insights composition missing'
require_text "$CSS" '.seo-geo-final-cta' 'final CTA composition missing'

printf '[corporate-v3] Checking accessibility/performance boundaries.\n'
require_text "$CSS" '@media (prefers-reduced-motion: reduce)' 'reduced-motion contract missing'
require_text "$CSS" 'color: #fff;' 'dark-surface high-contrast text contract missing'
if grep -Eqi '@import|https?://|url\(' "$CSS" "$RUNTIME"; then
  fail 'Corporate v3 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
visual_gzip="$(gzip -9c "$CSS" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + visual_gzip + runtime_gzip ))

printf '[corporate-v3] CSS gzip proxy: foundation=%s visual=%s runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$visual_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7900 )); then
  fail "Corporate v3 Home CSS gzip proxy ${total_gzip} exceeds 7900-byte safety ceiling below the 8192-byte network budget"
fi

printf '[corporate-v3] PASS: master Home visual and lean-asset contracts present.\n'
