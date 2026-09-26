#!/usr/bin/env bash
# Phase 10E.2A.5.3.1-5.5.1 private local handoff/staging/rewrite acceptance.

printf '[smoke] Checking private same-server local-clone staging and serialization-safe environment rewrite.\n'

LOCAL_HANDOFF_RUNNER="$TMP_DIR/portable-clone-local-package-handoff-runner.php"
cat >"$LOCAL_HANDOFF_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPackageHandoffController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalPayloadController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalDatabaseController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalFileController;
use SeoGeo\MigrationBridge\Clone\AdminCloneLocalEnvironmentRewriteController;
use SeoGeo\MigrationBridge\Clone\CloneInventoryStore;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\DeliveryStateStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneOrchestrator;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoff;
use SeoGeo\MigrationBridge\Clone\LocalClonePackageHandoffStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflight;
use SeoGeo\MigrationBridge\Clone\LocalCloneTargetPreflightStateStore;
use SeoGeo\MigrationBridge\Clone\LocalClonePayloadVerifier;
use SeoGeo\MigrationBridge\Clone\LocalClonePayloadVerificationStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\LocalCloneDatabaseRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneFileRestorer;
use SeoGeo\MigrationBridge\Clone\LocalCloneFileRestoreStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneEnvironmentRewriter;
use SeoGeo\MigrationBridge\Clone\LocalCloneEnvironmentRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeBootstrapper;
use SeoGeo\MigrationBridge\Clone\LocalCloneSandboxRuntimeStateStore;
use SeoGeo\MigrationBridge\Clone\LocalCloneStateStore;
use SeoGeo\MigrationBridge\Clone\PackageStateStore;
use SeoGeo\MigrationBridge\Plugin;

$options = array(
	CloneJobStore::OPTION_NAME,
	CloneInventoryStore::OPTION_NAME,
	PackageStateStore::OPTION_NAME,
	DeliveryStateStore::OPTION_NAME,
	LocalCloneStateStore::OPTION_NAME,
	LocalCloneBootstrapStateStore::OPTION_NAME,
	LocalCloneRuntimeStateStore::OPTION_NAME,
	LocalCloneSandboxRuntimeStateStore::OPTION_NAME,
	LocalClonePackageHandoffStateStore::OPTION_NAME,
	LocalCloneTargetPreflightStateStore::OPTION_NAME,
	LocalClonePayloadVerificationStateStore::OPTION_NAME,
	LocalCloneDatabaseRestoreStateStore::OPTION_NAME,
	LocalCloneFileRestoreStateStore::OPTION_NAME,
	LocalCloneEnvironmentRewriteStateStore::OPTION_NAME,
	ImportStateStore::OPTION_NAME,
	ImportRewriteStateStore::OPTION_NAME,
	ImportFileStateStore::OPTION_NAME,
	ImportDatabaseStateStore::OPTION_NAME,
	ImportPayloadStateStore::OPTION_NAME,
);
foreach ( $options as $option ) {
	delete_option( $option );
}

$jobs      = Plugin::clone_job_store();
$planner   = Plugin::local_clone_orchestrator();
$ownership = Plugin::local_clone_bootstrapper();
$core      = Plugin::local_clone_runtime_bootstrapper();
$sandbox   = Plugin::local_clone_sandbox_runtime_bootstrapper();
$handoff   = Plugin::local_clone_package_handoff();
$target_preflight = Plugin::local_clone_target_preflight();
$local_payload     = Plugin::local_clone_payload_verifier();
$local_database    = Plugin::local_clone_database_restorer();
$local_files       = Plugin::local_clone_file_restorer();
$local_rewriter    = Plugin::local_clone_environment_rewriter();
if (
	! $jobs instanceof CloneJobStore
	|| ! $planner instanceof LocalCloneOrchestrator
	|| ! $ownership instanceof LocalCloneBootstrapper
	|| ! $core instanceof LocalCloneRuntimeBootstrapper
	|| ! $sandbox instanceof LocalCloneSandboxRuntimeBootstrapper
	|| ! $handoff instanceof LocalClonePackageHandoff
	|| ! $target_preflight instanceof LocalCloneTargetPreflight
	|| ! $local_payload instanceof LocalClonePayloadVerifier
	|| ! $local_database instanceof LocalCloneDatabaseRestorer
	|| ! $local_files instanceof LocalCloneFileRestorer
	|| ! $local_rewriter instanceof LocalCloneEnvironmentRewriter
) {
	throw new RuntimeException( 'Local clone handoff services are unavailable.' );
}

$workspace = new ExportWorkspace();
$inventory = new CloneInventoryStore();
$packages  = new PackageStateStore();

$remove_tree = static function ( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $path );
};

$job_id        = 'local-package-handoff-0001';
$target_path   = trailingslashit( wp_normalize_path( ABSPATH ) ) . 'nuevaweb-package-handoff';
$target_url    = trailingslashit( home_url( '/nuevaweb-package-handoff/' ) );
$source_prefix = $GLOBALS['wpdb']->prefix;
$target_prefix = $source_prefix . 'sghandoff_';
$source_home   = home_url( '/' );
$source_site   = site_url( '/' );
$options_table = $source_prefix . 'options';
$posts_table   = $source_prefix . 'posts';
$source_table  = $source_prefix . 'sg_local_demo';
$quoted_source = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $source_table ) . chr( 96 );

$workspace->cleanup( $job_id );
$workspace->delete_delivery_archive( $job_id );
$remove_tree( $target_path );

