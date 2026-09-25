#!/usr/bin/env bash
# Phase 10E.2A.4.2 Portable Import full payload verification acceptance.

printf '[smoke] Checking resumable Portable Import private extraction + full checksum replay.\n'

IMPORT_PAYLOAD_RUNNER="$TMP_DIR/portable-clone-import-payload-runner.php"
cat >"$IMPORT_PAYLOAD_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportPayloadController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
update_option( 'blog_public', '0' );

foreach (
	array(
		'SEO_GEO_MIGRATION_SANDBOX'                  => true,
		'SEO_GEO_MIGRATION_OUTBOUND_SAFE'            => true,
		'SEO_GEO_MIGRATION_BACKUPS_READY'            => true,
		'SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED' => true,
	) as $constant => $value
) {
	if ( ! defined( $constant ) ) {
		define( $constant, $value );
	}
}

$jobs      = Plugin::clone_job_store();
$preflight = Plugin::clone_import_preflight();
$verifier  = Plugin::clone_import_payload_verifier();
$workspace = new ExportWorkspace();

if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
) {
	throw new RuntimeException( 'Portable Import payload verification services are unavailable.' );
}

/**
 * Replay the exact Portable Clone lexicographic BFS checksum on one source workspace.
 *
 * @return array{checksum:string,files:int,bytes:int}
 */
$workspace_checksum = static function ( string $root ): array {
	$seed         = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
	$checksum     = $seed;
	$file_count   = 0;
	$byte_count   = 0;
	$pending_dirs = array( '' );

	while ( array() !== $pending_dirs ) {
		$current = (string) array_shift( $pending_dirs );
		$path    = rtrim( wp_normalize_path( $root ), '/' ) . ( '' === $current ? '' : '/' . $current );
		$entries = scandir( $path, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			throw new RuntimeException( 'Could not scan source fixture workspace.' );
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$relative = '' === $current ? $entry : $current . '/' . $entry;
			$absolute = rtrim( wp_normalize_path( $root ), '/' ) . '/' . $relative;

			if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
				continue;
			}
			if ( is_link( $absolute ) ) {
				throw new RuntimeException( 'Fixture workspace contains an unexpected symlink.' );
			}
			if ( is_dir( $absolute ) ) {
				$pending_dirs[] = $relative;
				continue;
			}
			if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
				throw new RuntimeException( 'Fixture payload is unreadable.' );
			}

			$bytes = filesize( $absolute );
			$hash  = hash_file( 'sha256', $absolute );
			if ( false === $bytes || false === $hash ) {
				throw new RuntimeException( 'Fixture payload could not be hashed.' );
			}

			$record    = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash;
			$checksum  = hash( 'sha256', $checksum . "\n" . $record );
			++$file_count;
			$byte_count += (int) $bytes;
		}
	}

	return array(
		'checksum' => $checksum,
		'files'    => $file_count,
		'bytes'    => $byte_count,
	);
};

/**
 * Build one Portable Clone ZIP whose child manifests are valid.
 *
 * @return array{path:string,sha256:string,bytes:int,checksum:string}
 */
$build_archive = static function ( string $source_job_id, bool $wrong_checksum = false ) use ( $workspace, $workspace_checksum ): array {
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
	$database = $workspace->write( $source_job_id, 'database/manifest.json', $database_json );
	if ( ! is_array( $database ) ) {
		throw new RuntimeException( 'Could not write database manifest fixture.' );
	}

	$file_body = 'portable-import-payload-' . $source_job_id;
	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 1,
		'payload_bytes'               => strlen( $file_body ),
		'roots'                       => array(
			array(
				'id'         => 'uploads',
				'file_count' => 1,
				'byte_count' => strlen( $file_body ),
			),
		),
		'source_fingerprint'          => hash( 'sha256', 'payload-fixture-' . $source_job_id ),
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
	$files = $workspace->write( $source_job_id, 'files/manifest.json', $files_json );
	if ( ! is_array( $files ) || ! is_array( $workspace->write( $source_job_id, 'files/uploads/payload.txt', $file_body ) ) ) {
		throw new RuntimeException( 'Could not write files payload fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Source fixture workspace is unavailable.' );
	}

	$integrity = $workspace_checksum( $root );
	$declared_checksum = $wrong_checksum
		? hash( 'sha256', 'deliberately-wrong-package-checksum-' . $source_job_id )
		: $integrity['checksum'];

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
			'source_fingerprint' => hash( 'sha256', 'payload-fixture-' . $source_job_id ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $database['sha256'],
				'row_count'       => 0,
				'chunk_count'     => 0,
				'payload_bytes'   => 0,
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files['sha256'],
				'file_count'      => 1,
				'payload_bytes'   => strlen( $file_body ),
			),
		),
		'integrity'      => array(
			'algorithm'          => 'sha256',
			'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
			'checksum_scope'     => 'workspace-excluding-package-metadata',
			'payload_file_count' => $integrity['files'],
			'payload_bytes'      => $integrity['bytes'],
			'package_checksum'   => $declared_checksum,
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
		throw new RuntimeException( 'Could not write package manifest fixture.' );
	}

	$archive_files = array();
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
			$archive_files[] = $relative;
		}
	}
	sort( $archive_files, SORT_STRING );

	if ( ! $workspace->reset_delivery_archive( $source_job_id ) ) {
		throw new RuntimeException( 'Could not reset fixture delivery archive.' );
	}
	foreach ( array_chunk( $archive_files, 3 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append fixture archive batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize fixture archive.' );
	}
	$archive['checksum'] = $declared_checksum;

	return $archive;
};

