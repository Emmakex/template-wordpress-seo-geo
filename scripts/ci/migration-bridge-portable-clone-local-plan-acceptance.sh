#!/usr/bin/env bash
# Phase 10E.2A.5.1 local-clone destination plan + ownership contract acceptance.

printf '[smoke] Checking local-clone destination planning contract.\n'

LOCAL_CLONE_RUNNER="$TMP_DIR/portable-clone-local-plan-runner.php"
cat >"$LOCAL_CLONE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPlanController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

foreach (
	array(
		CloneJobStore::OPTION_NAME,
		CloneInventoryStore::OPTION_NAME,
		PackageStateStore::OPTION_NAME,
		LocalCloneStateStore::OPTION_NAME,
	) as $option
) {
	delete_option( $option );
}

$jobs         = Plugin::clone_job_store();
$orchestrator = Plugin::local_clone_orchestrator();
if ( ! $jobs instanceof CloneJobStore || ! $orchestrator instanceof LocalCloneOrchestrator ) {
	throw new RuntimeException( 'Local clone orchestration services are unavailable.' );
}

$inventory_store = new CloneInventoryStore();
$package_store   = new PackageStateStore();

$seed = static function ( string $job_id ) use ( $jobs, $inventory_store, $package_store ): void {
	$job = $jobs->create( 'local-clone', $job_id );
	if ( ! is_array( $job ) ) {
		throw new RuntimeException( 'Could not create local clone fixture job.' );
	}

	$fingerprint = hash( 'sha256', 'local-clone-source:' . $job_id );
	$inventory_store->save(
		$job_id,
		array(
			'status'       => 'complete',
			'database'     => array(
				'estimated_rows'  => 10,
				'estimated_bytes' => 2048,
			),
			'roots'        => array(),
			'file_count'   => 1,
			'byte_count'   => 1024,
			'fingerprint'  => $fingerprint,
			'blockers'     => array(),
			'completed_at' => gmdate( DATE_ATOM ),
			'updated_at'   => gmdate( DATE_ATOM ),
		)
	);
	$package_store->save(
		$job_id,
		array(
			'status'                 => 'complete',
			'stage'                  => 'complete',
			'payload_file_count'     => 3,
			'payload_byte_count'     => 4096,
			'verify_file_count'      => 3,
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

$safe_job = 'local-plan-fixture-0001';
$seed( $safe_job );
$safe_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-plan-fixture';
$safe_url  = trailingslashit( home_url( '/nuevaweb-plan-fixture/' ) );
$safe_prefix = $source_prefix . 'sgplan_';
if ( is_dir( $safe_path ) ) {
	throw new RuntimeException( 'Safe target fixture unexpectedly exists before planning.' );
}

$safe = $orchestrator->prepare( $safe_job, $safe_path, $safe_url, $safe_prefix, true );
if ( ! is_array( $safe ) ) {
	throw new RuntimeException( 'Safe local clone destination returned no state.' );
}
$safe_again = $orchestrator->prepare( $safe_job, ABSPATH, home_url( '/' ), $source_prefix, true );
$job_after_safe = $jobs->get( $safe_job );

$production_job = 'local-plan-fixture-0002';
$seed( $production_job );
$production = $orchestrator->prepare(
	$production_job,
	ABSPATH,
	home_url( '/' ),
	$source_prefix,
	true
);

$nonempty_job = 'local-plan-fixture-0003';
$seed( $nonempty_job );
$nonempty_path = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-nonempty-fixture';
wp_mkdir_p( $nonempty_path );
file_put_contents( trailingslashit( $nonempty_path ) . 'sentinel.txt', 'do-not-own' );
$nonempty = $orchestrator->prepare(
	$nonempty_job,
	$nonempty_path,
	trailingslashit( home_url( '/nuevaweb-nonempty-fixture/' ) ),
	$source_prefix . 'sgblocked_',
	true
);

$state_store = new LocalCloneStateStore();
global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		LocalCloneStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'safe'                  => $safe,
		'safe_again'            => $safe_again,
		'job_after_safe'        => $job_after_safe,
		'safe_target_created'   => file_exists( $safe_path ),
		'production'            => $production,
		'nonempty'              => $nonempty,
		'nonempty_still_exists' => is_file( trailingslashit( $nonempty_path ) . 'sentinel.txt' ),
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalPlanController::ACTION ),
		'public_absent'         => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalPlanController::ACTION ),
		'autoload'              => $autoload,
		'stored_safe'           => $state_store->get( $safe_job ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

@unlink( trailingslashit( $nonempty_path ) . 'sentinel.txt' );
@rmdir( $nonempty_path );
foreach (
	array(
		CloneJobStore::OPTION_NAME,
		CloneInventoryStore::OPTION_NAME,
		PackageStateStore::OPTION_NAME,
		LocalCloneStateStore::OPTION_NAME,
	) as $option
) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_CLONE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-plan-runner.php \
  || fail_smoke "local-clone-plan-runner-copy" "Could not copy local clone plan runner" "runner copied" "docker cp failed"

if ! LOCAL_CLONE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-plan-runner.php 2>"$TMP_DIR/portable-clone-local-plan.stderr")"; then
  LOCAL_CLONE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-plan.stderr" | head -c 1200)"
  fail_smoke "local-clone-plan-runner" "Local clone destination plan runner failed" "JSON contract report" "${LOCAL_CLONE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_CLONE_JSON" >"$TMP_DIR/portable-clone-local-plan.json"

if ! LOCAL_CLONE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-plan.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

safe = payload["safe"]
assert safe["schema_version"] == 1
assert safe["status"] == "ready"
assert safe["stage"] == "destination-plan"
assert safe["same_origin"] is True
assert safe["same_database"] is True
assert safe["payload_roots_exclude_target"] is True
assert safe["package_verified"] is True
assert safe["production_source_read_only"] is True
assert safe["target_owned"] is False
assert safe["bootstrap_allowed"] is True
assert safe["mutations_performed"] is False
assert safe["blockers"] == []
assert safe["target_url"].endswith("/nuevaweb-plan-fixture/")
assert safe["target_table_prefix"].endswith("sgplan_")
assert re.fullmatch(r"[a-f0-9]{64}", safe["plan_hash"])
assert payload["safe_target_created"] is False

# Ready plans are immutable: a later unsafe request returns the accepted plan.
assert payload["safe_again"]["plan_hash"] == safe["plan_hash"]
assert payload["safe_again"]["target_path"] == safe["target_path"]
assert payload["stored_safe"]["plan_hash"] == safe["plan_hash"]

job = payload["job_after_safe"]
assert job["phase"] == "prepare-target"
assert job["status"] == "active"
assert job["cursor"] == "planned"
assert job["counters"] == {"completed": 1, "total": 1}

production = payload["production"]
assert production["status"] == "blocked"
assert production["bootstrap_allowed"] is False
assert production["mutations_performed"] is False
assert "target-path-is-production" in production["blockers"]
assert "target-url-is-production" in production["blockers"]
assert "target-table-prefix-not-isolated" in production["blockers"]

nonempty = payload["nonempty"]
assert nonempty["status"] == "blocked"
assert "target-directory-not-empty-unowned" in nonempty["blockers"]
assert nonempty["target_owned"] is False
assert nonempty["mutations_performed"] is False
assert payload["nonempty_still_exists"] is True

assert payload["controller_registered"] is True
assert payload["public_absent"] is True
assert payload["autoload"] in ("off", "no")

print("ok")
PY
)"; then
  fail_smoke "local-clone-plan" "Local clone destination plan contract is invalid" "read-only frozen isolated destination plan" "${LOCAL_CLONE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone destination plan OK: verified package + isolated /nuevaweb/ contract, immutable ready plan, unsafe/non-empty target rejection, zero target mutation.\n'
