#!/usr/bin/env bash
# Phase 10E.2A.2 read-only source inventory + destination planner acceptance.

printf '[smoke] Checking Portable Clone read-only source inventory.\n'

FIXTURE_SETUP="$TMP_DIR/portable-clone-inventory-fixture.php"
cat >"$FIXTURE_SETUP" <<'PHP'
<?php

$uploads = wp_get_upload_dir();
$base = (string) $uploads['basedir'];
wp_mkdir_p( $base . '/seo-geo-inventory-fixture/cache' );
file_put_contents( $base . '/seo-geo-inventory-fixture/accepted-a.txt', 'portable-clone-a' );
file_put_contents( $base . '/seo-geo-inventory-fixture/accepted-b.txt', 'portable-clone-b' );
file_put_contents( $base . '/seo-geo-inventory-fixture/cache/excluded.txt', 'excluded-cache' );
file_put_contents( $base . '/seo-geo-inventory-fixture/excluded.tmp', 'excluded-temp' );
PHP

docker cp "$FIXTURE_SETUP" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-inventory-fixture.php \
  || fail_smoke "clone-inventory-fixture-copy" "Could not copy Portable Clone inventory fixture" "fixture copied" "docker cp failed"

wp_cli eval-file /var/www/html/wp-content/portable-clone-inventory-fixture.php >/dev/null \
  || fail_smoke "clone-inventory-fixture" "Could not create Portable Clone inventory fixture" "fixture created" "wp eval-file failed"

INVENTORY_RUNNER="$TMP_DIR/portable-clone-inventory-runner.php"
cat >"$INVENTORY_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneInventoryController;
use SeoGeo\MigrationBridge\Clone\CloneInventory;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );

$jobs = Plugin::clone_job_store();
$inventory = Plugin::clone_inventory();
$planner = Plugin::destination_safety_planner();

if ( ! $jobs instanceof CloneJobStore || ! $inventory instanceof CloneInventory || null === $planner ) {
	throw new RuntimeException( 'Portable Clone inventory services are unavailable.' );
}

$job = $jobs->create( 'export', 'clone-inventory-0001' );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create source inventory job.' );
}

$state = $inventory->start( 'clone-inventory-0001' );
if ( ! is_array( $state ) || 'running' !== $state['status'] ) {
	throw new RuntimeException( 'Could not start source inventory.' );
}

$steps = 0;
while ( 'complete' !== ( $state['status'] ?? null ) && 100 > $steps ) {
	$state = $inventory->advance( 'clone-inventory-0001', CloneInventory::MAX_BATCH_SIZE );
	if ( ! is_array( $state ) ) {
		throw new RuntimeException( 'Source inventory step failed.' );
	}
	++$steps;
}

if ( 'complete' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Source inventory did not finish within bounded fixture steps.' );
}

global $wpdb;
$inventory_autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		CloneInventoryStore::OPTION_NAME
	)
);

$positive = $planner->plan(
	ABSPATH . 'nuevaweb/',
	home_url( '/nuevaweb/' ),
	'seo_geo_clone_',
	true,
	(int) ( $state['byte_count'] ?? 0 )
);

$production_path = $planner->plan(
	ABSPATH,
	home_url( '/' ),
	(string) $wpdb->prefix,
	true,
	null
);

$uploads = wp_get_upload_dir();
$inside_uploads = $planner->plan(
	(string) $uploads['basedir'] . '/unsafe-target/',
	home_url( '/unsafe-target/' ),
	'seo_geo_clone_',
	true,
	null
);

$same_prefix = $planner->plan(
	ABSPATH . 'nuevaweb/',
	home_url( '/nuevaweb/' ),
	(string) $wpdb->prefix,
	true,
	null
);

