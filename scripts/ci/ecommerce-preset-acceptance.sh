#!/usr/bin/env bash
# Phase 7D Ecommerce preset acceptance.
#
# Sourced by self-contained-theme-smoke.sh so Ecommerce reuses the existing
# disposable zero-plugin WordPress/MariaDB fixture.

printf '[self-contained] Checking Ecommerce preset packaging and activation.\n'

for ecommerce_file in \
  "${BUILT_THEME}/presets/ecommerce/preset.json" \
  "${BUILT_THEME}/presets/ecommerce/content-map.json" \
  "${BUILT_THEME}/presets/ecommerce/patterns.json"; do
  [[ -f "$ecommerce_file" ]] \
    || fail_smoke "ecommerce-preset-package" "Built theme is missing an Ecommerce preset artifact" "file exists" "$ecommerce_file"
done

ecommerce_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/ecommerce-category-guide", "seo-geo-theme/ecommerce-buying-guide", "seo-geo-theme/ecommerce-policy-navigation", "seo-geo-theme/ecommerce-brand-editorial" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/ecommerce-patterns.stderr" | tr -d '\r\n'
}

prior_preset_pattern_state_for_ecommerce() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials", "seo-geo-theme/local-business-nap", "seo-geo-theme/local-business-service-area", "seo-geo-theme/local-business-location", "seo-geo-theme/publisher-article-summary", "seo-geo-theme/publisher-key-facts", "seo-geo-theme/publisher-sources", "seo-geo-theme/publisher-related-content" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/ecommerce-prior-patterns.stderr" | tr -d '\r\n'
}

ECOMMERCE_DEFAULT="$(ecommerce_pattern_state)"
[[ "$ECOMMERCE_DEFAULT" == "0000" ]] \
  || fail_smoke "ecommerce-preset-default" "Ecommerce patterns registered before explicit preset activation" "0000" "$ECOMMERCE_DEFAULT"

if ! ECOMMERCE_PROVIDER_STATE="$(wp_cli eval 'echo class_exists( "WooCommerce" ) ? "present" : "absent";' 2>"${TMP_DIR}/ecommerce-provider.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_PROVIDER_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-provider.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-provider-eval" "Could not inspect WooCommerce provider state" "absent in zero-plugin fixture" "${ECOMMERCE_PROVIDER_ERROR:-wp eval failed}"
fi
[[ "$ECOMMERCE_PROVIDER_STATE" == "absent" ]] \
  || fail_smoke "ecommerce-preset-zero-plugin-provider" "Zero-plugin fixture unexpectedly contains WooCommerce" "absent" "$ECOMMERCE_PROVIDER_STATE"

if ! ECOMMERCE_SCHEMA_BEFORE="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/ecommerce-schema-before.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_SCHEMA_BEFORE_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-schema-before.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-schema-before" "Could not snapshot Schema identity before Ecommerce activation" "sha256 option snapshot" "${ECOMMERCE_SCHEMA_BEFORE_ERROR:-wp eval failed}"
fi

wp_cli option update seo_geo_active_preset ecommerce >/dev/null \
  || fail_smoke "ecommerce-preset-option" "Could not activate Ecommerce preset" "option update succeeds" "failed"

ECOMMERCE_ACTIVE="$(ecommerce_pattern_state)"
[[ "$ECOMMERCE_ACTIVE" == "1111" ]] \
  || fail_smoke "ecommerce-preset-active" "Ecommerce preset did not register its complete pattern set without WooCommerce" "1111" "$ECOMMERCE_ACTIVE"

PRIOR_WHILE_ECOMMERCE="$(prior_preset_pattern_state_for_ecommerce)"
[[ "$PRIOR_WHILE_ECOMMERCE" == "0000000000" ]] \
  || fail_smoke "ecommerce-preset-isolation" "Activating Ecommerce also registered patterns from another preset" "0000000000" "$PRIOR_WHILE_ECOMMERCE"

if ! ECOMMERCE_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "ecommerce", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "identity" => $manifest["schema"]["site_identity"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "schema_owner" => $manifest["schema"]["product_schema_owner"] ?? null, "theme_schema" => $manifest["schema"]["theme_emits_product_schema"] ?? null, "provider" => $manifest["commerce_model"]["preferred_provider"] ?? null, "status" => $manifest["commerce_model"]["integration_status"] ?? null, "adapter" => $manifest["commerce_model"]["requires_supported_adapter_for_live_commerce"] ?? null, "zero_plugin" => $manifest["commerce_model"]["zero_plugin_preset_safe"] ?? null, "product_facts" => $manifest["commerce_model"]["provider_owns_product_facts"] ?? null, "facets" => $manifest["commerce_model"]["auto_index_facets"] ?? null, "commerce_i18n" => $manifest["commerce_model"]["provider_owns_multilingual_routing"] ?? null ) );' 2>"${TMP_DIR}/ecommerce-manifest.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-manifest.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-manifest-eval" "Could not resolve Ecommerce manifest through the theme registry" "manifest resolves" "${ECOMMERCE_MANIFEST_ERROR:-wp eval failed}"
fi

