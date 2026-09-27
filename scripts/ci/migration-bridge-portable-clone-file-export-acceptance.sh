#!/usr/bin/env bash
# Phase 10E.2A.3.2 resumable private file export acceptance.

printf '[smoke] Checking Portable Clone resumable file export.\n'

FILE_EXPORT_RUNNER="$TMP_DIR/portable-clone-file-export-runner.php"
cat >"$FILE_EXPORT_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneFileExportController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\FileExporter;
use SeoGeo\MigrationBridge\Clone\FileExportStateStore;
use SeoGeo\MigrationBridge\Plugin;

global $wpdb;

$job_id = 'clone-file-export-0001';
$fixture = wp_normalize_path( WP_CONTENT_DIR . '/seo-geo-file-export-fixture' );

$remove_tree = static function ( string $root ): void {
	if ( ! is_dir( $root ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}
	rmdir( $root );
};

$remove_tree( $fixture );
wp_mkdir_p( $fixture . '/uploads/cache' );
wp_mkdir_p( $fixture . '/uploads/2026/09' );
wp_mkdir_p( $fixture . '/plugins' );
wp_mkdir_p( $fixture . '/themes' );

$files = array(
	'uploads/a-photo.jpg'   => 'source-upload-a',
	'uploads/b-data.bin'    => "source-upload-b\x00binary",
	'uploads/2026/09/c-photo.jpg' => 'nested-source-upload-c',
	'plugins/alpha.php'     => "<?php\n// fixture alpha\n",
	'plugins/beta.css'      => '.fixture{display:block}',
	'themes/theme.css'      => 'body{font-family:sans-serif}',
	'themes/z-template.html'=> '<main>fixture</main>',
);
foreach ( $files as $relative => $bytes ) {
	$target = $fixture . '/' . $relative;
	if ( ! is_dir( dirname( $target ) ) ) {
		wp_mkdir_p( dirname( $target ) );
	}
	file_put_contents( $target, $bytes );
}
file_put_contents( $fixture . '/uploads/cache/ignored.txt', 'excluded-cache' );
file_put_contents( $fixture . '/plugins/source-debug.log', 'excluded-log' );

delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );

$jobs = Plugin::clone_job_store();
$exporter = Plugin::clone_file_exporter();
if ( ! $jobs instanceof CloneJobStore || ! $exporter instanceof FileExporter ) {
	throw new RuntimeException( 'Portable Clone file export services are unavailable.' );
}
$job = $jobs->create( 'export', $job_id );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create file export job.' );
}

$roots = array(
	array(
		'id' => 'uploads',
		'path' => $fixture . '/uploads',
		'file_count' => 3,
		'byte_count' => strlen( $files['uploads/a-photo.jpg'] ) + strlen( $files['uploads/b-data.bin'] ) + strlen( $files['uploads/2026/09/c-photo.jpg'] ),
		'read_only' => true,
	),
	array(
		'id' => 'plugins',
		'path' => $fixture . '/plugins',
		'file_count' => 2,
		'byte_count' => strlen( $files['plugins/alpha.php'] ) + strlen( $files['plugins/beta.css'] ),
		'read_only' => true,
	),
	array(
		'id' => 'themes',
		'path' => $fixture . '/themes',
		'file_count' => 2,
		'byte_count' => strlen( $files['themes/theme.css'] ) + strlen( $files['themes/z-template.html'] ),
		'read_only' => true,
	),
);

$fingerprint = hash( 'sha256', 'seo-geo-portable-clone-inventory-v1' );
$ordered_files = array(
	'uploads' => array( 'a-photo.jpg', 'b-data.bin', '2026/09/c-photo.jpg' ),
	'plugins' => array( 'alpha.php', 'beta.css' ),
	'themes'  => array( 'theme.css', 'z-template.html' ),
);
foreach ( $ordered_files as $root_id => $relative_paths ) {
	$prefix = $fixture . '/' . $root_id . '/';
	foreach ( $relative_paths as $relative ) {
		$path = $prefix . $relative;
		$record = 'file|' . $root_id . '|' . wp_normalize_path( $relative ) . '|' . (string) filesize( $path ) . '|' . hash_file( 'sha256', $path );
		$fingerprint = hash( 'sha256', $fingerprint . "\n" . $record );
	}
}

$now = gmdate( DATE_ATOM );
$inventory_store = new CloneInventoryStore();
$file_count = count( $files );
$byte_count = array_sum( array_map( 'strlen', array_values( $files ) ) );
$inventory_ok = $inventory_store->save(
	$job_id,
	array(
		'schema_version' => CloneInventoryStore::SCHEMA_VERSION,
		'job_id' => $job_id,
		'status' => 'complete',
		'database' => array(
			'table_prefix' => $wpdb->prefix,
			'table_count' => 0,
			'estimated_rows' => 0,
			'estimated_bytes' => 0,
			'tables' => array(),
			'read_only' => true,
			'available' => true,
		),
		'roots' => $roots,
		'root_index' => 3,
		'pending_dirs' => array(),
		'current_dir' => '',
		'after_name' => '',
		'file_count' => $file_count,
		'byte_count' => $byte_count,
		'excluded_count' => 2,
		'symlink_count' => 0,
		'unreadable_count' => 0,
		'fingerprint' => $fingerprint,
		'blockers' => array(),
		'started_at' => $now,
		'updated_at' => $now,
		'completed_at' => $now,
	)
);
if ( ! $inventory_ok ) {
	throw new RuntimeException( 'Could not save file export inventory fixture.' );
}

