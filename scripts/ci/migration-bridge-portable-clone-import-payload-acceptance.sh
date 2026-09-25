#!/usr/bin/env bash
# Phase 10E.2A.4.2 full payload verification + resumable private extraction acceptance.

printf '[smoke] Checking Portable Import full payload verification.\n'

PAYLOAD_RUNNER="$TMP_DIR/portable-clone-import-payload-runner.php"
cat >"$PAYLOAD_RUNNER" <<'PHP'
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

if ( ! defined( 'SEO_GEO_MIGRATION_SANDBOX' ) ) {
	define( 'SEO_GEO_MIGRATION_SANDBOX', true );
}
if ( ! defined( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE' ) ) {
	define( 'SEO_GEO_MIGRATION_OUTBOUND_SAFE', true );
}
if ( ! defined( 'SEO_GEO_MIGRATION_BACKUPS_READY' ) ) {
	define( 'SEO_GEO_MIGRATION_BACKUPS_READY', true );
}
if ( ! defined( ImportPreflight::TARGET_AUTHORIZED_MARKER ) ) {
	define( ImportPreflight::TARGET_AUTHORIZED_MARKER, true );
}

$jobs      = Plugin::clone_job_store();
$preflight = Plugin::clone_import_preflight();
$verifier  = Plugin::clone_import_payload_verifier();
if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
) {
	throw new RuntimeException( 'Portable Import payload services are unavailable.' );
}

$workspace = new ExportWorkspace();

/**
 * Reproduce the export checksum contract over one source workspace.
 *
 * @return array{checksum:string,count:int,bytes:int}
 */
$scan_payload = static function ( string $root ): array {
	$root     = trailingslashit( wp_normalize_path( $root ) );
	$pending  = array( '' );
	$checksum = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
	$count    = 0;
	$bytes    = 0;

	while ( array() !== $pending ) {
		$current  = (string) array_shift( $pending );
		$absolute = $root . ( '' === $current ? '' : $current . '/' );
		$entries  = scandir( $absolute, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			throw new RuntimeException( 'Could not scan payload fixture workspace.' );
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$relative = '' === $current ? $entry : $current . '/' . $entry;
			$path     = $root . $relative;

			if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
				continue;
			}
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$pending[] = $relative;
				continue;
			}
			if ( ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
				throw new RuntimeException( 'Unexpected fixture payload path: ' . $relative );
			}

			$file_bytes = filesize( $path );
			$hash       = hash_file( 'sha256', $path );
			if ( false === $file_bytes || false === $hash ) {
				throw new RuntimeException( 'Could not hash fixture payload: ' . $relative );
			}

			$record   = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $file_bytes . '|' . $hash;
			$checksum = hash( 'sha256', $checksum . "\n" . $record );
			++$count;
			$bytes += (int) $file_bytes;
		}
	}

	return array(
		'checksum' => $checksum,
		'count'    => $count,
		'bytes'    => $bytes,
	);
};

/**
 * Build one realistic accepted Portable Clone ZIP fixture.
 *
 * @return array{path:string,bytes:int,sha256:string,package_checksum:string,payload_count:int,payload_bytes:int}
 */
