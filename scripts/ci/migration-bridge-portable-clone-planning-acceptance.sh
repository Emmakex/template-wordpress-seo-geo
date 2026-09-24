#!/usr/bin/env bash
# Phase 10E.2A.1 portable clone planning/job/package acceptance.

printf '[smoke] Checking portable clone planning + resumable job contract.\n'

PORTABLE_RUNNER="$TMP_DIR/migration-portable-clone-runner.php"
cat >"$PORTABLE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Plugin;
use SeoGeo\MigrationBridge\Portable\AdminPortableCloneController;
use SeoGeo\MigrationBridge\Portable\PortableCloneJobStore;
use SeoGeo\MigrationBridge\Review\DependencyReviewStore;

$planner  = Plugin::portable_clone_planner();
$store    = Plugin::portable_clone_store();
$manifest = Plugin::portable_package_manifest();

if ( ! $planner || ! $store || ! $manifest ) {
	throw new RuntimeException( 'Portable clone services are unavailable.' );
}

$analysis = Plugin::analyzer()?->analyze();
$graph_builder = Plugin::dependency_graph();
$baseline = get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, null );
$snapshot = is_array( $baseline['snapshot'] ?? null ) ? $baseline['snapshot'] : null;
if ( ! is_array( $analysis ) || ! $graph_builder || ! is_array( $snapshot ) ) {
	throw new RuntimeException( 'Portable clone acceptance prerequisites are unavailable.' );
}

$graph = $graph_builder->build( $analysis, $snapshot );
$reviews = new DependencyReviewStore();
$reviewed_ids = array();
foreach ( $graph['components'] ?? array() as $component ) {
	if ( ! is_array( $component ) || 'UNKNOWN' !== ( $component['classification'] ?? null ) ) {
		continue;
	}
	$component_id = $component['component_id'] ?? null;
	if ( ! is_string( $component_id ) ) {
		continue;
	}
	if ( ! $reviews->save( $component_id, 'KEEP' ) ) {
		throw new RuntimeException( 'Could not prepare UNKNOWN dependency reviews.' );
	}
	$reviewed_ids[] = $component_id;
}

global $wpdb;
$tables_before = $wpdb->get_col( 'SHOW TABLES' );
$plan = $planner->local_subdirectory( 'nuevaweb' );
$invalid_plan = $planner->local_subdirectory( '../escape' );
$job = $store->start( $plan );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not start portable clone planning job.' );
}
$package = $manifest->build( $job );
$latest = $store->latest();
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		PortableCloneJobStore::OPTION_NAME
	)
);
$tables_after = $wpdb->get_col( 'SHOW TABLES' );

$prepare_registered = false !== has_action(
	'admin_post_' . AdminPortableCloneController::PREPARE_ACTION
);
$cancel_registered = false !== has_action(
	'admin_post_' . AdminPortableCloneController::CANCEL_ACTION
);

$store->cancel();
$cancelled = $store->latest();
$cleared = $store->clear_terminal();

foreach ( $reviewed_ids as $component_id ) {
	$reviews->clear( $component_id );
}

