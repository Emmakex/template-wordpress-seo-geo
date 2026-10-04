#!/usr/bin/env bash
# Reset/Rebuild Clone Reset Engine acceptance.
#
# This destructive fixture MUST run after all parity/migration acceptance because
# its purpose is to prove the final clone can shed the legacy runtime.

printf '[smoke] Checking reset-first Clone Reset Engine.\n'

RESET_RUNNER="$TMP_DIR/clone-reset-engine-runner.php"
cat >"$RESET_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Reset\CloneResetEngine;
use SeoGeo\MigrationBridge\Reset\RescueManifest;

wp_set_current_user( 1 );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';

$legacy_plugin_dir = WP_PLUGIN_DIR . '/legacy-reset-test';
$keep_plugin_dir   = WP_PLUGIN_DIR . '/keep-business-test';
$legacy_theme_dir  = get_theme_root() . '/legacy-reset-theme';

wp_mkdir_p( $legacy_plugin_dir );
wp_mkdir_p( $keep_plugin_dir );
wp_mkdir_p( $legacy_theme_dir );

file_put_contents(
	$legacy_plugin_dir . '/legacy-reset-test.php',
	"<?php\n/**\n * Plugin Name: Legacy Reset Test\n */\n"
);
file_put_contents(
	$keep_plugin_dir . '/keep-business-test.php',
	"<?php\n/**\n * Plugin Name: Keep Business Test\n */\n"
);
file_put_contents(
	$legacy_theme_dir . '/style.css',
	"/*\nTheme Name: Legacy Reset Theme\nVersion: 1.0.0\n*/\n"
);
file_put_contents( $legacy_theme_dir . '/index.php', "<?php echo 'legacy';\n" );

wp_clean_themes_cache( true );
activate_plugin( 'legacy-reset-test/legacy-reset-test.php' );
activate_plugin( 'keep-business-test/keep-business-test.php' );
switch_theme( 'legacy-reset-theme' );

if ( 'legacy-reset-theme' !== get_stylesheet() ) {
	throw new RuntimeException( 'Legacy fixture theme did not activate.' );
}

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Reset Preserved Page',
		'post_name'    => 'reset-preserved-page',
		'post_content' => '<!-- wp:paragraph --><p>Preserved rebuild source content.</p><!-- /wp:paragraph -->',
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

$manifest_service = Plugin::rescue_manifest();
$reset            = Plugin::clone_reset_engine();
if ( ! $manifest_service instanceof RescueManifest || ! $reset instanceof CloneResetEngine ) {
	throw new RuntimeException( 'Reset/Rebuild services did not initialize.' );
}

$manifest    = $manifest_service->capture();
$manifest_sha = (string) ( $manifest['manifest_sha256'] ?? '' );
$page_before = (string) get_post_field( 'post_content', $page_id );
$page_sha    = hash( 'sha256', $page_before );

wp_mkdir_p( WP_CONTENT_DIR . '/et-cache' );
file_put_contents( WP_CONTENT_DIR . '/et-cache/legacy.css', 'legacy-cache' );

$uploads = wp_get_upload_dir();
$elementor_css_dir = trailingslashit( (string) $uploads['basedir'] ) . 'elementor/css';
wp_mkdir_p( $elementor_css_dir );
file_put_contents( $elementor_css_dir . '/legacy.css', 'legacy-elementor-cache' );

if ( ! defined( 'SEO_GEO_MIGRATION_SANDBOX' ) ) {
	define( 'SEO_GEO_MIGRATION_SANDBOX', true );
}

$plan = $reset->plan( array( 'keep-business-test/keep-business-test.php' ) );
if ( true !== ( $plan['ready'] ?? false ) ) {
	throw new RuntimeException( 'Reset plan unexpectedly blocked: ' . implode( ',', $plan['blockers'] ?? array() ) );
}

$result = $reset->apply( array( 'keep-business-test/keep-business-test.php' ) );
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_code() . ': ' . $result->get_error_message() );
}

wp_clean_plugins_cache( true );
wp_clean_themes_cache( true );

$saved = $manifest_service->saved();
$page_after = (string) get_post_field( 'post_content', $page_id );
$active_plugins = array_values( array_map( 'strval', get_option( 'active_plugins', array() ) ) );

