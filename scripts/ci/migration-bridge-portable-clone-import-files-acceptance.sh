#!/usr/bin/env bash
# Phase 10E.2A.4.4 Portable Import destination file-staging acceptance.

printf '[smoke] Checking Portable Import destination file staging restore.\n'

IMPORT_FILES_RUNNER="$TMP_DIR/portable-clone-import-files-runner.php"
cat >"$IMPORT_FILES_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportFileController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStagingWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
delete_option( ImportDatabaseStateStore::OPTION_NAME );
delete_option( ImportFileStateStore::OPTION_NAME );
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

$jobs       = Plugin::clone_job_store();
$preflight  = Plugin::clone_import_preflight();
$verifier   = Plugin::clone_import_payload_verifier();
$db_restore = Plugin::clone_import_database_restorer();
$file_restore = Plugin::clone_import_file_restorer();
$stage      = Plugin::clone_import_file_staging_workspace();
$workspace  = new ExportWorkspace();

if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
	|| ! $db_restore instanceof ImportDatabaseRestorer
	|| ! $file_restore instanceof ImportFileRestorer
	|| ! $stage instanceof ImportFileStagingWorkspace
) {
	throw new RuntimeException( 'Portable Import file staging services are unavailable.' );
}

$sentinel = 'seo-geo-import-active-sentinel-0818.txt';
$uploads  = wp_upload_dir();
$active_paths = array(
	'uploads' => trailingslashit( (string) $uploads['basedir'] ) . $sentinel,
	'plugins' => trailingslashit( WP_PLUGIN_DIR ) . $sentinel,
	'themes'  => trailingslashit( get_theme_root() ) . $sentinel,
);
$active_contents = array(
	'uploads' => 'active-upload-sentinel',
	'plugins' => 'active-plugin-sentinel',
	'themes'  => 'active-theme-sentinel',
);
$originals = array();

foreach ( $active_paths as $root_id => $path ) {
	$directory = dirname( $path );
	if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
		throw new RuntimeException( 'Could not prepare active file sentinel directory.' );
	}
	$originals[ $root_id ] = array(
		'exists'  => is_file( $path ),
		'content' => is_file( $path ) ? file_get_contents( $path ) : null,
	);
	if ( false === file_put_contents( $path, $active_contents[ $root_id ] ) ) {
		throw new RuntimeException( 'Could not write active file sentinel.' );
	}
}

/**
 * Replay exact Portable Clone lexicographic BFS checksum.
 *
 * @return array{checksum:string,files:int,bytes:int}
 */
$workspace_checksum = static function ( string $root ): array {
	$checksum     = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
	$file_count   = 0;
	$byte_count   = 0;
	$pending_dirs = array( '' );

	while ( array() !== $pending_dirs ) {
		$current = (string) array_shift( $pending_dirs );
		$path    = rtrim( wp_normalize_path( $root ), '/' ) . ( '' === $current ? '' : '/' . $current );
		$entries = scandir( $path, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			throw new RuntimeException( 'Could not scan file staging fixture workspace.' );
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
				throw new RuntimeException( 'File staging fixture contains an unexpected symlink.' );
			}
			if ( is_dir( $absolute ) ) {
				$pending_dirs[] = $relative;
				continue;
			}

			$bytes = filesize( $absolute );
			$hash  = hash_file( 'sha256', $absolute );
			if ( false === $bytes || false === $hash ) {
				throw new RuntimeException( 'Could not hash file staging fixture payload.' );
			}

			$record     = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash;
			$checksum   = hash( 'sha256', $checksum . "\n" . $record );
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
 * Build one valid Portable Clone ZIP with three root-collision sentinel files.
 *
 * @return array{path:string,sha256:string,bytes:int}
 */
$build_archive = static function ( string $source_job_id ) use ( $workspace, $workspace_checksum, $sentinel ): array {
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
			'table_prefix'      => 'src_',
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
		throw new RuntimeException( 'Could not write file staging database manifest fixture.' );
	}

	$incoming = array(
		'uploads' => 'incoming-upload-from-package',
		'plugins' => 'incoming-plugin-from-package',
		'themes'  => 'incoming-theme-from-package',
	);
	$roots = array();
	$file_bytes = 0;
	foreach ( $incoming as $root_id => $payload ) {
		$payload_path = 'files/' . $root_id . '/' . $sentinel;
		$written = $workspace->write( $source_job_id, $payload_path, $payload );
		if ( ! is_array( $written ) ) {
			throw new RuntimeException( 'Could not write file staging payload fixture.' );
		}

		$record = array(
			'root'          => $root_id,
			'relative_path' => $sentinel,
			'payload_path'  => $payload_path,
			'byte_count'    => (int) $written['bytes'],
			'sha256'        => (string) $written['sha256'],
			'export_status' => 'copied',
		);
		$record_json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $record_json ) ) {
			throw new RuntimeException( 'Could not encode file metadata fixture.' );
		}
		$record_path = 'files-meta/' . $root_id . '/' . hash( 'sha256', $sentinel ) . '.json';
		if ( ! is_array( $workspace->write( $source_job_id, $record_path, $record_json . "\n" ) ) ) {
			throw new RuntimeException( 'Could not write file metadata fixture.' );
		}

		$roots[] = array(
			'id'         => $root_id,
			'file_count' => 1,
			'byte_count' => (int) $written['bytes'],
		);
		$file_bytes += (int) $written['bytes'];
	}

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 3,
		'payload_bytes'               => $file_bytes,
		'roots'                       => $roots,
		'source_fingerprint'          => hash( 'sha256', 'file-staging-fixture-' . $source_job_id ),
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
	if ( ! is_array( $files ) ) {
		throw new RuntimeException( 'Could not write file staging files manifest fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'File staging source workspace unavailable.' );
	}
	$integrity = $workspace_checksum( $root );

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
			'source_fingerprint' => hash( 'sha256', 'file-staging-fixture-' . $source_job_id ),
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
				'file_count'      => 3,
				'payload_bytes'   => $file_bytes,
			),
		),
		'integrity'      => array(
			'algorithm'          => 'sha256',
			'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
			'checksum_scope'     => 'workspace-excluding-package-metadata',
			'payload_file_count' => $integrity['files'],
			'payload_bytes'      => $integrity['bytes'],
			'package_checksum'   => $integrity['checksum'],
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
		throw new RuntimeException( 'Could not write file staging package manifest fixture.' );
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
		throw new RuntimeException( 'Could not reset file staging fixture archive.' );
	}
	foreach ( array_chunk( $archive_files, 4 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append file staging fixture archive batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize file staging fixture archive.' );
	}

	return $archive;
};

