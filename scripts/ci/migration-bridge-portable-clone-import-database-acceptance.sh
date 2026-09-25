#!/usr/bin/env bash
# Phase 10E.2A.4.3 Portable Import transactional staging database restore acceptance.

printf '[smoke] Checking Portable Import transactional staging database restore.\n'

IMPORT_DB_RUNNER="$TMP_DIR/portable-clone-import-database-runner.php"
cat >"$IMPORT_DB_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
delete_option( ImportDatabaseStateStore::OPTION_NAME );
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
$restorer  = Plugin::clone_import_database_restorer();
$workspace = new ExportWorkspace();

if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
	|| ! $restorer instanceof ImportDatabaseRestorer
) {
	throw new RuntimeException( 'Portable Import database restore services are unavailable.' );
}

global $wpdb;
if ( ! $wpdb instanceof wpdb ) {
	throw new RuntimeException( 'WordPress database runtime is unavailable.' );
}

$source_prefix = 'src_';
$source_table  = 'src_demo';
$active_table  = $wpdb->prefix . 'demo';
$quoted_active = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $active_table ) . chr( 96 );

// Test-only active destination sentinel. The restorer must never mutate it.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$quoted_active}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "CREATE TABLE {$quoted_active} (id bigint unsigned NOT NULL, title varchar(190) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->insert( $active_table, array( 'id' => 999, 'title' => 'active-sentinel' ), array( '%d', '%s' ) );

$active_snapshot = static function () use ( $wpdb, $quoted_active ): array {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( "SELECT id, title FROM {$quoted_active} ORDER BY id ASC", ARRAY_A );

	return is_array( $rows ) ? $rows : array();
};

$active_before = $active_snapshot();

/**
 * Replay the exact Portable Clone lexicographic BFS checksum over one source workspace.
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
			throw new RuntimeException( 'Could not scan database restore fixture workspace.' );
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
				throw new RuntimeException( 'Database restore fixture contains an unexpected symlink.' );
			}
			if ( is_dir( $absolute ) ) {
				$pending_dirs[] = $relative;
				continue;
			}

			$bytes = filesize( $absolute );
			$hash  = hash_file( 'sha256', $absolute );
			if ( false === $bytes || false === $hash ) {
				throw new RuntimeException( 'Could not hash database restore fixture payload.' );
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
 * Encode one exported database row.
 *
 * @param list<string|null> $values Raw values.
 * @return list<string|null>
 */
$encode_row = static function ( array $values ): array {
	return array_map(
		static function ( ?string $value ): ?string {
			if ( null === $value ) {
				return null;
			}
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe migration fixture encoding.
			return base64_encode( $value );
		},
		$values
	);
};

/**
 * Build one fully valid Portable Clone fixture archive with database chunks.
 *
 * @return array{path:string,sha256:string,bytes:int}
 */