echo wp_json_encode(
	array(
		'plan' => $plan,
		'result' => $result,
		'state' => array(
			'target_theme_active' => 'seo-geo-theme' === get_stylesheet(),
			'legacy_plugin_exists' => file_exists( $legacy_plugin_dir ),
			'legacy_theme_exists'  => is_dir( $legacy_theme_dir ),
			'core_plugin_exists'   => file_exists( WP_PLUGIN_DIR . '/seo-geo-core' ),
			'bridge_plugin_exists' => file_exists( WP_PLUGIN_DIR . '/seo-geo-migration-bridge' ),
			'keep_plugin_exists'   => file_exists( $keep_plugin_dir ),
			'keep_plugin_active'   => is_plugin_active( 'keep-business-test/keep-business-test.php' ),
			'bridge_plugin_active' => is_plugin_active( 'seo-geo-migration-bridge/seo-geo-migration-bridge.php' ),
			'manifest_unchanged'   => is_array( $saved ) && hash_equals( $manifest_sha, (string) ( $saved['manifest_sha256'] ?? '' ) ),
			'content_unchanged'    => hash_equals( $page_sha, hash( 'sha256', $page_after ) ),
			'et_cache_exists'      => is_dir( WP_CONTENT_DIR . '/et-cache' ),
			'elementor_css_exists' => is_dir( $elementor_css_dir ),
			'active_plugins'       => $active_plugins,
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$RESET_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/clone-reset-engine-runner.php \
  || fail_smoke "clone-reset-runner-copy" "Could not copy Clone Reset Engine runner" "runner copied" "docker cp failed"

if ! RESET_REPORT="$(wp_cli eval-file /var/www/html/wp-content/clone-reset-engine-runner.php 2>"$TMP_DIR/clone-reset-engine.stderr")"; then
  RESET_ERROR="$(tr -d '\r' <"$TMP_DIR/clone-reset-engine.stderr" | head -c 1200)"
  fail_smoke "clone-reset-runner" "Clone Reset Engine runner failed" "JSON reset report" "${RESET_ERROR:-wp eval-file failed}"
fi

printf '%s' "$RESET_REPORT" >"$TMP_DIR/clone-reset-engine-report.json"

if ! python3 - "$TMP_DIR/clone-reset-engine-report.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    report = json.load(handle)

plan = report["plan"]
result = report["result"]
state = report["state"]

assert plan["ready"] is True
assert plan["mode"] == "reset-rebuild-clone-reset-plan"
assert plan["safety"] == {
    "sandbox_only": True,
    "dependency_review_needed": False,
    "content_delete_allowed": False,
    "manifest_delete_allowed": False,
    "bridge_delete_allowed": False,
}
remove_plugins = {item["file"] for item in plan["plugins"]["remove"]}
keep_plugins = {item["file"] for item in plan["plugins"]["keep"]}
assert "legacy-reset-test/legacy-reset-test.php" in remove_plugins
assert "seo-geo-core/seo-geo-core.php" in remove_plugins
assert "keep-business-test/keep-business-test.php" in keep_plugins
assert "seo-geo-migration-bridge/seo-geo-migration-bridge.php" in keep_plugins
assert "legacy-reset-theme" in {item["stylesheet"] for item in plan["themes"]["remove"]}
assert len(plan["plan_sha256"]) == 64

assert result["status"] == "completed"
assert result["safety"]["manifest_unchanged"] is True
assert result["safety"]["content_unchanged"] is True
assert result["safety"]["target_theme_active"] is True
assert result["safety"]["bridge_active"] is True
assert result["safety"]["production_mutation"] is False
assert not result["errors"]

assert state["target_theme_active"] is True
assert state["legacy_plugin_exists"] is False
assert state["legacy_theme_exists"] is False
assert state["core_plugin_exists"] is False
assert state["bridge_plugin_exists"] is True
assert state["keep_plugin_exists"] is True
assert state["keep_plugin_active"] is True
assert state["bridge_plugin_active"] is True
assert state["manifest_unchanged"] is True
assert state["content_unchanged"] is True
assert state["et_cache_exists"] is False
assert state["elementor_css_exists"] is False
assert set(state["active_plugins"]) == {
    "keep-business-test/keep-business-test.php",
    "seo-geo-migration-bridge/seo-geo-migration-bridge.php",
}
PY
then
  fail_smoke "clone-reset-assertions" "Clone Reset Engine violated clean-runtime/content-preservation invariants" "all assertions pass" "assertion failure"
fi

printf '[smoke] Clone Reset Engine OK: legacy plugin/theme/runtime caches removed, SEO/GEO Theme activated, explicit business plugin + Migration Bridge retained, rescued content and manifest unchanged.\n'
