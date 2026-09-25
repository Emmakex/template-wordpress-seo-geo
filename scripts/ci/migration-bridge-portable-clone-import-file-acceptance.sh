#!/usr/bin/env bash
# Phase 10E.2A.4.4 Portable Import verified staging-file restore acceptance.

printf '[smoke] Checking Portable Import verified staging-file restore.\n'

IMPORT_FILE_RUNNER="$TMP_DIR/portable-clone-import-file-runner.php"
cat >"$IMPORT_FILE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportFileController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

foreach (
	array(
		CloneJobStore::OPTION_NAME,
		ImportStateStore::OPTION_NAME,
		ImportPayloadStateStore::OPTION_NAME,
		ImportDatabaseStateStore::OPTION_NAME,
		ImportFileStateStore::OPTION_NAME,
	) as $option_name
) {
	delete_option( $option_name );
}
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

$jobs       = Plugin::clone_job_store();
$preflight  = Plugin::clone_import_preflight();
$verifier   = Plugin::clone_import_payload_verifier();
$db_restore = Plugin::clone_import_database_restorer();
$file_restore = Plugin::clone_import_file_restorer();
if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
	|| ! $db_restore instanceof ImportDatabaseRestorer
	|| ! $file_restore instanceof ImportFileRestorer
) {
	throw new RuntimeException( 'Portable Import file staging services are unavailable.' );
}

$workspace = new ExportWorkspace();

/**
 * Reproduce package checksum contract over workspace excluding package metadata.
 *
 * @return array{checksum:string,file_count:int,byte_count:int}
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
			throw new RuntimeException( 'Could not scan file-restore fixture workspace.' );
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$relative = '' === $current ? $entry : $current . '/' . $entry;
			if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
				continue;
			}

			$path = $root . $relative;
			if ( is_link( $path ) ) {
				throw new RuntimeException( 'Unexpected symlink in file-restore fixture.' );
			}
			if ( is_dir( $path ) ) {
				$pending[] = $relative;
				continue;
			}
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				throw new RuntimeException( 'Unreadable file-restore fixture entry.' );
			}

			$size = filesize( $path );
			$hash = hash_file( 'sha256', $path );
			if ( false === $size || false === $hash ) {
				throw new RuntimeException( 'Could not hash file-restore fixture entry.' );
			}

			$record   = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $size . '|' . $hash;
			$checksum = hash( 'sha256', $checksum . "\n" . $record );
			++$count;
			$bytes += (int) $size;
		}
	}

	return array(
		'checksum'   => $checksum,
		'file_count' => $count,
		'byte_count' => $bytes,
	);
};

/**
 * Build one valid Portable Clone package with uploads/plugins/themes payload.
 *
 * @return array{path:string,bytes:int,sha256:string}
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
		throw new RuntimeException( 'Could not write database manifest for file fixture.' );
	}

	$files = array(
		array( 'root' => 'uploads', 'relative' => '2026/file.txt', 'content' => "portable upload\n" ),
		array( 'root' => 'plugins', 'relative' => 'sample/plugin.php', 'content' => "<?php\n// portable plugin\n" ),
		array( 'root' => 'themes', 'relative' => 'sample/style.css', 'content' => "body{display:block}\n" ),
	);
	$root_stats = array(
		'uploads' => array( 'file_count' => 0, 'byte_count' => 0 ),
		'plugins' => array( 'file_count' => 0, 'byte_count' => 0 ),
		'themes'  => array( 'file_count' => 0, 'byte_count' => 0 ),
	);
	$total_bytes = 0;

	foreach ( $files as $file ) {
		$payload_path = 'files/' . $file['root'] . '/' . $file['relative'];
		$written      = $workspace->write( $source_job_id, $payload_path, $file['content'] );
		if ( ! is_array( $written ) ) {
			throw new RuntimeException( 'Could not write file payload fixture: ' . $payload_path );
		}

		$record = array(
			'root'          => $file['root'],
			'relative_path' => $file['relative'],
			'payload_path'  => $payload_path,
			'byte_count'    => (int) $written['bytes'],
			'sha256'        => (string) $written['sha256'],
			'export_status' => 'copied',
		);
		$record_json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		$record_path = 'files-meta/' . $file['root'] . '/' . hash( 'sha256', $file['relative'] ) . '.json';
		if ( ! is_array( $workspace->write( $source_job_id, $record_path, $record_json ) ) ) {
			throw new RuntimeException( 'Could not write file record fixture.' );
		}

		++$root_stats[ $file['root'] ]['file_count'];
		$root_stats[ $file['root'] ]['byte_count'] += (int) $written['bytes'];
		$total_bytes += (int) $written['bytes'];
	}

	$roots = array();
	foreach ( array( 'uploads', 'plugins', 'themes' ) as $root_id ) {
		$roots[] = array(
			'id'         => $root_id,
			'file_count' => $root_stats[ $root_id ]['file_count'],
			'byte_count' => $root_stats[ $root_id ]['byte_count'],
		);
	}

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => count( $files ),
		'payload_bytes'               => $total_bytes,
		'roots'                       => $roots,
		'source_fingerprint'          => hash( 'sha256', 'file-restore-source-fixture' ),
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
	$files_json    = wp_json_encode( $files_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	$files_written = $workspace->write( $source_job_id, 'files/manifest.json', $files_json );
	if ( ! is_array( $files_written ) ) {
		throw new RuntimeException( 'Could not write files manifest fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'File-restore fixture workspace unavailable.' );
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
			'source_fingerprint' => hash( 'sha256', 'file-restore-source-fixture' ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $database_written['sha256'],
				'row_count'       => 0,
				'chunk_count'     => 0,
				'payload_bytes'   => 0,
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files_written['sha256'],
				'file_count'      => count( $files ),
				'payload_bytes'   => $total_bytes,
			),
		),
		'integrity'      => array(
			'algorithm'          => 'sha256',
			'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
			'checksum_scope'     => 'workspace-excluding-package-metadata',
			'payload_file_count' => $payload['file_count'],
			'payload_bytes'      => $payload['byte_count'],
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
		throw new RuntimeException( 'Could not reset file-restore fixture archive.' );
	}
	foreach ( array_chunk( $archive_files, 5 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append file-restore fixture archive.' );
		}
	}

	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize file-restore fixture archive.' );
	}

	return $archive;
};

/**
 * Prepare import through payload and database staging.
 */