// Test-only production sentinel. Local database staging must never mutate this table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$quoted_source}" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
$GLOBALS['wpdb']->query( "CREATE TABLE {$quoted_source} (id bigint unsigned NOT NULL, title varchar(190) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$GLOBALS['wpdb']->insert( $source_table, array( 'id' => 999, 'title' => 'production-sentinel' ), array( '%d', '%s' ) );

$source_snapshot = static function () use ( $quoted_source ): array {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	$rows = $GLOBALS['wpdb']->get_results( "SELECT id, title FROM {$quoted_source} ORDER BY id ASC", ARRAY_A );

	return is_array( $rows ) ? $rows : array();
};
$source_before = $source_snapshot();

$job = $jobs->create( 'local-clone', $job_id );
if ( ! is_array( $job ) ) {
	throw new RuntimeException( 'Could not create local handoff fixture job.' );
}

$source_fingerprint = hash( 'sha256', 'local-handoff-source:' . $job_id );

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
 * Write one source table schema/chunk and return manifest metadata.
 *
 * @param string                  $table      Source table.
 * @param list<string>            $columns    Exported columns.
 * @param string                  $id_column  Cursor/primary-key column.
 * @param list<list<string|null>> $rows       Raw rows.
 * @param string                  $schema_sql CREATE TABLE statement.
 * @return array<string,mixed>
 */
$write_table = static function (
	string $table,
	array $columns,
	string $id_column,
	array $rows,
	string $schema_sql
) use ( $workspace, $job_id, $encode_row ): array {
	$table_dir = 'database/tables/' . substr( hash( 'sha256', $table ), 0, 20 );
	$schema    = $workspace->write( $job_id, $table_dir . '/schema.sql', $schema_sql . "\n" );
	if ( ! is_array( $schema ) ) {
		throw new RuntimeException( 'Could not write local rewrite schema fixture.' );
	}

	$encoded_rows = array_map( $encode_row, $rows );
	$chunk        = array(
		'schema_version'   => 1,
		'table'            => $table,
		'strategy'         => 'primary-key',
		'cursor_column'    => $id_column,
		'cursor_start_b64' => '',
		'offset_start'     => 0,
		'chunk_index'      => 0,
		'row_count'        => count( $encoded_rows ),
		'columns'          => $columns,
		'value_encoding'   => 'base64-or-null',
		'rows'             => $encoded_rows,
	);
	$chunk_json = wp_json_encode( $chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $chunk_json ) ) {
		throw new RuntimeException( 'Could not encode local rewrite chunk fixture.' );
	}
	$chunk_path = $table_dir . '/chunks/000000.json';
	$written    = $workspace->write( $job_id, $chunk_path, $chunk_json );
	if ( ! is_array( $written ) ) {
		throw new RuntimeException( 'Could not write local rewrite chunk fixture.' );
	}

	return array(
		'schema_version' => 1,
		'name'           => $table,
		'slug'           => substr( hash( 'sha256', $table ), 0, 20 ),
		'schema'         => array(
			'path'   => $table_dir . '/schema.sql',
			'bytes'  => (int) $schema['bytes'],
			'sha256' => (string) $schema['sha256'],
		),
		'strategy'       => 'primary-key',
		'cursor_column'  => $id_column,
		'order_columns'  => array(),
		'columns'        => $columns,
		'chunks'         => array(
			array(
				'index'      => 0,
				'path'       => $chunk_path,
				'row_count'  => count( $encoded_rows ),
				'byte_count' => (int) $written['bytes'],
				'sha256'     => (string) $written['sha256'],
			),
		),
		'chunk_count'    => 1,
		'row_count'      => count( $encoded_rows ),
		'byte_count'     => (int) $written['bytes'],
		'complete'       => true,
	);
};

$serialized_value = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Fixture intentionally exercises serialization-safe rewrite.
	array(
		'url'   => $source_home . 'serialized',
		'asset' => $source_home . 'wp-content/uploads/2026/local.txt',
	)
);
$json_value = wp_json_encode(
	array(
		'url'    => $source_home . 'json',
		'nested' => array( 'site' => $source_site . 'admin' ),
	),
	JSON_UNESCAPED_SLASHES
);
if ( ! is_string( $json_value ) ) {
	throw new RuntimeException( 'Could not encode local rewrite JSON fixture.' );
}

$options_rows = array(
	array( '1', 'home', $source_home, 'yes' ),
	array( '2', 'siteurl', $source_site, 'yes' ),
	array( '3', 'plain_url', $source_home . 'catalog/item?x=1#top', 'yes' ),
	array( '4', 'serialized_payload', $serialized_value, 'yes' ),
	array( '5', 'json_payload', $json_value, 'yes' ),
	array( '6', 'api_token', 'token-value::' . $source_home . 'credential-context', 'yes' ),
);
$posts_rows = array(
	array(
		'1',
		'Visit ' . $source_home . 'about and keep https://external.example.test/reference',
		'Media ' . $source_home . 'wp-content/uploads/2026/local.txt',
		'',
	),
);

$options_schema = 'CREATE TABLE `' . $options_table . '` ('
	. '`option_id` bigint unsigned NOT NULL, '
	. '`option_name` varchar(191) NOT NULL, '
	. '`option_value` longtext NOT NULL, '
	. '`autoload` varchar(20) NOT NULL DEFAULT \'yes\', '
	. 'PRIMARY KEY (`option_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
$posts_schema = 'CREATE TABLE `' . $posts_table . '` ('
	. '`ID` bigint unsigned NOT NULL, '
	. '`post_content` longtext NOT NULL, '
	. '`post_excerpt` text NOT NULL, '
	. '`post_content_filtered` longtext NOT NULL, '
	. 'PRIMARY KEY (`ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$options_meta = $write_table(
	$options_table,
	array( 'option_id', 'option_name', 'option_value', 'autoload' ),
	'option_id',
	$options_rows,
	$options_schema
);
$posts_meta = $write_table(
	$posts_table,
	array( 'ID', 'post_content', 'post_excerpt', 'post_content_filtered' ),
	'ID',
	$posts_rows,
	$posts_schema
);

$files = array(
	array( 'root' => 'uploads', 'relative' => '2026/local.txt', 'content' => "local-upload\n" ),
	array( 'root' => 'plugins', 'relative' => 'sample-local/plugin.php', 'content' => "<?php\n// local staged plugin\n" ),
	array( 'root' => 'themes', 'relative' => 'sample-local/style.css', 'content' => "body{display:block}\n" ),
);
$root_stats = array(
	'uploads' => array( 'file_count' => 0, 'byte_count' => 0 ),
	'plugins' => array( 'file_count' => 0, 'byte_count' => 0 ),
	'themes'  => array( 'file_count' => 0, 'byte_count' => 0 ),
);
$total_file_bytes = 0;

