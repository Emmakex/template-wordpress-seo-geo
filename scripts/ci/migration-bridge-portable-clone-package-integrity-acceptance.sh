#!/usr/bin/env bash
# Phase 10E.2A.3.3 resumable package manifest + integrity acceptance.

printf '[smoke] Checking Portable Clone package manifest + integrity.\n'

PACKAGE_RUNNER="$TMP_DIR/portable-clone-package-integrity-runner.php"
cat >"$PACKAGE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminClonePackageController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Clone\PackageBuilder;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

global $wpdb;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );
delete_option( PackageStateStore::OPTION_NAME );

$jobs = Plugin::clone_job_store();
$builder = Plugin::clone_package_builder();
if ( ! $jobs instanceof CloneJobStore || ! $builder instanceof PackageBuilder ) {
	throw new RuntimeException( 'Portable Clone package services are unavailable.' );
}

$workspace = new ExportWorkspace();

$prepare = static function ( string $job_id ) use ( $jobs, $workspace, $wpdb ): array {
	$job = $jobs->create( 'export', $job_id );
	if ( ! is_array( $job ) ) {
		throw new RuntimeException( 'Could not create package fixture job.' );
	}

	$source_fingerprint = hash( 'sha256', 'package-source-' . $job_id );

	$schema = $workspace->write( $job_id, 'database/tables/table-a/schema.sql', "CREATE TABLE fixture (id bigint);\n" );
	$chunk = $workspace->write( $job_id, 'database/tables/table-a/chunks/000000.json', '{"rows":[["MQ=="]]}');
	if ( ! is_array( $schema ) || ! is_array( $chunk ) ) {
		throw new RuntimeException( 'Could not write database payload fixture.' );
	}

	$table = array(
		'schema_version' => 1,
		'name' => $wpdb->prefix . 'fixture',
		'slug' => 'table-a',
		'schema' => array(
			'path' => 'database/tables/table-a/schema.sql',
			'bytes' => $schema['bytes'],
			'sha256' => $schema['sha256'],
		),
		'strategy' => 'primary-key',
		'cursor_column' => 'id',
		'order_columns' => array(),
		'columns' => array( 'id' ),
		'chunks' => array(
			array(
				'index' => 0,
				'path' => 'database/tables/table-a/chunks/000000.json',
				'row_count' => 1,
				'byte_count' => $chunk['bytes'],
				'sha256' => $chunk['sha256'],
			),
		),
		'row_count' => 1,
		'byte_count' => $chunk['bytes'],
		'complete' => true,
	);
	$workspace->write( $job_id, 'database/tables/table-a/table.json', wp_json_encode( $table ) . "\n" );

	$db_manifest = array(
		'schema_version' => 1,
		'package_id' => $job_id,
		'payload_class' => 'database',
		'source' => array( 'table_prefix' => $wpdb->prefix ),
		'tables' => array( $table ),
		'table_count' => 1,
		'row_count' => 1,
		'payload_bytes' => $chunk['bytes'],
		'chunk_count' => 1,
		'production_source_read_only' => true,
		'credentials_in_payload' => false,
		'contains_private_site_data' => true,
		'repository_safe' => false,
	);
	$db_written = $workspace->write( $job_id, 'database/manifest.json', wp_json_encode( $db_manifest ) . "\n" );
	if ( ! is_array( $db_written ) ) {
		throw new RuntimeException( 'Could not write database manifest fixture.' );
	}

	$file = $workspace->write( $job_id, 'files/uploads/a.txt', 'portable-file-payload' );
	if ( ! is_array( $file ) ) {
		throw new RuntimeException( 'Could not write file payload fixture.' );
	}
	$file_record = array(
		'root' => 'uploads',
		'relative_path' => 'a.txt',
		'payload_path' => 'files/uploads/a.txt',
		'byte_count' => $file['bytes'],
		'sha256' => $file['sha256'],
		'export_status' => 'copied',
	);
	$record_path = 'files-meta/uploads/' . hash( 'sha256', 'a.txt' ) . '.json';
	$workspace->write( $job_id, $record_path, wp_json_encode( $file_record ) . "\n" );

	$file_manifest = array(
		'schema_version' => 1,
		'payload_class' => 'files',
		'file_count' => 1,
		'payload_bytes' => $file['bytes'],
		'roots' => array(
			array(
				'id' => 'uploads',
				'file_count' => 1,
				'byte_count' => $file['bytes'],
			),
		),
		'source_fingerprint' => $source_fingerprint,
		'file_records' => array(
			'format' => 'one-json-record-per-file',
			'directory' => 'files-meta/',
		),
		'production_source_read_only' => true,
		'credentials_in_payload' => false,
		'contains_private_site_data' => true,
		'repository_safe' => false,
	);
	$files_written = $workspace->write( $job_id, 'files/manifest.json', wp_json_encode( $file_manifest ) . "\n" );
	if ( ! is_array( $files_written ) ) {
		throw new RuntimeException( 'Could not write files manifest fixture.' );
	}

	$now = gmdate( DATE_ATOM );
	$inventory = new CloneInventoryStore();
	$inventory->save(
		$job_id,
		array(
			'schema_version' => CloneInventoryStore::SCHEMA_VERSION,
			'job_id' => $job_id,
			'status' => 'complete',
			'database' => array(
				'table_prefix' => $wpdb->prefix,
				'table_count' => 1,
				'estimated_rows' => 1,
				'estimated_bytes' => 1024,
				'tables' => array(),
				'read_only' => true,
				'available' => true,
			),
			'roots' => array(),
			'root_index' => 0,
			'pending_dirs' => array(),
			'current_dir' => '',
			'after_name' => '',
			'file_count' => 1,
			'byte_count' => $file['bytes'],
			'excluded_count' => 0,
			'symlink_count' => 0,
			'unreadable_count' => 0,
			'fingerprint' => $source_fingerprint,
			'blockers' => array(),
			'started_at' => $now,
			'updated_at' => $now,
			'completed_at' => $now,
		)
	);

	$db_state = new ExportStateStore();
	$db_state->save(
		$job_id,
		array(
			'schema_version' => ExportStateStore::SCHEMA_VERSION,
			'job_id' => $job_id,
			'status' => 'complete',
			'table_index' => 1,
			'table_count' => 1,
			'current_table' => '',
			'strategy' => '',
			'cursor_value_b64' => '',
			'offset' => 0,
			'chunk_index' => 0,
			'row_count' => 1,
			'byte_count' => $chunk['bytes'],
			'chunk_count' => 1,
			'tables_completed' => 1,
			'database_manifest_hash' => $db_written['sha256'],
			'blockers' => array(),
			'started_at' => $now,
			'updated_at' => $now,
			'completed_at' => $now,
		)
	);

	$file_state = new FileExportStateStore();
	$file_state->save(
		$job_id,
		array(
			'schema_version' => FileExportStateStore::SCHEMA_VERSION,
			'job_id' => $job_id,
			'status' => 'complete',
			'root_index' => 1,
			'root_count' => 1,
			'pending_dirs' => array(),
			'current_dir' => '',
			'after_name' => '',
			'file_count' => 1,
			'byte_count' => $file['bytes'],
			'inventory_file_count' => 1,
			'inventory_byte_count' => $file['bytes'],
			'export_fingerprint' => $source_fingerprint,
			'files_manifest_hash' => $files_written['sha256'],
			'blockers' => array(),
			'started_at' => $now,
			'updated_at' => $now,
			'completed_at' => $now,
		)
	);

	return array(
		'source_fingerprint' => $source_fingerprint,
		'file_bytes' => $file['bytes'],
	);
};

