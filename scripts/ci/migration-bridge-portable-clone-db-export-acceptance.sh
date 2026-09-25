#!/usr/bin/env bash
# Phase 10E.2A.3.1 resumable read-only database export acceptance.

printf '[smoke] Checking Portable Clone resumable database export.\n'

DB_EXPORT_RUNNER="$TMP_DIR/portable-clone-db-export-runner.php"
cat >"$DB_EXPORT_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneDatabaseExportController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DatabaseExporter;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Plugin;

global $wpdb;

$job_id = 'clone-db-export-0001';
$table = $wpdb->prefix . 'seo_geo_export_fixture';
$tick = chr( 96 );
$quoted_table = $tick . $table . $tick;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );

$wpdb->query( "DROP TABLE IF EXISTS " . $quoted_table );
$wpdb->query(
	"CREATE TABLE " . $quoted_table . " (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		label VARCHAR(191) NOT NULL,
		payload LONGTEXT NULL,
		PRIMARY KEY (id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

for ( $index = 1; $index <= 60; ++$index ) {
	$wpdb->insert(
		$table,
		array(
			'label' => 'row-' . $index,
			'payload' => 'private-fixture-' . str_repeat( (string) ( $index % 10 ), 24 ),
		),
		array( '%s', '%s' )
	);
}

$jobs = Plugin::clone_job_store();
$exporter = Plugin::clone_database_exporter();
if ( ! $jobs instanceof CloneJobStore || ! $exporter instanceof DatabaseExporter ) {
	throw new RuntimeException( 'Portable Clone database export services are unavailable.' );
}

$job = $jobs->create( 'export', $job_id );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create database export job.' );
}

$inventory_store = new CloneInventoryStore();
$now = gmdate( DATE_ATOM );
$inventory_ok = $inventory_store->save(
	$job_id,
	array(
		'schema_version' => CloneInventoryStore::SCHEMA_VERSION,
		'job_id' => $job_id,
		'status' => 'complete',
		'database' => array(
			'table_prefix' => $wpdb->prefix,
			'table_count' => 1,
			'estimated_rows' => 60,
			'estimated_bytes' => 4096,
			'tables' => array(
				array(
					'name' => $table,
					'engine' => 'InnoDB',
					'estimated_rows' => 60,
					'estimated_bytes' => 4096,
				),
			),
			'read_only' => true,
			'available' => true,
		),
		'roots' => array(),
		'root_index' => 0,
		'pending_dirs' => array(),
		'current_dir' => '',
		'after_name' => '',
		'file_count' => 0,
		'byte_count' => 0,
		'excluded_count' => 0,
		'symlink_count' => 0,
		'unreadable_count' => 0,
		'fingerprint' => hash( 'sha256', 'db-export-fixture' ),
		'blockers' => array(),
		'started_at' => $now,
		'updated_at' => $now,
		'completed_at' => $now,
	)
);
if ( ! $inventory_ok ) {
	throw new RuntimeException( 'Could not save database export inventory fixture.' );
}

$state = $exporter->start( $job_id );
if ( ! is_array( $state ) || 'running' !== $state['status'] ) {
	throw new RuntimeException( 'Could not start database export.' );
}

$steps = 0;
while ( 'complete' !== ( $state['status'] ?? null ) && 20 > $steps ) {
	$state = $exporter->advance( $job_id, 25 );
	if ( ! is_array( $state ) ) {
		throw new RuntimeException( 'Database export step failed.' );
	}
	++$steps;
}

if ( 'complete' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Database export did not complete within bounded fixture steps.' );
}

$workspace = new ExportWorkspace();
$manifest_json = $workspace->read( $job_id, 'database/manifest.json' );
$manifest = is_string( $manifest_json ) ? json_decode( $manifest_json, true ) : null;
$table_meta = is_array( $manifest ) && is_array( $manifest['tables'][0] ?? null ) ? $manifest['tables'][0] : null;

$source_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . $quoted_table );
$first_label = (string) $wpdb->get_var( "SELECT label FROM " . $quoted_table . " WHERE id = 1" );
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ExportStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'state' => $state,
		'steps' => $steps,
		'manifest' => $manifest,
		'table_meta' => $table_meta,
		'source_rows' => $source_rows,
		'first_label' => $first_label,
		'export_state_autoload' => $autoload,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneDatabaseExportController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$exporter->cleanup( $job_id );
$wpdb->query( "DROP TABLE IF EXISTS " . $quoted_table );
delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
PHP

docker cp "$DB_EXPORT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-db-export-runner.php \
  || fail_smoke "clone-db-export-runner-copy" "Could not copy Portable Clone DB export runner" "runner copied" "docker cp failed"

if ! EXPORT_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-db-export-runner.php 2>"$TMP_DIR/portable-clone-db-export.stderr")"; then
  EXPORT_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-db-export.stderr" | head -c 1600)"
  fail_smoke "clone-db-export-runner" "Portable Clone database export runner failed" "JSON export report" "${EXPORT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$EXPORT_JSON" >"$TMP_DIR/portable-clone-db-export.json"

if ! EXPORT_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-db-export.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

state = payload["state"]
manifest = payload["manifest"]
table = payload["table_meta"]

assert state["schema_version"] == 1
assert state["status"] == "complete"
assert state["table_count"] == 1
assert state["tables_completed"] == 1
assert state["row_count"] == 60
assert state["chunk_count"] == 3
assert state["byte_count"] > 0
assert re.fullmatch(r"[a-f0-9]{64}", state["database_manifest_hash"])
assert state["blockers"] == []
assert payload["steps"] >= 3
assert payload["export_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["source_rows"] == 60
assert payload["first_label"] == "row-1"

assert manifest["schema_version"] == 1
assert manifest["payload_class"] == "database"
assert manifest["table_count"] == 1
assert manifest["row_count"] == 60
assert manifest["chunk_count"] == 3
assert manifest["production_source_read_only"] is True
assert manifest["credentials_in_payload"] is False
assert manifest["contains_private_site_data"] is True
assert manifest["repository_safe"] is False

assert table["strategy"] == "primary-key"
assert table["cursor_column"] == "id"
assert table["row_count"] == 60
assert table["complete"] is True
assert len(table["chunks"]) == 3
assert [chunk["row_count"] for chunk in table["chunks"]] == [25, 25, 10]
assert all(re.fullmatch(r"[a-f0-9]{64}", chunk["sha256"]) for chunk in table["chunks"])
assert table["schema"]["path"].endswith("/schema.sql")
assert re.fullmatch(r"[a-f0-9]{64}", table["schema"]["sha256"])

serialized = json.dumps(manifest)
assert "wordpress-smoke-password" not in serialized
assert "root-smoke-password" not in serialized
assert "private-fixture-" not in serialized
print("ok")
PY
)"; then
  fail_smoke "clone-db-export-contract" "Portable Clone database export contract is invalid" "3 resumable private chunks and read-only source evidence" "${EXPORT_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Clone database export OK: 60 rows exported in 3 resumable private chunks; schema/chunk hashes present; source rows unchanged; credentials absent from manifest.\n'
