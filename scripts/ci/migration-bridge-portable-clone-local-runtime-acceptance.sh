#!/usr/bin/env bash
# Phase 10E.2A.5.2.1 local-clone WordPress core runtime copy acceptance.

printf '[smoke] Checking local-clone resumable WordPress core runtime copy.\n'

LOCAL_RUNTIME_RUNNER="$TMP_DIR/portable-clone-local-runtime-runner.php"
cat >"$LOCAL_RUNTIME_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalRuntimeController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
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
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$bootstrap = Plugin::local_clone_bootstrapper();
$runtime   = Plugin::local_clone_runtime_bootstrapper();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $bootstrap instanceof LocalCloneBootstrapper
	|| ! $runtime instanceof LocalCloneRuntimeBootstrapper
) {
	throw new RuntimeException( 'Local clone runtime services are unavailable.' );
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
		throw new RuntimeException( 'Could not create local clone runtime fixture job.' );
	}

	$fingerprint = hash( 'sha256', 'local-runtime-source:' . $job_id );
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

global $wpdb;
$source_prefix = $wpdb->prefix;

$success_job  = 'local-runtime-fixture-0001';
$success_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-runtime-success';
$remove_tree( $success_path );
$seed( $success_job );
$success_plan = $planner->prepare(
	$success_job,
	$success_path,
	trailingslashit( home_url( '/nuevaweb-runtime-success/' ) ),
	$source_prefix . 'sgrun1_',
	true
);
$success_claim = $bootstrap->claim( $success_job );
$success_state = null;
for ( $i = 0; $i < 120; ++$i ) {
	$success_state = $runtime->advance( $success_job, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $success_state ) || in_array( $success_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}
$success_release = $bootstrap->release( $success_job );

$tamper_job  = 'local-runtime-fixture-0002';
$tamper_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-runtime-tamper';
$remove_tree( $tamper_path );
$seed( $tamper_job );
$tamper_plan = $planner->prepare(
	$tamper_job,
	$tamper_path,
	trailingslashit( home_url( '/nuevaweb-runtime-tamper/' ) ),
	$source_prefix . 'sgrun2_',
	true
);
$tamper_claim = $bootstrap->claim( $tamper_job );
$tamper_state = null;
for ( $i = 0; $i < 120; ++$i ) {
	$tamper_state = $runtime->advance( $tamper_job, 200, 64 * 1024 * 1024 );
	if (
		! is_array( $tamper_state )
		|| 'blocked' === ( $tamper_state['status'] ?? null )
		|| 'core-verify' === ( $tamper_state['stage'] ?? null )
	) {
		break;
	}
}

$tamper_file = trailingslashit( $tamper_path ) . 'wp-settings.php';
if ( is_file( $tamper_file ) ) {
	file_put_contents( $tamper_file, "\n/* runtime tamper fixture */\n", FILE_APPEND );
}
$tamper_result = $runtime->advance( $tamper_job, 200, 64 * 1024 * 1024 );

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		LocalCloneRuntimeStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'success_plan'                  => $success_plan,
		'success_claim'                 => $success_claim,
		'success_state'                 => $success_state,
		'success_release'               => $success_release,
		'success_owner_marker_exists'   => is_file( trailingslashit( $success_path ) . LocalCloneBootstrapper::OWNER_MARKER ),
		'success_wp_admin'              => is_dir( trailingslashit( $success_path ) . 'wp-admin' ),
		'success_wp_includes'           => is_dir( trailingslashit( $success_path ) . 'wp-includes' ),
		'success_wp_settings'           => is_file( trailingslashit( $success_path ) . 'wp-settings.php' ),
		'success_index'                 => is_file( trailingslashit( $success_path ) . 'index.php' ),
		'success_wp_config_absent'      => ! file_exists( trailingslashit( $success_path ) . 'wp-config.php' ),
		'success_wp_content_absent'     => ! file_exists( trailingslashit( $success_path ) . 'wp-content' ),
		'tamper_plan'                   => $tamper_plan,
		'tamper_claim'                  => $tamper_claim,
		'tamper_before'                 => $tamper_state,
		'tamper_result'                 => $tamper_result,
		'controller_registered'         => false !== has_action( 'admin_post_' . AdminCloneLocalRuntimeController::ACTION ),
		'public_controller_absent'      => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalRuntimeController::ACTION ),
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

docker cp "$LOCAL_RUNTIME_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-runtime-runner.php \
  || fail_smoke "local-clone-runtime-runner-copy" "Could not copy local clone runtime runner" "runner copied" "docker cp failed"

if ! LOCAL_RUNTIME_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-runtime-runner.php 2>"$TMP_DIR/portable-clone-local-runtime.stderr")"; then
  LOCAL_RUNTIME_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-runtime.stderr" | head -c 1800)"
  fail_smoke "local-clone-runtime-runner" "Local clone core runtime runner failed" "JSON contract report" "${LOCAL_RUNTIME_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_RUNTIME_JSON" >"$TMP_DIR/portable-clone-local-runtime.json"

if ! LOCAL_RUNTIME_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-runtime.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

success = payload["success_state"]
assert payload["success_plan"] is not None, payload
assert payload["success_plan"]["status"] == "ready", payload
assert payload["success_claim"] is not None, payload
assert payload["success_claim"]["status"] == "claimed", payload
assert success is not None, payload
assert success["status"] == "complete", payload
assert success["stage"] == "core-complete"
assert success["runtime_core_ready"] is True
assert success["production_untouched"] is True
assert success["database_untouched"] is True
assert success["wp_content_untouched"] is True
assert success["bootstrap_next"] == "bridge-config-hardening"
assert success["blockers"] == []
assert success["copy_file_count"] > 500
assert success["copy_file_count"] == success["verify_file_count"]
assert success["copy_byte_count"] > 0
assert success["copy_byte_count"] == success["verify_byte_count"]
assert re.fullmatch(r"[a-f0-9]{64}", success["copy_fingerprint"])
assert success["copy_fingerprint"] == success["verify_fingerprint"]
assert payload["success_owner_marker_exists"] is True
assert payload["success_wp_admin"] is True
assert payload["success_wp_includes"] is True
assert payload["success_wp_settings"] is True
assert payload["success_index"] is True
assert payload["success_wp_config_absent"] is True
assert payload["success_wp_content_absent"] is True

release = payload["success_release"]
assert release["status"] == "blocked"
assert "bootstrap-release-target-has-unowned-entries" in release["blockers"]

before = payload["tamper_before"]
tamper = payload["tamper_result"]
assert payload["tamper_plan"]["status"] == "ready"
assert payload["tamper_claim"]["status"] == "claimed"
assert before["stage"] == "core-verify"
assert tamper["status"] == "blocked"
assert any(code in tamper["blockers"] for code in (
    "runtime-core-integrity-mismatch",
    "runtime-target-entry-drift",
))
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
assert payload["autoload"] in ("off", "no")

print("ok")
PY
)"; then
  fail_smoke "local-clone-runtime" "Local clone WordPress core runtime contract is invalid" "bounded copy + second-pass SHA-256 verification + tamper rejection" "${LOCAL_RUNTIME_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone WordPress core runtime OK: bounded copy/verification matched, wp-content/config untouched, tamper rejected.\n'
