#!/usr/bin/env bash
# Phase 7B Local Business preset acceptance.
#
# Sourced by self-contained-theme-smoke.sh to reuse the existing disposable
# WordPress/MariaDB fixture and structured fail_smoke diagnostics.

printf '[self-contained] Checking Local Business preset packaging and activation.\n'

for local_business_file in \
  "${BUILT_THEME}/presets/local-business/preset.json" \
  "${BUILT_THEME}/presets/local-business/content-map.json" \
  "${BUILT_THEME}/presets/local-business/patterns.json"; do
  [[ -f "$local_business_file" ]] \
    || fail_smoke "local-business-preset-package" "Built theme is missing a Local Business preset artifact" "file exists" "$local_business_file"
done

local_business_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/local-business-nap", "seo-geo-theme/local-business-service-area", "seo-geo-theme/local-business-location" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/local-business-patterns.stderr" | tr -d '\r\n'
}

corporate_pattern_state_for_local_business() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/local-business-corporate-patterns.stderr" | tr -d '\r\n'
}

LOCAL_BUSINESS_DEFAULT="$(local_business_pattern_state)"
[[ "$LOCAL_BUSINESS_DEFAULT" == "000" ]] \
  || fail_smoke "local-business-preset-default" "Local Business patterns registered before explicit preset activation" "000" "$LOCAL_BUSINESS_DEFAULT"

wp_cli option update seo_geo_active_preset local-business >/dev/null \
  || fail_smoke "local-business-preset-option" "Could not activate Local Business preset" "option update succeeds" "failed"

LOCAL_BUSINESS_ACTIVE="$(local_business_pattern_state)"
[[ "$LOCAL_BUSINESS_ACTIVE" == "111" ]] \
  || fail_smoke "local-business-preset-active" "Local Business preset did not register its complete pattern set" "111" "$LOCAL_BUSINESS_ACTIVE"

CORPORATE_WHILE_LOCAL="$(corporate_pattern_state_for_local_business)"
[[ "$CORPORATE_WHILE_LOCAL" == "000" ]] \
  || fail_smoke "local-business-preset-isolation" "Activating Local Business also registered Corporate-only patterns" "000" "$CORPORATE_WHILE_LOCAL"

if ! LOCAL_BUSINESS_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "local-business", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "site_type" => $manifest["site_type"] ?? null, "identity" => $manifest["schema"]["site_identity"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "multi" => $manifest["location_model"]["supports_multiple_locations"] ?? null, "auto_city" => $manifest["location_model"]["auto_generate_city_pages"] ?? null ) );' 2>"${TMP_DIR}/local-business-manifest.stderr" | tr -d '\r\n')"; then
  LOCAL_BUSINESS_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/local-business-manifest.stderr" | head -c 240)"
  fail_smoke "local-business-preset-manifest-eval" "Could not resolve Local Business manifest through the theme registry" "manifest resolves" "${LOCAL_BUSINESS_MANIFEST_ERROR:-wp eval failed}"
fi
[[ "$LOCAL_BUSINESS_MANIFEST" == '{"id":"local-business","site_type":"local-business","identity":"local_business","confirmation":true,"multi":true,"auto_city":false}' ]] \
  || fail_smoke "local-business-preset-manifest" "Local Business manifest did not preserve identity/multi-location/anti-doorway contract" '{"id":"local-business","site_type":"local-business","identity":"local_business","confirmation":true,"multi":true,"auto_city":false}' "$LOCAL_BUSINESS_MANIFEST"

if ! LOCAL_BUSINESS_SCHEMA_STATE="$(wp_cli eval '$configuration = get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ); echo is_array( $configuration ) && ( $configuration["site_entity_type"] ?? null ) === "local_business" ? "auto-enabled" : "not-auto-enabled";' 2>"${TMP_DIR}/local-business-schema-state.stderr" | tr -d '\r\n')"; then
  LOCAL_BUSINESS_SCHEMA_ERROR="$(tr -d '\r' <"${TMP_DIR}/local-business-schema-state.stderr" | head -c 240)"
  fail_smoke "local-business-preset-schema-eval" "Could not inspect LocalBusiness Schema identity after preset activation" "not-auto-enabled" "${LOCAL_BUSINESS_SCHEMA_ERROR:-wp eval failed}"
