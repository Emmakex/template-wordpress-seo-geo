#!/usr/bin/env bash
# Phase 7C Publisher preset acceptance.
#
# Sourced by self-contained-theme-smoke.sh so Publisher reuses the existing
# disposable WordPress/MariaDB fixture.

printf '[self-contained] Checking Publisher preset packaging and activation.\n'

for publisher_file in \
  "${BUILT_THEME}/presets/publisher/preset.json" \
  "${BUILT_THEME}/presets/publisher/content-map.json" \
  "${BUILT_THEME}/presets/publisher/patterns.json"; do
  [[ -f "$publisher_file" ]] \
    || fail_smoke "publisher-preset-package" "Built theme is missing a Publisher preset artifact" "file exists" "$publisher_file"
done

publisher_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/publisher-article-summary", "seo-geo-theme/publisher-key-facts", "seo-geo-theme/publisher-sources", "seo-geo-theme/publisher-related-content" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/publisher-patterns.stderr" | tr -d '\r\n'
}

corporate_pattern_state_for_publisher() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/publisher-corporate-patterns.stderr" | tr -d '\r\n'
}

local_business_pattern_state_for_publisher() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/local-business-nap", "seo-geo-theme/local-business-service-area", "seo-geo-theme/local-business-location" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/publisher-local-business-patterns.stderr" | tr -d '\r\n'
}

PUBLISHER_DEFAULT="$(publisher_pattern_state)"
[[ "$PUBLISHER_DEFAULT" == "0000" ]] \
  || fail_smoke "publisher-preset-default" "Publisher patterns registered before explicit preset activation" "0000" "$PUBLISHER_DEFAULT"

if ! PUBLISHER_SCHEMA_BEFORE="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/publisher-schema-before.stderr" | tr -d '\r\n')"; then
  PUBLISHER_SCHEMA_BEFORE_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-schema-before.stderr" | head -c 240)"
  fail_smoke "publisher-preset-schema-before" "Could not snapshot Schema identity before Publisher activation" "sha256 option snapshot" "${PUBLISHER_SCHEMA_BEFORE_ERROR:-wp eval failed}"
fi

wp_cli option update seo_geo_active_preset publisher >/dev/null \
  || fail_smoke "publisher-preset-option" "Could not activate Publisher preset" "option update succeeds" "failed"

PUBLISHER_ACTIVE="$(publisher_pattern_state)"
[[ "$PUBLISHER_ACTIVE" == "1111" ]] \
  || fail_smoke "publisher-preset-active" "Publisher preset did not register its complete pattern set" "1111" "$PUBLISHER_ACTIVE"

[[ "$(corporate_pattern_state_for_publisher)" == "000" ]] \
  || fail_smoke "publisher-preset-corporate-isolation" "Activating Publisher also registered Corporate-only patterns" "000" "$(corporate_pattern_state_for_publisher)"
[[ "$(local_business_pattern_state_for_publisher)" == "000" ]] \
  || fail_smoke "publisher-preset-local-isolation" "Activating Publisher also registered Local Business-only patterns" "000" "$(local_business_pattern_state_for_publisher)"

if ! PUBLISHER_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "publisher", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "site_type" => $manifest["site_type"] ?? null, "identity" => $manifest["schema"]["site_identity"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "article_source" => $manifest["schema"]["article_source"] ?? null, "author_source" => $manifest["schema"]["author_source"] ?? null, "dates_source" => $manifest["schema"]["dates_source"] ?? null, "auto_topics" => $manifest["editorial_model"]["auto_generate_topics"] ?? null, "infer_sources" => $manifest["editorial_model"]["infer_sources"] ?? null, "article_pages" => $manifest["editorial_model"]["article_schema_on_pages"] ?? null ) );' 2>"${TMP_DIR}/publisher-manifest.stderr" | tr -d '\r\n')"; then
  PUBLISHER_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-manifest.stderr" | head -c 240)"
  fail_smoke "publisher-preset-manifest-eval" "Could not resolve Publisher manifest through the theme registry" "manifest resolves" "${PUBLISHER_MANIFEST_ERROR:-wp eval failed}"
fi

PUBLISHER_MANIFEST_EXPECTED='{"id":"publisher","site_type":"publisher","identity":"organization","confirmation":true,"article_source":"wordpress-post","author_source":"wordpress-user","dates_source":"wordpress-post-dates","auto_topics":false,"infer_sources":false,"article_pages":false}'
[[ "$PUBLISHER_MANIFEST" == "$PUBLISHER_MANIFEST_EXPECTED" ]] \
  || fail_smoke "publisher-preset-manifest" "Publisher manifest did not preserve native editorial authority contract" "$PUBLISHER_MANIFEST_EXPECTED" "$PUBLISHER_MANIFEST"