$build_archive = static function ( string $source_job_id, string $engine = 'InnoDB' ) use (
	$workspace,
	$workspace_checksum,
	$encode_row,
	$source_prefix,
	$source_table
): array {
	$workspace->cleanup( $source_job_id );
	$workspace->delete_delivery_archive( $source_job_id );

	$table_dir = 'database/tables/' . substr( hash( 'sha256', $source_table ), 0, 20 );
	$schema_sql = 'CREATE TABLE `' . $source_table . '` ('
		. '`id` bigint unsigned NOT NULL, '
		. '`title` varchar(190) NOT NULL, '
		. 'PRIMARY KEY (`id`)) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;' . "\n";
	$schema = $workspace->write( $source_job_id, $table_dir . '/schema.sql', $schema_sql );
	if ( ! is_array( $schema ) ) {
		throw new RuntimeException( 'Could not write database schema fixture.' );
	}

	$chunk_rows = array(
		array(
			$encode_row( array( '1', 'alpha' ) ),
			$encode_row( array( '2', 'beta' ) ),
		),
		array(
			$encode_row( array( '3', 'gamma' ) ),
		),
	);
	$chunks = array();
	foreach ( $chunk_rows as $index => $rows ) {
		$chunk = array(
			'schema_version'   => 1,
			'table'            => $source_table,
			'strategy'         => 'primary-key',
			'cursor_column'    => 'id',
			'cursor_start_b64' => '',
			'offset_start'     => 0,
			'chunk_index'      => $index,
			'row_count'        => count( $rows ),
			'columns'          => array( 'id', 'title' ),
			'value_encoding'   => 'base64-or-null',
			'rows'             => $rows,
		);
		$json = wp_json_encode( $chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'Could not encode database chunk fixture.' );
		}
		$path    = $table_dir . '/chunks/' . sprintf( '%06d.json', $index );
		$written = $workspace->write( $source_job_id, $path, $json );
		if ( ! is_array( $written ) ) {
			throw new RuntimeException( 'Could not write database chunk fixture.' );
		}
		$chunks[] = array(
			'index'      => $index,
			'path'       => $path,
			'row_count'  => count( $rows ),
			'byte_count' => (int) $written['bytes'],
			'sha256'     => (string) $written['sha256'],
		);
	}

	$table_meta = array(
		'schema_version' => 1,
		'name'           => $source_table,
		'slug'           => substr( hash( 'sha256', $source_table ), 0, 20 ),
		'schema'         => array(
			'path'   => $table_dir . '/schema.sql',
			'bytes'  => (int) $schema['bytes'],
			'sha256' => (string) $schema['sha256'],
		),
		'strategy'       => 'primary-key',
		'cursor_column'  => 'id',
		'order_columns'  => array(),
		'columns'        => array( 'id', 'title' ),
		'chunks'         => $chunks,
		'chunk_count'    => count( $chunks ),
		'row_count'      => 3,
		'byte_count'     => array_sum( array_column( $chunks, 'byte_count' ) ),
		'complete'       => true,
	);

	$database_manifest = array(
		'schema_version'              => 1,
		'package_id'                  => $source_job_id,
		'payload_class'               => 'database',
		'source'                      => array(
			'home_url'          => 'https://source.example.test/',
			'site_url'          => 'https://source.example.test/',
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'table_prefix'      => $source_prefix,
		),
		'tables'                      => array( $table_meta ),
		'table_count'                 => 1,
		'row_count'                   => 3,
		'payload_bytes'               => (int) $table_meta['byte_count'],
		'chunk_count'                 => count( $chunks ),
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

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 0,
		'payload_bytes'               => 0,
		'roots'                       => array(),
		'source_fingerprint'          => hash( 'sha256', 'database-restore-fixture-' . $source_job_id ),
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
		throw new RuntimeException( 'Could not write files manifest fixture.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Database restore source fixture workspace unavailable.' );
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
			'source_fingerprint' => hash( 'sha256', 'database-restore-fixture-' . $source_job_id ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $database['sha256'],
				'row_count'       => 3,
				'chunk_count'     => count( $chunks ),
				'payload_bytes'   => (int) $table_meta['byte_count'],
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files['sha256'],
				'file_count'      => 0,
				'payload_bytes'   => 0,
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
		throw new RuntimeException( 'Could not reset database restore fixture archive.' );
	}
	foreach ( array_chunk( $archive_files, 4 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append database restore fixture archive batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize database restore fixture archive.' );
	}

	return $archive;
};

/**
 * Stage, preflight and fully verify one import job.
 *
 * @return array<string,mixed>
 */
$prepare_import = static function ( string $job_id, array $archive ) use ( $jobs, $preflight, $verifier ): array {
	if (
		! is_array( $jobs->create( 'import', $job_id ) )
		|| ! is_array( $preflight->stage( $job_id, $archive['path'] ) )
	) {
		throw new RuntimeException( 'Could not stage database restore import fixture.' );
	}

	$pre = $preflight->validate( $job_id );
	if ( ! is_array( $pre ) || 'preflight-ready' !== ( $pre['status'] ?? null ) ) {
		throw new RuntimeException( 'Database restore fixture did not pass preflight.' );
	}

	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		$payload = $verifier->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $payload ) ) {
			throw new RuntimeException( 'Database restore fixture payload verification failed to return state.' );
		}
		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			$import = ( new ImportStateStore() )->get( $job_id );
			if (
				! is_array( $import )
				|| 'payload-verified' !== ( $import['status'] ?? null )
				|| true !== ( $import['restore_allowed'] ?? false )
			) {
				throw new RuntimeException( 'Database restore fixture did not unlock guarded restore eligibility.' );
			}

			return $import;
		}
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			throw new RuntimeException( 'Database restore fixture payload verification was blocked.' );
		}
	}

	throw new RuntimeException( 'Database restore fixture payload verification did not finish.' );
};