fi
[[ "$LOCAL_BUSINESS_SCHEMA_STATE" == "not-auto-enabled" ]] \
  || fail_smoke "local-business-preset-schema-confirmation" "Activating the Local Business preset auto-enabled LocalBusiness Schema identity" "not-auto-enabled" "$LOCAL_BUSINESS_SCHEMA_STATE"

if ! LOCAL_BUSINESS_CATEGORY="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/local-business-nap" ); echo is_array( $pattern ) ? wp_json_encode( $pattern["categories"] ?? array() ) : "";' 2>"${TMP_DIR}/local-business-category.stderr" | tr -d '\r\n')"; then
  LOCAL_BUSINESS_CATEGORY_ERROR="$(tr -d '\r' <"${TMP_DIR}/local-business-category.stderr" | head -c 240)"
  fail_smoke "local-business-preset-category-eval" "Could not resolve Local Business pattern category" '["seo-geo-local-business"]' "${LOCAL_BUSINESS_CATEGORY_ERROR:-wp eval failed}"
fi
[[ "$LOCAL_BUSINESS_CATEGORY" == '["seo-geo-local-business"]' ]] \
  || fail_smoke "local-business-preset-category" "Local Business pattern registry reused the wrong preset category" '["seo-geo-local-business"]' "$LOCAL_BUSINESS_CATEGORY"

if ! LOCAL_BUSINESS_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/local-business-nap" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/local-business-title-en.stderr" | tr -d '\r\n')"; then
  LOCAL_BUSINESS_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/local-business-title-en.stderr" | head -c 240)"
  fail_smoke "local-business-preset-en-eval" "Could not resolve Local Business English pattern title" "Local Business visible NAP details" "${LOCAL_BUSINESS_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$LOCAL_BUSINESS_TITLE_EN" == "Local Business visible NAP details" ]] \
  || fail_smoke "local-business-preset-en" "Local Business English copy is not active in the default locale" "Local Business visible NAP details" "$LOCAL_BUSINESS_TITLE_EN"

if ! LOCAL_BUSINESS_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/local-business-nap", "seo-geo-theme/local-business-service-area", "seo-geo-theme/local-business-location" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/local-business-nap" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/local-business-title-es.stderr" | tr -d '\r\n')"; then
  LOCAL_BUSINESS_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/local-business-title-es.stderr" | head -c 240)"
  fail_smoke "local-business-preset-es-eval" "Could not resolve Local Business Spanish pattern title" "Datos NAP visibles del negocio local" "${LOCAL_BUSINESS_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$LOCAL_BUSINESS_TITLE_ES" == "Datos NAP visibles del negocio local" ]] \
  || fail_smoke "local-business-preset-es" "Local Business preset did not ship Spanish copy with English" "Datos NAP visibles del negocio local" "$LOCAL_BUSINESS_TITLE_ES"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "local-business-preset-corporate-option" "Could not switch fixture from Local Business to Corporate" "option update succeeds" "failed"

LOCAL_BUSINESS_WHILE_CORPORATE="$(local_business_pattern_state)"
[[ "$LOCAL_BUSINESS_WHILE_CORPORATE" == "000" ]] \
  || fail_smoke "local-business-preset-switch-isolation" "Local Business patterns remained registered after switching to Corporate in a fresh request" "000" "$LOCAL_BUSINESS_WHILE_CORPORATE"

CORPORATE_AFTER_SWITCH="$(corporate_pattern_state_for_local_business)"
[[ "$CORPORATE_AFTER_SWITCH" == "111" ]] \
  || fail_smoke "local-business-preset-corporate-switch" "Corporate preset did not register after switching away from Local Business" "111" "$CORPORATE_AFTER_SWITCH"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "local-business-preset-unsupported-option" "Could not set unsupported preset probe" "option update succeeds" "failed"

[[ "$(local_business_pattern_state)" == "000" ]] \
  || fail_smoke "local-business-preset-unsupported-local" "Unsupported preset registered Local Business patterns" "000" "$(local_business_pattern_state)"
[[ "$(corporate_pattern_state_for_local_business)" == "000" ]] \
  || fail_smoke "local-business-preset-unsupported-corporate" "Unsupported preset registered Corporate patterns" "000" "$(corporate_pattern_state_for_local_business)"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "local-business-preset-reset" "Could not reset Local Business preset fixture" "preset option removed" "failed"

printf '[self-contained] Local Business preset activation OK.\n'
