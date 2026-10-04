#!/usr/bin/env bash
# Phase 7A Corporate preset acceptance.
#
# This file is sourced by self-contained-theme-smoke.sh and reuses its
# disposable WordPress fixture and structured fail_smoke diagnostic.

printf '[self-contained] Checking Corporate preset packaging and activation.\n'

for corporate_file in \
  "${BUILT_THEME}/inc/presets.php" \
  "${BUILT_THEME}/presets/corporate/preset.json" \
  "${BUILT_THEME}/presets/corporate/content-map.json" \
  "${BUILT_THEME}/presets/corporate/patterns.json" \
  "${BUILT_THEME}/assets/css/presets/corporate.css"; do
  [[ -f "$corporate_file" ]] \
    || fail_smoke "corporate-preset-package" "Built theme is missing a Corporate preset artifact" "file exists" "$corporate_file"
done

corporate_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/corporate-preset-patterns.stderr" | tr -d '\r\n'
}

CORPORATE_STATE_DEFAULT="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_DEFAULT" == "000" ]] \
  || fail_smoke "corporate-preset-default" "Corporate preset patterns registered before explicit preset activation" "000" "$CORPORATE_STATE_DEFAULT"

CORPORATE_VISUAL_DEFAULT="$(wp_cli eval 'do_action( "wp_enqueue_scripts" ); echo wp_style_is( "seo-geo-theme-preset-corporate", "enqueued" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-visual-default.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_VISUAL_DEFAULT" == "0" ]] \
  || fail_smoke "corporate-preset-visual-default" "Corporate visual system loaded before explicit preset activation" "0" "$CORPORATE_VISUAL_DEFAULT"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "corporate-preset-option" "Could not activate Corporate preset" "option update succeeds" "failed"

CORPORATE_STATE_ACTIVE="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_ACTIVE" == "111" ]] \
  || fail_smoke "corporate-preset-active" "Corporate preset did not register its complete pattern set after activation" "111" "$CORPORATE_STATE_ACTIVE"

CORPORATE_VISUAL_ACTIVE="$(wp_cli eval 'do_action( "wp_enqueue_scripts" ); echo wp_style_is( "seo-geo-theme-preset-corporate", "enqueued" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-visual-active.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_VISUAL_ACTIVE" == "1" ]] \
  || fail_smoke "corporate-preset-visual-active" "Corporate preset did not enqueue its isolated visual stylesheet" "1" "$CORPORATE_VISUAL_ACTIVE"

CORPORATE_BODY_CLASS="$(wp_cli eval '$classes = seo_geo_theme_preset_body_class( array() ); echo in_array( "seo-geo-preset-corporate", $classes, true ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-body-class.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_BODY_CLASS" == "1" ]] \
  || fail_smoke "corporate-preset-body-class" "Corporate preset did not expose its stable frontend body class" "1" "$CORPORATE_BODY_CLASS"

