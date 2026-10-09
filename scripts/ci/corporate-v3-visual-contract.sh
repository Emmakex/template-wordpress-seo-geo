#!/usr/bin/env bash
set -euo pipefail

V5_RUNTIME="packages/seo-geo-theme/assets/css/presets/corporate-v5-runtime.css"
FOUNDATION="packages/seo-geo-theme/style.css"
MODEL="packages/seo-geo-theme/inc/Strategic/CorporateHomeModelResolver.php"
RENDERER="packages/seo-geo-theme/inc/Strategic/CorporateHomeRenderer.php"
SURFACE_RUNTIME="packages/seo-geo-theme/inc/Strategic/StrategicSurfaceRuntime.php"
BOOTSTRAP="packages/seo-geo-theme/inc/strategic.php"
TEMPLATE="packages/seo-geo-theme/strategic-templates/corporate-home.php"
CONTENT_MAP="presets/corporate/content-map.json"
BUILD_SCRIPT="scripts/build-theme-package.sh"

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

for file in "$V5_RUNTIME" "$FOUNDATION" "$MODEL" "$RENDERER" "$SURFACE_RUNTIME" "$BOOTSTRAP" "$TEMPLATE" "$CONTENT_MAP" "$BUILD_SCRIPT"; do
  [[ -f "$file" ]] || fail "required Corporate v5 path missing: $file"
done

printf '[corporate-v5] Checking semantic model boundary.\n'
require_text "$CONTENT_MAP" '"corporate-home-v1"' 'canonical corporate-home-v1 model missing'
grep -Eq "private const MODEL_ID[[:space:]]*=[[:space:]]*'corporate-home-v1';" "$MODEL" || fail 'resolver is not bound to corporate-home-v1'
require_text "$MODEL" "seo_geo_theme_preset_document( 'corporate', 'content-map.json' )" 'resolver must read the canonical Corporate content contract'
require_text "$MODEL" "apply_filters( 'seo_geo_theme_corporate_home_model'" 'Manager/external model handoff filter missing'
require_text "$MODEL" "'source'   => 'hydrated-semantic-blocks'" 'transition source must be explicit'

printf '[corporate-v5] Checking Theme-owned renderer and chrome boundary.\n'
require_text "$SURFACE_RUNTIME" "add_filter( 'template_include'" 'strategic template selector missing'
require_text "$SURFACE_RUNTIME" "strategic-templates/corporate-home.php" 'Corporate strategic document template missing from selector'
require_text "$SURFACE_RUNTIME" "'corporate' === \$preset_id" 'Corporate preset dispatch guard missing'
require_text "$SURFACE_RUNTIME" "wp_dequeue_style( 'seo-geo-theme-preset-corporate-v2' )" 'v5 must remove legacy Corporate visual CSS'
require_text "$SURFACE_RUNTIME" "wp_dequeue_style( 'seo-geo-theme-preset-corporate-v3-runtime' )" 'v5 must remove legacy Gutenberg escape CSS'
require_text "$RENDERER" 'seo-geo-corporate-v5-home' 'v5 root renderer marker missing'
require_text "$RENDERER" '<h1 id="seo-geo-corporate-v5-title" class="seo-geo-corporate-lead">' 'renderer must own the single visible Home H1'
require_text "$RENDERER" 'seo-geo-corporate-native-capabilities' 'capabilities renderer missing'
require_text "$RENDERER" 'seo-geo-corporate-native-process' 'process renderer missing'
require_text "$RENDERER" 'seo-geo-corporate-native-insights' 'Insights renderer missing'
require_text "$RENDERER" 'seo-geo-final-cta' 'final CTA renderer missing'
require_text "$RENDERER" 'new WP_Query' 'Insights must remain dynamic WordPress content'
require_text "$TEMPLATE" 'wp_head();' 'strategic document must preserve WordPress head hooks'
require_text "$TEMPLATE" 'wp_footer();' 'strategic document must preserve WordPress footer hooks'
require_text "$TEMPLATE" 'seo-geo-strategic-header' 'Theme-owned strategic header missing'
require_text "$TEMPLATE" 'seo-geo-strategic-footer' 'Theme-owned strategic footer missing'
require_text "$TEMPLATE" 'seo-geo/preset-navigation' 'strategic chrome must keep preset navigation authority'

if grep -Fq 'block_template_part(' "$TEMPLATE"; then
  fail 'Corporate v5 strategic chrome must not inherit database-overridable block template parts'
