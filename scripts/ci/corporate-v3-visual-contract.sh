#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
V5_RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v5-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
MODEL="packages/seo-geo-theme/inc/Strategic/CorporateHomeModelResolver.php"
RENDERER="packages/seo-geo-theme/inc/Strategic/CorporateHomeRenderer.php"
SURFACE_RUNTIME="packages/seo-geo-theme/inc/Strategic/StrategicSurfaceRuntime.php"
BOOTSTRAP="packages/seo-geo-theme/inc/strategic.php"
TEMPLATE="packages/seo-geo-theme/strategic-templates/corporate-home.php"
CONTENT_MAP="presets/corporate/content-map.json"

fail() {
  printf '[corporate-v5] FAIL: %s\n' "$1" >&2
  exit 1
}

require_text() {
  local file="$1"
  local text="$2"
  local label="$3"
  grep -Fq -- "$text" "$file" || fail "$label"
}

for file in "$CSS" "$V5_RUNTIME" "$FOUNDATION" "$MODEL" "$RENDERER" "$SURFACE_RUNTIME" "$BOOTSTRAP" "$TEMPLATE" "$CONTENT_MAP"; do
  [[ -f "$file" ]] || fail "required Corporate v5 path missing: $file"
done

printf '[corporate-v5] Checking semantic model boundary.\n'
require_text "$CONTENT_MAP" '"corporate-home-v1"' 'canonical corporate-home-v1 model missing'
grep -Eq "private const MODEL_ID[[:space:]]*=[[:space:]]*'corporate-home-v1';" "$MODEL" || fail 'resolver is not bound to corporate-home-v1'
require_text "$MODEL" "seo_geo_theme_preset_document( 'corporate', 'content-map.json' )" 'resolver must read the canonical Corporate content contract'
require_text "$MODEL" "apply_filters( 'seo_geo_theme_corporate_home_model'" 'Manager/external model handoff filter missing'
require_text "$MODEL" "'source'   => 'hydrated-semantic-blocks'" 'transition source must be explicit'

printf '[corporate-v5] Checking Theme-owned renderer boundary.\n'
require_text "$SURFACE_RUNTIME" "add_filter( 'template_include'" 'strategic template selector missing'
require_text "$SURFACE_RUNTIME" "strategic-templates/corporate-home.php" 'Corporate strategic document template missing from selector'
require_text "$SURFACE_RUNTIME" "'corporate' !== seo_geo_theme_active_preset_id()" 'Corporate preset guard missing'
require_text "$RENDERER" 'seo-geo-corporate-v5-home' 'v5 root renderer marker missing'
require_text "$RENDERER" '<h1 id="seo-geo-corporate-v5-title" class="seo-geo-corporate-lead">' 'renderer must own the single visible Home H1'
require_text "$RENDERER" 'seo-geo-corporate-native-capabilities' 'capabilities renderer missing'
require_text "$RENDERER" 'seo-geo-corporate-native-process' 'process renderer missing'
require_text "$RENDERER" 'seo-geo-corporate-native-insights' 'Insights renderer missing'
require_text "$RENDERER" 'seo-geo-final-cta' 'final CTA renderer missing'
require_text "$RENDERER" 'new WP_Query' 'Insights must remain dynamic WordPress content'
require_text "$TEMPLATE" 'wp_head();' 'strategic document must preserve WordPress head hooks'
require_text "$TEMPLATE" 'wp_footer();' 'strategic document must preserve WordPress footer hooks'
require_text "$TEMPLATE" "block_template_part( 'header' )" 'Theme header integration missing'
require_text "$TEMPLATE" "block_template_part( 'footer' )" 'Theme footer integration missing'

if grep -Fq 'wp-block-post-content' "$TEMPLATE" "$RENDERER" "$V5_RUNTIME"; then
  fail 'Corporate v5 strategic frontend must not render Gutenberg post-content as its layout authority'
fi
if grep -Fq 'contentSize' "$TEMPLATE" "$RENDERER" "$V5_RUNTIME"; then
  fail 'Corporate v5 strategic frontend must not depend on Gutenberg contentSize'
fi
if grep -Fq ':has(' "$V5_RUNTIME"; then
  fail 'Corporate v5 runtime must not use selector hacks to escape Gutenberg layout'
fi

printf '[corporate-v5] Checking legacy escape removal and lean runtime.\n'
require_text "$SURFACE_RUNTIME" "wp_dequeue_style( 'seo-geo-theme-preset-corporate-v3-runtime' )" 'v5 must remove the legacy Gutenberg escape stylesheet'
require_text "$SURFACE_RUNTIME" 'assets/css/presets/corporate-v5-runtime.css' 'v5 canvas runtime is not enqueued'
require_text "$V5_RUNTIME" 'Corporate v5: Theme-owned strategic canvas.' 'v5 canvas ownership marker missing'
require_text "$V5_RUNTIME" 'margin-inline:auto!important' 'strategic bounded sections must center themselves'
require_text "$V5_RUNTIME" 'margin-left:0!important' 'strategic full-bleed sections must not depend on Gutenberg offset hacks'

printf '[corporate-v5] Checking retained WOW/responsive foundation.\n'
require_text "$CSS" 'Corporate v4.2 WOW master visual layer.' 'accepted WOW art-direction stylesheet missing'
require_text "$CSS" '--cv4-shell:min(1400px,calc(100vw - 4rem));' '1400px master shell missing'
require_text "$CSS" '@media (max-width:1200px)' 'wide-laptop typography contract missing'
require_text "$CSS" '@media (max-width:1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width:820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width:520px)' '390-class responsive contract missing'
require_text "$CSS" '@media (prefers-reduced-motion:reduce)' 'reduced-motion contract missing'
require_text "$CSS" 'word-break:normal;' 'long-title word-break guard missing'
require_text "$CSS" 'hyphens:none;' 'long-title hyphenation guard missing'

if grep -Eqi '@import|https?://|url\(' "$CSS" "$V5_RUNTIME"; then
  fail 'Corporate v5 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
visual_gzip="$(gzip -9c "$CSS" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$V5_RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + visual_gzip + runtime_gzip ))

printf '[corporate-v5] CSS gzip proxy: foundation=%s visual=%s v5-runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$visual_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7100 )); then
  fail "Corporate v5 Home CSS gzip proxy ${total_gzip} exceeds the existing 7100-byte safety ceiling"
fi

printf '[corporate-v5] PASS: Theme-owned semantic renderer, dynamic Insights and lean canvas contracts present.\n'