/**
 * Stage, preflight, verify payload and complete empty database staging.
 *
 * @return array<string,mixed>
 */
$prepare_import = static function ( string $job_id, array $archive ) use ( $jobs, $preflight, $verifier, $db_restore ): array {
	if (
		! is_array( $jobs->create( 'import', $job_id ) )
		|| ! is_array( $preflight->stage( $job_id, $archive['path'] ) )
	) {
		throw new RuntimeException( 'Could not stage file restore import fixture.' );
	}

	$pre = $preflight->validate( $job_id );
	if ( ! is_array( $pre ) || 'preflight-ready' !== ( $pre['status'] ?? null ) ) {
		throw new RuntimeException( 'File restore fixture did not pass preflight.' );
	}

	for ( $iteration = 0; $iteration < 160; ++$iteration ) {
		$payload = $verifier->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $payload ) ) {
			throw new RuntimeException( 'File restore fixture payload verification returned no state.' );
		}
		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			throw new RuntimeException( 'File restore fixture payload verification was blocked.' );
		}
	}
	if ( ! isset( $payload ) || 'complete' !== ( $payload['status'] ?? null ) ) {
		throw new RuntimeException( 'File restore fixture payload verification did not finish.' );
	}

	for ( $iteration = 0; $iteration < 40; ++$iteration ) {
		$database = $db_restore->advance( $job_id, 10 );
		if ( ! is_array( $database ) ) {
			throw new RuntimeException( 'File restore fixture database staging returned no state.' );
		}
		if ( in_array( $database['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			break;
		}
	}
	if (
		! isset( $database )
		|| 'complete' !== ( $database['status'] ?? null )
		|| true !== ( $database['active_tables_untouched'] ?? false )
	) {
		throw new RuntimeException( 'File restore fixture database staging did not complete safely.' );
	}

	return $database;
};

/**
 * Run file staging to terminal state.
 *
 * @return array<string,mixed>
 */
$run_files = static function ( string $job_id ) use ( $file_restore ): array {
	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		$state = $file_restore->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $state ) ) {
			throw new RuntimeException( 'Destination file staging returned no state.' );
		}
		if ( in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}
	}

	throw new RuntimeException( 'Destination file staging did not reach a terminal state.' );
};

$good_archive = $build_archive( 'clone-import-files-source-good-0001' );
$prepare_import( 'clone-import-files-good-0001', $good_archive );
$good = $run_files( 'clone-import-files-good-0001' );

$staged_contents = array();
foreach ( array( 'uploads', 'plugins', 'themes' ) as $root_id ) {
	$info = $stage->file_info( 'clone-import-files-good-0001', $root_id, $sentinel );
	if ( ! is_array( $info ) ) {
		throw new RuntimeException( 'Expected staged file is missing.' );
	}
	$staged_contents[ $root_id ] = file_get_contents( $info['path'] );
}
$active_after_good = array();
foreach ( $active_paths as $root_id => $path ) {
	$active_after_good[ $root_id ] = file_get_contents( $path );
}