$run_until_terminal = static function ( string $job_id ) use ( $verifier ): array {
	$state = null;
	for ( $iteration = 0; $iteration < 100; ++$iteration ) {
		$state = $verifier->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $state ) ) {
			throw new RuntimeException( 'Payload verification returned no state.' );
		}
		if ( in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}
	}
	throw new RuntimeException( 'Payload verification did not reach a terminal state.' );
};

$good_archive = $build_archive( 'clone-import-payload-source-good-0001', false );
$good_job = $jobs->create( 'import', 'clone-import-payload-good-0001' );
if (
	! is_array( $good_job )
	|| ! is_array( $preflight->stage( 'clone-import-payload-good-0001', $good_archive['path'] ) )
) {
	throw new RuntimeException( 'Could not stage positive payload fixture.' );
}
$good_preflight = $preflight->validate( 'clone-import-payload-good-0001' );
if ( ! is_array( $good_preflight ) || 'preflight-ready' !== ( $good_preflight['status'] ?? null ) ) {
	throw new RuntimeException( 'Positive payload fixture did not pass preflight.' );
}
$good_mid = $verifier->advance( 'clone-import-payload-good-0001', 1, 1048576 );
$good_payload = $run_until_terminal( 'clone-import-payload-good-0001' );
$good_import = ( new ImportStateStore() )->get( 'clone-import-payload-good-0001' );

$drift_archive = $build_archive( 'clone-import-payload-source-drift-0001', false );
$drift_job = $jobs->create( 'import', 'clone-import-payload-drift-0001' );
if (
	! is_array( $drift_job )
	|| ! is_array( $preflight->stage( 'clone-import-payload-drift-0001', $drift_archive['path'] ) )
) {
	throw new RuntimeException( 'Could not stage destination-drift payload fixture.' );
}
$drift_preflight = $preflight->validate( 'clone-import-payload-drift-0001' );
if ( ! is_array( $drift_preflight ) || 'preflight-ready' !== ( $drift_preflight['status'] ?? null ) ) {
	throw new RuntimeException( 'Destination-drift fixture did not pass initial preflight.' );
}
update_option( 'blog_public', '1' );
$drift_payload = $run_until_terminal( 'clone-import-payload-drift-0001' );
$drift_import = ( new ImportStateStore() )->get( 'clone-import-payload-drift-0001' );
update_option( 'blog_public', '0' );

$bad_archive = $build_archive( 'clone-import-payload-source-bad-0001', true );
$bad_job = $jobs->create( 'import', 'clone-import-payload-bad-0001' );
if (
	! is_array( $bad_job )
	|| ! is_array( $preflight->stage( 'clone-import-payload-bad-0001', $bad_archive['path'] ) )
) {
	throw new RuntimeException( 'Could not stage checksum-mismatch payload fixture.' );
}
$bad_preflight = $preflight->validate( 'clone-import-payload-bad-0001' );
if ( ! is_array( $bad_preflight ) || 'preflight-ready' !== ( $bad_preflight['status'] ?? null ) ) {
	throw new RuntimeException( 'Checksum-mismatch fixture must pass manifest preflight.' );
}
$bad_payload = $run_until_terminal( 'clone-import-payload-bad-0001' );
$bad_import = ( new ImportStateStore() )->get( 'clone-import-payload-bad-0001' );

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportPayloadStateStore::OPTION_NAME
	)
);

