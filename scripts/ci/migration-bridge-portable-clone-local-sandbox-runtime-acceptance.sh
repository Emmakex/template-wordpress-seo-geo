#!/usr/bin/env bash
# Phase 10E.2A.5.2.2.2 local-clone isolated sandbox runtime acceptance.

printf '[smoke] Checking local-clone Migration Bridge + isolated sandbox hardening.\n'

LOCAL_SANDBOX_RUNTIME_RUNNER="$TMP_DIR/portable-clone-local-sandbox-runtime-runner.php"
cat >"$LOCAL_SANDBOX_RUNTIME_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalSandboxRuntimeController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

$options = array(
	CloneJobStore::OPTION_NAME,
	CloneInventoryStore::OPTION_NAME,
	PackageStateStore::OPTION_NAME,
	LocalCloneStateStore::OPTION_NAME,
	LocalCloneBootstrapStateStore::OPTION_NAME,
	LocalCloneRuntimeStateStore::OPTION_NAME,
	LocalCloneSandboxRuntimeStateStore::OPTION_NAME,
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$ownership = Plugin::local_clone_bootstrapper();
$core      = Plugin::local_clone_runtime_bootstrapper();
$sandbox   = Plugin::local_clone_sandbox_runtime_bootstrapper();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $ownership instanceof LocalCloneBootstrapper
	|| ! $core instanceof LocalCloneRuntimeBootstrapper
	|| ! $sandbox instanceof LocalCloneSandboxRuntimeBootstrapper
) {
	throw new RuntimeException( 'Local clone sandbox runtime services are unavailable.' );
}

$inventories = new CloneInventoryStore();
$packages    = new PackageStateStore();

$remove_tree = static function ( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $path );
};

$seed = static function ( string $job_id ) use ( $jobs, $inventories, $packages ): void {
	$job = $jobs->create( 'local-clone', $job_id );
	if ( ! is_array( $job ) ) {
		throw new RuntimeException( 'Could not create sandbox runtime fixture job.' );
	}

	$fingerprint = hash( 'sha256', 'sandbox-runtime-source:' . $job_id );
	$inventories->save(
		$job_id,
		array(
			'status'       => 'complete',
			'database'     => array(
				'estimated_rows'  => 4,
				'estimated_bytes' => 4096,
			),
			'roots'        => array(),
			'file_count'   => 3,
			'byte_count'   => 4096,
			'fingerprint'  => $fingerprint,
			'blockers'     => array(),
			'completed_at' => gmdate( DATE_ATOM ),
			'updated_at'   => gmdate( DATE_ATOM ),
		)
	);
	$packages->save(
		$job_id,
		array(
			'status'                 => 'complete',
			'stage'                  => 'complete',
			'payload_file_count'     => 5,
			'payload_byte_count'     => 8192,
			'verify_file_count'      => 5,
			'verify_byte_count'      => 8192,
			'package_checksum'       => hash( 'sha256', 'package:' . $job_id ),
			'verification_checksum'  => hash( 'sha256', 'package:' . $job_id ),
			'source_fingerprint'     => $fingerprint,
			'database_manifest_hash' => hash( 'sha256', 'database:' . $job_id ),
			'files_manifest_hash'    => hash( 'sha256', 'files:' . $job_id ),
			'package_manifest_hash'  => hash( 'sha256', 'manifest:' . $job_id ),
			'blockers'               => array(),
			'completed_at'           => gmdate( DATE_ATOM ),
			'updated_at'             => gmdate( DATE_ATOM ),
		)
	);
};

global $wpdb;
$source_prefix = $wpdb->prefix;
$job_id        = 'local-sandbox-runtime-0001';
$target_path   = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-sandbox-runtime';
$target_url    = trailingslashit( home_url( '/nuevaweb-sandbox-runtime/' ) );
$target_prefix = $source_prefix . 'sgsand_';

$remove_tree( $target_path );
$seed( $job_id );

$plan = $planner->prepare( $job_id, $target_path, $target_url, $target_prefix, true );
$claim = $ownership->claim( $job_id );

