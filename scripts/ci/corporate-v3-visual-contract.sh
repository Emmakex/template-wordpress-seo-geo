#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v3-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
FUNCTIONS="packages/seo-geo-theme/functions.php"

fail() {
  printf '[corporate-v4.1] FAIL: %s\n' "$1" >&2
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

printf '[corporate-v4.1] Checking master presentation ownership.\n'
require_text "$CSS" 'Corporate v4.1 WOW master visual layer.' 'Corporate v4.1 ownership marker missing'
require_text "$FUNCTIONS" 'seo_geo_theme_corporate_master_home_layer' 'Corporate master Home detector missing'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v2.css" 'Corporate master presentation asset is not loaded'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v3-runtime.css" 'Corporate master runtime asset is not loaded'
require_text "$FUNCTIONS" "array( 'seo-geo-theme' )" 'Corporate master must load directly from the Theme foundation, not legacy Corporate CSS'
require_text "$FUNCTIONS" 'return;' 'Corporate master asset branch must exit before legacy preset enqueue'

printf '[corporate-v4.1] Checking field-corrected composition contract.\n'
require_text "$CSS" 'width: 100vw;' 'full-bleed composition ownership missing'
require_text "$CSS" 'min-height: min(760px, calc(100vh - 86px));' 'balanced hero stage missing'
require_text "$CSS" 'font-size: clamp(3.35rem,5.2vw,6.15rem) !important;' 'field-corrected hero scale missing'
require_text "$CSS" 'grid-template-columns: minmax(0,1.08fr) minmax(0,.92fr);' 'balanced capabilities composition missing'
require_text "$CSS" '.seo-geo-corporate-card:nth-child(1)' 'featured capability panel missing'
require_text "$CSS" '.seo-geo-corporate-native-process::after { display: none; }' 'method decoration correction missing'
require_text "$CSS" '.seo-geo-corporate-native-insights .wp-block-post-template' 'magazine Insights composition missing'
require_text "$CSS" 'min-height: min(620px,72vh);' 'balanced closing CTA scene missing'
require_text "$CSS" 'content: "↗";' 'CTA directional visual cue missing'
require_text "$CSS" '.seo-geo-page-shell:has(.seo-geo-corporate-native-hero) > .seo-geo-page-title' 'draft preview title-collapse contract missing'

printf '[corporate-v4.1] Checking first-class responsive widths.\n'
require_text "$CSS" '@media (max-width: 1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width: 820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width: 520px)' '390-class responsive contract missing'
require_text "$RUNTIME" '@media (max-width: 1100px)' '1024-class navigation runtime missing'
require_text "$CSS" 'grid-template-columns: 1fr !important;' 'single-column mobile recomposition missing'
require_text "$CSS" 'word-break: normal;' 'long-title word-break guard missing'
require_text "$CSS" 'hyphens: none;' 'long-title hyphenation guard missing'

printf '[corporate-v4.1] Checking accessibility/performance boundaries.\n'
require_text "$CSS" '@media (prefers-reduced-motion: reduce)' 'reduced-motion contract missing'
require_text "$CSS" 'clip-path: inset(50%) !important;' 'semantic preview H1 visually-hidden contract missing'
require_text "$CSS" 'color: #fff;' 'dark-surface high-contrast text contract missing'
if grep -Eqi '@import|https?://|url\(' "$CSS" "$RUNTIME"; then
  fail 'Corporate v4.1 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
visual_gzip="$(gzip -9c "$CSS" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + visual_gzip + runtime_gzip ))

printf '[corporate-v4.1] CSS gzip proxy: foundation=%s visual=%s runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$visual_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7100 )); then
  fail "Corporate v4.1 Home CSS gzip proxy ${total_gzip} exceeds 7100-byte safety ceiling calibrated below the 8192-byte Lighthouse network budget"
fi

printf '[corporate-v4.1] PASS: field-corrected Home composition and lean-asset contracts present.\n'