foreach ( $files as $file ) {
	$payload_path = 'files/' . $file['root'] . '/' . $file['relative'];
	$written      = $workspace->write( $job_id, $payload_path, $file['content'] );
	if ( ! is_array( $written ) ) {
		throw new RuntimeException( 'Could not write local file payload fixture: ' . $payload_path );
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
	if ( ! is_array( $workspace->write( $job_id, $record_path, $record_json ) ) ) {
		throw new RuntimeException( 'Could not write local file record fixture.' );
	}

	++$root_stats[ $file['root'] ]['file_count'];
	$root_stats[ $file['root'] ]['byte_count'] += (int) $written['bytes'];
	$total_file_bytes += (int) $written['bytes'];
}

$database_manifest = array(
	'schema_version'              => 1,
	'package_id'                  => $job_id,
	'payload_class'               => 'database',
	'source'                      => array(
		'home_url'     => $source_home,
		'site_url'     => $source_site,
		'table_prefix' => $source_prefix,
	),
	'tables'                      => array( $options_meta, $posts_meta ),
	'table_count'                 => 2,
	'row_count'                   => count( $options_rows ) + count( $posts_rows ),
	'payload_bytes'               => (int) $options_meta['byte_count'] + (int) $posts_meta['byte_count'],
	'chunk_count'                 => 2,
	'production_source_read_only' => true,
	'credentials_in_payload'      => false,
	'contains_private_site_data'  => true,
	'repository_safe'             => false,
	'generated_at'                => gmdate( DATE_ATOM ),
);
$database_json = wp_json_encode( $database_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
$database_written = $workspace->write( $job_id, 'database/manifest.json', $database_json );
if ( ! is_array( $database_written ) ) {
	throw new RuntimeException( 'Could not write local handoff database manifest.' );
}

$files_manifest = array(
	'schema_version'              => 1,
	'payload_class'               => 'files',
	'file_count'                  => count( $files ),
	'payload_bytes'               => $total_file_bytes,
	'roots'                       => array(
		array( 'id' => 'uploads', 'file_count' => $root_stats['uploads']['file_count'], 'byte_count' => $root_stats['uploads']['byte_count'] ),
		array( 'id' => 'plugins', 'file_count' => $root_stats['plugins']['file_count'], 'byte_count' => $root_stats['plugins']['byte_count'] ),
		array( 'id' => 'themes', 'file_count' => $root_stats['themes']['file_count'], 'byte_count' => $root_stats['themes']['byte_count'] ),
	),
	'source_fingerprint'          => $source_fingerprint,
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
$files_written = $workspace->write( $job_id, 'files/manifest.json', $files_json );
if ( ! is_array( $files_written ) ) {
	throw new RuntimeException( 'Could not write local handoff files manifest.' );
}

$root = $workspace->root_path( $job_id );
if ( null === $root ) {
	throw new RuntimeException( 'Local handoff workspace unavailable.' );
}

$pending       = array( '' );
$checksum      = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
$payload_files = 0;
$payload_bytes = 0;
while ( array() !== $pending ) {
	$current = (string) array_shift( $pending );
	$dir     = rtrim( $root, '/' ) . ( '' === $current ? '' : '/' . $current );
	$entries = scandir( $dir, SCANDIR_SORT_ASCENDING );
	if ( false === $entries ) {
		throw new RuntimeException( 'Could not scan local handoff workspace.' );
	}

	foreach ( $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$relative = '' === $current ? $entry : $current . '/' . $entry;
		if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
			continue;
		}
		$path = rtrim( $root, '/' ) . '/' . $relative;
		if ( is_dir( $path ) ) {
			$pending[] = $relative;
			continue;
		}
		$bytes = filesize( $path );
		$hash  = hash_file( 'sha256', $path );
		if ( false === $bytes || false === $hash ) {
			throw new RuntimeException( 'Could not hash local handoff payload.' );
		}
		$checksum = hash(
			'sha256',
			$checksum . "\n" . 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $bytes . '|' . $hash
		);
		++$payload_files;
		$payload_bytes += (int) $bytes;
	}
}


$manifest = array(
	'schema_version' => 1,
	'mode'           => 'portable-clone-package',
	'package_id'     => $job_id,
	'operation'      => 'local-clone',
	'source'         => array(
		'home_url'           => $source_home,
		'site_url'           => $source_site,
		'wordpress_version'  => get_bloginfo( 'version' ),
		'php_version'        => PHP_VERSION,
		'source_fingerprint' => $source_fingerprint,
	),
	'payload'        => array(
		'database' => array(
			'manifest_path'   => 'database/manifest.json',
			'manifest_sha256' => (string) $database_written['sha256'],
			'row_count'       => (int) $database_manifest['row_count'],
			'chunk_count'     => (int) $database_manifest['chunk_count'],
			'payload_bytes'   => (int) $database_manifest['payload_bytes'],
		),
		'files'    => array(
			'manifest_path'   => 'files/manifest.json',
			'manifest_sha256' => (string) $files_written['sha256'],
			'file_count'      => count( $files ),
			'payload_bytes'   => (int) $files_manifest['payload_bytes'],
		),
	),
	'integrity'      => array(
		'algorithm'          => 'sha256',
		'checksum_contract'  => 'lexicographic-bfs-path+bytes+sha256-v1',
		'checksum_scope'     => 'workspace-excluding-package-metadata',
		'payload_file_count' => $payload_files,
		'payload_bytes'      => $payload_bytes,
		'package_checksum'   => $checksum,
		'verification_pass'  => true,
		'verified'           => true,
	),
	'safety'         => array(
		'production_source_read_only' => true,
		'credentials_in_manifest'     => false,
		'contains_private_site_data'  => true,
		'repository_safe'             => false,
		'delivery_ready'              => false,
	),
	'generated_at'   => gmdate( DATE_ATOM ),
);
$manifest_json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
$manifest_written = $workspace->write( $job_id, 'package/manifest.json', $manifest_json );
if ( ! is_array( $manifest_written ) ) {
	throw new RuntimeException( 'Could not write local handoff package manifest.' );
}

$now = gmdate( DATE_ATOM );
$inventory->save(
	$job_id,
	array(
		'status'       => 'complete',
		'database'     => array(
			'estimated_rows'  => (int) $database_manifest['row_count'],
			'estimated_bytes' => 4096,
		),
		'roots'        => array(),
		'file_count'   => count( $files ),
		'byte_count'   => 4096,
		'fingerprint'  => $source_fingerprint,
		'blockers'     => array(),
		'completed_at' => $now,
		'updated_at'   => $now,
	)
);
$packages->save(
	$job_id,
	array(
		'schema_version'         => PackageStateStore::SCHEMA_VERSION,
		'job_id'                 => $job_id,
		'status'                 => 'complete',
		'stage'                  => 'complete',
		'directory_active'       => false,
		'pending_dirs'           => array(),
		'current_dir'            => '',
		'after_name'             => '',
		'payload_file_count'     => $payload_files,
		'payload_byte_count'     => $payload_bytes,
		'verify_file_count'      => $payload_files,
		'verify_byte_count'      => $payload_bytes,
		'package_checksum'       => $checksum,
		'verification_checksum'  => $checksum,
		'source_fingerprint'     => $source_fingerprint,
		'database_manifest_hash' => hash_file( 'sha256', rtrim( $root, '/' ) . '/database/manifest.json' ),
		'files_manifest_hash'    => hash_file( 'sha256', rtrim( $root, '/' ) . '/files/manifest.json' ),
		'package_manifest_hash'  => (string) $manifest_written['sha256'],
		'blockers'               => array(),
		'started_at'             => $now,
		'updated_at'             => $now,
		'completed_at'           => $now,
	)
);

$frozen_manifest_hash = hash_file( 'sha256', rtrim( $root, '/' ) . '/package/manifest.json' );
$frozen_package_state = $packages->get( $job_id );
if ( false === $frozen_manifest_hash || ! is_array( $frozen_package_state ) ) {
	throw new RuntimeException( 'Frozen local handoff package identity unavailable.' );
}

$plan  = $planner->prepare( $job_id, $target_path, $target_url, $target_prefix, true );
$claim = $ownership->claim( $job_id );

$core_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$core_state = $core->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $core_state ) || in_array( $core_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$sandbox_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$sandbox_state = $sandbox->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $sandbox_state ) || in_array( $sandbox_state['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$handoff_state = null;
for ( $i = 0; $i < 140; ++$i ) {
	$handoff_state = $handoff->advance( $job_id, 200, 64 * 1024 * 1024 );
	if ( ! is_array( $handoff_state ) || in_array( $handoff_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
}

$verified_before = $handoff->verified_snapshot( $job_id );
$delivery_info   = Plugin::clone_package_delivery()?->download_info( $job_id );
$manifest_after  = $workspace->read( $job_id, 'package/manifest.json' );
$manifest_parsed = is_string( $manifest_after ) ? json_decode( $manifest_after, true ) : null;
$manifest_after_hash = is_string( $manifest_after ) ? hash( 'sha256', $manifest_after ) : '';
$package_after        = $packages->get( $job_id );

$target_preflight_state = $target_preflight->advance( $job_id );
$target_preflight_verified = $target_preflight->verified_snapshot( $job_id );
$target_preflight_child = is_array( $target_preflight_state )
	? ( new ImportStateStore() )->get( (string) ( $target_preflight_state['child_import_job_id'] ?? '' ) )
	: null;

$local_payload_mid = $local_payload->advance( $job_id, 1, 1024 * 1024 );
$local_payload_state = $local_payload_mid;
for ( $i = 0; $i < 140; ++$i ) {
	if ( is_array( $local_payload_state ) && in_array( $local_payload_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
	$local_payload_state = $local_payload->advance( $job_id, 1, 1024 * 1024 );
}
$local_payload_verified = $local_payload->verified_snapshot( $job_id );
$payload_child_id = is_array( $target_preflight_state ) ? (string) $target_preflight_state['child_import_job_id'] : '';
$payload_child_import = '' !== $payload_child_id ? ( new ImportStateStore() )->get( $payload_child_id ) : null;
$payload_child_state = '' !== $payload_child_id ? ( new ImportPayloadStateStore() )->get( $payload_child_id ) : null;
$target_preflight_after_payload = $target_preflight->verified_snapshot( $job_id );

$local_database_mid = $local_database->advance( $job_id, 10 );
$local_database_state = $local_database_mid;
for ( $i = 0; $i < 140; ++$i ) {
	if ( is_array( $local_database_state ) && in_array( $local_database_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
	$local_database_state = $local_database->advance( $job_id, 10 );
}
$local_database_verified = $local_database->verified_snapshot( $job_id );
$local_database_plan = $local_database->staging_plan( $job_id );
$database_child_state = '' !== $payload_child_id ? ( new ImportDatabaseStateStore() )->get( $payload_child_id ) : null;

$staging_by_source = array();
if ( is_array( $local_database_plan ) && is_array( $local_database_plan['tables'] ?? null ) ) {
	foreach ( $local_database_plan['tables'] as $table_plan ) {
		if (
			is_array( $table_plan )
			&& is_string( $table_plan['source_table'] ?? null )
			&& is_string( $table_plan['staging_table'] ?? null )
		) {
			$staging_by_source[ $table_plan['source_table'] ] = $table_plan['staging_table'];
		}
	}
}
$options_staging_table = (string) ( $staging_by_source[ $options_table ] ?? '' );
$posts_staging_table   = (string) ( $staging_by_source[ $posts_table ] ?? '' );
$quote_table = static function ( string $table ): string {
	return chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table ) . chr( 96 );
};

$read_staged_options = static function () use ( $options_staging_table, $quote_table ): array {
	if ( '' === $options_staging_table ) {
		return array();
	}
	$quoted = $quote_table( $options_staging_table );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only read of deterministic job-owned staging table.
	$rows = $GLOBALS['wpdb']->get_results( "SELECT option_id, option_name, option_value FROM {$quoted} ORDER BY option_id ASC", ARRAY_A );

	return is_array( $rows ) ? $rows : array();
};
$read_staged_posts = static function () use ( $posts_staging_table, $quote_table ): array {
	if ( '' === $posts_staging_table ) {
		return array();
	}
	$quoted = $quote_table( $posts_staging_table );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only read of deterministic job-owned staging table.
	$rows = $GLOBALS['wpdb']->get_results( "SELECT ID, post_content, post_excerpt, post_content_filtered FROM {$quoted} ORDER BY ID ASC", ARRAY_A );

	return is_array( $rows ) ? $rows : array();
};
$staging_options_before_rewrite = $read_staged_options();
$staging_posts_before_rewrite   = $read_staged_posts();
$source_after_database = $source_snapshot();

$table_pattern_after_database = $GLOBALS['wpdb']->esc_like( $target_prefix ) . '%';
$target_tables_after_database = $GLOBALS['wpdb']->get_col(
	$GLOBALS['wpdb']->prepare(
		'SHOW TABLES LIKE %s',
		$table_pattern_after_database
	)
);

$local_files_mid = $local_files->advance( $job_id, 1, 1024 * 1024 );
$local_files_state = $local_files_mid;
for ( $i = 0; $i < 180; ++$i ) {
	if ( is_array( $local_files_state ) && in_array( $local_files_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
	$local_files_state = $local_files->advance( $job_id, 1, 1024 * 1024 );
}
$local_files_verified = $local_files->verified_snapshot( $job_id );
$file_child_state = '' !== $payload_child_id ? ( new ImportFileStateStore() )->get( $payload_child_id ) : null;
$file_staging_root = $local_files->staging_root( $job_id );
$staged_upload = '' !== $payload_child_id
	? $workspace->import_staged_file_info( $payload_child_id, 'uploads', '2026/local.txt' )
	: null;
$staged_plugin = '' !== $payload_child_id
	? $workspace->import_staged_file_info( $payload_child_id, 'plugins', 'sample-local/plugin.php' )
	: null;
$staged_theme = '' !== $payload_child_id
	? $workspace->import_staged_file_info( $payload_child_id, 'themes', 'sample-local/style.css' )
	: null;
$target_upload_absent_after_files = ! file_exists( trailingslashit( $target_path ) . 'wp-content/uploads/2026/local.txt' );
$target_plugin_absent_after_files = ! file_exists( trailingslashit( $target_path ) . 'wp-content/plugins/sample-local/plugin.php' );
$target_theme_absent_after_files = ! file_exists( trailingslashit( $target_path ) . 'wp-content/themes/sample-local/style.css' );
$target_bridge_present_after_files = is_file( trailingslashit( $target_path ) . 'wp-content/plugins/seo-geo-migration-bridge/seo-geo-migration-bridge.php' );

$local_rewrite_mid = $local_rewriter->advance( $job_id, 1 );
$local_rewrite_state = $local_rewrite_mid;
for ( $i = 0; $i < 220; ++$i ) {
	if ( is_array( $local_rewrite_state ) && in_array( $local_rewrite_state['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
	$local_rewrite_state = $local_rewriter->advance( $job_id, 1 );
}
$local_rewrite_verified = $local_rewriter->verified_snapshot( $job_id );
$rewrite_child_state = '' !== $payload_child_id ? ( new ImportRewriteStateStore() )->get( $payload_child_id ) : null;
$staging_options_after_rewrite = $read_staged_options();
$staging_posts_after_rewrite   = $read_staged_posts();
$job_before_mutation = $jobs->get( $job_id );

$table_pattern = $GLOBALS['wpdb']->esc_like( $target_prefix ) . '%';
$target_tables = $GLOBALS['wpdb']->get_col(
	$GLOBALS['wpdb']->prepare(
		'SHOW TABLES LIKE %s',
		$table_pattern
	)
);

$target_verified_after_child_drift = null;
if ( is_array( $payload_child_import ) && is_array( $target_preflight_state ) ) {
	$child_id = (string) $target_preflight_state['child_import_job_id'];
	$child_store = new ImportStateStore();
	$drifted_child = $payload_child_import;
	$drifted_child['destination_table_prefix'] = $target_prefix . 'drift_';
	$child_store->save( $child_id, $drifted_child );
	$target_verified_after_child_drift = $target_preflight->verified_snapshot( $job_id );
	$child_store->save( $child_id, $payload_child_import );
}

$target_verified_after_mutation = null;
$local_rewrite_verified_after_mutation = null;
$local_rewrite_after_mutation = null;
$local_payload_verified_after_mutation = null;
$local_payload_after_mutation = null;
$payload_child_after_mutation = null;
$created_table = $target_prefix . 'tamper_guard';
$GLOBALS['wpdb']->query( "CREATE TABLE {$created_table} (id bigint unsigned NOT NULL)" );
$target_verified_after_mutation = $target_preflight->verified_snapshot( $job_id );
$local_rewrite_verified_after_mutation = $local_rewriter->verified_snapshot( $job_id );
$local_rewrite_after_mutation = $local_rewriter->advance( $job_id, 1 );
$local_payload_verified_after_mutation = $local_payload->verified_snapshot( $job_id );
$local_payload_after_mutation = $local_payload->advance( $job_id, 1, 1024 * 1024 );
$payload_child_after_mutation = '' !== $payload_child_id ? ( new ImportStateStore() )->get( $payload_child_id ) : null;
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$created_table}" );

$local_payload_after_recovery = $local_payload->advance( $job_id, 1, 1024 * 1024 );
$payload_child_after_recovery = '' !== $payload_child_id ? ( new ImportStateStore() )->get( $payload_child_id ) : null;
$local_database_verified_after_recovery = $local_database->verified_snapshot( $job_id );
$local_database_after_recovery = $local_database->advance( $job_id, 10 );
$local_files_verified_after_recovery = $local_files->verified_snapshot( $job_id );
$local_files_after_recovery = $local_files->advance( $job_id, 1, 1024 * 1024 );
$local_rewrite_verified_before_recovery = $local_rewriter->verified_snapshot( $job_id );
$local_rewrite_after_recovery = $local_rewriter->advance( $job_id, 1 );
$local_rewrite_verified_after_recovery = $local_rewriter->verified_snapshot( $job_id );
$job_after_recovery = $jobs->get( $job_id );

$archive_path = is_array( $delivery_info ) ? (string) $delivery_info['path'] : '';
if ( '' !== $archive_path && is_file( $archive_path ) ) {
	file_put_contents( $archive_path, "tamper", FILE_APPEND );
}
$verified_after_tamper = $handoff->verified_snapshot( $job_id );
$local_rewrite_verified_after_archive_tamper = $local_rewriter->verified_snapshot( $job_id );
$local_rewrite_after_archive_tamper = $local_rewriter->advance( $job_id, 1 );
$local_files_verified_after_archive_tamper = $local_files->verified_snapshot( $job_id );
$local_files_after_archive_tamper = $local_files->advance( $job_id, 1, 1024 * 1024 );
$local_database_verified_after_archive_tamper = $local_database->verified_snapshot( $job_id );
$local_database_after_archive_tamper = $local_database->advance( $job_id, 10 );
$local_payload_verified_after_archive_tamper = $local_payload->verified_snapshot( $job_id );
$local_payload_after_archive_tamper = $local_payload->advance( $job_id, 1, 1024 * 1024 );
$payload_child_after_archive_tamper = '' !== $payload_child_id ? ( new ImportStateStore() )->get( $payload_child_id ) : null;

$autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalClonePackageHandoffStateStore::OPTION_NAME
	)
);

$payload_autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalClonePayloadVerificationStateStore::OPTION_NAME
	)
);

$database_autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalCloneDatabaseRestoreStateStore::OPTION_NAME
	)
);

$file_autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalCloneFileRestoreStateStore::OPTION_NAME
	)
);

$rewrite_autoload = $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		"SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
		LocalCloneEnvironmentRewriteStateStore::OPTION_NAME
	)
);

$source_after_all = $source_snapshot();

echo wp_json_encode(
	array(
		'plan'                        => $plan,
		'claim'                       => $claim,
		'core_state'                  => $core_state,
		'sandbox_state'               => $sandbox_state,
		'handoff_state'               => $handoff_state,
		'target_preflight_state'      => $target_preflight_state,
		'target_preflight_verified'   => is_array( $target_preflight_verified ),
		'target_preflight_child'      => $target_preflight_child,
		'local_payload_mid'           => $local_payload_mid,
		'local_payload_state'         => $local_payload_state,
		'local_payload_verified'      => is_array( $local_payload_verified ),
		'payload_child_import'        => $payload_child_import,
		'payload_child_state'         => $payload_child_state,
		'target_preflight_after_payload' => is_array( $target_preflight_after_payload ),
		'local_database_mid'          => $local_database_mid,
		'local_database_state'        => $local_database_state,
		'local_database_verified'     => is_array( $local_database_verified ),
		'local_database_plan'         => $local_database_plan,
		'database_child_state'        => $database_child_state,
		'options_table'               => $options_table,
		'posts_table'                 => $posts_table,
		'options_staging_table'       => $options_staging_table,
		'posts_staging_table'         => $posts_staging_table,
		'staging_options_before_rewrite' => $staging_options_before_rewrite,
		'staging_posts_before_rewrite' => $staging_posts_before_rewrite,
		'staging_options_after_rewrite' => $staging_options_after_rewrite,
		'staging_posts_after_rewrite' => $staging_posts_after_rewrite,
		'source_table'                => $source_table,
		'source_prefix'               => $source_prefix,
		'source_before'               => $source_before,
		'source_after_database'       => $source_after_database,
		'source_after_all'            => $source_after_all,
		'target_table_count_after_database' => is_array( $target_tables_after_database ) ? count( $target_tables_after_database ) : -1,
		'local_files_mid'             => $local_files_mid,
		'local_files_state'           => $local_files_state,
		'local_files_verified'        => is_array( $local_files_verified ),
		'file_child_state'            => $file_child_state,
		'file_staging_root'           => $file_staging_root,
		'staged_upload'               => $staged_upload,
		'staged_plugin'               => $staged_plugin,
		'staged_theme'                => $staged_theme,
		'target_upload_absent_after_files' => $target_upload_absent_after_files,
		'target_plugin_absent_after_files' => $target_plugin_absent_after_files,
		'target_theme_absent_after_files' => $target_theme_absent_after_files,
		'target_bridge_present_after_files' => $target_bridge_present_after_files,
		'local_rewrite_mid'           => $local_rewrite_mid,
		'local_rewrite_state'         => $local_rewrite_state,
		'local_rewrite_verified'      => is_array( $local_rewrite_verified ),
		'rewrite_child_state'         => $rewrite_child_state,
		'job_before_mutation'         => $job_before_mutation,
		'target_verified_after_mutation' => is_array( $target_verified_after_mutation ),
		'local_rewrite_verified_after_mutation' => is_array( $local_rewrite_verified_after_mutation ),
		'local_rewrite_after_mutation' => $local_rewrite_after_mutation,
		'local_payload_verified_after_mutation' => is_array( $local_payload_verified_after_mutation ),
		'local_payload_after_mutation' => $local_payload_after_mutation,
		'payload_child_after_mutation' => $payload_child_after_mutation,
		'local_payload_after_recovery' => $local_payload_after_recovery,
		'local_database_verified_after_recovery' => is_array( $local_database_verified_after_recovery ),
		'local_database_after_recovery' => $local_database_after_recovery,
		'local_files_verified_after_recovery' => is_array( $local_files_verified_after_recovery ),
		'local_files_after_recovery' => $local_files_after_recovery,
		'local_rewrite_verified_before_recovery' => is_array( $local_rewrite_verified_before_recovery ),
		'local_rewrite_after_recovery' => $local_rewrite_after_recovery,
		'local_rewrite_verified_after_recovery' => is_array( $local_rewrite_verified_after_recovery ),
		'payload_child_after_recovery' => $payload_child_after_recovery,
		'job_after_recovery'          => $job_after_recovery,
		'target_verified_after_child_drift' => is_array( $target_verified_after_child_drift ),
		'verified_before'             => is_array( $verified_before ),
		'verified_after_tamper'       => is_array( $verified_after_tamper ),
		'local_rewrite_verified_after_archive_tamper' => is_array( $local_rewrite_verified_after_archive_tamper ),
		'local_rewrite_after_archive_tamper' => $local_rewrite_after_archive_tamper,
		'local_files_verified_after_archive_tamper' => is_array( $local_files_verified_after_archive_tamper ),
		'local_files_after_archive_tamper' => $local_files_after_archive_tamper,
		'local_database_verified_after_archive_tamper' => is_array( $local_database_verified_after_archive_tamper ),
		'local_database_after_archive_tamper' => $local_database_after_archive_tamper,
		'local_payload_verified_after_archive_tamper' => is_array( $local_payload_verified_after_archive_tamper ),
		'local_payload_after_archive_tamper' => $local_payload_after_archive_tamper,
		'payload_child_after_archive_tamper' => $payload_child_after_archive_tamper,
		'delivery_info_before_tamper' => $delivery_info,
		'frozen_manifest_hash'        => $frozen_manifest_hash,
		'manifest_after_hash'         => $manifest_after_hash,
		'package_state_manifest_hash_before' => (string) $frozen_package_state['package_manifest_hash'],
		'package_state_manifest_hash_after'  => is_array( $package_after ) ? (string) ( $package_after['package_manifest_hash'] ?? '' ) : '',
		'manifest_operation'          => is_array( $manifest_parsed ) ? (string) ( $manifest_parsed['operation'] ?? '' ) : '',
		'manifest_delivery_ready'     => is_array( $manifest_parsed ) ? (bool) ( $manifest_parsed['safety']['delivery_ready'] ?? true ) : true,
		'manifest_has_delivery'       => is_array( $manifest_parsed ) && array_key_exists( 'delivery', $manifest_parsed ),
		'target_table_count'          => is_array( $target_tables ) ? count( $target_tables ) : -1,
		'uploads_absent'              => ! file_exists( trailingslashit( $target_path ) . 'wp-content/uploads' ),
		'themes_absent'               => ! file_exists( trailingslashit( $target_path ) . 'wp-content/themes' ),
		'sandbox_still_verified'      => is_array( $sandbox->verified_snapshot( $job_id ) ),
		'controller_registered'       => false !== has_action( 'admin_post_' . AdminCloneLocalPackageHandoffController::ACTION ),
		'target_preflight_service_registered' => $target_preflight instanceof LocalCloneTargetPreflight,
		'local_payload_service_registered' => $local_payload instanceof LocalClonePayloadVerifier,
		'local_payload_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalPayloadController::ACTION ),
		'local_payload_public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalPayloadController::ACTION ),
		'local_database_service_registered' => $local_database instanceof LocalCloneDatabaseRestorer,
		'local_database_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalDatabaseController::ACTION ),
		'local_database_public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalDatabaseController::ACTION ),
		'local_files_service_registered' => $local_files instanceof LocalCloneFileRestorer,
		'local_files_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalFileController::ACTION ),
		'local_files_public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalFileController::ACTION ),
		'local_rewrite_service_registered' => $local_rewriter instanceof LocalCloneEnvironmentRewriter,
		'local_rewrite_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneLocalEnvironmentRewriteController::ACTION ),
		'local_rewrite_public_controller_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalEnvironmentRewriteController::ACTION ),
		'public_controller_absent'    => false === has_action( 'admin_post_nopriv_' . AdminCloneLocalPackageHandoffController::ACTION ),
		'autoload'                    => $autoload,
		'payload_autoload'            => $payload_autoload,
		'database_autoload'           => $database_autoload,
		'file_autoload'               => $file_autoload,
		'rewrite_autoload'            => $rewrite_autoload,
		'job'                         => $jobs->get( $job_id ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ( is_array( $local_database_plan ) && is_array( $local_database_plan['tables'] ?? null ) ) {
	foreach ( $local_database_plan['tables'] as $table_plan ) {
		if ( ! is_array( $table_plan ) || ! is_string( $table_plan['staging_table'] ?? null ) ) {
			continue;
		}
		$cleanup_table = chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $table_plan['staging_table'] ) . chr( 96 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test-only cleanup of deterministic staging table.
		$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$cleanup_table}" );
	}
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test-only production sentinel cleanup.
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS {$quoted_source}" );

$workspace->delete_delivery_archive( $job_id );
$workspace->cleanup( $job_id );
if ( '' !== $payload_child_id ) {
	$workspace->cleanup( $payload_child_id );
}
$remove_tree( $target_path );
foreach ( $options as $option ) {
	delete_option( $option );
}
PHP

docker cp "$LOCAL_HANDOFF_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-local-package-handoff-runner.php \
  || fail_smoke "local-clone-handoff-runner-copy" "Could not copy local clone handoff runner" "runner copied" "docker cp failed"

if ! LOCAL_HANDOFF_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-local-package-handoff-runner.php 2>"$TMP_DIR/portable-clone-local-package-handoff.stderr")"; then
  LOCAL_HANDOFF_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-local-package-handoff.stderr" | head -c 2200)"
  fail_smoke "local-clone-handoff-runner" "Local clone package handoff runner failed" "JSON contract report" "${LOCAL_HANDOFF_ERROR:-wp eval-file failed}"
fi

printf '%s' "$LOCAL_HANDOFF_JSON" >"$TMP_DIR/portable-clone-local-package-handoff.json"

if ! LOCAL_HANDOFF_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-local-package-handoff.json" <<'PY'
import hashlib
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

assert payload["plan"] is not None, payload
assert payload["plan"]["status"] == "ready", payload
assert payload["claim"] is not None, payload
assert payload["claim"]["status"] == "claimed", payload

core = payload["core_state"]
assert core is not None and core["status"] == "complete", payload
assert core["runtime_core_ready"] is True, payload

sandbox = payload["sandbox_state"]
assert sandbox is not None and sandbox["status"] == "complete", payload
assert sandbox["sandbox_runtime_ready"] is True, payload

handoff = payload["handoff_state"]
assert handoff is not None, payload
assert handoff["status"] == "ready", payload
assert handoff["transport"] == "private-same-server", payload
assert handoff["handoff_ready"] is True, payload
assert handoff["handoff_next"] == "target-intake-preflight", payload
assert handoff["archive_bytes"] > 0, payload
assert re.fullmatch(r"[a-f0-9]{64}", handoff["archive_sha256"]), payload
assert handoff["blockers"] == [], payload

target = payload["target_preflight_state"]
assert target is not None, payload
assert target["status"] == "ready", payload
assert target["preflight_ready"] is True, payload
assert target["restore_allowed"] is False, payload
assert target["database_untouched"] is True, payload
assert target["client_content_untouched"] is True, payload
assert target["preflight_next"] == "payload-extraction", payload
assert target["blockers"] == [], payload
assert re.fullmatch(r"[a-f0-9]{64}", target["destination_authority_sha256"]), payload
assert target["handoff_archive_sha256"] == handoff["archive_sha256"], payload
assert target["handoff_archive_bytes"] == handoff["archive_bytes"], payload
assert target["package_manifest_sha256"] == handoff["package_manifest_hash"], payload
assert target["package_checksum"] == handoff["package_checksum"], payload
assert payload["target_preflight_verified"] is True, payload
assert payload["target_verified_after_mutation"] is False, payload
assert payload["target_verified_after_child_drift"] is False, payload
assert payload["target_preflight_service_registered"] is True, payload

mid = payload["local_payload_mid"]
assert mid is not None, payload
assert mid["status"] == "running", payload
assert mid["stage"] in ("extract", "verify"), payload

local_payload = payload["local_payload_state"]
assert local_payload is not None, payload
assert local_payload["status"] == "ready", payload
assert local_payload["stage"] == "complete", payload
assert local_payload["payload_verified"] is True, payload
assert local_payload["child_restore_allowed"] is True, payload
assert local_payload["database_untouched"] is True, payload
assert local_payload["client_content_untouched"] is True, payload
assert local_payload["payload_next"] == "database-staging-restore", payload
assert local_payload["verification_checksum"] == target["package_checksum"], payload
assert local_payload["verify_file_count"] == local_payload["expected_file_count"], payload
assert local_payload["verify_byte_count"] == local_payload["expected_byte_count"], payload
assert local_payload["blockers"] == [], payload
assert payload["local_payload_verified"] is True, payload
assert payload["target_preflight_after_payload"] is True, payload

payload_state = payload["payload_child_state"]
assert payload_state is not None, payload
assert payload_state["status"] == "complete", payload
assert payload_state["stage"] == "complete", payload
assert payload_state["verification_checksum"] == target["package_checksum"], payload

payload_import = payload["payload_child_import"]
assert payload_import is not None, payload
assert payload_import["status"] == "payload-verified", payload
assert payload_import["full_payload_verified"] is True, payload
assert payload_import["restore_allowed"] is True, payload
assert payload_import["blockers"] == [], payload
assert "restore-runtime-guard-required" in payload_import["advisories"], payload

local_db_mid = payload["local_database_mid"]
assert local_db_mid is not None, payload
assert local_db_mid["status"] == "running", payload
assert local_db_mid["stage"] in ("rows", "table-verify", "schema"), payload

local_db = payload["local_database_state"]
assert local_db is not None, payload
assert local_db["status"] == "ready", payload
assert local_db["stage"] == "complete", payload
assert local_db["destination_prefix"] == handoff["target_table_prefix"], payload
assert local_db["rows_restored"] == 3, payload
assert local_db["tables_completed"] == 1, payload
assert local_db["table_count"] == 1, payload
assert local_db["active_tables_untouched"] is True, payload
assert local_db["target_tables_untouched"] is True, payload
assert local_db["client_content_untouched"] is True, payload
assert local_db["database_next"] == "file-staging-restore", payload
assert local_db["blockers"] == [], payload
assert payload["local_database_verified"] is True, payload

db_child = payload["database_child_state"]
assert db_child is not None, payload
assert db_child["status"] == "complete", payload
assert db_child["stage"] == "complete", payload
assert db_child["rows_restored"] == 3, payload
assert db_child["tables_completed"] == 1, payload
assert db_child["active_tables_untouched"] is True, payload
assert db_child["destination_prefix"] == handoff["target_table_prefix"], payload
assert db_child["staging_namespace"] == local_db["staging_namespace"], payload

db_plan = payload["local_database_plan"]
assert db_plan is not None, payload
assert db_plan["destination_prefix"] == handoff["target_table_prefix"], payload
assert db_plan["staging_namespace"] == local_db["staging_namespace"], payload
assert len(db_plan["tables"]) == 1, payload
assert db_plan["tables"][0]["source_table"] == payload["source_table"], payload
assert db_plan["tables"][0]["target_table"] == handoff["target_table_prefix"] + payload["source_table"][len(payload["source_prefix"]):], payload
assert db_plan["tables"][0]["staging_table"] == payload["staging_table"], payload
assert not payload["staging_table"].startswith(payload["source_prefix"]), payload
assert not payload["staging_table"].startswith(handoff["target_table_prefix"]), payload

assert payload["staging_rows"] == [
    {"id": "1", "title": "alpha"},
    {"id": "2", "title": "beta"},
    {"id": "3", "title": "gamma"},
], payload
assert payload["source_before"] == [{"id": "999", "title": "production-sentinel"}], payload
assert payload["source_after_database"] == payload["source_before"], payload
assert payload["source_after_all"] == payload["source_before"], payload
assert payload["target_table_count_after_database"] == 0, payload

files_mid = payload["local_files_mid"]
assert files_mid is not None, payload
assert files_mid["status"] == "running", payload
assert files_mid["stage"] in ("copy", "verify"), payload

local_files = payload["local_files_state"]
assert local_files is not None, payload
assert local_files["status"] == "ready", payload
assert local_files["stage"] == "complete", payload
assert local_files["file_count"] == 3, payload
assert local_files["verify_file_count"] == 3, payload
assert local_files["file_count"] == local_files["expected_file_count"], payload
assert local_files["byte_count"] == local_files["expected_byte_count"], payload
assert local_files["verify_file_count"] == local_files["expected_file_count"], payload
assert local_files["verify_byte_count"] == local_files["expected_byte_count"], payload
assert local_files["active_roots_untouched"] is True, payload
assert local_files["target_client_roots_untouched"] is True, payload
assert local_files["private_staging_verified"] is True, payload
assert local_files["file_next"] == "environment-rewrite", payload
assert local_files["blockers"] == [], payload
assert payload["local_files_verified"] is True, payload
assert payload["file_staging_root"], payload

file_child = payload["file_child_state"]
assert file_child is not None, payload
assert file_child["status"] == "complete", payload
assert file_child["stage"] == "complete", payload
assert file_child["file_count"] == 3, payload
assert file_child["verify_file_count"] == 3, payload
assert file_child["active_roots_untouched"] is True, payload

expected_files = {
    "staged_upload": b"local-upload\n",
    "staged_plugin": b"<?php\n// local staged plugin\n",
    "staged_theme": b"body{display:block}\n",
}
for key, content in expected_files.items():
    info = payload[key]
    assert info is not None, payload
    assert info["bytes"] == len(content), payload
    assert info["sha256"] == hashlib.sha256(content).hexdigest(), payload
    assert info["path"].startswith(payload["file_staging_root"]), payload

assert payload["target_upload_absent_after_files"] is True, payload
assert payload["target_plugin_absent_after_files"] is True, payload
assert payload["target_theme_absent_after_files"] is True, payload
assert payload["target_bridge_present_after_files"] is True, payload

assert payload["local_payload_verified_after_mutation"] is False, payload
blocked_parent = payload["local_payload_after_mutation"]
assert blocked_parent is not None and blocked_parent["status"] == "blocked", payload
assert "local-payload-parent-authority-unavailable" in blocked_parent["blockers"], payload
blocked_child = payload["payload_child_after_mutation"]
assert blocked_child is not None, payload
assert blocked_child["status"] == "blocked", payload
assert blocked_child["full_payload_verified"] is False, payload
assert blocked_child["restore_allowed"] is False, payload
assert "local-payload-parent-authority-unavailable" in blocked_child["blockers"], payload

recovered_parent = payload["local_payload_after_recovery"]
assert recovered_parent is not None, payload
assert recovered_parent["status"] == "ready", payload
assert recovered_parent["payload_verified"] is True, payload
assert recovered_parent["child_restore_allowed"] is True, payload
recovered_child = payload["payload_child_after_recovery"]
assert recovered_child is not None, payload
assert recovered_child["status"] == "payload-verified", payload
assert recovered_child["full_payload_verified"] is True, payload
assert recovered_child["restore_allowed"] is True, payload
assert payload["local_database_verified_after_recovery"] is True, payload
db_recovered = payload["local_database_after_recovery"]
assert db_recovered is not None and db_recovered["status"] == "ready", payload
assert db_recovered["stage"] == "complete", payload
assert payload["local_files_verified_after_recovery"] is True, payload
files_recovered = payload["local_files_after_recovery"]
assert files_recovered is not None and files_recovered["status"] == "ready", payload
assert files_recovered["stage"] == "complete", payload
assert files_recovered["private_staging_verified"] is True, payload

job_after_recovery = payload["job_after_recovery"]
assert job_after_recovery["status"] == "active", payload
assert job_after_recovery["phase"] == "restore-files", payload
assert job_after_recovery["cursor"] == "local-file-staging-complete", payload

assert payload["local_files_verified_after_archive_tamper"] is False, payload
archive_files_blocked = payload["local_files_after_archive_tamper"]
assert archive_files_blocked is not None and archive_files_blocked["status"] == "blocked", payload
assert "local-files-parent-authority-unavailable" in archive_files_blocked["blockers"], payload

assert payload["local_database_verified_after_archive_tamper"] is False, payload
archive_db_blocked = payload["local_database_after_archive_tamper"]
assert archive_db_blocked is not None and archive_db_blocked["status"] == "blocked", payload
assert "local-database-parent-authority-unavailable" in archive_db_blocked["blockers"], payload

assert payload["local_payload_verified_after_archive_tamper"] is False, payload
archive_blocked_parent = payload["local_payload_after_archive_tamper"]
assert archive_blocked_parent is not None and archive_blocked_parent["status"] == "blocked", payload
assert "local-payload-parent-authority-unavailable" in archive_blocked_parent["blockers"], payload
archive_blocked_child = payload["payload_child_after_archive_tamper"]
assert archive_blocked_child is not None, payload
assert archive_blocked_child["status"] == "blocked", payload
assert archive_blocked_child["full_payload_verified"] is False, payload
assert archive_blocked_child["restore_allowed"] is False, payload

assert payload["local_payload_service_registered"] is True, payload
assert payload["local_payload_controller_registered"] is True, payload
assert payload["local_payload_public_controller_absent"] is True, payload
assert payload["local_database_service_registered"] is True, payload
assert payload["local_database_controller_registered"] is True, payload
assert payload["local_database_public_controller_absent"] is True, payload
assert payload["local_files_service_registered"] is True, payload
assert payload["local_files_controller_registered"] is True, payload
assert payload["local_files_public_controller_absent"] is True, payload

child = payload["target_preflight_child"]
assert child is not None, payload
assert child["status"] == "preflight-ready", payload
assert child["transport"] == "private-same-server", payload
assert child["local_handoff_parent_job_id"] == handoff["job_id"], payload
assert child["destination_home_url"] == handoff["target_url"], payload
assert child["destination_site_url"] == handoff["target_url"], payload
assert child["destination_table_prefix"] == handoff["target_table_prefix"], payload
assert child["destination_mode"] == "subdirectory", payload
assert child["destination_storage_isolated"] is True, payload
assert child["search_visibility_disabled"] is True, payload
assert child["outbound_safe"] is True, payload
assert child["backups_ready"] is True, payload
assert child["target_authorized"] is True, payload
assert child["manifest_contract_valid"] is True, payload
assert child["child_manifest_hashes_valid"] is True, payload
assert child["full_payload_verified"] is False, payload
assert child["restore_allowed"] is False, payload
assert child["blockers"] == [], payload
assert "full-payload-checksum-pending" in child["advisories"], payload

delivery = payload["delivery_info_before_tamper"]
assert delivery is not None, payload
assert delivery["bytes"] == handoff["archive_bytes"], payload
assert delivery["sha256"] == handoff["archive_sha256"], payload
assert re.fullmatch(r"[a-f0-9]{64}", delivery["sha256"]), payload

assert payload["verified_before"] is True, payload
assert payload["verified_after_tamper"] is False, payload
assert payload["frozen_manifest_hash"] == payload["manifest_after_hash"], payload
assert payload["package_state_manifest_hash_before"] == payload["package_state_manifest_hash_after"], payload
assert payload["package_state_manifest_hash_after"] == payload["frozen_manifest_hash"], payload
assert payload["manifest_operation"] == "local-clone", payload
assert payload["manifest_delivery_ready"] is False, payload
assert payload["manifest_has_delivery"] is False, payload

assert payload["target_table_count"] == 0, payload
assert payload["uploads_absent"] is True, payload
assert payload["themes_absent"] is True, payload
assert payload["sandbox_still_verified"] is True, payload
assert payload["controller_registered"] is True, payload
assert payload["public_controller_absent"] is True, payload
assert payload["autoload"] in ("off", "no", "auto-off"), payload
assert payload["payload_autoload"] in ("off", "no", "auto-off"), payload
assert payload["database_autoload"] in ("off", "no", "auto-off"), payload
assert payload["file_autoload"] in ("off", "no", "auto-off"), payload

job_before_mutation = payload["job_before_mutation"]
assert job_before_mutation["operation"] == "local-clone", payload
assert job_before_mutation["status"] == "active", payload
assert job_before_mutation["phase"] == "restore-files", payload
assert job_before_mutation["cursor"] == "local-file-staging-complete", payload

job = payload["job"]
assert job["operation"] == "local-clone", payload
assert job["status"] == "failed-retryable", payload
assert job["error_code"] == "local-payload-parent-authority-unavailable", payload

print("ok")
PY
)"; then
  fail_smoke "local-clone-package-handoff" "Local clone handoff/intake/payload/database/file contract is invalid" "immutable package + DB staging + verified private files + zero active target promotion" "${LOCAL_HANDOFF_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Local clone handoff + database + file staging OK: rows and client files restored only to isolated job-owned staging, production/target roots stayed untouched, authority drift revoked restore eligibility.\n'