// Destination drift after one staged file must block the next mutation.
$drift_archive = $build_archive( 'clone-import-files-source-drift-0001' );
$prepare_import( 'clone-import-files-drift-0001', $drift_archive );
$drift_first = $file_restore->advance( 'clone-import-files-drift-0001', 1, 1048576 );
if ( ! is_array( $drift_first ) ) {
	throw new RuntimeException( 'Could not advance destination-drift file fixture.' );
}
update_option( 'blog_public', '1' );
$drift_blocked = $file_restore->advance( 'clone-import-files-drift-0001', 1, 1048576 );
update_option( 'blog_public', '0' );

// Payload drift after accepted checksum must be rejected against per-file metadata.
$tamper_archive = $build_archive( 'clone-import-files-source-tamper-0001' );
$prepare_import( 'clone-import-files-tamper-0001', $tamper_archive );
$tamper_info = $workspace->import_extracted_file_info(
	'clone-import-files-tamper-0001',
	'files/uploads/' . $sentinel
);
if ( ! is_array( $tamper_info ) || false === file_put_contents( $tamper_info['path'], 'tampered-after-verification' ) ) {
	throw new RuntimeException( 'Could not create post-verification file drift fixture.' );
}
$tamper_blocked = $file_restore->advance( 'clone-import-files-tamper-0001', 1, 1048576 );

$active_after_all = array();
foreach ( $active_paths as $root_id => $path ) {
	$active_after_all[ $root_id ] = file_get_contents( $path );
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
		'good'                     => $good,
		'staged_contents'          => $staged_contents,
		'active_expected'          => $active_contents,
		'active_after_good'        => $active_after_good,
		'active_after_all'         => $active_after_all,
		'drift_first'              => $drift_first,
		'drift_blocked'            => $drift_blocked,
		'tamper_blocked'           => $tamper_blocked,
		'file_state_autoload'      => $autoload,
		'controller_registered'    => false !== has_action( 'admin_post_' . AdminCloneImportFileController::ACTION ),
		'public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportFileController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

foreach (
	array(
		'clone-import-files-good-0001',
		'clone-import-files-drift-0001',
		'clone-import-files-tamper-0001',
	) as $import_job
) {
	$stage->cleanup( $import_job );
	$workspace->cleanup( $import_job );
}

foreach (
	array(
		'clone-import-files-source-good-0001',
		'clone-import-files-source-drift-0001',
		'clone-import-files-source-tamper-0001',
	) as $source_job
) {
	$workspace->cleanup( $source_job );
	$workspace->delete_delivery_archive( $source_job );
}

foreach ( $active_paths as $root_id => $path ) {
	$original = $originals[ $root_id ];
	if ( true === $original['exists'] ) {
		file_put_contents( $path, (string) $original['content'] );
	} else {
		@unlink( $path );
	}
}

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
delete_option( ImportDatabaseStateStore::OPTION_NAME );
delete_option( ImportFileStateStore::OPTION_NAME );
PHP

docker cp "$IMPORT_FILES_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-files-runner.php \
  || fail_smoke "clone-import-files-runner-copy" "Could not copy Portable Import file staging runner" "runner copied" "docker cp failed"

if ! IMPORT_FILES_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-files-runner.php 2>"$TMP_DIR/portable-clone-import-files.stderr")"; then
  IMPORT_FILES_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-files.stderr" | head -c 3000)"
  fail_smoke "clone-import-files-runner" "Portable Import file staging runner failed" "JSON file staging report" "${IMPORT_FILES_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_FILES_JSON" >"$TMP_DIR/portable-clone-import-files.json"

if ! IMPORT_FILES_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-files.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good"]
assert good["status"] == "complete"
assert good["file_count"] == 3
assert good["expected_file_count"] == 3
assert good["roots_completed"] == 3
assert good["root_count"] == 3
assert good["active_files_untouched"] is True
assert good["blockers"] == []

assert payload["staged_contents"] == {
    "uploads": "incoming-upload-from-package",
    "plugins": "incoming-plugin-from-package",
    "themes": "incoming-theme-from-package",
}
assert payload["active_after_good"] == payload["active_expected"]
assert payload["active_after_all"] == payload["active_expected"]

drift = payload["drift_blocked"]
assert drift["status"] == "blocked"
assert "import-files-runtime-guard-failed" in drift["blockers"]
assert drift["active_files_untouched"] is True

tamper = payload["tamper_blocked"]
assert tamper["status"] == "blocked"
assert "import-files-source-integrity-failed" in tamper["blockers"]
assert tamper["active_files_untouched"] is True

assert payload["file_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-files-contract" "Portable Import file staging contract is invalid" "private verified staging + drift/tamper rejection + active sentinels intact" "${IMPORT_FILES_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import file staging OK: 3 roots restored to job-owned staging, active sentinels untouched, destination drift/tamper rejected.\n'