$core_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$core_state = $core->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $core_state ) || in_array( $core_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$sandbox_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$sandbox_state = $sandbox->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $sandbox_state ) || in_array( $sandbox_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$verified_before = $sandbox->verified_snapshot( $job_id );

$config_path = trailingslashit( $target_path ) . 'wp-config.php';
$mu_path = trailingslashit( $target_path ) . 'wp-content/mu-plugins/seo-geo-migration-sandbox-bootstrap.php';
$bridge_source = trailingslashit( wp_normalize_path( (string) constant( 'SEO_GEO_MIGRATION_BRIDGE_DIR' ) ) ) . 'seo-geo-migration-bridge.php';
$bridge_target = trailingslashit( $target_path ) . 'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php';

$config = is_file( $config_path ) ? file_get_contents( $config_path ) : false;
$mu = is_file( $mu_path ) ? file_get_contents( $mu_path ) : false;
$source_bridge_hash = is_file( $bridge_source ) ? hash_file( 'sha256', $bridge_source ) : false;
$target_bridge_hash = is_file( $bridge_target ) ? hash_file( 'sha256', $bridge_target ) : false;

$table_pattern = $wpdb->esc_like( $target_prefix ) . '%';
$target_tables = $wpdb->get_col(
	$wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$table_pattern
	)
);

$state_json = wp_json_encode( $sandbox_state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$db_password = defined( 'DB_PASSWORD' ) && is_string( constant( 'DB_PASSWORD' ) ) ? (string) constant( 'DB_PASSWORD' ) : '';
$state_secret_free = is_string( $state_json ) && ( '' === $db_password || ! str_contains( $state_json, $db_password ) );

$config_checks = array(
	'target_prefix' => is_string( $config ) && str_contains( $config, '$table_prefix = ' . var_export( $target_prefix, true ) . ';' ),
	'target_home'   => is_string( $config ) && str_contains( $config, "define( 'WP_HOME', " . var_export( untrailingslashit( $target_url ), true ) . ' );' ),
	'sandbox'       => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_SANDBOX', true );" ),
	'mode'          => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_SANDBOX_MODE', 'subdirectory' );" ),
	'storage'       => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_STORAGE_ISOLATED', true );" ),
	'outbound'      => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );" ),
	'backups'       => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );" ),
	'authorized'    => is_string( $config ) && str_contains( $config, "define( 'SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED', true );" ),
	'cron_disabled' => is_string( $config ) && str_contains( $config, "define( 'DISABLE_WP_CRON', true );" ),
);

$mu_checks = array(
	'blog_public'     => is_string( $mu ) && str_contains( $mu, "'pre_option_blog_public'" ),
	'mail_block'      => is_string( $mu ) && str_contains( $mu, "'pre_wp_mail'" ),
	'http_block'      => is_string( $mu ) && str_contains( $mu, "'pre_http_request'" ),
	'isolated_path'   => is_string( $mu ) && str_contains( $mu, '$is_sandbox_path' ),
	'bridge_loader'   => is_string( $mu ) && str_contains( $mu, "seo-geo-migration-bridge/seo-geo-migration-bridge.php" ),
	'x_robots'        => is_string( $mu ) && str_contains( $mu, 'X-Robots-Tag' ),
);

if ( is_file( $mu_path ) ) {
	file_put_contents( $mu_path, "\n/* sandbox hardening tamper fixture */\n", FILE_APPEND );
}
$verified_after_tamper = $sandbox->verified_snapshot( $job_id );

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		LocalCloneSandboxRuntimeStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'plan'                    => $plan,
		'claim'                   => $claim,
		'core_state'              => $core_state,
		'sandbox_state'           => $sandbox_state,
		'verified_before'         => is_array( $verified_before ),
		'verified_after_tamper'   => is_array( $verified_after_tamper ),
		'config_exists'           => is_file( $config_path ),
		'mu_exists'               => is_file( $mu_path ),
		'bridge_exists'           => is_file( $bridge_target ),
		'bridge_hash_matches'     => is_string( $source_bridge_hash ) && is_string( $target_bridge_hash ) && hash_equals( $source_bridge_hash, $target_bridge_hash ),
		'config_checks'           => $config_checks,
		'mu_checks'               => $mu_checks,
		'target_table_count'      => is_array( $target_tables ) ? count( $target_tables ) : -1,
		'uploads_absent'          => ! file_exists( trailingslashit( $target_path ) . 'wp-content/uploads' ),
		'themes_absent'           => ! file_exists( trailingslashit( $target_path ) . 'wp-content/themes' ),
		'state_secret_free'       => $state_secret_free,
		'controller_registered'   => false !== has_action( 'admin_post_' . AdminCloneLocalSandboxRuntimeController::ACTION ),
		'public_controller_absent'=> false === has_action( 'admin_post_nopriv_' . AdminCloneLocalSandboxRuntimeController::ACTION ),
		'autoload'                => $autoload,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$remove_tree( $target_path );
