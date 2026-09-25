#!/usr/bin/env bash
# Phase 10E.2A.4.1 Portable Import private intake + destination preflight acceptance.

printf '[smoke] Checking Portable Import intake + destination preflight.\n'

IMPORT_RUNNER="$TMP_DIR/portable-clone-import-preflight-runner.php"
cat >"$IMPORT_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
update_option( 'blog_public', '0' );

if ( ! defined( 'SEO_GEO_MIGRATION_SANDBOX' ) ) {
	define( 'SEO_GEO_MIGRATION_SANDBOX', true );
}
if ( ! defined( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE' ) ) {
	define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
}
if ( ! defined( 'SEO_GEO_MIGRATION_BACKUPS_READY' ) ) {
	define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
}

$jobs = Plugin::clone_job_store();
$preflight = Plugin::clone_import_preflight();
if ( ! $jobs instanceof CloneJobStore || ! $preflight instanceof ImportPreflight ) {
	throw new RuntimeException( 'Portable Import preflight services are unavailable.' );
}

$workspace = new ExportWorkspace();

/**
 * Build one private Portable Clone fixture archive.
 *
 * @return array{path:string,sha256:string,bytes:int}
 */
$build_archive = static function ( string $source_job_id, bool $tamper_database_reference = false ) use ( $workspace ): array {
	$workspace->cleanup( $source_job_id );
	$workspace->delete_delivery_archive( $source_job_id );

	$database_manifest = array(
		'schema_version'              => 1,
		'package_id'                  => $source_job_id,
		'payload_class'               => 'database',
		'source'                      => array(
			'home_url'          => 'https://source.example.test/',
			'site_url'          => 'https://source.example.test/',
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'table_prefix'      => 'wp_',
		),
		'tables'                      => array(),
		'table_count'                 => 0,
		'row_count'                   => 0,
		'payload_bytes'               => 0,
		'chunk_count'                 => 0,
		'production_source_read_only' => true,
		'credentials_in_payload'      => false,
		'contains_private_site_data'  => true,
		'repository_safe'             => false,
		'generated_at'                => gmdate( DATE_ATOM ),
	);
	$database_json = wp_json_encode( $database_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	$database_written = $workspace->write( $source_job_id, 'database/manifest.json', $database_json );
	if ( ! is_array( $database_written ) ) {
		throw new RuntimeException( 'Could not write import database manifest fixture.' );
	}

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 1,
		'payload_bytes'               => strlen( 'portable-import-file' ),
		'roots'                       => array(
			array(
				'id'         => 'uploads',
				'file_count' => 1,
				'byte_count' => strlen( 'portable-import-file' ),
			),
		),
		'source_fingerprint'          => hash( 'sha256', 'portable-import-source-fixture' ),
		'file_records'                => array(
			'format'    => 'one-json-record-per-file',
			'directory' => 'files-meta/',
		),
		'production_source_read_only' => true,
		'credentials_in_payload'      => false,
		'contains_private_site_data'  => true,
		'repository_safe'             => false,
		'generated_at'                => gmdate( DATE_ATOM ),
	);
	$files_json = wp_json_encode( $files_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	$files_written = $workspace->write( $source_job_id, 'files/manifest.json', $files_json );
	if ( ! is_array( $files_written ) ) {
		throw new RuntimeException( 'Could not write import files manifest fixture.' );
	}

	if ( ! is_array( $workspace->write( $source_job_id, 'files/uploads/import-fixture.txt', 'portable-import-file' ) ) ) {
		throw new RuntimeException( 'Could not write import payload fixture.' );
	}

	$database_hash = $tamper_database_reference
		? hash( 'sha256', 'deliberately-wrong-database-manifest-reference' )
		: (string) $database_written['sha256'];

	$package_manifest = array(
		'schema_version' => 1,
		'mode'           => 'portable-clone-package',
		'package_id'     => $source_job_id,
		'operation'      => 'export',
		'source'         => array(
			'home_url'           => 'https://source.example.test/',
			'site_url'           => 'https://source.example.test/',
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'source_fingerprint' => hash( 'sha256', 'portable-import-source-fixture' ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => $database_hash,
				'row_count'       => 0,
				'chunk_count'     => 0,
				'payload_bytes'   => 0,
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files_written['sha256'],
				'file_count'      => 1,
				'payload_bytes'   => strlen( 'portable-import-file' ),
			),
		),
		'integrity'      => array(
			'algorithm'          => 'sha256',
			'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
			'checksum_scope'     => 'workspace-excluding-package-metadata',
			'payload_file_count' => 3,
			'payload_bytes'      => strlen( $database_json ) + strlen( $files_json ) + strlen( 'portable-import-file' ),
			'package_checksum'   => hash( 'sha256', 'portable-import-package-checksum-fixture' ),
			'verification_pass'  => true,
			'verified'           => true,
		),
		'safety'         => array(
			'production_source_read_only' => true,
			'credentials_in_manifest'     => false,
			'contains_private_site_data'  => true,
			'repository_safe'             => false,
			'delivery_ready'              => true,
		),
		'delivery'       => array(
			'format'             => 'zip',
			'authenticated_only' => true,
			'public_url'         => false,
			'retention_hours'    => 24,
		),
		'generated_at'   => gmdate( DATE_ATOM ),
	);
	$package_json = wp_json_encode( $package_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	if ( ! is_array( $workspace->write( $source_job_id, 'package/manifest.json', $package_json ) ) ) {
		throw new RuntimeException( 'Could not write import package manifest fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Import source workspace unavailable.' );
	}

	$files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $iterator as $item ) {
		if ( ! $item->isFile() || $item->isLink() ) {
			continue;
		}
		$absolute = wp_normalize_path( $item->getPathname() );
		$relative = ltrim( substr( $absolute, strlen( trailingslashit( wp_normalize_path( $root ) ) ) ), '/' );
		if ( '' !== $relative ) {
			$files[] = $relative;
		}
	}
	sort( $files, SORT_STRING );

	if ( ! $workspace->reset_delivery_archive( $source_job_id ) ) {
		throw new RuntimeException( 'Could not reset import source delivery archive.' );
	}
	foreach ( array_chunk( $files, 3 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append import source archive batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize import source archive.' );
	}

	return $archive;
};

$good_archive = $build_archive( 'clone-import-source-good-0001' );

$missing_auth_job = $jobs->create( 'import', 'clone-import-noauth-0001' );
if ( ! is_array( $missing_auth_job ) ) {
	throw new RuntimeException( 'Could not create missing-authorization import job.' );
}
if ( ! is_array( $preflight->stage( 'clone-import-noauth-0001', $good_archive['path'] ) ) ) {
	throw new RuntimeException( 'Could not privately stage import ZIP.' );
}
$missing_auth = $preflight->validate( 'clone-import-noauth-0001' );
if ( ! is_array( $missing_auth ) ) {
	throw new RuntimeException( 'Missing-authorization preflight did not return state.' );
}

if ( ! defined( ImportPreflight::TARGET_AUTHORIZED_MARKER ) ) {
	define( ImportPreflight::TARGET_AUTHORIZED_MARKER, true );
}

$good_job = $jobs->create( 'import', 'clone-import-good-0001' );
if ( ! is_array( $good_job ) ) {
	throw new RuntimeException( 'Could not create positive import job.' );
}
$good_staged = $preflight->stage( 'clone-import-good-0001', $good_archive['path'] );
$good = $preflight->validate( 'clone-import-good-0001' );
if ( ! is_array( $good_staged ) || ! is_array( $good ) ) {
	throw new RuntimeException( 'Positive import preflight could not complete.' );
}

$tampered_archive = $build_archive( 'clone-import-source-badhash-0001', true );
$tampered_job = $jobs->create( 'import', 'clone-import-badhash-0001' );
if ( ! is_array( $tampered_job ) ) {
	throw new RuntimeException( 'Could not create tampered import job.' );
}
if ( ! is_array( $preflight->stage( 'clone-import-badhash-0001', $tampered_archive['path'] ) ) ) {
	throw new RuntimeException( 'Could not stage tampered import ZIP.' );
}
$tampered = $preflight->validate( 'clone-import-badhash-0001' );
if ( ! is_array( $tampered ) ) {
	throw new RuntimeException( 'Tampered import preflight did not return state.' );
}

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportStateStore::OPTION_NAME
	)
);

$staged_info = $workspace->import_archive_info( 'clone-import-good-0001' );
$good_root   = $workspace->root_path( 'clone-import-good-0001' );

echo wp_json_encode(
	array(
		'missing_auth' => $missing_auth,
		'good' => $good,
		'tampered' => $tampered,
		'staged_info' => $staged_info,
		'staged_private' => is_array( $staged_info )
			&& is_string( $good_root )
			&& str_starts_with( wp_normalize_path( $staged_info['path'] ), trailingslashit( wp_normalize_path( $good_root ) ) ),
		'import_state_autoload' => $autoload,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneImportController::ACTION ),
		'public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

foreach (
	array(
		'clone-import-noauth-0001',
		'clone-import-good-0001',
		'clone-import-badhash-0001',
		'clone-import-source-good-0001',
		'clone-import-source-badhash-0001',
	) as $cleanup_job
) {
	$workspace->cleanup( $cleanup_job );
	$workspace->delete_delivery_archive( $cleanup_job );
}
delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
PHP

docker cp "$IMPORT_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-preflight-runner.php \
  || fail_smoke "clone-import-preflight-runner-copy" "Could not copy Portable Import preflight runner" "runner copied" "docker cp failed"

if ! IMPORT_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-preflight-runner.php 2>"$TMP_DIR/portable-clone-import-preflight.stderr")"; then
  IMPORT_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-preflight.stderr" | head -c 2400)"
  fail_smoke "clone-import-preflight-runner" "Portable Import preflight runner failed" "JSON import report" "${IMPORT_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_JSON" >"$TMP_DIR/portable-clone-import-preflight.json"

if ! IMPORT_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-preflight.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

missing = payload["missing_auth"]
assert missing["status"] == "blocked"
assert "import-target-authorization-missing" in missing["blockers"]
assert missing["restore_allowed"] is False
assert missing["full_payload_verified"] is False

good = payload["good"]
assert good["schema_version"] == 1
assert good["status"] == "preflight-ready"
assert good["blockers"] == []
assert "full-payload-checksum-pending" in good["advisories"]
assert good["manifest_contract_valid"] is True
assert good["child_manifest_hashes_valid"] is True
assert good["target_authorized"] is True
assert good["search_visibility_disabled"] is True
assert good["outbound_safe"] is True
assert good["backups_ready"] is True
assert good["full_payload_verified"] is False
assert good["restore_allowed"] is False
assert good["archive_bytes"] > 0
assert re.fullmatch(r"[a-f0-9]{64}", good["archive_sha256"])
assert re.fullmatch(r"[a-f0-9]{64}", good["package_manifest_sha256"])
assert re.fullmatch(r"[a-f0-9]{64}", good["package_checksum"])
assert good["archive_entry_count"] >= 3
assert good["disk_free_bytes"] is not None
assert good["disk_free_bytes"] >= good["disk_required_bytes"]

bad = payload["tampered"]
assert bad["status"] == "blocked"
assert "import-database-manifest-hash-mismatch" in bad["blockers"]
assert bad["restore_allowed"] is False
assert bad["full_payload_verified"] is False

assert payload["staged_private"] is True
assert payload["staged_info"]["bytes"] == good["archive_bytes"]
assert payload["staged_info"]["sha256"] == good["archive_sha256"]
assert payload["import_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-preflight-contract" "Portable Import intake/preflight contract is invalid" "private staging + package/destination safety + restore disabled" "${IMPORT_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import preflight OK: ZIP staged privately, unsafe destination auth blocked, package/child manifests verified, tampered child hash rejected, restore remained disabled.\n'