$job_id = 'clone-package-good-0001';
$prepare( $job_id );
$state = $builder->start( $job_id );
if ( ! is_array( $state ) || 'running' !== $state['status'] || 'build' !== $state['stage'] ) {
	throw new RuntimeException( 'Could not start package integrity fixture.' );
}

$steps = 0;
while ( 'complete' !== ( $state['status'] ?? null ) && 80 > $steps ) {
	$state = $builder->advance( $job_id, 4, 1024 * 1024 );
	if ( ! is_array( $state ) ) {
		throw new RuntimeException( 'Package integrity step failed.' );
	}
	++$steps;
}
if ( 'complete' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Package integrity did not complete within bounded fixture steps: ' . wp_json_encode( $state ) );
}

$manifest_json = $workspace->read( $job_id, 'package/manifest.json' );
$manifest = is_string( $manifest_json ) ? json_decode( $manifest_json, true ) : null;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		PackageStateStore::OPTION_NAME
	)
);

$bad_job_id = 'clone-package-bad-0002';
$prepare( $bad_job_id );
$bad_state = $builder->start( $bad_job_id );
$bad_steps = 0;
while ( is_array( $bad_state ) && 'verify' !== ( $bad_state['stage'] ?? null ) && 50 > $bad_steps ) {
	$bad_state = $builder->advance( $bad_job_id, 50, 64 * 1024 * 1024 );
	++$bad_steps;
}
if ( ! is_array( $bad_state ) || 'verify' !== ( $bad_state['stage'] ?? null ) ) {
	throw new RuntimeException( 'Negative fixture did not reach verification pass.' );
}
$workspace->write( $bad_job_id, 'files/uploads/a.txt', 'tampered-after-build-pass' );
$bad_state = $builder->advance( $bad_job_id, 50, 64 * 1024 * 1024 );