$prepare_import = static function ( string $job_id, array $archive ) use ( $jobs, $preflight, $verifier, $db_restore ): void {
	if (
		! is_array( $jobs->create( 'import', $job_id ) )
		|| ! is_array( $preflight->stage( $job_id, $archive['path'] ) )
	) {
		throw new RuntimeException( 'Could not stage file-restore import fixture.' );
	}

	$pre = $preflight->validate( $job_id );
	if ( ! is_array( $pre ) || 'preflight-ready' !== ( $pre['status'] ?? null ) ) {
		throw new RuntimeException( 'File-restore fixture did not pass preflight.' );
	}

	for ( $iteration = 0; $iteration < 160; ++$iteration ) {
		$payload = $verifier->advance( $job_id, 2, 1048576 );
		if ( ! is_array( $payload ) ) {
			throw new RuntimeException( 'File-restore fixture payload verifier returned no state.' );
		}
		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			throw new RuntimeException( 'File-restore fixture payload verification was blocked.' );
		}
	}

	for ( $iteration = 0; $iteration < 20; ++$iteration ) {
		$db = $db_restore->advance( $job_id, 10 );
		if ( ! is_array( $db ) ) {
			throw new RuntimeException( 'File-restore fixture database staging returned no state.' );
		}
		if ( 'complete' === ( $db['status'] ?? null ) ) {
			return;
		}
		if ( 'blocked' === ( $db['status'] ?? null ) ) {
			throw new RuntimeException( 'File-restore fixture database staging was blocked.' );
		}
	}

	throw new RuntimeException( 'File-restore fixture database staging did not complete.' );
};

$active_roots = array(
	'uploads' => trailingslashit( wp_upload_dir()['basedir'] ),
	'plugins' => trailingslashit( WP_PLUGIN_DIR ),
	'themes'  => trailingslashit( get_theme_root() ),
);
$sentinels = array();
foreach ( $active_roots as $root_id => $root_path ) {
	if ( ! is_dir( $root_path ) && ! wp_mkdir_p( $root_path ) ) {
		throw new RuntimeException( 'Could not prepare active root sentinel directory.' );
	}
	$path = $root_path . 'seo-geo-active-sentinel-' . $root_id . '.txt';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Disposable smoke-only sentinel outside plugin runtime.
	file_put_contents( $path, 'active-' . $root_id );
	$sentinels[ $root_id ] = array(
		'path' => $path,
		'hash' => hash_file( 'sha256', $path ),
	);
}

$archive = $build_archive( 'clone-import-files-source-0001' );

