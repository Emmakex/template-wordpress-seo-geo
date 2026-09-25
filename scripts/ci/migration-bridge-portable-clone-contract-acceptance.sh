#!/usr/bin/env bash
# Phase 10E.2A.1 Portable Clone Engine contract acceptance.

printf '[smoke] Checking Portable Clone resumable job/manifest contract.\n'

CLONE_RUNNER="$TMP_DIR/portable-clone-contract-runner.php"
cat >"$CLONE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\CloneManifest;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );

$store = Plugin::clone_job_store();
if ( ! $store instanceof CloneJobStore ) {
	throw new RuntimeException( 'Portable Clone job store service is unavailable.' );
}

$job = $store->create( 'export', 'clone-fixture-0001' );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create deterministic clone fixture job.' );
}

$same = $store->create( 'export', 'clone-fixture-0001' );
if ( ! is_array( $same ) || $same['job_id'] !== $job['job_id'] ) {
	throw new RuntimeException( 'Repeated clone job creation was not idempotent.' );
}

$progress = $store->update_progress(
	'clone-fixture-0001',
	'inventory',
	'plugins:10',
	array(
		'completed' => 10,
		'total'     => 25,
	)
);

$paused = $store->transition( 'clone-fixture-0001', 'paused', 'operator-paused' );
$resumed = $store->transition( 'clone-fixture-0001', 'active', null );
$latest = $store->latest();
$manifest = ( new CloneManifest() )->build( $latest );

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		CloneJobStore::OPTION_NAME
	)
);

$controller_registered = false !== has_action( 'admin_post_' . AdminCloneController::ACTION );

echo wp_json_encode(
	array(
		'created'               => $job,
		'progress'              => $progress,
		'paused'                => $paused,
		'resumed'               => $resumed,
		'latest'                => $latest,
		'manifest'              => $manifest,
		'autoload'              => $autoload,
		'controller_registered' => $controller_registered,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

delete_option( CloneJobStore::OPTION_NAME );
PHP

docker cp "$CLONE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-contract-runner.php \
  || fail_smoke "clone-contract-runner-copy" "Could not copy Portable Clone contract runner" "runner copied" "docker cp failed"

if ! CLONE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-contract-runner.php 2>"$TMP_DIR/portable-clone-contract.stderr")"; then
  CLONE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-contract.stderr" | head -c 900)"
  fail_smoke "clone-contract-runner" "Portable Clone contract runner failed" "JSON contract report" "${CLONE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$CLONE_JSON" >"$TMP_DIR/portable-clone-contract.json"

if ! CLONE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-contract.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

created = payload["created"]
assert created["schema_version"] == 1
assert created["job_id"] == "clone-fixture-0001"
assert created["operation"] == "export"
assert created["phase"] == "created"
assert created["status"] == "active"
assert created["cursor"] is None
assert created["counters"] == {"completed": 0, "total": None}
assert created["error_code"] is None

progress = payload["progress"]
assert progress["phase"] == "inventory"
assert progress["cursor"] == "plugins:10"
assert progress["counters"] == {"completed": 10, "total": 25}

assert payload["paused"]["status"] == "paused"
assert payload["paused"]["error_code"] == "operator-paused"
assert payload["resumed"]["status"] == "active"
assert payload["resumed"]["error_code"] is None

latest = payload["latest"]
assert latest["job_id"] == "clone-fixture-0001"
assert latest["phase"] == "inventory"
assert latest["status"] == "active"

manifest = payload["manifest"]
assert manifest["schema_version"] == 1
assert manifest["mode"] == "portable-clone-planning"
assert manifest["package_id"] == "clone-fixture-0001"
assert manifest["operation"] == "export"
assert manifest["integrity"]["algorithm"] == "sha256"
assert manifest["integrity"]["package_checksum"] is None
assert manifest["integrity"]["verified"] is False
assert manifest["resumability"]["job_schema_version"] == 1
assert manifest["resumability"]["batched"] is True
assert manifest["resumability"]["single_request_job"] is False
assert set(manifest["payload"]) == {"database", "uploads", "plugins", "themes"}
assert all(item["status"] == "not-started" for item in manifest["payload"].values())

safety = manifest["safety"]
assert safety["production_source_mutation_allowed"] is False
assert safety["production_database_restore_allowed"] is False
assert safety["third_party_clone_plugin_required"] is False
assert safety["payload_created_in_this_phase"] is False
assert safety["credentials_in_manifest"] is False
assert safety["private_payload_repository_safe"] is False

assert payload["controller_registered"] is True
assert payload["autoload"] in ("off", "no")

print("ok")
PY
)"; then
  fail_smoke "clone-contract" "Portable Clone job/manifest contract is invalid" "resumable bounded planning-only contract" "${CLONE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Clone contract OK: non-autoloaded resumable planning job + versioned privacy-bounded manifest; no payload copy in 10E.2A.1.\n'
