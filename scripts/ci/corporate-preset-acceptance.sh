#!/usr/bin/env bash
# Corporate Native preset acceptance.
#
# This file is sourced by self-contained-theme-smoke.sh and reuses its
# disposable WordPress fixture and structured fail_smoke diagnostic.

printf '[self-contained] Checking Corporate preset packaging and activation.\n'

for corporate_file in \
  "${BUILT_THEME}/inc/presets.php" \
  "${BUILT_THEME}/presets/corporate/preset.json" \
  "${BUILT_THEME}/presets/corporate/content-map.json" \
  "${BUILT_THEME}/presets/corporate/patterns.json" \
  "${BUILT_THEME}/templates/front-page.html" \
  "${BUILT_THEME}/assets/css/presets/corporate.css"; do
  [[ -f "$corporate_file" ]] \
    || fail_smoke "corporate-preset-package" "Built theme is missing a Corporate preset artifact" "file exists" "$corporate_file"
done

corporate_pattern_state() {
  wp_cli eval '$registry = WP_Block_Patterns_Registry::get_instance(); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials", "seo-geo-theme/corporate-native-hero", "seo-geo-theme/corporate-native-capabilities", "seo-geo-theme/corporate-native-proof", "seo-geo-theme/corporate-native-process", "seo-geo-theme/corporate-native-insights" ) as $slug ) { echo $registry->is_registered( $slug ) ? "1" : "0"; }' 2>"${TMP_DIR}/corporate-preset-patterns.stderr" | tr -d '\r\n'
}

CORPORATE_STATE_DEFAULT="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_DEFAULT" == "00000000" ]] \
  || fail_smoke "corporate-preset-default" "Corporate preset patterns registered before explicit preset activation" "00000000" "$CORPORATE_STATE_DEFAULT"

CORPORATE_VISUAL_DEFAULT="$(wp_cli eval 'do_action( "wp_enqueue_scripts" ); echo wp_style_is( "seo-geo-theme-preset-corporate", "enqueued" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-visual-default.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_VISUAL_DEFAULT" == "0" ]] \
  || fail_smoke "corporate-preset-visual-default" "Corporate visual system loaded before explicit preset activation" "0" "$CORPORATE_VISUAL_DEFAULT"

wp_cli option update seo_geo_active_preset corporate >/dev/null \
  || fail_smoke "corporate-preset-option" "Could not activate Corporate preset" "option update succeeds" "failed"

CORPORATE_STATE_ACTIVE="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_ACTIVE" == "11111111" ]] \
  || fail_smoke "corporate-preset-active" "Corporate preset did not register its complete pattern set after activation" "11111111" "$CORPORATE_STATE_ACTIVE"

CORPORATE_VISUAL_ACTIVE="$(wp_cli eval 'do_action( "wp_enqueue_scripts" ); echo wp_style_is( "seo-geo-theme-preset-corporate", "enqueued" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-visual-active.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_VISUAL_ACTIVE" == "1" ]] \
  || fail_smoke "corporate-preset-visual-active" "Corporate preset did not enqueue its isolated visual stylesheet" "1" "$CORPORATE_VISUAL_ACTIVE"

CORPORATE_BODY_CLASS="$(wp_cli eval '$classes = seo_geo_theme_preset_body_class( array() ); echo in_array( "seo-geo-preset-corporate", $classes, true ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-body-class.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_BODY_CLASS" == "1" ]] \
  || fail_smoke "corporate-preset-body-class" "Corporate preset did not expose its stable frontend body class" "1" "$CORPORATE_BODY_CLASS"

printf '[self-contained] Checking Corporate Native front-page semantic template.\n'
CORPORATE_FRONT_TEMPLATE="$(cat "${BUILT_THEME}/templates/front-page.html")"
[[ "$CORPORATE_FRONT_TEMPLATE" == *'"tagName":"main"'* ]] \
  || fail_smoke "corporate-front-main" "Corporate Native front page lost its main landmark" '"tagName":"main"' "$CORPORATE_FRONT_TEMPLATE"
[[ "$CORPORATE_FRONT_TEMPLATE" == *'wp:post-title {"level":1'* ]] \
  || fail_smoke "corporate-front-h1" "Corporate Native front page lost page-owned H1 authority" 'wp:post-title level 1' "$CORPORATE_FRONT_TEMPLATE"
[[ "$CORPORATE_FRONT_TEMPLATE" == *'wp:post-content'* ]] \
  || fail_smoke "corporate-front-content" "Corporate Native front page lost native post-content composition" 'wp:post-content' "$CORPORATE_FRONT_TEMPLATE"

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