echo wp_json_encode(
	array(
		'state' => $state,
		'steps' => $steps,
		'manifest' => $manifest,
		'package_state_autoload' => $autoload,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminClonePackageController::ACTION ),
		'negative_state' => $bad_state,
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$workspace->cleanup( $job_id );
$workspace->cleanup( $bad_job_id );
delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );
delete_option( PackageStateStore::OPTION_NAME );
PHP

docker cp "$PACKAGE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-package-integrity-runner.php \
  || fail_smoke "clone-package-runner-copy" "Could not copy Portable Clone package integrity runner" "runner copied" "docker cp failed"

if ! PACKAGE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-package-integrity-runner.php 2>"$TMP_DIR/portable-clone-package-integrity.stderr")"; then
  PACKAGE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-package-integrity.stderr" | head -c 2000)"
  fail_smoke "clone-package-runner" "Portable Clone package integrity runner failed" "JSON package report" "${PACKAGE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PACKAGE_JSON" >"$TMP_DIR/portable-clone-package-integrity.json"

if ! PACKAGE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-package-integrity.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

state = payload["state"]
manifest = payload["manifest"]
negative = payload["negative_state"]

assert state["schema_version"] == 1
assert state["status"] == "complete"
assert state["stage"] == "complete"
assert state["payload_file_count"] > 3
assert state["payload_file_count"] == state["verify_file_count"]
assert state["payload_byte_count"] == state["verify_byte_count"]
assert state["package_checksum"] == state["verification_checksum"]
assert re.fullmatch(r"[a-f0-9]{64}", state["package_checksum"])
assert re.fullmatch(r"[a-f0-9]{64}", state["package_manifest_hash"])
assert state["blockers"] == []
assert payload["steps"] >= 2
assert payload["package_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True

assert manifest["schema_version"] == 1
assert manifest["mode"] == "portable-clone-package"
assert manifest["package_id"] == "clone-package-good-0001"
assert manifest["operation"] == "export"
assert manifest["source"]["source_fingerprint"] == state["source_fingerprint"]
assert re.fullmatch(r"[a-f0-9]{64}", manifest["payload"]["database"]["manifest_sha256"])
assert re.fullmatch(r"[a-f0-9]{64}", manifest["payload"]["files"]["manifest_sha256"])
assert manifest["integrity"]["algorithm"] == "sha256"
assert manifest["integrity"]["checksum_scope"] == "workspace-excluding-package-metadata"
assert manifest["integrity"]["package_checksum"] == state["package_checksum"]
assert manifest["integrity"]["verification_pass"] is True
assert manifest["integrity"]["verified"] is True
assert manifest["safety"]["production_source_read_only"] is True
assert manifest["safety"]["credentials_in_manifest"] is False
assert manifest["safety"]["repository_safe"] is False
assert manifest["safety"]["delivery_ready"] is False

assert negative["status"] == "blocked"
assert "package-exported-payload-mismatch" in negative["blockers"]
print("ok")
PY
)"; then
  fail_smoke "clone-package-contract" "Portable Clone package manifest/integrity contract is invalid" "two-pass verified checksum plus tamper blocker" "${PACKAGE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Clone package integrity OK: deterministic checksum reproduced across two bounded passes; package manifest verified; tampered payload blocked.\n'