$good_job = 'clone-import-files-good-0001';
$prepare_import( $good_job, $archive );
$good = null;
for ( $iteration = 0; $iteration < 120; ++$iteration ) {
	$good = $file_restore->advance( $good_job, 1, 1048576 );
	if ( ! is_array( $good ) ) {
		throw new RuntimeException( 'Good file staging returned no state.' );
	}
	if ( in_array( $good['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}
if ( ! is_array( $good ) || 'complete' !== ( $good['status'] ?? null ) ) {
	throw new RuntimeException( 'Good file staging did not complete: ' . wp_json_encode( $good ) );
}

$staged = array();
foreach (
	array(
		'uploads' => '2026/file.txt',
		'plugins' => 'sample/plugin.php',
		'themes'  => 'sample/style.css',
	) as $root_id => $relative
) {
	$info = $workspace->import_staged_file_info( $good_job, $root_id, $relative );
	if ( ! is_array( $info ) ) {
		throw new RuntimeException( 'Expected staged file is missing.' );
	}
	$staged[ $root_id ] = array(
		'bytes'  => $info['bytes'],
		'sha256' => $info['sha256'],
	);
}

$active_unchanged = true;
foreach ( $sentinels as $sentinel ) {
	$current = hash_file( 'sha256', $sentinel['path'] );
	$active_unchanged = is_string( $current ) && hash_equals( (string) $sentinel['hash'], $current ) && $active_unchanged;
}

$bad_job = 'clone-import-files-tamper-0001';
$prepare_import( $bad_job, $archive );
$bad = null;
for ( $iteration = 0; $iteration < 80; ++$iteration ) {
	$bad = $file_restore->advance( $bad_job, 1, 1048576 );
	if ( ! is_array( $bad ) ) {
		throw new RuntimeException( 'Tamper fixture file staging returned no state.' );
	}
	if ( 'verify' === ( $bad['stage'] ?? null ) ) {
		break;
	}
	if ( 'blocked' === ( $bad['status'] ?? null ) ) {
		throw new RuntimeException( 'Tamper fixture blocked before verification pass.' );
	}
}
if ( ! is_array( $bad ) || 'verify' !== ( $bad['stage'] ?? null ) ) {
	throw new RuntimeException( 'Tamper fixture never reached verification pass.' );
}

$bad_root = $workspace->import_file_staging_root( $bad_job );
if ( ! is_string( $bad_root ) ) {
	throw new RuntimeException( 'Tamper fixture staging root unavailable.' );
}
$tamper_path = $bad_root . 'uploads/2026/file.txt';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Deliberate smoke-only corruption of job-owned staging file.
file_put_contents( $tamper_path, 'tampered-after-copy' );

$bad = $file_restore->advance( $bad_job, 1, 1048576 );
if ( ! is_array( $bad ) ) {
	throw new RuntimeException( 'Tampered verification returned no state.' );
}

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportFileStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'good' => $good,
		'bad' => $bad,
		'staged' => $staged,
		'active_unchanged' => $active_unchanged,
		'staging_root' => $workspace->import_file_staging_root( $good_job ),
		'import_state_autoload' => $autoload,
		'controller_registered' => false !== has_action( 'admin_post_' . AdminCloneImportFileController::ACTION ),
		'public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportFileController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

foreach ( $sentinels as $sentinel ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes disposable smoke-only sentinel.
	@unlink( $sentinel['path'] );
}
foreach ( array( $good_job, $bad_job, 'clone-import-files-source-0001' ) as $cleanup_job ) {
	$workspace->cleanup( $cleanup_job );
	$workspace->delete_delivery_archive( $cleanup_job );
}
foreach (
	array(
		CloneJobStore::OPTION_NAME,
		ImportStateStore::OPTION_NAME,
		ImportPayloadStateStore::OPTION_NAME,
		ImportDatabaseStateStore::OPTION_NAME,
		ImportFileStateStore::OPTION_NAME,
	) as $option_name
) {
	delete_option( $option_name );
}
PHP

docker cp "$IMPORT_FILE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-file-runner.php \
  || fail_smoke "clone-import-file-runner-copy" "Could not copy Portable Import file restore runner" "runner copied" "docker cp failed"

if ! IMPORT_FILE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-file-runner.php 2>"$TMP_DIR/portable-clone-import-file.stderr")"; then
  IMPORT_FILE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-file.stderr" | head -c 2600)"
  fail_smoke "clone-import-file-runner" "Portable Import staging-file runner failed" "JSON file-restore report" "${IMPORT_FILE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_FILE_JSON" >"$TMP_DIR/portable-clone-import-file.json"

if ! IMPORT_FILE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-file.json" <<'PY'
import json
import os
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good"]
assert good["schema_version"] == 1
assert good["status"] == "complete"
assert good["stage"] == "complete"
assert good["file_count"] == 3
assert good["verify_file_count"] == 3
assert good["file_count"] == good["expected_file_count"]
assert good["byte_count"] == good["expected_byte_count"]
assert good["verify_byte_count"] == good["expected_byte_count"]
assert good["active_roots_untouched"] is True
assert good["blockers"] == []
assert re.fullmatch(r"[a-f0-9]{64}", good["files_manifest_sha256"])
assert re.fullmatch(r"[a-f0-9]{64}", good["payload_archive_sha256"])

assert set(payload["staged"].keys()) == {"uploads", "plugins", "themes"}
for record in payload["staged"].values():
    assert record["bytes"] > 0
    assert re.fullmatch(r"[a-f0-9]{64}", record["sha256"])

assert payload["active_unchanged"] is True
assert "/import/staged-files/" in payload["staging_root"].replace("\\", "/")
assert payload["import_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True

bad = payload["bad"]
assert bad["status"] == "blocked"
assert "import-files-verification-failed" in bad["blockers"]
assert bad["active_roots_untouched"] is True

print("ok")
PY
)"; then
  fail_smoke "clone-import-file-contract" "Portable Import file staging contract is invalid" "private two-pass staging + active wp-content untouched + tamper rejection" "${IMPORT_FILE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import file restore OK: uploads/plugins/themes staged privately in bounded batches, second SHA-256 pass matched, active roots stayed unchanged, deliberate staged-file tamper was blocked.\n'