fi
if grep -Fq 'wp-block-post-content' "$TEMPLATE" "$RENDERER" "$V5_RUNTIME"; then
  fail 'Corporate v5 strategic frontend must not render Gutenberg post-content as its layout authority'
fi
if grep -Fq 'contentSize' "$TEMPLATE" "$RENDERER" "$V5_RUNTIME"; then
  fail 'Corporate v5 strategic frontend must not depend on Gutenberg contentSize'
fi
if grep -Fq ':has(' "$V5_RUNTIME"; then
  fail 'Corporate v5 runtime must not use selector hacks to escape Gutenberg layout'
fi

printf '[corporate-v5] Checking A3 lighter visual system.\n'
require_text "$V5_RUNTIME" 'Corporate v5 A3: lighter Theme-owned strategic visual system.' 'A3 lighter visual-system marker missing'
require_text "$V5_RUNTIME" '--v5-shell:min(1400px,calc(100vw - 4rem));' '1400px strategic shell missing'
require_text "$V5_RUNTIME" '.seo-geo-strategic-header__inner' 'strategic header layout missing'
require_text "$V5_RUNTIME" '.seo-geo-corporate-native-hero__grid' 'strategic hero grid missing'
require_text "$V5_RUNTIME" '.seo-geo-corporate-card-grid' 'capabilities grid missing'
require_text "$V5_RUNTIME" '.seo-geo-corporate-process-grid' 'process grid missing'
require_text "$V5_RUNTIME" 'linear-gradient(145deg,#edf8f3,#dcefe8)' 'A3 light Method surface missing'
require_text "$V5_RUNTIME" 'linear-gradient(135deg,#d9f5eb,#f5fbf8)' 'A3 light final CTA surface missing'
require_text "$V5_RUNTIME" 'background:linear-gradient(135deg,#143f43,#0d3034)' 'A3 branded teal footer missing'
require_text "$V5_RUNTIME" '.seo-geo-corporate-native-insights .wp-block-post-template' 'Insights magazine grid missing'
require_text "$V5_RUNTIME" 'list-style:none' 'Insights/navigation list reset missing'
require_text "$V5_RUNTIME" '.seo-geo-final-cta>p:last-child a' 'final CTA button styling missing'
require_text "$V5_RUNTIME" '.seo-geo-strategic-footer__inner' 'strategic footer layout missing'
require_text "$V5_RUNTIME" '.seo-geo-strategic-footer .seo-geo-preset-navigation__item a' 'footer navigation treatment missing'
require_text "$V5_RUNTIME" '@media(max-width:1200px)' 'wide-laptop typography contract missing'
require_text "$V5_RUNTIME" '@media(max-width:1100px)' '1024-class responsive contract missing'
require_text "$V5_RUNTIME" '@media(max-width:820px)' '768-class responsive contract missing'
require_text "$V5_RUNTIME" '@media(max-width:520px)' '390-class responsive contract missing'
require_text "$V5_RUNTIME" '@media(prefers-reduced-motion:reduce)' 'reduced-motion contract missing'
require_text "$V5_RUNTIME" 'word-break:normal' 'long-title word-break guard missing'
require_text "$V5_RUNTIME" 'hyphens:none' 'long-title hyphenation guard missing'
require_text "$BUILD_SCRIPT" '"${THEME_SOURCE}/assets/css/presets/corporate-v5-runtime.css"' 'release build must include v5 visual runtime'

if grep -Fq '"${THEME_SOURCE}/assets/css/presets/corporate-v2.css"' "$BUILD_SCRIPT"; then
  fail 'Corporate v5 client bundle must not carry legacy v4 Corporate CSS debt'
fi
if grep -Eqi '@import|https?://|url\(' "$V5_RUNTIME"; then
  fail 'Corporate v5 presentation must not add remote visual dependencies'
fi

foundation_gzip="$(gzip -9c "$FOUNDATION" | wc -c | tr -d ' ')"
runtime_gzip="$(gzip -9c "$V5_RUNTIME" | wc -c | tr -d ' ')"
total_gzip=$(( foundation_gzip + runtime_gzip ))

printf '[corporate-v5] CSS gzip proxy: foundation=%s v5-runtime=%s total=%s bytes.\n' \
  "$foundation_gzip" "$runtime_gzip" "$total_gzip"

if (( total_gzip > 7100 )); then
  fail "Corporate v5 Home CSS gzip proxy ${total_gzip} exceeds the existing 7100-byte safety ceiling"
fi

printf '[corporate-v5] PASS: Theme-owned chrome, semantic renderer, A3 lighter palette and lean responsive bundle contracts present.\n'