/**
 * Run database staging restore to a terminal state.
 *
 * @return array<string,mixed>
 */
$run_database = static function ( string $job_id, int $batch_rows = 1 ) use ( $restorer ): array {
	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		$state = $restorer->advance( $job_id, $batch_rows );
		if ( ! is_array( $state ) ) {
			throw new RuntimeException( 'Database staging restore returned no state.' );
		}
		if ( in_array( $state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
			return $state;
		}
	}

	throw new RuntimeException( 'Database staging restore did not reach a terminal state.' );
};

$good_archive = $build_archive( 'clone-import-db-source-good-0001', 'InnoDB' );
$prepare_import( 'clone-import-db-good-0001', $good_archive );
$good = $run_database( 'clone-import-db-good-0001', 1 );

$good_namespace = (string) ( $good['staging_namespace'] ?? '' );
$good_staging   = $good_namespace . substr( hash( 'sha256', $source_table ), 0, 16 );
$quoted_staging = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $good_staging ) . chr( 96 );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$staging_rows = $wpdb->get_results( "SELECT id, title FROM {$quoted_staging} ORDER BY id ASC", ARRAY_A );
$active_after_good = $active_snapshot();

// Destination drift after one transactional row batch must block the next mutation.
$drift_archive = $build_archive( 'clone-import-db-source-drift-0001', 'InnoDB' );
$prepare_import( 'clone-import-db-drift-0001', $drift_archive );
$drift_schema = $restorer->advance( 'clone-import-db-drift-0001', 1 );
$drift_first  = $restorer->advance( 'clone-import-db-drift-0001', 1 );
if ( ! is_array( $drift_schema ) || ! is_array( $drift_first ) ) {
	throw new RuntimeException( 'Could not advance destination-drift database restore fixture.' );
}
update_option( 'blog_public', '1' );
$drift_blocked = $restorer->advance( 'clone-import-db-drift-0001', 1 );
update_option( 'blog_public', '0' );

// Nontransactional source schema must be rejected before row restore.
$myisam_archive = $build_archive( 'clone-import-db-source-myisam-0001', 'MyISAM' );
$prepare_import( 'clone-import-db-myisam-0001', $myisam_archive );
$myisam = $restorer->advance( 'clone-import-db-myisam-0001', 1 );

$active_after_all = $active_snapshot();

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportDatabaseStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'good'                     => $good,
		'good_staging_table'       => $good_staging,
		'staging_rows'             => $staging_rows,
		'active_before'            => $active_before,
		'active_after_good'        => $active_after_good,
		'active_after_all'         => $active_after_all,
		'drift_schema'             => $drift_schema,
		'drift_first'              => $drift_first,
		'drift_blocked'            => $drift_blocked,
		'myisam'                   => $myisam,
		'database_state_autoload'  => $autoload,
		'controller_registered'    => false !== has_action( 'admin_post_' . AdminCloneImportDatabaseController::ACTION ),
		'public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportDatabaseController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