ECOMMERCE_MANIFEST_EXPECTED='{"id":"ecommerce","identity":"organization","confirmation":true,"schema_owner":"commerce-provider","theme_schema":false,"provider":"woocommerce","status":"preferred-provider-contract-only","adapter":true,"zero_plugin":true,"product_facts":true,"facets":false,"commerce_i18n":true}'
[[ "$ECOMMERCE_MANIFEST" == "$ECOMMERCE_MANIFEST_EXPECTED" ]] \
  || fail_smoke "ecommerce-preset-manifest" "Ecommerce manifest did not preserve provider ownership/zero-plugin contract" "$ECOMMERCE_MANIFEST_EXPECTED" "$ECOMMERCE_MANIFEST"

if ! ECOMMERCE_SCHEMA_AFTER="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/ecommerce-schema-after.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_SCHEMA_AFTER_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-schema-after.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-schema-after" "Could not snapshot Schema identity after Ecommerce activation" "sha256 option snapshot" "${ECOMMERCE_SCHEMA_AFTER_ERROR:-wp eval failed}"
fi
[[ "$ECOMMERCE_SCHEMA_AFTER" == "$ECOMMERCE_SCHEMA_BEFORE" ]] \
  || fail_smoke "ecommerce-preset-schema-mutation" "Activating Ecommerce mutated the existing Schema identity configuration" "$ECOMMERCE_SCHEMA_BEFORE" "$ECOMMERCE_SCHEMA_AFTER"

if ! ECOMMERCE_CATEGORY="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/ecommerce-category-guide" ); echo is_array( $pattern ) ? wp_json_encode( $pattern["categories"] ?? array() ) : "";' 2>"${TMP_DIR}/ecommerce-category.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_CATEGORY_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-category.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-category-eval" "Could not resolve Ecommerce pattern category" '["seo-geo-ecommerce"]' "${ECOMMERCE_CATEGORY_ERROR:-wp eval failed}"
fi
[[ "$ECOMMERCE_CATEGORY" == '["seo-geo-ecommerce"]' ]] \
  || fail_smoke "ecommerce-preset-category" "Ecommerce pattern registry used the wrong preset category" '["seo-geo-ecommerce"]' "$ECOMMERCE_CATEGORY"

if ! ECOMMERCE_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/ecommerce-category-guide" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/ecommerce-title-en.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-title-en.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-en-eval" "Could not resolve Ecommerce English pattern title" "Ecommerce category guide" "${ECOMMERCE_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$ECOMMERCE_TITLE_EN" == "Ecommerce category guide" ]] \
  || fail_smoke "ecommerce-preset-en" "Ecommerce English copy is not active in the default locale" "Ecommerce category guide" "$ECOMMERCE_TITLE_EN"

if ! ECOMMERCE_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/ecommerce-category-guide", "seo-geo-theme/ecommerce-buying-guide", "seo-geo-theme/ecommerce-policy-navigation", "seo-geo-theme/ecommerce-brand-editorial" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/ecommerce-category-guide" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/ecommerce-title-es.stderr" | tr -d '\r\n')"; then
  ECOMMERCE_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/ecommerce-title-es.stderr" | head -c 240)"
  fail_smoke "ecommerce-preset-es-eval" "Could not resolve Ecommerce Spanish pattern title" "Guía editorial de categoría Ecommerce" "${ECOMMERCE_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$ECOMMERCE_TITLE_ES" == "Guía editorial de categoría Ecommerce" ]] \
  || fail_smoke "ecommerce-preset-es" "Ecommerce preset did not ship Spanish copy with English" "Guía editorial de categoría Ecommerce" "$ECOMMERCE_TITLE_ES"

wp_cli option update seo_geo_active_preset publisher >/dev/null \
  || fail_smoke "ecommerce-preset-publisher-option" "Could not switch fixture from Ecommerce to Publisher" "option update succeeds" "failed"
[[ "$(ecommerce_pattern_state)" == "0000" ]] \
  || fail_smoke "ecommerce-preset-switch-isolation" "Ecommerce patterns remained registered after switching to Publisher in a fresh request" "0000" "$(ecommerce_pattern_state)"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "ecommerce-preset-unsupported-option" "Could not set unsupported preset probe" "option update succeeds" "failed"
[[ "$(ecommerce_pattern_state)" == "0000" ]] \
  || fail_smoke "ecommerce-preset-unsupported" "Unsupported preset registered Ecommerce patterns" "0000" "$(ecommerce_pattern_state)"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "ecommerce-preset-reset" "Could not reset Ecommerce preset fixture" "preset option removed" "failed"

printf '[self-contained] Ecommerce preset activation OK without WooCommerce.\n'