printf '[self-contained] Checking Corporate migration-aware navigation.\n'
CORPORATE_LEGACY_MENU_ID="$(wp_cli menu create 'Primary Menu' --porcelain 2>/dev/null | tr -d '\r\n')"
[[ "$CORPORATE_LEGACY_MENU_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "corporate-nav-menu-create" "Could not create legacy primary menu fixture" "numeric menu ID" "$CORPORATE_LEGACY_MENU_ID"

wp_cli menu item add-custom "$CORPORATE_LEGACY_MENU_ID" 'Company' "$BASE_URL/company/" >/dev/null \
  || fail_smoke "corporate-nav-menu-company" "Could not add Company fixture navigation item" "menu item created" "failed"
wp_cli menu item add-custom "$CORPORATE_LEGACY_MENU_ID" 'Insights' "$BASE_URL/insights/" >/dev/null \
  || fail_smoke "corporate-nav-menu-insights" "Could not add Insights fixture navigation item" "menu item created" "failed"
wp_cli menu item add-custom "$CORPORATE_LEGACY_MENU_ID" 'Contact' "$BASE_URL/contact/" >/dev/null \
  || fail_smoke "corporate-nav-menu-contact" "Could not add Contact fixture navigation item" "menu item created" "failed"

CORPORATE_NAV_HTML="$(wp_cli eval 'echo do_blocks( "<!-- wp:seo-geo/preset-navigation {\\"location\\":\\"primary\\"} /-->" );' 2>"${TMP_DIR}/corporate-preset-navigation.stderr")"
for expected_nav_fragment in \
  'seo-geo-preset-navigation--primary' \
  '>Company<' \
  '>Insights<' \
  '>Contact<' \
  '<details class="seo-geo-preset-navigation__mobile">'; do
  [[ "$CORPORATE_NAV_HTML" == *"$expected_nav_fragment"* ]] \
    || fail_smoke "corporate-nav-render" "Corporate navigation did not reuse the bounded legacy primary menu" "$expected_nav_fragment" "$CORPORATE_NAV_HTML"
done
[[ "$CORPORATE_NAV_HTML" != *"wp-block-page-list"* ]] \
  || fail_smoke "corporate-nav-all-pages" "Corporate navigation fell back to an unbounded all-pages list" "no wp-block-page-list markup" "$CORPORATE_NAV_HTML"

if ! CORPORATE_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "corporate", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "site_type" => $manifest["site_type"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "visual" => $manifest["visual"]["design_system"] ?? null ) );' 2>"${TMP_DIR}/corporate-preset-manifest.stderr" | tr -d '\r\n')"; then
  CORPORATE_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-manifest.stderr" | head -c 240)"
  fail_smoke "corporate-preset-manifest-eval" "Could not resolve Corporate preset manifest through the theme registry" "manifest resolves" "${CORPORATE_MANIFEST_ERROR:-wp eval failed}" "wp eval seo_geo_theme_preset_document"
fi
[[ "$CORPORATE_MANIFEST" == '{"id":"corporate","site_type":"corporate","confirmation":true,"visual":"corporate-v1.1"}' ]] \
  || fail_smoke "corporate-preset-manifest" "Corporate preset manifest did not preserve its identity/confirmation/visual contract" '{"id":"corporate","site_type":"corporate","confirmation":true,"visual":"corporate-v1"}' "$CORPORATE_MANIFEST"

if ! CORPORATE_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-en.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-en.stderr" | head -c 240)"
  fail_smoke "corporate-preset-en-eval" "Could not resolve Corporate English pattern title" "Corporate verified metrics" "${CORPORATE_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_EN" == "Corporate verified metrics" ]] \
  || fail_smoke "corporate-preset-en" "Corporate preset English copy is not active in the default locale" "Corporate verified metrics" "$CORPORATE_TITLE_EN"

if ! CORPORATE_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-es.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-es.stderr" | head -c 240)"
  fail_smoke "corporate-preset-es-eval" "Could not resolve Corporate Spanish pattern title" "Métricas corporativas verificadas" "${CORPORATE_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_ES" == "Métricas corporativas verificadas" ]] \
  || fail_smoke "corporate-preset-es" "Corporate preset did not ship Spanish copy with English" "Métricas corporativas verificadas" "$CORPORATE_TITLE_ES"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "corporate-preset-invalid-option" "Could not set unsupported preset probe" "option update succeeds" "failed"

CORPORATE_STATE_UNSUPPORTED="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_UNSUPPORTED" == "000" ]] \
  || fail_smoke "corporate-preset-allowlist" "Unsupported preset ID activated Corporate pattern behavior" "000" "$CORPORATE_STATE_UNSUPPORTED"

CORPORATE_VISUAL_UNSUPPORTED="$(wp_cli eval 'do_action( "wp_enqueue_scripts" ); echo wp_style_is( "seo-geo-theme-preset-corporate", "enqueued" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-visual-unsupported.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_VISUAL_UNSUPPORTED" == "0" ]] \
  || fail_smoke "corporate-preset-visual-allowlist" "Unsupported preset activated Corporate visual behavior" "0" "$CORPORATE_VISUAL_UNSUPPORTED"

if ! CORPORATE_UNSUPPORTED="$(wp_cli eval 'echo null === seo_geo_theme_active_preset_id() ? "null" : "unexpected";' 2>"${TMP_DIR}/corporate-preset-unsupported.stderr" | tr -d '\r\n')"; then
  CORPORATE_UNSUPPORTED_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-unsupported.stderr" | head -c 240)"
  fail_smoke "corporate-preset-allowlist-eval" "Could not evaluate unsupported preset allowlist" "null" "${CORPORATE_UNSUPPORTED_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_UNSUPPORTED" == "null" ]] \
  || fail_smoke "corporate-preset-allowlist-id" "Theme accepted an unsupported preset ID" "null" "$CORPORATE_UNSUPPORTED"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "corporate-preset-reset" "Could not reset Corporate preset fixture" "preset option removed" "failed"

printf '[self-contained] Corporate preset activation OK.\n'
