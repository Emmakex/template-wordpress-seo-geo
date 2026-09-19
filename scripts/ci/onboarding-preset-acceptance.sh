#!/usr/bin/env bash
# Phase 8A theme onboarding acceptance.
#
# Sourced by self-contained-theme-smoke.sh so onboarding reuses the existing
# disposable zero-plugin WordPress/MariaDB fixture.

printf '[self-contained] Checking Phase 8A onboarding preset screen.\n'

[[ -f "${BUILT_THEME}/inc/onboarding.php" ]] \
  || fail_smoke "onboarding-package" "Built theme is missing the onboarding module" "inc/onboarding.php exists" "missing"

if ! ONBOARDING_FUNCTIONS="$(wp_cli eval 'foreach ( array( "seo_geo_theme_onboarding_register_page", "seo_geo_theme_onboarding_render_page", "seo_geo_theme_onboarding_validate_preset", "seo_geo_theme_onboarding_save_preset", "seo_geo_theme_onboarding_copy" ) as $function ) { echo function_exists( $function ) ? "1" : "0"; }' 2>"${TMP_DIR}/onboarding-functions.stderr" | tr -d '\r\n')"; then
  ONBOARDING_FUNCTIONS_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-functions.stderr" | head -c 240)"
  fail_smoke "onboarding-functions-eval" "Could not inspect onboarding runtime functions" "11111" "${ONBOARDING_FUNCTIONS_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_FUNCTIONS" == "11111" ]] \
  || fail_smoke "onboarding-functions" "Built theme did not load the complete onboarding module" "11111" "$ONBOARDING_FUNCTIONS"

if ! ONBOARDING_VALIDATION="$(wp_cli eval '$values = array( "corporate", "local-business", "publisher", "ecommerce" ); foreach ( $values as $value ) { echo seo_geo_theme_onboarding_validate_preset( $value ) === $value ? "1" : "0"; } echo seo_geo_theme_onboarding_validate_preset( "" ) === "" ? "1" : "0"; echo null === seo_geo_theme_onboarding_validate_preset( "unsupported-preset" ) ? "1" : "0"; echo null === seo_geo_theme_onboarding_validate_preset( array( "corporate" ) ) ? "1" : "0";' 2>"${TMP_DIR}/onboarding-validation.stderr" | tr -d '\r\n')"; then
  ONBOARDING_VALIDATION_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-validation.stderr" | head -c 240)"
  fail_smoke "onboarding-validation-eval" "Could not execute onboarding preset validation" "1111111" "${ONBOARDING_VALIDATION_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_VALIDATION" == "1111111" ]] \
  || fail_smoke "onboarding-validation" "Onboarding preset validator did not preserve the allowlist/no-preset contract" "1111111" "$ONBOARDING_VALIDATION"

if ! ONBOARDING_COPY="$(wp_cli eval 'echo wp_json_encode( array( "en" => seo_geo_theme_onboarding_copy( "page_title", "en_US" ), "es" => seo_geo_theme_onboarding_copy( "page_title", "es_ES" ) ) );' 2>"${TMP_DIR}/onboarding-copy.stderr" | tr -d '\r\n')"; then
  ONBOARDING_COPY_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-copy.stderr" | head -c 240)"
  fail_smoke "onboarding-copy-eval" "Could not resolve onboarding EN/ES copy" '{"en":"SEO/GEO setup","es":"Configuración SEO/GEO"}' "${ONBOARDING_COPY_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_COPY" == '{"en":"SEO\/GEO setup","es":"Configuraci\u00f3n SEO\/GEO"}' || "$ONBOARDING_COPY" == '{"en":"SEO/GEO setup","es":"Configuración SEO/GEO"}' ]] \
  || fail_smoke "onboarding-copy" "Onboarding did not ship English and Spanish copy together" 'SEO/GEO setup + Configuración SEO/GEO' "$ONBOARDING_COPY"

if ! ONBOARDING_MENU="$(wp_cli eval 'wp_set_current_user( 1 ); do_action( "admin_menu" ); global $submenu; $found = false; foreach ( $submenu["themes.php"] ?? array() as $item ) { if ( isset( $item[2] ) && "seo-geo-setup" === $item[2] ) { $found = true; break; } } echo $found ? "registered" : "missing";' 2>"${TMP_DIR}/onboarding-menu.stderr" | tr -d '\r\n')"; then
  ONBOARDING_MENU_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-menu.stderr" | head -c 240)"
  fail_smoke "onboarding-menu-eval" "Could not inspect Appearance admin menu registration" "registered" "${ONBOARDING_MENU_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_MENU" == "registered" ]] \
  || fail_smoke "onboarding-menu" "Theme-owned onboarding screen was not registered under Appearance" "registered" "$ONBOARDING_MENU"

if ! ONBOARDING_DEFAULT="$(wp_cli eval 'delete_option( "seo_geo_active_preset" ); echo null === seo_geo_theme_active_preset_id() ? "none" : "unexpected";' 2>"${TMP_DIR}/onboarding-default.stderr" | tr -d '\r\n')"; then
  ONBOARDING_DEFAULT_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-default.stderr" | head -c 240)"
  fail_smoke "onboarding-default-eval" "Could not inspect onboarding default preset state" "none" "${ONBOARDING_DEFAULT_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_DEFAULT" == "none" ]] \
  || fail_smoke "onboarding-default" "Onboarding default installation selected a preset implicitly" "none" "$ONBOARDING_DEFAULT"

if ! ONBOARDING_RENDER="$(wp_cli eval 'wp_set_current_user( 1 ); update_option( "seo_geo_active_preset", "publisher" ); ob_start(); seo_geo_theme_onboarding_render_page(); $html = (string) ob_get_clean(); echo wp_json_encode( array( "form" => str_contains( $html, "admin-post.php" ), "action" => str_contains( $html, "seo_geo_save_preset" ), "nonce" => str_contains( $html, "seo_geo_onboarding_nonce" ), "publisher" => str_contains( $html, "Publisher" ), "baseline" => str_contains( $html, "zero required plugins" ) ) );' 2>"${TMP_DIR}/onboarding-render.stderr" | tr -d '\r\n')"; then
  ONBOARDING_RENDER_ERROR="$(tr -d '\r' <"${TMP_DIR}/onboarding-render.stderr" | head -c 240)"
  fail_smoke "onboarding-render-eval" "Could not render the onboarding admin screen" '{"form":true,"action":true,"nonce":true,"publisher":true,"baseline":true}' "${ONBOARDING_RENDER_ERROR:-wp eval failed}"
fi
[[ "$ONBOARDING_RENDER" == '{"form":true,"action":true,"nonce":true,"publisher":true,"baseline":true}' ]] \
  || fail_smoke "onboarding-render" "Onboarding admin screen is missing the secure preset form or effective status content" '{"form":true,"action":true,"nonce":true,"publisher":true,"baseline":true}' "$ONBOARDING_RENDER"

wp_cli eval 'delete_option( "seo_geo_active_preset" );' >/dev/null \
  || fail_smoke "onboarding-reset" "Could not reset onboarding preset fixture" "preset option removed" "failed"

printf '[self-contained] Phase 8A onboarding preset screen OK.\n'