if ! PUBLISHER_SCHEMA_AFTER="$(wp_cli eval 'echo hash( "sha256", serialize( get_option( \SeoGeo\Core\Schema\SchemaIdentityResolver::OPTION_NAME, null ) ) );' 2>"${TMP_DIR}/publisher-schema-after.stderr" | tr -d '\r\n')"; then
  PUBLISHER_SCHEMA_AFTER_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-schema-after.stderr" | head -c 240)"
  fail_smoke "publisher-preset-schema-after" "Could not snapshot Schema identity after Publisher activation" "sha256 option snapshot" "${PUBLISHER_SCHEMA_AFTER_ERROR:-wp eval failed}"
fi
[[ "$PUBLISHER_SCHEMA_AFTER" == "$PUBLISHER_SCHEMA_BEFORE" ]] \
  || fail_smoke "publisher-preset-schema-mutation" "Activating Publisher mutated the existing Schema identity configuration" "$PUBLISHER_SCHEMA_BEFORE" "$PUBLISHER_SCHEMA_AFTER"

if ! PUBLISHER_CATEGORY="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/publisher-article-summary" ); echo is_array( $pattern ) ? wp_json_encode( $pattern["categories"] ?? array() ) : "";' 2>"${TMP_DIR}/publisher-category.stderr" | tr -d '\r\n')"; then
  PUBLISHER_CATEGORY_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-category.stderr" | head -c 240)"
  fail_smoke "publisher-preset-category-eval" "Could not resolve Publisher pattern category" '["seo-geo-publisher"]' "${PUBLISHER_CATEGORY_ERROR:-wp eval failed}"
fi
[[ "$PUBLISHER_CATEGORY" == '["seo-geo-publisher"]' ]] \
  || fail_smoke "publisher-preset-category" "Publisher pattern registry used the wrong preset category" '["seo-geo-publisher"]' "$PUBLISHER_CATEGORY"

if ! PUBLISHER_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/publisher-article-summary" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/publisher-title-en.stderr" | tr -d '\r\n')"; then
  PUBLISHER_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-title-en.stderr" | head -c 240)"
  fail_smoke "publisher-preset-en-eval" "Could not resolve Publisher English pattern title" "Publisher article summary" "${PUBLISHER_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$PUBLISHER_TITLE_EN" == "Publisher article summary" ]] \
  || fail_smoke "publisher-preset-en" "Publisher English copy is not active in the default locale" "Publisher article summary" "$PUBLISHER_TITLE_EN"

if ! PUBLISHER_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/publisher-article-summary", "seo-geo-theme/publisher-key-facts", "seo-geo-theme/publisher-sources", "seo-geo-theme/publisher-related-content" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/publisher-article-summary" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/publisher-title-es.stderr" | tr -d '\r\n')"; then
  PUBLISHER_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/publisher-title-es.stderr" | head -c 240)"
  fail_smoke "publisher-preset-es-eval" "Could not resolve Publisher Spanish pattern title" "Resumen editorial del artículo" "${PUBLISHER_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$PUBLISHER_TITLE_ES" == "Resumen editorial del artículo" ]] \
  || fail_smoke "publisher-preset-es" "Publisher preset did not ship Spanish copy with English" "Resumen editorial del artículo" "$PUBLISHER_TITLE_ES"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "publisher-preset-corporate-option" "Could not switch fixture from Publisher to Corporate" "option update succeeds" "failed"
[[ "$(publisher_pattern_state)" == "0000" ]] \
  || fail_smoke "publisher-preset-switch-isolation" "Publisher patterns remained registered after switching to Corporate in a fresh request" "0000" "$(publisher_pattern_state)"
[[ "$(corporate_pattern_state_for_publisher)" == "111" ]] \
  || fail_smoke "publisher-preset-corporate-switch" "Corporate preset did not register after switching away from Publisher" "111" "$(corporate_pattern_state_for_publisher)"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "publisher-preset-unsupported-option" "Could not set unsupported preset probe" "option update succeeds" "failed"
[[ "$(publisher_pattern_state)" == "0000" ]] \
  || fail_smoke "publisher-preset-unsupported" "Unsupported preset registered Publisher patterns" "0000" "$(publisher_pattern_state)"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "publisher-preset-reset" "Could not reset Publisher preset fixture" "preset option removed" "failed"

printf '[self-contained] Publisher preset activation OK.\n'
