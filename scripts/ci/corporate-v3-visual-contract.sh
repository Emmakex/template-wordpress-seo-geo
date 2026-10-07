#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v3-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
FUNCTIONS="packages/seo-geo-theme/functions.php"

fail() {
  printf '[corporate-v4.3] FAIL: %s\n' "$1" >&2
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

printf '[corporate-v4.3] Checking master presentation ownership.\n'
require_text "$CSS" 'Corporate v4.2 WOW master visual layer.' 'Corporate v4 base ownership marker missing'
require_text "$RUNTIME" 'Corporate v4.3 field pass: escape Gutenberg constrained widths on master surfaces.' 'Corporate v4.3 field-pass marker missing'
require_text "$FUNCTIONS" 'seo_geo_theme_corporate_master_home_layer' 'Corporate master Home detector missing'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v2.css" 'Corporate master presentation asset is not loaded'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v3-runtime.css" 'Corporate master runtime asset is not loaded'
require_text "$FUNCTIONS" "array( 'seo-geo-theme' )" 'Corporate master must load directly from the Theme foundation, not legacy Corporate CSS'
require_text "$FUNCTIONS" 'return;' 'Corporate master asset branch must exit before legacy preset enqueue'
require_text "$FUNCTIONS" 'function seo_geo_theme_asset_version' 'content-derived asset version helper missing'
require_text "$FUNCTIONS" "hash_file( 'sha256', \$path )" 'asset version must be derived from content bytes'
require_text "$FUNCTIONS" 'seo_geo_theme_asset_version( $visual_stylesheet )' 'Corporate visual asset is not cache-busted by content hash'
require_text "$FUNCTIONS" 'seo_geo_theme_asset_version( $runtime_stylesheet )' 'Corporate runtime asset is not cache-busted by content hash'

printf '[corporate-v4.3] Checking wide-layout escape contract.\n'
require_text "$CSS" '--cv4-shell:min(1400px,calc(100vw - 4rem));' '1400px horizontal master shell missing'
require_text "$RUNTIME" 'grid-template-columns:minmax(0,1.55fr) minmax(220px,.45fr)!important;' 'v4.3 wider hero composition missing'
require_text "$RUNTIME" 'max-width:28ch!important;' 'v4.3 hero reading measure missing'
require_text "$RUNTIME" 'font-size:clamp(3rem,3.8vw,4.7rem)!important;' 'v4.3 hero typography missing'
require_text "$RUNTIME" '.seo-geo-corporate-native-section{width:var(--cv4-shell)!important;max-width:none!important;margin-inline:auto!important}' 'native section must escape Gutenberg constrained width'
require_text "$RUNTIME" '.seo-geo-corporate-native-insights>.wp-block-query' 'Insights query width escape missing'
require_text "$RUNTIME" '.seo-geo-corporate-process-grid{width:100%!important;max-width:none!important;margin-inline:auto!important}' 'process grid width escape missing'
require_text "$RUNTIME" 'max-width:50ch!important;' 'process body reading measure missing'
require_text "$RUNTIME" 'max-width:18ch!important;font-size:clamp(2.8rem,3.7vw,4.3rem)!important' 'featured Insight title measure missing'
require_text "$CSS" 'min-height:min(600px,70vh);' 'rebalanced closing CTA scene missing'
require_text "$CSS" 'content:"↗";' 'CTA directional visual cue missing'
require_text "$CSS" '.seo-geo-page-shell:has(.seo-geo-corporate-native-hero) > .seo-geo-page-title' 'draft preview title-collapse contract missing'

printf '[corporate-v4.3] Checking first-class responsive widths.\n'
require_text "$CSS" '@media (max-width:1200px)' 'wide-laptop typography contract missing'
require_text "$CSS" '@media (max-width:1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width:820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width:520px)' '390-class responsive contract missing'
require_text "$RUNTIME" '@media (max-width: 1100px)' '1024-class navigation runtime missing'
require_text "$RUNTIME" '@media (min-width:821px) and (max-width:1200px)' 'v4.3 laptop layout tuning missing'
require_text "$CSS" 'grid-template-columns:1fr !important;' 'single-column mobile recomposition missing'
require_text "$CSS" 'word-break:normal;' 'long-title word-break guard missing'
require_text "$CSS" 'hyphens:none;' 'long-title hyphenation guard missing'

printf '[corporate-v4.3] Checking accessibility/performance boundaries.\n'
require_text "$CSS" '@media (prefers-reduced-motion:reduce)' 'reduced-motion contract missing'
require_text "$CSS" 'clip-path:inset(50%) !important;' 'semantic preview H1 visually-hidden contract missing'
require_text "$CSS" 'color:#fff;' 'dark-surface high-contrast text contract missing'
if grep -Eqi '@import|https?://|url\(' "$CSS" "$RUNTIME"; then
  fail 'Corporate v4.3 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
visual_gzip="$(gzip -9c "$CSS" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + visual_gzip + runtime_gzip ))

printf '[corporate-v4.3] CSS gzip proxy: foundation=%s visual=%s runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$visual_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7100 )); then
  fail "Corporate v4.3 Home CSS gzip proxy ${total_gzip} exceeds 7100-byte safety ceiling calibrated below the 8192-byte Lighthouse network budget"
fi

printf '[corporate-v4.3] PASS: layout escape, cache-safe assets and lean-asset contracts present.\n'