echo wp_json_encode(
	array(
		'state'                 => $state,
		'steps'                 => $steps,
		'inventory_autoload'    => $inventory_autoload,
		'positive_plan'         => $positive,
		'production_path_plan'  => $production_path,
		'inside_uploads_plan'   => $inside_uploads,
		'same_prefix_plan'      => $same_prefix,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneInventoryController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
PHP

docker cp "$INVENTORY_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-inventory-runner.php \
  || fail_smoke "clone-inventory-runner-copy" "Could not copy Portable Clone inventory runner" "runner copied" "docker cp failed"

if ! INVENTORY_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-inventory-runner.php 2>"$TMP_DIR/portable-clone-inventory.stderr")"; then
  INVENTORY_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-inventory.stderr" | head -c 1200)"
  fail_smoke "clone-inventory-runner" "Portable Clone source inventory runner failed" "JSON inventory report" "${INVENTORY_ERROR:-wp eval-file failed}"
fi

printf '%s' "$INVENTORY_JSON" >"$TMP_DIR/portable-clone-inventory.json"

if ! INVENTORY_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-inventory.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

state = payload["state"]
assert state["schema_version"] == 1
assert state["status"] == "complete"
assert state["database"]["available"] is True
assert state["database"]["read_only"] is True
assert state["database"]["table_count"] >= 1
assert state["database"]["estimated_rows"] >= 0
assert state["database"]["estimated_bytes"] >= 0
assert len(state["database"]["tables"]) == state["database"]["table_count"]
assert {root["id"] for root in state["roots"]} == {"uploads", "plugins", "themes"}
assert state["file_count"] >= 2
assert state["byte_count"] > 0
assert state["excluded_count"] >= 2
assert state["symlink_count"] >= 0
assert state["unreadable_count"] >= 0
assert re.fullmatch(r"[a-f0-9]{64}", state["fingerprint"])
assert state["fingerprint_scope"] == "database-structure-estimates+file-content"
assert state["blockers"] == []
assert payload["steps"] >= 1
assert payload["inventory_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True

positive = payload["positive_plan"]
assert positive["mode"] == "read-only-destination-plan"
assert positive["ready"] is True
assert positive["same_origin"] is True
assert positive["target_base_path"] == "/nuevaweb/"
assert positive["nested_under_wordpress_root"] is True
assert positive["payload_roots_exclude_target"] is True
assert positive["target_table_prefix"] == "seo_geo_clone_"
assert positive["mutations_performed"] is False
assert positive["blockers"] == []

production = payload["production_path_plan"]
assert production["ready"] is False
assert "target-path-is-production" in production["blockers"]
assert "target-url-is-production" in production["blockers"]
assert "target-table-prefix-not-isolated" in production["blockers"]

uploads = payload["inside_uploads_plan"]
assert uploads["ready"] is False
assert any(code.startswith("target-inside-source-uploads") for code in uploads["blockers"])

prefix = payload["same_prefix_plan"]
assert prefix["ready"] is False
assert "target-table-prefix-not-isolated" in prefix["blockers"]

print("ok")
PY
)"; then
  fail_smoke "clone-source-inventory" "Portable Clone read-only inventory/destination planner contract is invalid" "complete bounded source inventory and safe local target plan" "${INVENTORY_ASSERTION:-python assertion failed}"
fi

wp_cli eval '
$uploads = wp_get_upload_dir();
$base = (string) $uploads["basedir"] . "/seo-geo-inventory-fixture";
if ( is_file( $base . "/accepted-a.txt" ) ) { unlink( $base . "/accepted-a.txt" ); }
if ( is_file( $base . "/accepted-b.txt" ) ) { unlink( $base . "/accepted-b.txt" ); }
if ( is_file( $base . "/excluded.tmp" ) ) { unlink( $base . "/excluded.tmp" ); }
if ( is_file( $base . "/cache/excluded.txt" ) ) { unlink( $base . "/cache/excluded.txt" ); }
if ( is_dir( $base . "/cache" ) ) { rmdir( $base . "/cache" ); }
if ( is_dir( $base ) ) { rmdir( $base ); }
' >/dev/null 2>&1 || true

printf '[smoke] Portable Clone source inventory OK: DB metadata + uploads/plugins/themes hashed read-only in resumable batches; destination planner blocks production/payload-root/prefix collisions.\n'
