#!/usr/bin/env bash
# Phase 8A Site Analyzer acceptance.
#
# Sourced by wordpress-smoke.sh so the temporary Migration Bridge reuses the
# existing disposable WordPress/MariaDB fixture instead of creating a new stack.

printf '[smoke] Checking Phase 8A read-only Site Analyzer.\n'

FIXTURE_ROOT="${TMP_DIR}/migration-bridge-fixtures"
mkdir -p \
  "${FIXTURE_ROOT}/elementor" \
  "${FIXTURE_ROOT}/woocommerce" \
  "${FIXTURE_ROOT}/wordpress-seo" \
  "${FIXTURE_ROOT}/divi-builder" \
  "${FIXTURE_ROOT}/legacy-site-fixture" \
  "${FIXTURE_ROOT}/legacy-child"

cat >"${FIXTURE_ROOT}/elementor/elementor.php" <<'PHP'
<?php
/**
 * Plugin Name: Elementor Fixture
 * Version: 9.9.9
 */
PHP

cat >"${FIXTURE_ROOT}/woocommerce/woocommerce.php" <<'PHP'
<?php
/**
 * Plugin Name: WooCommerce Fixture
 * Version: 9.9.9
 */
PHP

cat >"${FIXTURE_ROOT}/wordpress-seo/wp-seo.php" <<'PHP'
<?php
/**
 * Plugin Name: Yoast SEO Fixture
 * Version: 9.9.9
 */
PHP

cat >"${FIXTURE_ROOT}/divi-builder/divi-builder.php" <<'PHP'
<?php
/**
 * Plugin Name: Divi Builder Fixture
 * Version: 9.9.9
 */
PHP

cat >"${FIXTURE_ROOT}/legacy-site-fixture/legacy-site-fixture.php" <<'PHP'
<?php
/**
 * Plugin Name: Legacy Site Fixture
 * Version: 1.0.0
 */
add_action(
	'init',
	static function (): void {
		register_post_type(
			'legacy_case',
			array(
				'public'  => true,
				'show_ui' => true,
				'label'   => 'Legacy Cases',
			)
		);
		register_taxonomy(
			'legacy_topic',
			array( 'legacy_case' ),
			array(
				'public'  => true,
				'show_ui' => true,
				'label'   => 'Legacy Topics',
			)
		);
		add_shortcode(
			'legacy_cta',
			static function (): string {
				return 'fixture';
			}
		);
	}
);
PHP

cat >"${FIXTURE_ROOT}/legacy-child/style.css" <<'CSS'
/*
Theme Name: Legacy Child Fixture
Template: seo-geo-theme
Version: 1.0.0
*/
CSS

for plugin_dir in elementor woocommerce wordpress-seo divi-builder legacy-site-fixture; do
  docker exec "$WP_CONTAINER" mkdir -p "/var/www/html/wp-content/plugins/${plugin_dir}" \
    || fail_smoke "migration-fixture-plugin-dir" "Could not create migration fixture plugin directory" "directory created" "$plugin_dir"
  docker cp "${FIXTURE_ROOT}/${plugin_dir}/." "$WP_CONTAINER":"/var/www/html/wp-content/plugins/${plugin_dir}/" \
    || fail_smoke "migration-fixture-plugin-copy" "Could not copy migration fixture plugin" "plugin copied" "$plugin_dir"
done

docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/themes/legacy-child \
  || fail_smoke "migration-fixture-theme-dir" "Could not create child-theme fixture directory" "directory created" "failed"
docker cp "${FIXTURE_ROOT}/legacy-child/." "$WP_CONTAINER":/var/www/html/wp-content/themes/legacy-child/ \
  || fail_smoke "migration-fixture-theme-copy" "Could not copy child-theme fixture" "theme copied" "failed"

docker exec "$WP_CONTAINER" chown -R www-data:www-data \
  /var/www/html/wp-content/plugins/elementor \
  /var/www/html/wp-content/plugins/woocommerce \
  /var/www/html/wp-content/plugins/wordpress-seo \
  /var/www/html/wp-content/plugins/divi-builder \
  /var/www/html/wp-content/plugins/legacy-site-fixture \
  /var/www/html/wp-content/themes/legacy-child \
  || fail_smoke "migration-fixture-permissions" "Could not set migration fixture ownership" "www-data owns fixtures" "failed"

wp_cli plugin activate elementor woocommerce legacy-site-fixture >/dev/null \
  || fail_smoke "migration-fixture-activate" "Could not activate migration analyzer fixture plugins" "fixture plugins active" "activation failed"

wp_cli menu create 'Legacy Menu' >/dev/null \
  || fail_smoke "migration-fixture-menu" "Could not create migration menu fixture" "menu created" "failed"

wp_cli eval 'wp_update_custom_css_post( ".legacy-fixture { display: block; }" );' >/dev/null \
  || fail_smoke "migration-fixture-custom-css" "Could not create custom CSS fixture" "custom CSS saved" "failed"