echo wp_json_encode(
	array(
		'plan'               => $plan,
		'invalid_plan'       => $invalid_plan,
		'job'                => $job,
		'latest'             => $latest,
		'package'            => $package,
		'autoload'           => $autoload,
		'tables_before'      => $tables_before,
		'tables_after'       => $tables_after,
		'prepare_registered' => $prepare_registered,
		'cancel_registered'  => $cancel_registered,
		'cancelled'          => $cancelled,
		'cleared'            => $cleared,
		'after_clear'        => $store->latest(),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
PHP

docker cp "$PORTABLE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/migration-portable-clone-runner.php \
  || fail_smoke "portable-runner-copy" "Could not copy portable clone runner" "runner copied" "docker cp failed"

if ! PORTABLE_JSON="$(wp_cli eval-file /var/www/html/wp-content/migration-portable-clone-runner.php 2>"$TMP_DIR/portable-clone.stderr")"; then
  PORTABLE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone.stderr" | head -c 1200)"
  fail_smoke "portable-clone-runner" "Portable clone planning runner failed" "JSON portable clone report" "${PORTABLE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PORTABLE_JSON" >"$TMP_DIR/migration-portable-clone.json"

if ! PORTABLE_ASSERTION="$(python3 - "$TMP_DIR/migration-portable-clone.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

plan = payload["plan"]
assert plan["schema_version"] == 1
assert plan["mode"] == "local-subdirectory"
assert plan["ready"] is True
assert plan["blockers"] == []
assert plan["target"]["directory"] == "nuevaweb"
assert plan["target"]["home_url"].endswith("/nuevaweb/")
assert plan["target"]["table_prefix"] != plan["source"]["table_prefix"]
assert plan["target"]["exists"] is False
assert plan["target"]["empty"] is True
assert plan["review"]["complete"] is True
assert plan["safety"]["production_mutation_allowed"] is False
assert plan["safety"]["destructive_overwrite_allowed"] is False
assert plan["safety"]["target_must_be_empty"] is True
assert plan["safety"]["credentials_exported"] is False

invalid = payload["invalid_plan"]
assert invalid["ready"] is False
assert "target-directory-invalid" in invalid["blockers"]

job = payload["job"]
assert job["schema_version"] == 1
assert job["kind"] == "portable-clone"
assert job["status"] == "planned"
assert job["stage"] == "inventory"
assert job["plan"]["target"]["directory"] == "nuevaweb"
assert payload["latest"]["job_id"] == job["job_id"]

assert payload["autoload"] != "on"
assert payload["tables_before"] == payload["tables_after"]
assert payload["prepare_registered"] is True
assert payload["cancel_registered"] is True

package = payload["package"]
assert package["schema_version"] == 1
assert package["kind"] == "seo-geo-portable-site-package"
assert package["package_id"] == job["job_id"]
assert package["payload"]["files"]["segments"] == []
assert package["payload"]["database"]["segments"] == []
assert package["integrity"]["algorithm"] == "sha256"
assert package["privacy"] == {
    "contains_private_site_data": True,
    "repository_safe": False,
    "wp_config_included": False,
    "credentials_included": False,
    "auth_salts_included": False,
    "package_requires_protected_storage": True,
}
assert package["safety"]["production_restore_allowed"] is False
assert package["safety"]["blind_dynamic_data_restore_allowed"] is False
assert package["safety"]["target_integrity_required"] is True
assert package["safety"]["resume_required"] is True

assert payload["cancelled"]["status"] == "cancelled"
assert payload["cancelled"]["stage"] == "cancelled"
assert payload["cleared"] is True
assert payload["after_clear"] is None

print("ok")
PY
)"; then
  fail_smoke "portable-clone-contract" "Portable clone planning/job/package contract is invalid" "safe non-mutating resumable plan" "${PORTABLE_ASSERTION:-python assertion failed}"
fi

# Existing non-empty target must be rejected without deleting it.
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/occupied-clone \
  || fail_smoke "portable-occupied-dir" "Could not create occupied target fixture" "directory created" "mkdir failed"
docker exec "$WP_CONTAINER" sh -c 'printf occupied > /var/www/html/occupied-clone/keep.txt' \
  || fail_smoke "portable-occupied-file" "Could not create occupied target fixture file" "file created" "write failed"

if ! OCCUPIED_JSON="$(wp_cli eval '
$planner = \SeoGeo\MigrationBridge\Plugin::portable_clone_planner();
$graph = \SeoGeo\MigrationBridge\Plugin::dependency_graph()->build(
	\SeoGeo\MigrationBridge\Plugin::analyzer()->analyze(),
	( get_option( \SeoGeo\MigrationBridge\BaselineSnapshotStore::OPTION_NAME, array() )["snapshot"] ?? null )
);
$reviews = new \SeoGeo\MigrationBridge\Review\DependencyReviewStore();
foreach ( $graph["components"] ?? array() as $component ) {
	if ( is_array( $component ) && "UNKNOWN" === ( $component["classification"] ?? null ) && is_string( $component["component_id"] ?? null ) ) {
		$reviews->save( $component["component_id"], "KEEP" );
	}
}
echo wp_json_encode( $planner->local_subdirectory( "occupied-clone" ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' 2>"$TMP_DIR/portable-occupied.stderr")"; then
  fail_smoke "portable-occupied-plan" "Could not plan occupied target fixture" "JSON plan" "$(cat "$TMP_DIR/portable-occupied.stderr" | head -c 500)"
fi
printf '%s' "$OCCUPIED_JSON" >"$TMP_DIR/portable-occupied.json"

if ! OCCUPIED_ASSERTION="$(python3 - "$TMP_DIR/portable-occupied.json" <<'PY'
import json
import sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    plan = json.load(handle)
assert plan["ready"] is False
assert plan["target"]["exists"] is True
assert plan["target"]["empty"] is False
assert "target-directory-not-empty" in plan["blockers"]
print("ok")
PY
)"; then
  fail_smoke "portable-occupied-guard" "Portable planner did not block a non-empty target" "target-directory-not-empty" "${OCCUPIED_ASSERTION:-python assertion failed}"
fi

docker exec "$WP_CONTAINER" test -f /var/www/html/occupied-clone/keep.txt \
  || fail_smoke "portable-occupied-preservation" "Portable planning removed or changed occupied target data" "keep.txt still exists" "missing"

docker exec "$WP_CONTAINER" rm -rf /var/www/html/occupied-clone \
  || fail_smoke "portable-occupied-cleanup" "Could not clean test-only occupied target fixture" "fixture removed" "rm failed"

printf '[smoke] Portable clone A.1 OK: target safety planned; job state non-autoloaded/resumable; package marked private; no site files/tables copied.\n'
