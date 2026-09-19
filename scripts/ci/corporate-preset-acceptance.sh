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
  "${BUILT_THEME}/presets/corporate/patterns.json"; do
  [[ -f "$corporate_file" ]] \
    || fail_smoke "corporate-preset-package" "Built theme is missing a Corporate preset artifact" "file exists" "$corporate_file"
done

corporate_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/corporate-preset-patterns.stderr" | tr -d '\r\n'
}

CORPORATE_STATE_DEFAULT="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_DEFAULT" == "000" ]] \
  || fail_smoke "corporate-preset-default" "Corporate preset patterns registered before explicit preset activation" "000" "$CORPORATE_STATE_DEFAULT"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "corporate-preset-option" "Could not activate Corporate preset" "option update succeeds" "failed"

CORPORATE_STATE_ACTIVE="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_ACTIVE" == "111" ]] \
  || fail_smoke "corporate-preset-active" "Corporate preset did not register its complete pattern set after activation" "111" "$CORPORATE_STATE_ACTIVE"

if ! CORPORATE_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "corporate", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "site_type" => $manifest["site_type"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null ) );' 2>"${TMP_DIR}/corporate-preset-manifest.stderr" | tr -d '\r\n')"; then
  CORPORATE_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-manifest.stderr" | head -c 240)"
  fail_smoke "corporate-preset-manifest-eval" "Could not resolve Corporate preset manifest through the theme registry" "manifest resolves" "${CORPORATE_MANIFEST_ERROR:-wp eval failed}" "wp eval seo_geo_theme_preset_document"
fi
[[ "$CORPORATE_MANIFEST" == '{"id":"corporate","site_type":"corporate","confirmation":true}' ]] \
  || fail_smoke "corporate-preset-manifest" "Corporate preset manifest did not preserve its identity/confirmation contract" '{"id":"corporate","site_type":"corporate","confirmation":true}' "$CORPORATE_MANIFEST"

if ! CORPORATE_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-en.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-en.stderr" | head -c 240)"
  fail_smoke "corporate-preset-en-eval" "Could not resolve Corporate English pattern title" "Corporate verified metrics" "${CORPORATE_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_EN" == "Corporate verified metrics" ]] \
  || fail_smoke "corporate-preset-en" "Corporate preset English copy is not active in the default locale" "Corporate verified metrics" "$CORPORATE_TITLE_EN"

wp_cli eval 'update_option( "WPLANG", "es_ES", false );' >/dev/null \
  || fail_smoke "corporate-preset-es-locale" "Could not switch Corporate fixture to es_ES" "WPLANG=es_ES" "failed"

if ! CORPORATE_TITLE_ES="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-es.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-es.stderr" | head -c 240)"
  fail_smoke "corporate-preset-es-eval" "Could not resolve Corporate Spanish pattern title" "Métricas corporativas verificadas" "${CORPORATE_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_ES" == "Métricas corporativas verificadas" ]] \
  || fail_smoke "corporate-preset-es" "Corporate preset did not ship Spanish copy with English" "Métricas corporativas verificadas" "$CORPORATE_TITLE_ES"

wp_cli option update seo_geo_active_preset publisher >/dev/null \
  || fail_smoke "corporate-preset-invalid-option" "Could not set unsupported preset probe" "option update succeeds" "failed"

CORPORATE_STATE_UNSUPPORTED="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_UNSUPPORTED" == "000" ]] \
  || fail_smoke "corporate-preset-allowlist" "Unsupported preset ID activated Corporate pattern behavior" "000" "$CORPORATE_STATE_UNSUPPORTED"

if ! CORPORATE_UNSUPPORTED="$(wp_cli eval 'echo null === seo_geo_theme_active_preset_id() ? "null" : "unexpected";' 2>"${TMP_DIR}/corporate-preset-unsupported.stderr" | tr -d '\r\n')"; then
  CORPORATE_UNSUPPORTED_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-unsupported.stderr" | head -c 240)"
  fail_smoke "corporate-preset-allowlist-eval" "Could not evaluate unsupported preset allowlist" "null" "${CORPORATE_UNSUPPORTED_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_UNSUPPORTED" == "null" ]] \
  || fail_smoke "corporate-preset-allowlist-id" "Theme accepted an unsupported preset ID" "null" "$CORPORATE_UNSUPPORTED"

wp_cli eval 'delete_option( "seo_geo_active_preset" ); delete_option( "WPLANG" );' >/dev/null \
  || fail_smoke "corporate-preset-reset" "Could not reset Corporate preset fixture" "preset and WPLANG removed" "failed"

printf '[self-contained] Corporate preset activation OK.\n'