if ! MIGRATION_STATE_BEFORE="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"${TMP_DIR}/migration-state-before.stderr" | tr -d '\r\n')"; then
  MIGRATION_STATE_BEFORE_ERROR="$(tr -d '\r' <"${TMP_DIR}/migration-state-before.stderr" | head -c 240)"
  fail_smoke "migration-state-before" "Could not snapshot migration fixture state" "sha256 state snapshot" "${MIGRATION_STATE_BEFORE_ERROR:-wp eval failed}"
fi

if ! MIGRATION_REPORT="$(wp_cli eval '$analyzer = \SeoGeo\MigrationBridge\Plugin::analyzer(); if ( ! $analyzer ) { exit( 1 ); } echo wp_json_encode( $analyzer->analyze() );' 2>"${TMP_DIR}/migration-analyzer.stderr")"; then
  MIGRATION_ANALYZER_ERROR="$(tr -d '\r' <"${TMP_DIR}/migration-analyzer.stderr" | head -c 240)"
  fail_smoke "migration-analyzer-eval" "Could not execute the read-only Site Analyzer" "JSON report" "${MIGRATION_ANALYZER_ERROR:-wp eval failed}"
fi

printf '%s' "$MIGRATION_REPORT" >"${TMP_DIR}/migration-report.json"

if ! MIGRATION_REPORT_RESULT="$(python3 - "${TMP_DIR}/migration-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

assert report["schema_version"] == 1
assert report["mode"] == "read-only"
assert report["safety"] == {
    "mutations_performed": False,
    "content_scan_performed": False,
    "credentials_collected": False,
    "option_values_exported": False,
}

plugins = {row["basename"]: row for row in report["plugins"]}
assert plugins["elementor/elementor.php"]["active"] is True
assert plugins["woocommerce/woocommerce.php"]["active"] is True
assert plugins["wordpress-seo/wp-seo.php"]["active"] is False
assert plugins["divi-builder/divi-builder.php"]["active"] is False
assert plugins["seo-geo-migration-bridge/seo-geo-migration-bridge.php"]["active"] is True

builders = {row["id"]: row for row in report["builders"]}
assert builders["native-blocks"]["active"] is True
assert builders["elementor"]["installed"] is True and builders["elementor"]["active"] is True
assert builders["divi"]["installed"] is True and builders["divi"]["active"] is False
assert all(row["content_scan_performed"] is False for row in builders.values())

providers = report["providers"]
business = {row["id"]: row for row in providers["business_systems"]}
seo = {row["id"]: row for row in providers["seo"]}
assert business["woocommerce"]["active"] is True
assert seo["yoast"]["active"] is False

themes = {row["stylesheet"]: row for row in report["themes"]}
assert themes["seo-geo-theme"]["status"] == "active"
assert themes["legacy-child"]["status"] == "inactive"
assert themes["legacy-child"]["is_child_theme"] is True
assert themes["legacy-child"]["template"] == "seo-geo-theme"

post_types = {row["name"] for row in report["content_model"]["post_types"]}
taxonomies = {row["name"] for row in report["content_model"]["taxonomies"]}
assert "legacy_case" in post_types
assert "legacy_topic" in taxonomies
assert "legacy_cta" in report["content_model"]["shortcodes"]
assert any(row["name"] == "Legacy Menu" for row in report["content_model"]["menus"])
assert "page.html" in report["content_model"]["templates"]["block_templates"]
assert report["customization"]["custom_css"]["present"] is True
assert report["customization"]["custom_css"]["bytes"] > 0
assert report["customization"]["functions_php"]["present"] is True

print("ok")
PY
)"; then
  fail_smoke "migration-report-contract" "Site Analyzer report contract is invalid" "complete read-only inventory" "${MIGRATION_REPORT_RESULT:-python assertion failed}"
fi

if ! MIGRATION_STATE_AFTER="$(wp_cli eval 'global $wpdb; echo hash( "sha256", serialize( array( get_option( "active_plugins", array() ), get_option( "stylesheet" ), get_option( "template" ), get_option( "permalink_structure" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) ) ) );' 2>"${TMP_DIR}/migration-state-after.stderr" | tr -d '\r\n')"; then
  MIGRATION_STATE_AFTER_ERROR="$(tr -d '\r' <"${TMP_DIR}/migration-state-after.stderr" | head -c 240)"
  fail_smoke "migration-state-after" "Could not snapshot post-analysis migration fixture state" "sha256 state snapshot" "${MIGRATION_STATE_AFTER_ERROR:-wp eval failed}"
fi

[[ "$MIGRATION_STATE_AFTER" == "$MIGRATION_STATE_BEFORE" ]] \
  || fail_smoke "migration-read-only-regression" "Site Analyzer changed protected WordPress state" "$MIGRATION_STATE_BEFORE" "$MIGRATION_STATE_AFTER"

printf '[smoke] Phase 8A Site Analyzer OK: themes/plugins/builders/providers/content model inventoried without protected-state mutation.\n'