$build_archive = static function ( string $source_job_id ) use ( $workspace, $scan_payload ): array {
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
		throw new RuntimeException( 'Could not write payload database manifest fixture.' );
	}

	if ( ! is_array( $workspace->write( $source_job_id, 'database/tables/site/schema.sql', "CREATE TABLE fixture (id bigint);\n" ) ) ) {
		throw new RuntimeException( 'Could not write payload schema fixture.' );
	}
	if ( ! is_array( $workspace->write( $source_job_id, 'database/tables/site/chunks/000000.json', '{"rows":[["MQ=="]]}' . "\n" ) ) ) {
		throw new RuntimeException( 'Could not write payload chunk fixture.' );
	}

	$file_payload = 'portable-import-payload-file';
	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 1,
		'payload_bytes'               => strlen( $file_payload ),
		'roots'                       => array(
			array(
				'id'         => 'uploads',
				'file_count' => 1,
				'byte_count' => strlen( $file_payload ),
			),
		),
		'source_fingerprint'          => hash( 'sha256', 'portable-import-payload-source' ),
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
		throw new RuntimeException( 'Could not write payload files manifest fixture.' );
	}
	if ( ! is_array( $workspace->write( $source_job_id, 'files/uploads/import-fixture.txt', $file_payload ) ) ) {
		throw new RuntimeException( 'Could not write payload file fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Payload fixture workspace unavailable.' );
	}

	$payload = $scan_payload( $root );
	$package = array(
		'schema_version' => 1,
		'mode'           => 'portable-clone-package',
		'package_id'     => $source_job_id,
		'operation'      => 'export',
		'source'         => array(
			'home_url'           => 'https://source.example.test/',
			'site_url'           => 'https://source.example.test/',
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'source_fingerprint' => hash( 'sha256', 'portable-import-payload-source' ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $database_written['sha256'],
				'row_count'       => 1,
				'chunk_count'     => 1,
				'payload_bytes'   => strlen( $database_json ),
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files_written['sha256'],
				'file_count'      => 1,
				'payload_bytes'   => strlen( $file_payload ),
			),
		),
		'integrity'      => array(
			'algorithm'          => 'sha256',
			'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
			'checksum_scope'     => 'workspace-excluding-package-metadata',
			'payload_file_count' => $payload['count'],
			'payload_bytes'      => $payload['bytes'],
			'package_checksum'   => $payload['checksum'],
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
	$package_json = wp_json_encode( $package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	if ( ! is_array( $workspace->write( $source_job_id, 'package/manifest.json', $package_json ) ) ) {
		throw new RuntimeException( 'Could not write payload package manifest fixture.' );
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
		throw new RuntimeException( 'Could not reset payload fixture ZIP.' );
	}
	foreach ( array_chunk( $files, 4 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append payload fixture ZIP batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize payload fixture ZIP.' );
	}

	return array(
		'path'             => $archive['path'],
		'bytes'            => $archive['bytes'],
		'sha256'           => $archive['sha256'],
		'package_checksum' => $payload['checksum'],
		'payload_count'    => $payload['count'],
		'payload_bytes'    => $payload['bytes'],
	);
};

$good_archive = $build_archive( 'clone-import-payload-source-good-0001' );
$good_job = $jobs->create( 'import', 'clone-import-payload-good-0001' );
if ( ! is_array( $good_job ) ) {
	throw new RuntimeException( 'Could not create positive payload import job.' );
}
if ( ! is_array( $preflight->stage( 'clone-import-payload-good-0001', $good_archive['path'] ) ) ) {
	throw new RuntimeException( 'Could not stage positive payload archive.' );
}
$good_preflight = $preflight->validate( 'clone-import-payload-good-0001' );
if ( ! is_array( $good_preflight ) || 'preflight-ready' !== ( $good_preflight['status'] ?? null ) ) {
	throw new RuntimeException( 'Positive payload preflight was not ready: ' . wp_json_encode( $good_preflight ) );
}

$good_state = null;
$good_steps = 0;
while ( 120 > $good_steps ) {
	$good_state = $verifier->advance( 'clone-import-payload-good-0001', 2, 1024 * 1024 );
	if ( ! is_array( $good_state ) ) {
		throw new RuntimeException( 'Positive payload verifier returned no state.' );
	}
	if ( in_array( $good_state['status'] ?? null, array( 'verified', 'blocked' ), true ) ) {
		break;
	}
	++$good_steps;
}
$good_import = ( new ImportStateStore() )->get( 'clone-import-payload-good-0001' );
if ( ! is_array( $good_state ) || ! is_array( $good_import ) ) {
	throw new RuntimeException( 'Positive payload verification state unavailable.' );
}

$tamper_archive = $build_archive( 'clone-import-payload-source-tamper-0001' );
$tamper_job = $jobs->create( 'import', 'clone-import-payload-tamper-0001' );
if ( ! is_array( $tamper_job ) ) {
	throw new RuntimeException( 'Could not create tamper payload import job.' );
}
if ( ! is_array( $preflight->stage( 'clone-import-payload-tamper-0001', $tamper_archive['path'] ) ) {
	throw new RuntimeException( 'Could not stage tamper payload archive.' );
}
$tamper_preflight = $preflight->validate( 'clone-import-payload-tamper-0001' );
if ( ! is_array( $tamper_preflight ) || 'preflight-ready' !== ( $tamper_preflight['status'] ?? null ) ) {
	throw new RuntimeException( 'Tamper payload preflight was not ready.' );
}

$tamper_state = null;
for ( $step = 0; $step < 120; ++$step ) {
	$tamper_state = $verifier->advance( 'clone-import-payload-tamper-0001', 2, 1024 * 1024 );
	if ( ! is_array( $tamper_state ) ) {
		throw new RuntimeException( 'Tamper payload verifier returned no state.' );
	}
	if ( 'verify' === ( $tamper_state['stage'] ?? null ) ) {
		break;
	}
	if ( 'blocked' === ( $tamper_state['status'] ?? null ) ) {
		throw new RuntimeException( 'Tamper fixture blocked before deliberate payload mutation.' );
	}
}

$tamper_root = $workspace->import_payload_root( 'clone-import-payload-tamper-0001', false );
if ( ! is_string( $tamper_root ) ) {
	throw new RuntimeException( 'Tamper extracted payload root unavailable.' );
}
$tamper_file = $tamper_root . 'files/uploads/import-fixture.txt';
// Test-only deliberate corruption inside the job-owned private extracted payload.
if ( false === file_put_contents( $tamper_file, 'deliberately-mutated-private-payload' ) ) {
	throw new RuntimeException( 'Could not mutate private payload fixture.' );
}

for ( $step = 0; $step < 120; ++$step ) {
	$tamper_state = $verifier->advance( 'clone-import-payload-tamper-0001', 2, 1024 * 1024 );
	if ( ! is_array( $tamper_state ) ) {
		throw new RuntimeException( 'Tamper verification returned no state.' );
	}
	if ( 'blocked' === ( $tamper_state['status'] ?? null ) ) {
		break;
	}
}
$tamper_import = ( new ImportStateStore() )->get( 'clone-import-payload-tamper-0001' );

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportPayloadStateStore::OPTION_NAME
	)
);

$good_payload_root = $workspace->import_payload_root( 'clone-import-payload-good-0001', false );

echo wp_json_encode(
	array(
		'good_state'              => $good_state,
		'good_import'             => $good_import,
		'good_steps'              => $good_steps,
		'good_archive'            => $good_archive,
		'good_payload_root_private' => is_string( $good_payload_root )
			&& str_starts_with(
				wp_normalize_path( $good_payload_root ),
				trailingslashit( wp_normalize_path( $workspace->base_path() ) )
			),
		'tamper_state'            => $tamper_state,
		'tamper_import'           => $tamper_import,
		'payload_state_autoload'  => $autoload,
		'controller_registered'   => false !== has_action( 'admin_post_' . AdminCloneImportPayloadController::ACTION ),
		'public_controller_absent'=> false === has_action( 'admin_post_nopriv_' . AdminCloneImportPayloadController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

foreach (
	array(
		'clone-import-payload-good-0001',
		'clone-import-payload-tamper-0001',
		'clone-import-payload-source-good-0001',
		'clone-import-payload-source-tamper-0001',
	) as $cleanup_job
) {
	$workspace->cleanup( $cleanup_job );
	$workspace->delete_delivery_archive( $cleanup_job );
}
delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
PHP

docker cp "$PAYLOAD_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-payload-runner.php \
  || fail_smoke "clone-import-payload-runner-copy" "Could not copy Portable Import payload runner" "runner copied" "docker cp failed"

if ! PAYLOAD_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-payload-runner.php 2>"$TMP_DIR/portable-clone-import-payload.stderr")"; then
  PAYLOAD_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-payload.stderr" | head -c 2600)"
  fail_smoke "clone-import-payload-runner" "Portable Import payload runner failed" "JSON payload report" "${PAYLOAD_ERROR:-wp eval-file failed}"
fi

printf '%s' "$PAYLOAD_JSON" >"$TMP_DIR/portable-clone-import-payload.json"

if ! PAYLOAD_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-payload.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good_state"]
gate = payload["good_import"]
archive = payload["good_archive"]

assert good["schema_version"] == 1
assert good["status"] == "verified"
assert good["stage"] == "complete"
assert good["blockers"] == []
assert good["package_checksum"] == archive["package_checksum"]
assert good["verification_checksum"] == archive["package_checksum"]
assert good["expected_file_count"] == archive["payload_count"]
assert good["verified_file_count"] == archive["payload_count"]
assert good["expected_bytes"] == archive["payload_bytes"]
assert good["verified_bytes"] == archive["payload_bytes"]
assert good["extracted_file_count"] == good["archive_file_count"]
assert good["extracted_bytes"] > good["verified_bytes"]
assert re.fullmatch(r"[a-f0-9]{64}", good["archive_sha256"])
assert payload["good_steps"] >= 2
assert payload["good_payload_root_private"] is True

assert gate["status"] == "preflight-ready"
assert gate["full_payload_verified"] is True
assert gate["restore_allowed"] is True
assert gate["blockers"] == []
assert "full-payload-checksum-pending" not in gate["advisories"]

bad = payload["tamper_state"]
bad_gate = payload["tamper_import"]
assert bad["status"] == "blocked"
assert "import-payload-integrity-mismatch" in bad["blockers"]
assert bad_gate["full_payload_verified"] is False
assert bad_gate["restore_allowed"] is False

assert payload["payload_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-payload-contract" "Portable Import full payload verification contract is invalid" "bounded private extraction + exact checksum replay + restore gate" "${PAYLOAD_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import payload OK: private extraction resumed, exact package checksum replay opened restore gate, deliberate extracted-payload tamper remained blocked.\n'
