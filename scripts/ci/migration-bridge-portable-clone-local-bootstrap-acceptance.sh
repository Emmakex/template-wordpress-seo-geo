#!/usr/bin/env bash
# Phase 10E.2A.5.2.1 local-clone target ownership bootstrap acceptance.

printf '[smoke] Checking local-clone target ownership bootstrap.\n'

LOCAL_BOOTSTRAP_RUNNER="$TMP_DIR/portable-clone-local-bootstrap-runner.php"
cat >"$LOCAL_BOOTSTRAP_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalBootstrapController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

$options = array(
	CloneJobStore::OPTION_NAME,
	CloneInventoryStore::OPTION_NAME,
	PackageStateStore::OPTION_NAME,
	LocalCloneStateStore::OPTION_NAME,
	LocalCloneBootstrapStateStore::OPTION_NAME,
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$bootstrap = Plugin::local_clone_bootstrapper();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $bootstrap instanceof LocalCloneBootstrapper
) {
	throw new RuntimeException( 'Local clone ownership services are unavailable.' );
}

$inventories = new CloneInventoryStore();
$packages    = new PackageStateStore();

$seed = static function ( string $job_id ) use ( $jobs, $inventories, $packages ): void {
	$job = $jobs->create( 'local-clone', $job_id );
	if ( ! is_array( $job ) ) {
		throw new RuntimeException( 'Could not create local clone fixture job.' );
	}

	$fingerprint = hash( 'sha256', 'local-bootstrap-source:' . $job_id );
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

$created_job = 'local-bootstrap-fixture-0001';
$seed( $created_job );
$created_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-bootstrap-created';
$created_url  = trailingslashit( home_url( '/nuevaweb-bootstrap-created/' ) );
$created_plan = $planner->prepare( $created_job, $created_path, $created_url, $source_prefix . 'sgboot1_', true );
if ( ! is_array( $created_plan ) || 'ready' !== ( $created_plan['status'] ?? null ) ) {
	throw new RuntimeException( 'Created-target fixture plan is not ready.' );
}

$claimed = $bootstrap->claim( $created_job );
$claimed_again = $bootstrap->claim( $created_job );
$marker_path = trailingslashit( $created_path ) . LocalCloneBootstrapper::OWNER_MARKER;
$marker_content = is_file( $marker_path ) ? file_get_contents( $marker_path ) : false;
$created_entries = is_dir( $created_path )
	? array_values( array_diff( scandir( $created_path ) ?: array(), array( '.', '..' ) ) )
	: array();
$released = $bootstrap->release( $created_job );
$created_exists_after_release = file_exists( $created_path );

$existing_job = 'local-bootstrap-fixture-0002';
$seed( $existing_job );
$existing_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-bootstrap-existing';
wp_mkdir_p( $existing_path );
$existing_plan = $planner->prepare(
	$existing_job,
	$existing_path,
	trailingslashit( home_url( '/nuevaweb-bootstrap-existing/' ) ),
	$source_prefix . 'sgboot2_',
	true
);
$existing_claimed = $bootstrap->claim( $existing_job );
$existing_released = $bootstrap->release( $existing_job );
$existing_left_empty = is_dir( $existing_path )
	&& array() === array_values( array_diff( scandir( $existing_path ) ?: array(), array( '.', '..' ) ) );

$tamper_job = 'local-bootstrap-fixture-0003';
$seed( $tamper_job );
$tamper_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-bootstrap-tamper';
$tamper_plan = $planner->prepare(
	$tamper_job,
	$tamper_path,
	trailingslashit( home_url( '/nuevaweb-bootstrap-tamper/' ) ),
	$source_prefix . 'sgboot3_',
	true
);
$tamper_claimed = $bootstrap->claim( $tamper_job );
$tamper_marker = trailingslashit( $tamper_path ) . LocalCloneBootstrapper::OWNER_MARKER;
file_put_contents( $tamper_marker, "<?php /* tampered */\n" );
$tamper_result = $bootstrap->claim( $tamper_job );
$tamper_release = $bootstrap->release( $tamper_job );

$store = new LocalCloneBootstrapStateStore();
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		LocalCloneBootstrapStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'created_plan'                 => $created_plan,
		'claimed'                      => $claimed,
		'claimed_again'                => $claimed_again,
		'created_entries'              => $created_entries,
		'marker_content'               => is_string( $marker_content ) ? $marker_content : '',
		'released'                     => $released,
		'created_exists_after_release' => $created_exists_after_release,
		'existing_plan'                => $existing_plan,
		'existing_claimed'             => $existing_claimed,
		'existing_released'            => $existing_released,
		'existing_left_empty'          => $existing_left_empty,
		'tamper_plan'                   => $tamper_plan,
		'tamper_claimed'                => $tamper_claimed,
		'tamper_result'                 => $tamper_result,
		'tamper_release'                => $tamper_release,
		'tamper_marker_exists'          => is_file( $tamper_marker ),
		'controller_claim_registered'   => false !== has_action( 'admin_post_' . AdminCloneLocalBootstrapController::CLAIM_ACTION ),
		'controller_release_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalBootstrapController::RELEASE_ACTION ),
		'public_claim_absent'           => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalBootstrapController::CLAIM_ACTION ),
		'public_release_absent'         => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalBootstrapController::RELEASE_ACTION ),
		'autoload'                      => $autoload,
		'stored_created'                => $store->get( $created_job ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ( is_file( $tamper_marker ) ) {
	@unlink( $tamper_marker );
}
if ( is_dir( $tamper_path ) ) {
	@rmdir( $tamper_path );
}
if ( is_dir( $existing_path ) ) {
	@rmdir( $existing_path );
}
foreach ( $options as $option ) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_BOOTSTRAP_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-bootstrap-runner.php \
  || fail_smoke "local-clone-bootstrap-runner-copy" "Could not copy local clone bootstrap runner" "runner copied" "docker cp failed"

if ! LOCAL_BOOTSTRAP_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-bootstrap-runner.php 2>"$TMP_DIR/portable-clone-local-bootstrap.stderr")"; then
  LOCAL_BOOTSTRAP_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-bootstrap.stderr" | head -c 1400)"
  fail_smoke "local-clone-bootstrap-runner" "Local clone bootstrap runner failed" "JSON contract report" "${LOCAL_BOOTSTRAP_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_BOOTSTRAP_JSON" >"$TMP_DIR/portable-clone-local-bootstrap.json"

