#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v3-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
FUNCTIONS="packages/seo-geo-theme/functions.php"

fail() {
  printf '[corporate-v4] FAIL: %s\n' "$1" >&2
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

printf '[corporate-v4] Checking master presentation ownership.\n'
require_text "$CSS" 'Corporate v4 WOW master visual layer.' 'Corporate v4 ownership marker missing'
require_text "$FUNCTIONS" 'seo_geo_theme_corporate_master_home_layer' 'Corporate master Home detector missing'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v2.css" 'Corporate master presentation asset is not loaded'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v3-runtime.css" 'Corporate master runtime asset is not loaded'
require_text "$FUNCTIONS" "array( 'seo-geo-theme' )" 'Corporate master must load directly from the Theme foundation, not legacy Corporate CSS'
require_text "$FUNCTIONS" 'return;' 'Corporate master asset branch must exit before legacy preset enqueue'

printf '[corporate-v4] Checking WOW composition contract.\n'
require_text "$CSS" 'width: 100vw;' 'full-bleed composition ownership missing'
require_text "$CSS" 'min-height: min(820px, calc(100vh - 90px));' 'dominant hero stage missing'
require_text "$CSS" 'font-size: clamp(3.7rem, 6.8vw, 7.6rem) !important;' 'hero editorial scale missing'
require_text "$CSS" 'grid-template-columns: repeat(12, minmax(0, 1fr));' '12-column capability/insight system missing'
require_text "$CSS" '.seo-geo-corporate-card:nth-child(1)' 'featured capability statement panel missing'
require_text "$CSS" '.seo-geo-corporate-native-process' 'full-bleed method scene missing'
require_text "$CSS" '.seo-geo-corporate-native-insights .wp-block-post-template' 'magazine Insights composition missing'
require_text "$CSS" 'min-height: min(760px, 82vh);' 'closing CTA scene is not dominant enough'
require_text "$CSS" 'content: "↗";' 'CTA directional visual cue missing'

printf '[corporate-v4] Checking first-class responsive widths.\n'
require_text "$CSS" '@media (max-width: 1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width: 820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width: 520px)' '390-class responsive contract missing'
require_text "$RUNTIME" '@media (max-width: 1100px)' '1024-class navigation runtime missing'
require_text "$CSS" 'grid-template-columns: 1fr !important;' 'single-column mobile recomposition missing'
require_text "$CSS" 'word-break: normal;' 'long-title word-break guard missing'
require_text "$CSS" 'hyphens: none;' 'long-title hyphenation guard missing'

printf '[corporate-v4] Checking accessibility/performance boundaries.\n'
require_text "$CSS" '@media (prefers-reduced-motion: reduce)' 'reduced-motion contract missing'
require_text "$CSS" 'color: #fff;' 'dark-surface high-contrast text contract missing'
if grep -Eqi '@import|https?://|url\(' "$CSS" "$RUNTIME"; then
  fail 'Corporate v4 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
visual_gzip="$(gzip -9c "$CSS" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + visual_gzip + runtime_gzip ))

printf '[corporate-v4] CSS gzip proxy: foundation=%s visual=%s runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$visual_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7900 )); then
  fail "Corporate v4 Home CSS gzip proxy ${total_gzip} exceeds 7900-byte safety ceiling below the 8192-byte network budget"
fi

printf '[corporate-v4] PASS: WOW Home composition and lean-asset contracts present.\n'
