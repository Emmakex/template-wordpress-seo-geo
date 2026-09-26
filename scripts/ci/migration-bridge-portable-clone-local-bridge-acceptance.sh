#!/usr/bin/env bash
# Phase 10E.2A.5.2.2.2.1 local-clone Migration Bridge control-runtime acceptance.

printf '[smoke] Checking local-clone Migration Bridge control runtime.\n'

LOCAL_BRIDGE_RUNNER="$TMP_DIR/portable-clone-local-bridge-runner.php"
cat >"$LOCAL_BRIDGE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalBridgeController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneBridgeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneBridgeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
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
	LocalCloneBridgeStateStore::OPTION_NAME,
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$bootstrap = Plugin::local_clone_bootstrapper();
$runtime   = Plugin::local_clone_runtime_bootstrapper();
$bridge    = Plugin::local_clone_bridge_bootstrapper();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $bootstrap instanceof LocalCloneBootstrapper
	|| ! $runtime instanceof LocalCloneRuntimeBootstrapper
	|| ! $bridge instanceof LocalCloneBridgeBootstrapper
) {
	throw new RuntimeException( 'Local clone Bridge runtime services are unavailable.' );
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
		throw new RuntimeException( 'Could not create local clone Bridge fixture job.' );
	}

	$fingerprint = hash( 'sha256', 'local-bridge-source:' . $job_id );
	$inventories->save(
		$job_id,
		array(
			'status'       => 'complete',
			'database'     => array(
				'estimated_rows'  => 3,
				'estimated_bytes' => 2048,
			),
			'roots'        => array(),
			'file_count'   => 2,
			'byte_count'   => 2048,
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
			'payload_file_count'     => 4,
			'payload_byte_count'     => 4096,
			'verify_file_count'      => 4,
			'verify_byte_count'      => 4096,
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

$prepare_core = static function (
	string $job_id,
	string $target_path,
	string $target_url,
	string $target_prefix
) use ( $seed, $planner, $bootstrap, $runtime ): array {
	$seed( $job_id );
	$plan = $planner->prepare( $job_id, $target_path, $target_url, $target_prefix, true );
	$claim = $bootstrap->claim( $job_id );
	$core = null;
	for ( $i = 0; $i < 120; ++$i ) {
		$core = $runtime->advance( $job_id, 200, 64 * 1024 * 1024 );
		if ( ! is_array( $core ) || in_array( $core['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			break;
		}
	}

	return array(
		'plan'  => $plan,
		'claim' => $claim,
		'core'  => $core,
	);
};

global $wpdb;
$source_prefix = $wpdb->prefix;

$success_job  = 'local-bridge-fixture-0001';
$success_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-bridge-success';
$remove_tree( $success_path );
$success_base = $prepare_core(
	$success_job,
	$success_path,
	trailingslashit( home_url( '/nuevaweb-bridge-success/' ) ),
	$source_prefix . 'sgbridge1_'
);
$success_bridge = null;
for ( $i = 0; $i < 40; ++$i ) {
	$success_bridge = $bridge->advance( $success_job, 200, 32 * 1024 * 1024 );
	if ( ! is_array( $success_bridge ) || in_array( $success_bridge['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$target_plugin = trailingslashit( $success_path ) . 'wp-content/plugins/seo-geo-migration-bridge';

$tamper_job  = 'local-bridge-fixture-0002';
$tamper_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-bridge-tamper';
$remove_tree( $tamper_path );
$tamper_base = $prepare_core(
	$tamper_job,
	$tamper_path,
	trailingslashit( home_url( '/nuevaweb-bridge-tamper/' ) ),
	$source_prefix . 'sgbridge2_'
);
$tamper_before = null;
for ( $i = 0; $i < 40; ++$i ) {
	$tamper_before = $bridge->advance( $tamper_job, 200, 32 * 1024 * 1024 );
	if (
		! is_array( $tamper_before )
		|| 'blocked' === ( $tamper_before['status'] ?? null )
		|| 'bridge-verify' === ( $tamper_before['stage'] ?? null )
	) {
		break;
	}
}

$tamper_file = trailingslashit( $tamper_path ) . 'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php';
if ( is_file( $tamper_file ) ) {
	file_put_contents( $tamper_file, "\n/* Bridge runtime tamper fixture */\n", FILE_APPEND );
}
$tamper_result = $bridge->advance( $tamper_job, 200, 32 * 1024 * 1024 );

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		LocalCloneBridgeStateStore::OPTION_NAME
	)
);

$wp_content_entries = is_dir( trailingslashit( $success_path ) . 'wp-content' )
	? array_values( array_diff( scandir( trailingslashit( $success_path ) . 'wp-content' ) ?: array(), array( '.', '..' ) ) )
	: array();
$plugins_entries = is_dir( trailingslashit( $success_path ) . 'wp-content/plugins' )
	? array_values( array_diff( scandir( trailingslashit( $success_path ) . 'wp-content/plugins' ) ?: array(), array( '.', '..' ) ) )
	: array();

echo wp_json_encode(
	array(
		'success_base'                  => $success_base,
		'success_bridge'                => $success_bridge,
		'target_plugin_main'            => is_file( trailingslashit( $target_plugin ) . 'seo-geo-migration-bridge.php' ),
		'target_plugin_service'         => is_file( trailingslashit( $target_plugin ) . 'src/Plugin.php' ),
		'target_wp_content_entries'     => $wp_content_entries,
		'target_plugins_entries'        => $plugins_entries,
		'target_uploads_absent'         => ! file_exists( trailingslashit( $success_path ) . 'wp-content/uploads' ),
		'target_themes_absent'          => ! file_exists( trailingslashit( $success_path ) . 'wp-content/themes' ),
		'target_wp_config_absent'       => ! file_exists( trailingslashit( $success_path ) . 'wp-config.php' ),
		'tamper_base'                   => $tamper_base,
		'tamper_before'                 => $tamper_before,
		'tamper_result'                 => $tamper_result,
		'controller_registered'         => false !== has_action( 'admin_post_' . AdminCloneLocalBridgeController::ACTION ),
		'public_controller_absent'      => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalBridgeController::ACTION ),
		'autoload'                      => $autoload,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$remove_tree( $success_path );
$remove_tree( $tamper_path );
foreach ( $options as $option ) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_BRIDGE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-bridge-runner.php \
  || fail_smoke "local-clone-bridge-runner-copy" "Could not copy local clone Bridge runner" "runner copied" "docker cp failed"

if ! LOCAL_BRIDGE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-bridge-runner.php 2>"$TMP_DIR/portable-clone-local-bridge.stderr")"; then
  LOCAL_BRIDGE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-bridge.stderr" | head -c 1800)"
  fail_smoke "local-clone-bridge-runner" "Local clone Bridge runtime runner failed" "JSON contract report" "${LOCAL_BRIDGE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_BRIDGE_JSON" >"$TMP_DIR/portable-clone-local-bridge.json"

if ! LOCAL_BRIDGE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-bridge.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

base = payload["success_base"]
assert base["plan"]["status"] == "ready", payload
assert base["claim"]["status"] == "claimed", payload
assert base["core"]["status"] == "complete", payload
assert base["core"]["runtime_core_ready"] is True, payload

bridge = payload["success_bridge"]
assert bridge is not None, payload
assert bridge["status"] == "complete", payload
assert bridge["stage"] == "bridge-complete", payload
assert bridge["bridge_runtime_ready"] is True, payload
assert bridge["client_content_untouched"] is True, payload
assert bridge["database_untouched"] is True, payload
assert bridge["bootstrap_next"] == "isolated-config-hardening", payload
assert bridge["blockers"] == [], payload
assert bridge["copy_file_count"] > 25, payload
assert bridge["copy_file_count"] == bridge["verify_file_count"], payload
assert bridge["copy_byte_count"] > 0, payload
assert bridge["copy_byte_count"] == bridge["verify_byte_count"], payload
assert re.fullmatch(r"[a-f0-9]{64}", bridge["copy_fingerprint"]), payload
assert bridge["copy_fingerprint"] == bridge["verify_fingerprint"], payload
assert payload["target_plugin_main"] is True, payload
assert payload["target_plugin_service"] is True, payload
assert payload["target_wp_content_entries"] == ["plugins"], payload
assert payload["target_plugins_entries"] == ["seo-geo-migration-bridge"], payload
assert payload["target_uploads_absent"] is True, payload
assert payload["target_themes_absent"] is True, payload
assert payload["target_wp_config_absent"] is True, payload

tamper_base = payload["tamper_base"]
before = payload["tamper_before"]
tamper = payload["tamper_result"]
assert tamper_base["core"]["status"] == "complete", payload
assert before is not None and before["stage"] == "bridge-verify", payload
assert tamper is not None and tamper["status"] == "blocked", payload
assert any(code in tamper["blockers"] for code in (
    "bridge-integrity-mismatch",
    "bridge-target-entry-drift",
)), payload

assert payload["controller_registered"] is True, payload
assert payload["public_controller_absent"] is True, payload
assert payload["autoload"] in ("off", "no"), payload

print("ok")
PY
)"; then
  fail_smoke "local-clone-bridge" "Local clone Migration Bridge runtime contract is invalid" "bounded Bridge copy + exact second-pass verification + client-content exclusion + tamper rejection" "${LOCAL_BRIDGE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone Migration Bridge runtime OK: control plugin copied/verified, client wp-content/config/database untouched, tamper rejected.\n'