$database_store = new ExportStateStore();
$database_ok = $database_store->save(
	$job_id,
	array(
		'schema_version' => ExportStateStore::SCHEMA_VERSION,
		'job_id' => $job_id,
		'status' => 'complete',
		'table_index' => 0,
		'table_count' => 0,
		'current_table' => '',
		'strategy' => '',
		'cursor_value_b64' => '',
		'offset' => 0,
		'chunk_index' => 0,
		'row_count' => 0,
		'byte_count' => 0,
		'chunk_count' => 0,
		'tables_completed' => 0,
		'database_manifest_hash' => hash( 'sha256', 'empty-database-manifest' ),
		'blockers' => array(),
		'started_at' => $now,
		'updated_at' => $now,
		'completed_at' => $now,
	)
);
if ( ! $database_ok ) {
	throw new RuntimeException( 'Could not save completed database export fixture.' );
}

$drifted_upload = 'source-upload-expanded-live';
if ( strlen( $drifted_upload ) === strlen( $files['uploads/a-photo.jpg'] ) ) {
	throw new RuntimeException( 'Drift fixture must change the original byte count.' );
}
file_put_contents( $fixture . '/uploads/a-photo.jpg', $drifted_upload );

$state = $exporter->start( $job_id );
if ( ! is_array( $state ) || 'running' !== $state['status'] ) {
	throw new RuntimeException( 'Could not start file export.' );
}

$interrupted = false;
$interrupt_once = true;
$interrupt = static function ( string $option, mixed $old_value, mixed $value ) use ( &$interrupt_once, &$interrupted, $job_id ): void {
	unset( $old_value );
	if ( ! $interrupt_once || FileExportStateStore::OPTION_NAME !== $option || ! is_array( $value ) ) {
		return;
	}
	$candidate = is_array( $value[ $job_id ] ?? null ) ? $value[ $job_id ] : array();
	if ( 1 <= (int) ( $candidate['file_count'] ?? 0 ) ) {
		$interrupt_once = false;
		$interrupted = true;
		throw new RuntimeException( 'simulated-file-export-request-interruption' );
	}
};
add_action( 'updated_option', $interrupt, 10, 3 );
try {
	$exporter->advance( $job_id, 7, 1024 * 1024 );
} catch ( RuntimeException $exception ) {
	if ( 'simulated-file-export-request-interruption' !== $exception->getMessage() ) {
		throw $exception;
	}
}
remove_action( 'updated_option', $interrupt, 10 );
$checkpointed_state = $exporter->snapshot( $job_id );
if ( ! $interrupted || ! is_array( $checkpointed_state ) || 1 > (int) ( $checkpointed_state['file_count'] ?? 0 ) ) {
	throw new RuntimeException( 'File export did not retain a durable checkpoint across the simulated interruption.' );
}
$state = $checkpointed_state;

$steps = 0;
while ( 'complete' !== ( $state['status'] ?? null ) && 20 > $steps ) {
	$state = $exporter->advance( $job_id, 2, 1024 * 1024 );
	if ( ! is_array( $state ) ) {
		throw new RuntimeException( 'File export step failed.' );
	}
	++$steps;
}
if ( 'complete' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'File export did not complete within bounded fixture steps: ' . wp_json_encode( $state ) );
}

$recovery_file_count_before = (int) ( $state['file_count'] ?? 0 );
$recovery_request_sequence_before = (int) ( $state['request_sequence'] ?? 0 );
$legacy_blocked_state = $state;
$legacy_blocked_state['status'] = 'blocked';
$legacy_blocked_state['blockers'] = array();
// Real 0.8.39 state can retain the terminal last_action while blockers metadata is absent after normalization/job recovery.
$legacy_blocked_state['last_action'] = 'source-files-changed-since-inventory';
$legacy_blocked_state['source_drift'] = false;
$legacy_blocked_state['inventory_hash_match'] = true;
$legacy_blocked_state['files_manifest_hash'] = '';
if ( ! ( new FileExportStateStore() )->save( $job_id, $legacy_blocked_state ) ) {
	throw new RuntimeException( 'Could not persist legacy blocked file-export fixture.' );
}
$jobs->transition( $job_id, 'failed-terminal', 'source-files-changed-since-inventory' );
$state = $exporter->advance( $job_id, 7, 1024 * 1024 );
if ( ! is_array( $state ) || 'complete' !== ( $state['status'] ?? null ) ) {
	throw new RuntimeException( 'Could not recover fingerprint-only legacy blocked export.' );
}
$legacy_block_recovered_without_recopy = (
	$recovery_file_count_before === (int) ( $state['file_count'] ?? -1 )
	&& $recovery_request_sequence_before === (int) ( $state['request_sequence'] ?? -1 )
);