CORPORATE_NAV_HTML="$(wp_cli eval '$runtime = seo_geo_theme_preset_navigation_runtime(); echo $runtime->render( array( "location" => "primary" ) );' 2>"${TMP_DIR}/corporate-preset-navigation.stderr")"
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

CORPORATE_NAV_BLOCK_REGISTERED="$(wp_cli eval '$registry = WP_Block_Type_Registry::get_instance(); echo $registry->is_registered( "seo-geo/preset-navigation" ) ? "1" : "0";' 2>"${TMP_DIR}/corporate-preset-nav-block.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_NAV_BLOCK_REGISTERED" == "1" ]] \
  || fail_smoke "corporate-nav-block" "Preset navigation dynamic block was not registered" "1" "$CORPORATE_NAV_BLOCK_REGISTERED"

printf '[self-contained] Checking Corporate SEO/GEO rendered-content semantics.\n'
CORPORATE_SEMANTIC_TOKEN='d276abfeceab40cca0e158fc6217176554b8e54a1f85b6eb004941797db52171'
CORPORATE_SEMANTIC_CONTENT='<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Legacy inner H1</h1><!-- /wp:heading --><!-- wp:paragraph --><p>Accuracy 100{'"$CORPORATE_SEMANTIC_TOKEN"'}</p><!-- /wp:paragraph -->'
CORPORATE_SEMANTIC_PAGE_ID="$(wp_cli post create --post_type=page --post_status=publish --post_title='Corporate Semantic Guard' --post_name='corporate-semantic-guard' --post_content="$CORPORATE_SEMANTIC_CONTENT" --porcelain 2>"${TMP_DIR}/corporate-semantic-create.stderr" | tr -d '\r\n')"
[[ "$CORPORATE_SEMANTIC_PAGE_ID" =~ ^[0-9]+$ ]] \
  || fail_smoke "corporate-semantic-page" "Could not create Corporate semantic guard fixture" "numeric page ID" "$CORPORATE_SEMANTIC_PAGE_ID"

CORPORATE_SEMANTIC_HTML="$(curl -fsS "$BASE_URL/corporate-semantic-guard/" 2>"${TMP_DIR}/corporate-semantic-http.stderr")" \
  || fail_smoke "corporate-semantic-http" "Could not fetch Corporate semantic guard page" "HTTP 200 HTML" "$(head -c 240 "${TMP_DIR}/corporate-semantic-http.stderr" 2>/dev/null || true)"

CORPORATE_H1_COUNT="$(printf '%s' "$CORPORATE_SEMANTIC_HTML" | grep -Eoi '<h1([[:space:]>])' | wc -l | tr -d ' ')"
[[ "$CORPORATE_H1_COUNT" == "1" ]] \
  || fail_smoke "corporate-single-h1" "Corporate singular HTML did not preserve exactly one document H1" "1" "$CORPORATE_H1_COUNT"

[[ "$CORPORATE_SEMANTIC_HTML" == *">Legacy inner H1</h2>"* ]] \
  || fail_smoke "corporate-content-h1-downgrade" "Legacy content H1 was not rendered as H2 under the page-owned H1" "Legacy inner H1 rendered as h2" "$CORPORATE_SEMANTIC_HTML"

[[ "$CORPORATE_SEMANTIC_HTML" == *"Accuracy 100%"* ]] \
  || fail_smoke "corporate-legacy-percent-visible" "Legacy Divi percentage placeholder was not normalized in public HTML" "Accuracy 100%" "$CORPORATE_SEMANTIC_HTML"

[[ "$CORPORATE_SEMANTIC_HTML" != *"$CORPORATE_SEMANTIC_TOKEN"* ]] \
  || fail_smoke "corporate-legacy-percent-token" "Legacy Divi percentage token leaked into public HTML" "token absent" "$CORPORATE_SEMANTIC_TOKEN"

CORPORATE_MARKDOWN="$(wp_cli eval '$post = get_post( '"$CORPORATE_SEMANTIC_PAGE_ID"' ); $resolver = \SeoGeo\Core\Runtime::markdown_alternates(); if ( ! $post instanceof WP_Post || null === $resolver ) { exit( 1 ); } $html_url = get_permalink( $post ); echo $resolver->render_markdown( array( "post" => $post, "html_url" => $html_url, "markdown_url" => $html_url . "index.md", "language" => null ) );' 2>"${TMP_DIR}/corporate-semantic-markdown.stderr")" \
  || fail_smoke "corporate-semantic-markdown" "Could not render Corporate GEO Markdown fixture" "Markdown output" "$(head -c 240 "${TMP_DIR}/corporate-semantic-markdown.stderr" 2>/dev/null || true)"