foreach ( $options as $option ) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_SANDBOX_RUNTIME_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-sandbox-runtime-runner.php \
  || fail_smoke "local-clone-sandbox-runtime-runner-copy" "Could not copy local clone sandbox runtime runner" "runner copied" "docker cp failed"

if ! LOCAL_SANDBOX_RUNTIME_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-sandbox-runtime-runner.php 2>"$TMP_DIR/portable-clone-local-sandbox-runtime.stderr")"; then
  LOCAL_SANDBOX_RUNTIME_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-sandbox-runtime.stderr" | head -c 2000)"
  fail_smoke "local-clone-sandbox-runtime-runner" "Local clone sandbox runtime runner failed" "JSON contract report" "${LOCAL_SANDBOX_RUNTIME_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_SANDBOX_RUNTIME_JSON" >"$TMP_DIR/portable-clone-local-sandbox-runtime.json"

if ! LOCAL_SANDBOX_RUNTIME_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-sandbox-runtime.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

assert payload["plan"] is not None, payload
assert payload["plan"]["status"] == "ready", payload
assert payload["claim"] is not None, payload
assert payload["claim"]["status"] == "claimed", payload

core = payload["core_state"]
assert core is not None, payload
assert core["status"] == "complete", payload
assert core["runtime_core_ready"] is True, payload

sandbox = payload["sandbox_state"]
assert sandbox is not None, payload
assert sandbox["status"] == "complete", payload
assert sandbox["stage"] == "complete", payload
assert sandbox["sandbox_runtime_ready"] is True, payload
assert sandbox["sandbox_marker_enabled"] is True, payload
assert sandbox["storage_isolated"] is True, payload
assert sandbox["outbound_blocked"] is True, payload
assert sandbox["search_blocked"] is True, payload
assert sandbox["target_authorized"] is True, payload
assert sandbox["backups_ready"] is True, payload
assert sandbox["database_untouched"] is True, payload
assert sandbox["client_content_untouched"] is True, payload
assert sandbox["bootstrap_next"] == "package-handoff", payload
assert sandbox["blockers"] == [], payload
assert sandbox["bridge_copy_files"] > 5, payload
assert sandbox["bridge_copy_files"] == sandbox["bridge_verify_files"], payload
assert sandbox["bridge_copy_bytes"] > 0, payload
assert sandbox["bridge_copy_bytes"] == sandbox["bridge_verify_bytes"], payload
assert re.fullmatch(r"[a-f0-9]{64}", sandbox["bridge_copy_fingerprint"]), payload
assert sandbox["bridge_copy_fingerprint"] == sandbox["bridge_verify_fingerprint"], payload
assert re.fullmatch(r"[a-f0-9]{64}", sandbox["wp_config_sha256"]), payload
assert re.fullmatch(r"[a-f0-9]{64}", sandbox["mu_plugin_sha256"]), payload

assert payload["verified_before"] is True, payload
assert payload["verified_after_tamper"] is False, payload
assert payload["config_exists"] is True, payload
assert payload["mu_exists"] is True, payload
assert payload["bridge_exists"] is True, payload
assert payload["bridge_hash_matches"] is True, payload
assert all(payload["config_checks"].values()), payload
assert all(payload["mu_checks"].values()), payload
assert payload["target_table_count"] == 0, payload
assert payload["uploads_absent"] is True, payload
assert payload["themes_absent"] is True, payload
assert payload["state_secret_free"] is True, payload
assert payload["controller_registered"] is True, payload
assert payload["public_controller_absent"] is True, payload
assert payload["autoload"] in ("off", "no"), payload

print("ok")
PY
)"; then
  fail_smoke "local-clone-sandbox-runtime" "Local clone isolated sandbox runtime contract is invalid" "Bridge double verification + isolated config + active noindex/outbound hardening + zero target tables/client content" "${LOCAL_SANDBOX_RUNTIME_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone sandbox runtime OK: Bridge verified twice, isolated config/hardening generated, target tables/client content absent, control-file tamper invalidated readiness.\n'