$workspace = new ExportWorkspace();
$manifest_json = $workspace->read( $job_id, 'files/manifest.json' );
$manifest = is_string( $manifest_json ) ? json_decode( $manifest_json, true ) : null;
$copied_upload = $workspace->read( $job_id, 'files/uploads/a-photo.jpg' );
$excluded_cache = $workspace->read( $job_id, 'files/uploads/cache/ignored.txt' );
$excluded_log = $workspace->read( $job_id, 'files/plugins/source-debug.log' );
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		FileExportStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'state' => $state,
		'steps' => $steps,
		'interrupted' => $interrupted,
		'checkpointed_file_count' => (int) ( $checkpointed_state['file_count'] ?? 0 ),
		'legacy_block_recovered_without_recopy' => $legacy_block_recovered_without_recopy,
		'legacy_blocker_metadata_missing' => array() === ( $legacy_blocked_state['blockers'] ?? null ),
		'manifest' => $manifest,
		'copied_upload_matches' => $copied_upload === $drifted_upload,
		'excluded_cache_absent' => null === $excluded_cache,
		'excluded_log_absent' => null === $excluded_log,
		'source_upload_matches_drift' => file_get_contents( $fixture . '/uploads/a-photo.jpg' ) === $drifted_upload,
		'file_export_state_autoload' => $autoload,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneFileExportController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

$workspace->cleanup( $job_id );
$remove_tree( $fixture );
delete_option( CloneJobStore::OPTION_NAME );
delete_option( CloneInventoryStore::OPTION_NAME );
delete_option( ExportStateStore::OPTION_NAME );
delete_option( FileExportStateStore::OPTION_NAME );
PHP

docker cp "$FILE_EXPORT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-file-export-runner.php \
  || fail_smoke "clone-file-export-runner-copy" "Could not copy Portable Clone file export runner" "runner copied" "docker cp failed"

if ! FILE_EXPORT_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-file-export-runner.php 2>"$TMP_DIR/portable-clone-file-export.stderr")"; then
  FILE_EXPORT_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-file-export.stderr" | head -c 1800)"
  fail_smoke "clone-file-export-runner" "Portable Clone file export runner failed" "JSON export report" "${FILE_EXPORT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$FILE_EXPORT_JSON" >"$TMP_DIR/portable-clone-file-export.json"

if ! FILE_EXPORT_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-file-export.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

state = payload["state"]
manifest = payload["manifest"]

assert state["schema_version"] == 1
assert state["status"] == "complete"
assert state["file_count"] == 7
assert state["inventory_file_count"] == 7
assert state["byte_count"] != state["inventory_byte_count"]
assert re.fullmatch(r"[a-f0-9]{64}", state["export_fingerprint"])
assert re.fullmatch(r"[a-f0-9]{64}", state["files_manifest_hash"])
assert state["blockers"] == []
assert payload["steps"] >= 3
assert payload["interrupted"] is True
assert payload["checkpointed_file_count"] >= 1
assert payload["legacy_block_recovered_without_recopy"] is True
assert payload["legacy_blocker_metadata_missing"] is True
assert state["checkpoint_count"] >= 3
assert state["last_action"] == "complete"
assert state["source_drift"] is True
assert state["inventory_hash_match"] is False
assert state["inventory_byte_match"] is False
assert isinstance(state["recent_events"], list)
assert any("file-copied" in event for event in state["recent_events"])
assert payload["file_export_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["copied_upload_matches"] is True
assert payload["excluded_cache_absent"] is True
assert payload["excluded_log_absent"] is True
assert payload["source_upload_matches_drift"] is True

assert manifest["schema_version"] == 1
assert manifest["payload_class"] == "files"
assert manifest["file_count"] == 7
assert manifest["payload_bytes"] == state["byte_count"]
assert manifest["source_fingerprint"] == state["export_fingerprint"]
assert manifest["inventory_hash_match"] is False
assert manifest["inventory_byte_match"] is False
assert manifest["inventory_payload_bytes"] == state["inventory_byte_count"]
assert manifest["payload_bytes"] != manifest["inventory_payload_bytes"]
assert manifest["source_drift"] is True
assert manifest["inventory_fingerprint"] != manifest["source_fingerprint"]
assert [root["id"] for root in manifest["roots"]] == ["uploads", "plugins", "themes"]
assert manifest["file_records"]["format"] == "one-json-record-per-file"
assert manifest["production_source_read_only"] is True
assert manifest["credentials_in_payload"] is False
assert manifest["contains_private_site_data"] is True
assert manifest["repository_safe"] is False

serialized = json.dumps(manifest)
assert "source-upload-a" not in serialized
assert "fixture alpha" not in serialized
print("ok")
PY
)"; then
  fail_smoke "clone-file-export-contract" "Portable Clone file export contract is invalid" "7 verified private file copies with durable interruption recovery, legacy blocked-state recovery without recopy, live-source byte drift capture, hashes and exclusions" "${FILE_EXPORT_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Clone file export OK: 7 accepted files copied with durable mid-request checkpoints; nested traversal, interruption recovery, legacy blocked-state recovery without recopy and same-size live-source drift capture passed; exclusions preserved.\n'