[[ "$CORPORATE_MARKDOWN" == *"Accuracy 100%"* ]] \
  || fail_smoke "corporate-markdown-percent-visible" "GEO Markdown did not receive normalized authored text" "Accuracy 100%" "$CORPORATE_MARKDOWN"

[[ "$CORPORATE_MARKDOWN" != *"$CORPORATE_SEMANTIC_TOKEN"* ]] \
  || fail_smoke "corporate-markdown-percent-token" "Legacy Divi percentage token leaked into GEO Markdown" "token absent" "$CORPORATE_SEMANTIC_TOKEN"

if ! CORPORATE_MANIFEST="$(wp_cli eval '$manifest = seo_geo_theme_preset_document( "corporate", "preset.json" ); if ( ! is_array( $manifest ) ) { exit( 1 ); } echo wp_json_encode( array( "id" => $manifest["id"] ?? null, "site_type" => $manifest["site_type"] ?? null, "confirmation" => $manifest["schema"]["requires_confirmation"] ?? null, "visual" => $manifest["visual"]["design_system"] ?? null ) );' 2>"${TMP_DIR}/corporate-preset-manifest.stderr" | tr -d '\r\n')"; then
  CORPORATE_MANIFEST_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-manifest.stderr" | head -c 240)"
  fail_smoke "corporate-preset-manifest-eval" "Could not resolve Corporate preset manifest through the theme registry" "manifest resolves" "${CORPORATE_MANIFEST_ERROR:-wp eval failed}" "wp eval seo_geo_theme_preset_document"
fi
[[ "$CORPORATE_MANIFEST" == '{"id":"corporate","site_type":"corporate","confirmation":true,"visual":"corporate-native-v1"}' ]] \
  || fail_smoke "corporate-preset-manifest" "Corporate preset manifest did not preserve its identity/confirmation/visual contract" '{"id":"corporate","site_type":"corporate","confirmation":true,"visual":"corporate-native-v1"}' "$CORPORATE_MANIFEST"

if ! CORPORATE_TITLE_EN="$(wp_cli eval '$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-en.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_EN_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-en.stderr" | head -c 240)"
  fail_smoke "corporate-preset-en-eval" "Could not resolve Corporate English pattern title" "Corporate verified metrics" "${CORPORATE_TITLE_EN_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_EN" == "Corporate verified metrics" ]] \
  || fail_smoke "corporate-preset-en" "Corporate preset English copy is not active in the default locale" "Corporate verified metrics" "$CORPORATE_TITLE_EN"

if ! CORPORATE_TITLE_ES="$(wp_cli eval 'add_filter( "locale", static fn( $locale ) => "es_ES", PHP_INT_MAX ); switch_to_locale( "es_ES" ); foreach ( array( "seo-geo-theme/corporate-case-study", "seo-geo-theme/corporate-stats", "seo-geo-theme/corporate-testimonials", "seo-geo-theme/corporate-native-hero", "seo-geo-theme/corporate-native-capabilities", "seo-geo-theme/corporate-native-proof", "seo-geo-theme/corporate-native-process", "seo-geo-theme/corporate-native-insights" ) as $slug ) { unregister_block_pattern( $slug ); } seo_geo_theme_register_active_preset_patterns(); $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "seo-geo-theme/corporate-stats" ); echo is_array( $pattern ) ? (string) ( $pattern["title"] ?? "" ) : "";' 2>"${TMP_DIR}/corporate-preset-title-es.stderr" | tr -d '\r\n')"; then
  CORPORATE_TITLE_ES_ERROR="$(tr -d '\r' <"${TMP_DIR}/corporate-preset-title-es.stderr" | head -c 240)"
  fail_smoke "corporate-preset-es-eval" "Could not resolve Corporate Spanish pattern title" "Métricas corporativas verificadas" "${CORPORATE_TITLE_ES_ERROR:-wp eval failed}"
fi
[[ "$CORPORATE_TITLE_ES" == "Métricas corporativas verificadas" ]] \
  || fail_smoke "corporate-preset-es" "Corporate preset did not ship Spanish copy with English" "Métricas corporativas verificadas" "$CORPORATE_TITLE_ES"

wp_cli option update seo_geo_active_preset unsupported-preset >/dev/null \
  || fail_smoke "corporate-preset-invalid-option" "Could not set unsupported preset probe" "option update succeeds" "failed"

CORPORATE_STATE_UNSUPPORTED="$(corporate_pattern_state)"
[[ "$CORPORATE_STATE_UNSUPPORTED" == "00000000" ]] \
  || fail_smoke "corporate-preset-allowlist" "Unsupported preset ID activated Corporate pattern behavior" "00000000" "$CORPORATE_STATE_UNSUPPORTED"

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