if ! LOCAL_BOOTSTRAP_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-bootstrap.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

claimed = payload["claimed"]
assert claimed["status"] == "claimed"
assert claimed["stage"] == "target-ownership"
assert claimed["target_created"] is True
assert claimed["target_owned"] is True
assert claimed["production_untouched"] is True
assert claimed["database_untouched"] is True
assert claimed["bootstrap_next"] == "runtime-copy"
assert claimed["blockers"] == []
assert claimed["marker_relative_path"] == ".seo-geo-migration-local-clone-owner.php"
assert re.fullmatch(r"[a-f0-9]{64}", claimed["marker_sha256"])
assert payload["claimed_again"]["marker_sha256"] == claimed["marker_sha256"]
assert payload["created_entries"] == [".seo-geo-migration-local-clone-owner.php"]

marker = payload["marker_content"]
assert marker.startswith("<?php")
assert "DB_PASSWORD" not in marker
assert "AUTH_KEY" not in marker
assert "package_checksum" not in marker.lower() or "base64" not in marker.lower()

released = payload["released"]
assert released["status"] == "released"
assert released["target_owned"] is False
assert payload["created_exists_after_release"] is False
assert payload["stored_created"]["status"] == "released"

existing = payload["existing_claimed"]
assert existing["status"] == "claimed"
assert existing["target_created"] is False
assert existing["target_owned"] is True
assert payload["existing_released"]["status"] == "released"
assert payload["existing_left_empty"] is True

tamper = payload["tamper_result"]
assert payload["tamper_claimed"]["status"] == "claimed"
assert tamper["status"] == "blocked"
assert "bootstrap-owner-marker-changed" in tamper["blockers"]
assert payload["tamper_release"]["status"] == "blocked"
assert payload["tamper_marker_exists"] is True

assert payload["controller_claim_registered"] is True
assert payload["controller_release_registered"] is True
assert payload["public_claim_absent"] is True
assert payload["public_release_absent"] is True
assert payload["autoload"] in ("off", "no")

print("ok")
PY
)"; then
  fail_smoke "local-clone-bootstrap" "Local clone ownership bootstrap contract is invalid" "job-owned target marker + safe release + tamper rejection" "${LOCAL_BOOTSTRAP_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone ownership bootstrap OK: exact frozen target claimed, deterministic marker verified, safe release bounded, tamper rejected; no runtime/database copy.\n'