$good_root = $workspace->import_extraction_root( 'clone-import-payload-good-0001' );
$good_file = $workspace->import_extracted_file_info( 'clone-import-payload-good-0001', 'files/uploads/payload.txt' );

echo wp_json_encode(
	array(
		'good_mid'                  => $good_mid,
		'good_payload'              => $good_payload,
		'good_import'               => $good_import,
		'good_checksum'             => $good_archive['checksum'],
		'good_private_root'         => is_string( $good_root ) && str_contains( $good_root, '/import/extracted/' ),
		'good_extracted_file'       => $good_file,
		'drift_payload'             => $drift_payload,
		'drift_import'              => $drift_import,
		'bad_payload'               => $bad_payload,
		'bad_import'                => $bad_import,
		'payload_state_autoload'    => $autoload,
		'controller_registered'     => false !== has_action( 'admin_post_' . AdminCloneImportPayloadController::ACTION ),
		'public_controller_absent'  => false === has_action( 'admin_post_nopriv_' . AdminCloneImportPayloadController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

foreach (
	array(
		'clone-import-payload-good-0001',
		'clone-import-payload-drift-0001',
		'clone-import-payload-bad-0001',
		'clone-import-payload-source-good-0001',
		'clone-import-payload-source-drift-0001',
		'clone-import-payload-source-bad-0001',
	) as $cleanup_job
) {
	$workspace->cleanup( $cleanup_job );
	$workspace->delete_delivery_archive( $cleanup_job );
}
delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
PHP

docker cp "$IMPORT_PAYLOAD_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-payload-runner.php \
  || fail_smoke "clone-import-payload-runner-copy" "Could not copy Portable Import payload runner" "runner copied" "docker cp failed"

if ! IMPORT_PAYLOAD_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-payload-runner.php 2>"$TMP_DIR/portable-clone-import-payload.stderr")"; then
  IMPORT_PAYLOAD_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-payload.stderr" | head -c 3000)"
  fail_smoke "clone-import-payload-runner" "Portable Import payload runner failed" "JSON payload report" "${IMPORT_PAYLOAD_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_PAYLOAD_JSON" >"$TMP_DIR/portable-clone-import-payload.json"

if ! IMPORT_PAYLOAD_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-payload.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

mid = payload["good_mid"]
assert mid["status"] == "running"
assert mid["stage"] in ("extract", "verify")
assert mid["archive_cursor"] >= 1 or mid["verify_file_count"] >= 1

good = payload["good_payload"]
assert good["status"] == "complete"
assert good["stage"] == "complete"
assert good["verify_file_count"] == good["expected_file_count"]
assert good["verify_byte_count"] == good["expected_byte_count"]
assert good["verification_checksum"] == payload["good_checksum"]
assert re.fullmatch(r"[a-f0-9]{64}", good["verification_checksum"])

good_import = payload["good_import"]
assert good_import["status"] == "payload-verified"
assert good_import["full_payload_verified"] is True
assert good_import["restore_allowed"] is True
assert "full-payload-checksum-pending" not in good_import["advisories"]
assert "restore-runtime-guard-required" in good_import["advisories"]
assert payload["good_private_root"] is True
assert payload["good_extracted_file"]["bytes"] > 0

drift = payload["drift_payload"]
assert drift["status"] == "complete"
assert drift["stage"] == "complete"
drift_import = payload["drift_import"]
assert drift_import["status"] == "blocked"
assert drift_import["full_payload_verified"] is False
assert drift_import["restore_allowed"] is False
assert "import-search-visibility-not-disabled" in drift_import["blockers"]

bad = payload["bad_payload"]
assert bad["status"] == "blocked"
assert "import-payload-checksum-mismatch" in bad["blockers"]
bad_import = payload["bad_import"]
assert bad_import["status"] == "blocked"
assert bad_import["full_payload_verified"] is False
assert bad_import["restore_allowed"] is False
assert "import-payload-checksum-mismatch" in bad_import["blockers"]

assert payload["payload_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-payload-contract" "Portable Import full payload verification contract is invalid" "resumable private extraction + exact checksum replay + mismatch blocker" "${IMPORT_PAYLOAD_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import payload verification OK: bounded private extraction resumed, exact checksum unlocked restore eligibility only after fresh destination preflight, destination drift and checksum mismatch kept restore blocked.\n'
