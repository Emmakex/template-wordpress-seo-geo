#!/usr/bin/env bash
set -euo pipefail

CSS="packages/seo-geo-theme/assets/css/presets/corporate-v2.css"
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
[[ -f "$FUNCTIONS" ]] || fail "theme bootstrap missing: $FUNCTIONS"

printf '[corporate-v3] Checking master presentation ownership.\n'
require_text "$CSS" 'Corporate v3 master visual layer.' 'Corporate v3 ownership marker missing'
require_text "$FUNCTIONS" "assets/css/presets/corporate-v2.css" 'Corporate compatibility presentation layer is not loaded'
require_text "$FUNCTIONS" "seo-geo-theme-preset-corporate-v2" 'Corporate presentation style handle missing'

printf '[corporate-v3] Checking approved responsive acceptance widths.\n'
require_text "$CSS" '@media (max-width: 1100px)' '1024-class responsive contract missing'
require_text "$CSS" '@media (max-width: 820px)' '768-class responsive contract missing'
require_text "$CSS" '@media (max-width: 600px)' '390-class responsive contract missing'
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
if grep -Eqi '@import|https?://|url\(' "$CSS"; then
  fail 'Corporate v3 presentation must not add remote visual dependencies'
fi

printf '[corporate-v3] PASS: master Home visual contract present.\n'