// Test-only cleanup of deterministic fixture staging/sentinel tables.
foreach (
	array(
		'clone-import-db-good-0001',
		'clone-import-db-drift-0001',
		'clone-import-db-myisam-0001',
	) as $import_job
) {
	$namespace = $wpdb->prefix . 'sgm_' . substr( hash( 'sha256', $import_job ), 0, 10 ) . '_';
	$pattern   = $wpdb->esc_like( $namespace ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
	foreach ( is_array( $tables ) ? $tables : array() as $table ) {
		if ( ! is_string( $table ) || ! str_starts_with( $table, $namespace ) ) {
			continue;
		}
		$quoted = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table ) . chr( 96 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test-only cleanup of deterministic fixture staging tables.
		$wpdb->query( "DROP TABLE {$quoted}" );
	}
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test-only cleanup.
$wpdb->query( "DROP TABLE {$quoted_active}" );

foreach (
	array(
		'clone-import-db-good-0001',
		'clone-import-db-drift-0001',
		'clone-import-db-myisam-0001',
		'clone-import-db-source-good-0001',
		'clone-import-db-source-drift-0001',
		'clone-import-db-source-myisam-0001',
	) as $cleanup_job
) {
	$workspace->cleanup( $cleanup_job );
	$workspace->delete_delivery_archive( $cleanup_job );
}

delete_option( CloneJobStore::OPTION_NAME );
delete_option( ImportStateStore::OPTION_NAME );
delete_option( ImportPayloadStateStore::OPTION_NAME );
delete_option( ImportDatabaseStateStore::OPTION_NAME );
PHP

docker cp "$IMPORT_DB_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-database-runner.php \
  || fail_smoke "clone-import-database-runner-copy" "Could not copy Portable Import database restore runner" "runner copied" "docker cp failed"

if ! IMPORT_DB_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-database-runner.php 2>"$TMP_DIR/portable-clone-import-database.stderr")"; then
  IMPORT_DB_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-database.stderr" | head -c 3200)"
  fail_smoke "clone-import-database-runner" "Portable Import staging database restore runner failed" "JSON database restore report" "${IMPORT_DB_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_DB_JSON" >"$TMP_DIR/portable-clone-import-database.json"

if ! IMPORT_DB_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-database.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good"]
assert good["status"] == "complete"
assert good["stage"] == "complete"
assert good["table_count"] == 1
assert good["tables_completed"] == 1
assert good["chunk_count"] == 2
assert good["chunks_completed"] == 2
assert good["rows_restored"] == 3
assert good["active_tables_untouched"] is True
assert re.fullmatch(r"[A-Za-z0-9_]{1,64}", payload["good_staging_table"])

assert payload["staging_rows"] == [
    {"id": "1", "title": "alpha"},
    {"id": "2", "title": "beta"},
    {"id": "3", "title": "gamma"},
]
expected_active = [{"id": "999", "title": "active-sentinel"}]
assert payload["active_before"] == expected_active
assert payload["active_after_good"] == expected_active
assert payload["active_after_all"] == expected_active

assert payload["drift_first"]["status"] == "running"
assert payload["drift_first"]["rows_restored"] == 1
drift = payload["drift_blocked"]
assert drift["status"] == "blocked"
assert "import-database-runtime-guard-failed" in drift["blockers"]
assert drift["rows_restored"] == 1
assert drift["active_tables_untouched"] is True

myisam = payload["myisam"]
assert myisam["status"] == "blocked"
assert "import-database-schema-unsupported" in myisam["blockers"]
assert myisam["rows_restored"] == 0
assert myisam["active_tables_untouched"] is True

assert payload["database_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-database-contract" "Portable Import staging database restore contract is invalid" "transactional staging restore + drift/schema blockers + active table preservation" "${IMPORT_DB_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import database restore OK: verified rows restored transactionally into job-owned staging tables; active destination table stayed unchanged; destination drift and MyISAM schema were blocked.\n'
