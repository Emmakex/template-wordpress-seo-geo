#!/usr/bin/env bash
# Phase 7E SaaS / Digital Product preset acceptance.
#
# Sourced by self-contained-theme-smoke.sh so 7E reuses the existing
# disposable zero-plugin WordPress/MariaDB fixture.

printf '[self-contained] Checking SaaS / Digital Product preset packaging and activation.\n'

for saas_file in \
  "${BUILT_THEME}/presets/saas-digital-product/preset.json" \
  "${BUILT_THEME}/presets/saas-digital-product/content-map.json" \
  "${BUILT_THEME}/presets/saas-digital-product/patterns.json"; do
  [[ -f "$saas_file" ]] \
    || fail_smoke "saas-preset-package" "Built theme is missing a SaaS / Digital Product preset artifact" "file exists" "$saas_file"
done

saas_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/saas-product-overview", "seo-geo-theme/saas-use-cases", "seo-geo-theme/saas-integrations", "seo-geo-theme/saas-pricing-plans", "seo-geo-theme/saas-comparison-framework" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/saas-patterns.stderr" | tr -d '\r\n'
}

prior_preset_pattern_state_for_saas() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials", "seo-geo-theme/local-business-nap", "seo-geo-theme/local-business-service-area", "seo-geo-theme/local-business-location", "seo-geo-theme/publisher-article-summary", "seo-geo-theme/publisher-key-facts", "seo-geo-theme/publisher-sources", "seo-geo-theme/publisher-related-content", "seo-geo-theme/ecommerce-category-guide", "seo-geo-theme/ecommerce-buying-guide", "seo-geo-theme/ecommerce-policy-navigation", "seo-geo-theme/ecommerce-brand-editorial" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/saas-prior-patterns.stderr" | tr -d '\r\n'
}

SAAS_DEFAULT="$(saas_pattern_state)"
[[ "$SAAS_DEFAULT" == "00000" ]] \
  || fail_smoke "saas-preset-default" "SaaS patterns registered before explicit preset activation" "00000" "$SAAS_DEFAULT"

if ! SAAS_SCHEMA_BEFORE="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/saas-schema-before.stderr" | tr -d '\r\n')"; then
  SAAS_SCHEMA_BEFORE_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-schema-before.stderr" | head -c 240)"
  fail_smoke "saas-preset-schema-before" "Could not snapshot Schema identity before SaaS activation" "sha256 option snapshot" "${SAAS_SCHEMA_BEFORE_ERROR:-wp eval failed}"
fi

wp_cli option update seo_geo_active_preset saas-digital-product >/dev/null \
  || fail_smoke "saas-preset-option" "Could not activate SaaS / Digital Product preset" "option update succeeds" "failed"

SAAS_ACTIVE="$(saas_pattern_state)"
[[ "$SAAS_ACTIVE" == "11111" ]] \
  || fail_smoke "saas-preset-active" "SaaS preset did not register its complete pattern set" "11111" "$SAAS_ACTIVE"

PRIOR_WHILE_SAAS="$(prior_preset_pattern_state_for_saas)"
[[ "$PRIOR_WHILE_SAAS" == "00000000000000" ]] \
  || fail_smoke "saas-preset-isolation" "Activating SaaS also registered patterns from another preset" "00000000000000" "$PRIOR_WHILE_SAAS"

if ! SAAS_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "saas-digital-product", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "identity" => $manifest["schema"]["site_identity"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "product_entity" => $manifest["schema"]["optional_product_entity"] ?? null, "product_status" => $manifest["schema"]["software_application_status"] ?? null, "theme_product_schema" => $manifest["schema"]["theme_emits_software_application"] ?? null, "explicit_contract" => $manifest["schema"]["requires_explicit_supported_contract"] ?? null, "zero_plugin" => $manifest["product_model"]["zero_plugin_preset_safe"] ?? null, "external_backend" => $manifest["product_model"]["product_backend_external"] ?? null, "credentials" => $manifest["product_model"]["credentials_never_created_by_preset"] ?? null, "auto_pricing" => $manifest["product_model"]["auto_generate_pricing"] ?? null, "auto_comparisons" => $manifest["product_model"]["auto_generate_comparison_pages"] ?? null ) );' 2>"${TMP_DIR}/saas-manifest.stderr" | tr -d '\r\n')"; then
  SAAS_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-manifest.stderr" | head -c 240)"
  fail_smoke "saas-preset-manifest-eval" "Could not resolve SaaS manifest through the theme registry" "manifest resolves" "${SAAS_MANIFEST_ERROR:-wp eval failed}"
fi

SAAS_MANIFEST_EXPECTED='{"id":"saas-digital-product","identity":"organization","confirmation":true,"product_entity":"SoftwareApplication","product_status":"recommendation-only","theme_product_schema":false,"explicit_contract":true,"zero_plugin":true,"external_backend":true,"credentials":true,"auto_pricing":false,"auto_comparisons":false}'
[[ "$SAAS_MANIFEST" == "$SAAS_MANIFEST_EXPECTED" ]] \
  || fail_smoke "saas-preset-manifest" "SaaS manifest did not preserve product/provider and anti-inference boundaries" "$SAAS_MANIFEST_EXPECTED" "$SAAS_MANIFEST"

if ! SAAS_SCHEMA_AFTER="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/saas-schema-after.stderr" | tr -d '\r\n')"; then
  SAAS_SCHEMA_AFTER_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-schema-after.stderr" | head -c 240)"
  fail_smoke "saas-preset-schema-after" "Could not snapshot Schema identity after SaaS activation" "sha256 option snapshot" "${SAAS_SCHEMA_AFTER_ERROR:-wp eval failed}"
fi
[[ "$SAAS_SCHEMA_AFTER" == "$SAAS_SCHEMA_BEFORE" ]] \
  || fail_smoke "saas-preset-schema-mutation" "Activating SaaS mutated the existing Schema identity configuration" "$SAAS_SCHEMA_BEFORE" "$SAAS_SCHEMA_AFTER"

if ! SAAS_CATEGORY="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/saas-product-overview" ); echo is_array( $pattern ) ? wp_json_encode( $pattern["categories"] ?? array() ) : "";' 2>"${TMP_DIR}/saas-category.stderr" | tr -d '\r\n')"; then
  SAAS_CATEGORY_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-category.stderr" | head -c 240)"
  fail_smoke "saas-preset-category-eval" "Could not resolve SaaS pattern category" '["seo-geo-saas"]' "${SAAS_CATEGORY_ERROR:-wp eval failed}"
fi
[[ "$SAAS_CATEGORY" == '["seo-geo-saas"]' ]] \
  || fail_smoke "saas-preset-category" "SaaS pattern registry used the wrong preset category" '["seo-geo-saas"]' "$SAAS_CATEGORY"

if ! SAAS_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/saas-product-overview" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/saas-title-en.stderr" | tr -d '\r\n')"; then
  SAAS_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-title-en.stderr" | head -c 240)"
  fail_smoke "saas-preset-en-eval" "Could not resolve SaaS English pattern title" "SaaS product overview" "${SAAS_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$SAAS_TITLE_EN" == "SaaS product overview" ]] \
  || fail_smoke "saas-preset-en" "SaaS English copy is not active in the default locale" "SaaS product overview" "$SAAS_TITLE_EN"

if ! SAAS_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/saas-product-overview", "seo-geo-theme/saas-use-cases", "seo-geo-theme/saas-integrations", "seo-geo-theme/saas-pricing-plans", "seo-geo-theme/saas-comparison-framework" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/saas-product-overview" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/saas-title-es.stderr" | tr -d '\r\n')"; then
  SAAS_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/saas-title-es.stderr" | head -c 240)"
  fail_smoke "saas-preset-es-eval" "Could not resolve SaaS Spanish pattern title" "Resumen de producto SaaS" "${SAAS_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$SAAS_TITLE_ES" == "Resumen de producto SaaS" ]] \
  || fail_smoke "saas-preset-es" "SaaS preset did not ship Spanish copy with English" "Resumen de producto SaaS" "$SAAS_TITLE_ES"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "saas-preset-corporate-option" "Could not switch fixture from SaaS to Corporate" "option update succeeds" "failed"
[[ "$(saas_pattern_state)" == "00000" ]] \
  || fail_smoke "saas-preset-switch-isolation" "SaaS patterns remained registered after switching to Corporate in a fresh request" "00000" "$(saas_pattern_state)"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "saas-preset-unsupported-option" "Could not set unsupported preset probe" "option update succeeds" "failed"
[[ "$(saas_pattern_state)" == "00000" ]] \
  || fail_smoke "saas-preset-unsupported" "Unsupported preset registered SaaS patterns" "00000" "$(saas_pattern_state)"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "saas-preset-reset" "Could not reset SaaS preset fixture" "preset option removed" "failed"

printf '[self-contained] SaaS / Digital Product preset activation OK with zero external product dependencies.\n'
