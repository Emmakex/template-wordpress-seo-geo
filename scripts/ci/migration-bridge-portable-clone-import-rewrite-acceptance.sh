#!/usr/bin/env bash
# Phase 10E.2A.4.5 serialization-safe staging environment rewrite acceptance.

printf '[smoke] Checking Portable Import serialization-safe environment rewrite.\n'

IMPORT_REWRITE_RUNNER="$TMP_DIR/portable-clone-import-rewrite-runner.php"
cat >"$IMPORT_REWRITE_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportRewriteController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFinalizeController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportDatabaseActivationController;
use SeoGeo\MigrationBridge\Clone\AdminCloneImportFilePromotionController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivator;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseActivationStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFilePromoter;
use SeoGeo\MigrationBridge\Clone\ImportEnvironmentRewriter;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportRewriteStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizeStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFinalizationPlanner;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Plugin;

foreach (
	array(
		CloneJobStore::OPTION_NAME,
		ImportStateStore::OPTION_NAME,
		ImportPayloadStateStore::OPTION_NAME,
		ImportDatabaseStateStore::OPTION_NAME,
		ImportFileStateStore::OPTION_NAME,
		ImportRewriteStateStore::OPTION_NAME,
		ImportFinalizeStateStore::OPTION_NAME,
	) as $option_name
) {
	delete_option( $option_name );
}
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

$jobs         = Plugin::clone_job_store();
$preflight    = Plugin::clone_import_preflight();
$verifier     = Plugin::clone_import_payload_verifier();
$db_restore   = Plugin::clone_import_database_restorer();
$file_restore = Plugin::clone_import_file_restorer();
$rewriter     = Plugin::clone_import_environment_rewriter();
$finalizer    = Plugin::clone_import_finalization_planner();
$activator    = Plugin::clone_import_database_activator();
$promoter     = Plugin::clone_import_file_promoter();
$workspace    = new ExportWorkspace();

if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
	|| ! $db_restore instanceof ImportDatabaseRestorer
	|| ! $file_restore instanceof ImportFileRestorer
	|| ! $rewriter instanceof ImportEnvironmentRewriter
	|| ! $finalizer instanceof ImportFinalizationPlanner
	|| ! $activator instanceof ImportDatabaseActivator
	|| ! $promoter instanceof ImportFilePromoter
) {
	throw new RuntimeException( 'Portable Import environment rewrite/finalization services are unavailable.' );
}

global $wpdb;
if ( ! $wpdb instanceof wpdb ) {
	throw new RuntimeException( 'WordPress database runtime is unavailable.' );
}

$source_home   = 'https://source.example.test/';
$source_site   = 'https://source.example.test/wordpress/';
$source_prefix = 'src_';
$source_theme = 'seo-geo-fixture-theme';
$source_bridge_plugin = 'seo-geo-migration-bridge/seo-geo-migration-bridge.php';
$options_table = $source_prefix . 'options';
$posts_table   = $source_prefix . 'posts';
$destination_home = home_url( '/' );
$destination_site = site_url( '/' );
$destination_template   = (string) get_option( 'template', '' );
$destination_stylesheet = (string) get_option( 'stylesheet', '' );

$upload_dir = wp_upload_dir( null, false );
if ( ! is_array( $upload_dir ) || ! empty( $upload_dir['error'] ) || ! is_string( $upload_dir['basedir'] ?? null ) ) {
	throw new RuntimeException( 'Could not resolve active uploads directory for promotion smoke.' );
}
$promotion_upload_sentinel = trailingslashit( $upload_dir['basedir'] ) . 'seo-geo-promotion-active-sentinel.txt';
if ( ! is_dir( dirname( $promotion_upload_sentinel ) ) && ! wp_mkdir_p( dirname( $promotion_upload_sentinel ) ) ) {
	throw new RuntimeException( 'Could not prepare active uploads sentinel directory.' );
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-only active-root rollback sentinel.
if ( false === file_put_contents( $promotion_upload_sentinel, 'active-root-before-promotion' ) ) {
	throw new RuntimeException( 'Could not create active uploads rollback sentinel.' );
}
$promotion_upload_sentinel_hash = hash_file( 'sha256', $promotion_upload_sentinel );

$active_sentinel_name  = 'seo_geo_rewrite_active_sentinel';
$active_sentinel_value = $source_home . 'must-stay-active';
delete_option( $active_sentinel_name );
add_option( $active_sentinel_name, $active_sentinel_value, '', false );

/**
 * Replay the exact Portable Clone lexicographic BFS checksum.
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
			throw new RuntimeException( 'Could not scan environment-rewrite fixture workspace.' );
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
				throw new RuntimeException( 'Environment-rewrite fixture contains a symlink.' );
			}
			if ( is_dir( $absolute ) ) {
				$pending_dirs[] = $relative;
				continue;
			}

			$bytes = filesize( $absolute );
			$hash  = hash_file( 'sha256', $absolute );
			if ( false === $bytes || false === $hash ) {
				throw new RuntimeException( 'Could not hash environment-rewrite fixture payload.' );
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
 * Base64 encode one database row.
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
 * Write one database table schema/chunk and return manifest metadata.
 *
 * @param string                    $source_job_id Source fixture job.
 * @param string                    $table         Source table.
 * @param list<string>              $columns       Columns.
 * @param string                    $id_column     Primary key.
 * @param list<list<string|null>>   $rows          Raw rows.
 * @param string                    $schema_sql    CREATE TABLE statement.
 * @return array<string,mixed>
 */
$write_table = static function (
	string $source_job_id,
	string $table,
	array $columns,
	string $id_column,
	array $rows,
	string $schema_sql
) use ( $workspace, $encode_row ): array {
	$table_dir = 'database/tables/' . substr( hash( 'sha256', $table ), 0, 20 );
	$schema    = $workspace->write( $source_job_id, $table_dir . '/schema.sql', $schema_sql . "\n" );
	if ( ! is_array( $schema ) ) {
		throw new RuntimeException( 'Could not write rewrite fixture schema.' );
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
	$json = wp_json_encode( $chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) ) {
		throw new RuntimeException( 'Could not encode rewrite fixture chunk.' );
	}

	$chunk_path = $table_dir . '/chunks/000000.json';
	$written    = $workspace->write( $source_job_id, $chunk_path, $json );
	if ( ! is_array( $written ) ) {
		throw new RuntimeException( 'Could not write rewrite fixture chunk.' );
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

/**
 * Build one valid Portable Clone fixture with environment-sensitive WordPress data.
 *
 * @return array{path:string,sha256:string,bytes:int}
 */
$build_archive = static function ( string $source_job_id ) use (
	$workspace,
	$workspace_checksum,
	$write_table,
	$source_home,
	$source_site,
	$source_prefix,
	$options_table,
	$posts_table,
	$source_theme,
	$source_bridge_plugin
): array {
	$workspace->cleanup( $source_job_id );
	$workspace->delete_delivery_archive( $source_job_id );

	$serialized = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Test fixture deliberately exercises safe serialized rewriting.
		array(
			'url'         => $source_home . 'serialized',
			'asset'       => $source_home . 'wp-content/uploads/2026/file.txt',
			'nested_json' => wp_json_encode( array( 'deep' => $source_site . 'deep' ), JSON_UNESCAPED_SLASHES ),
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
		throw new RuntimeException( 'Could not encode JSON rewrite fixture.' );
	}

	$options_rows = array(
		array( '1', 'home', $source_home, 'yes' ),
		array( '2', 'siteurl', $source_site, 'yes' ),
		array( '3', 'plain_url', $source_home . 'catalog/item?x=1#top', 'yes' ),
		array( '4', 'serialized_payload', $serialized, 'yes' ),
		array( '5', 'json_payload', $json_value, 'yes' ),
		array( '6', 'api_token', 'token-value::' . $source_home . 'credential-context', 'yes' ),
		array( '7', 'opaque_safe', 'a:1:{s:3:"bad";s:4:"nope"', 'yes' ),
		array( '8', 'active_plugins', serialize( array( $source_bridge_plugin ) ), 'yes' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress option fixture.
		array( '9', 'template', $source_theme, 'yes' ),
		array( '10', 'stylesheet', $source_theme, 'yes' ),
	);
	$posts_rows = array(
		array(
			'1',
			'Visit ' . $source_home . 'about and keep https://external.example.test/reference',
			'Media ' . $source_home . 'wp-content/uploads/2026/file.txt',
			'',
		),
	);

	$options_schema = 'CREATE TABLE ' . $options_table . ' ('
		. 'option_id bigint unsigned NOT NULL, '
		. 'option_name varchar(191) NOT NULL, '
		. 'option_value longtext NOT NULL, '
		. 'autoload varchar(20) NOT NULL DEFAULT \'yes\', '
		. 'PRIMARY KEY (option_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
	$posts_schema = 'CREATE TABLE ' . $posts_table . ' ('
		. 'ID bigint unsigned NOT NULL, '
		. 'post_content longtext NOT NULL, '
		. 'post_excerpt text NOT NULL, '
		. 'post_content_filtered longtext NOT NULL, '
		. 'PRIMARY KEY (ID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

	$options_meta = $write_table(
		$source_job_id,
		$options_table,
		array( 'option_id', 'option_name', 'option_value', 'autoload' ),
		'option_id',
		$options_rows,
		$options_schema
	);
	$posts_meta = $write_table(
		$source_job_id,
		$posts_table,
		array( 'ID', 'post_content', 'post_excerpt', 'post_content_filtered' ),
		'ID',
		$posts_rows,
		$posts_schema
	);

	$database_manifest = array(
		'schema_version'              => 1,
		'package_id'                  => $source_job_id,
		'payload_class'               => 'database',
		'source'                      => array(
			'home_url'          => $source_home,
			'site_url'          => $source_site,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'table_prefix'      => $source_prefix,
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
	$database      = $workspace->write( $source_job_id, 'database/manifest.json', $database_json );
	if ( ! is_array( $database ) ) {
		throw new RuntimeException( 'Could not write rewrite database manifest.' );
	}

	$file_specs = array(
		array(
			'root'     => 'uploads',
			'relative' => '2026/file.txt',
			'content'  => 'staged-file-with-source-url::' . $source_home . 'must-remain-byte-identical',
		),
		array(
			'root'     => 'plugins',
			'relative' => $source_bridge_plugin,
			'content'  => "<?php\n/* Plugin Name: SEO GEO Migration Bridge smoke fixture */\n",
		),
		array(
			'root'     => 'themes',
			'relative' => $source_theme . '/style.css',
			'content'  => "/*\nTheme Name: SEO GEO Fixture Theme\n*/\n",
		),
	);

	$root_summaries = array(
		'uploads' => array( 'file_count' => 0, 'byte_count' => 0 ),
		'plugins' => array( 'file_count' => 0, 'byte_count' => 0 ),
		'themes'  => array( 'file_count' => 0, 'byte_count' => 0 ),
	);
	$file_count_total = 0;
	$file_bytes_total = 0;
	foreach ( $file_specs as $spec ) {
		$root_id  = (string) $spec['root'];
		$relative = (string) $spec['relative'];
		$written  = $workspace->write( $source_job_id, 'files/' . $root_id . '/' . $relative, (string) $spec['content'] );
		if ( ! is_array( $written ) ) {
			throw new RuntimeException( 'Could not write rewrite file payload: ' . $root_id . '/' . $relative );
		}

		$record = array(
			'root'          => $root_id,
			'relative_path' => $relative,
			'payload_path'  => 'files/' . $root_id . '/' . $relative,
			'byte_count'    => (int) $written['bytes'],
			'sha256'        => (string) $written['sha256'],
			'export_status' => 'copied',
		);
		$record_path = 'files-meta/' . $root_id . '/' . hash( 'sha256', $relative ) . '.json';
		if ( ! is_array( $workspace->write( $source_job_id, $record_path, wp_json_encode( $record ) . "\n" ) ) ) {
			throw new RuntimeException( 'Could not write rewrite file record.' );
		}

		++$root_summaries[ $root_id ]['file_count'];
		$root_summaries[ $root_id ]['byte_count'] += (int) $written['bytes'];
		++$file_count_total;
		$file_bytes_total += (int) $written['bytes'];
	}

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => $file_count_total,
		'payload_bytes'               => $file_bytes_total,
		'roots'                       => array(
			array(
				'id'         => 'uploads',
				'file_count' => $root_summaries['uploads']['file_count'],
				'byte_count' => $root_summaries['uploads']['byte_count'],
			),
			array(
				'id'         => 'plugins',
				'file_count' => $root_summaries['plugins']['file_count'],
				'byte_count' => $root_summaries['plugins']['byte_count'],
			),
			array(
				'id'         => 'themes',
				'file_count' => $root_summaries['themes']['file_count'],
				'byte_count' => $root_summaries['themes']['byte_count'],
			),
		),
		'source_fingerprint'          => hash( 'sha256', 'rewrite-fixture-' . $source_job_id ),
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
	$files      = $workspace->write( $source_job_id, 'files/manifest.json', $files_json );
	if ( ! is_array( $files ) ) {
		throw new RuntimeException( 'Could not write rewrite files manifest.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Rewrite fixture source workspace unavailable.' );
	}
	$integrity = $workspace_checksum( $root );

	$package_manifest = array(
		'schema_version' => 1,
		'mode'           => 'portable-clone-package',
		'package_id'     => $source_job_id,
		'operation'      => 'export',
		'source'         => array(
			'home_url'           => $source_home,
			'site_url'           => $source_site,
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'source_fingerprint' => hash( 'sha256', 'rewrite-fixture-' . $source_job_id ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $database['sha256'],
				'row_count'       => (int) $database_manifest['row_count'],
				'chunk_count'     => 2,
				'payload_bytes'   => (int) $database_manifest['payload_bytes'],
			),
			'files'    => array(
				'manifest_path'   => 'files/manifest.json',
				'manifest_sha256' => (string) $files['sha256'],
				'file_count'      => $file_count_total,
				'payload_bytes'   => $file_bytes_total,
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
		throw new RuntimeException( 'Could not write rewrite package manifest.' );
	}

	$archive_files = array();
	$iterator      = new RecursiveIteratorIterator(
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
		throw new RuntimeException( 'Could not reset rewrite fixture archive.' );
	}
	foreach ( array_chunk( $archive_files, 5 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append rewrite fixture archive batch.' );
		}
	}
	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize rewrite fixture archive.' );
	}

	return $archive;
};

/**
 * Prepare one job through verified DB + file staging.
 */
$prepare_import = static function ( string $job_id, array $archive ) use (
	$jobs,
	$preflight,
	$verifier,
	$db_restore,
	$file_restore
): void {
	if (
		! is_array( $jobs->create( 'import', $job_id ) )
		|| ! is_array( $preflight->stage( $job_id, $archive['path'] ) )
	) {
		throw new RuntimeException( 'Could not stage environment rewrite import fixture.' );
	}

	$pre = $preflight->validate( $job_id );
	if ( ! is_array( $pre ) || 'preflight-ready' !== ( $pre['status'] ?? null ) ) {
		throw new RuntimeException( 'Environment rewrite fixture did not pass preflight.' );
	}

	for ( $iteration = 0; $iteration < 200; ++$iteration ) {
		$payload = $verifier->advance( $job_id, 2, 1048576 );
		if ( ! is_array( $payload ) ) {
			throw new RuntimeException( 'Environment rewrite payload verifier returned no state.' );
		}
		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment rewrite payload verification was blocked.' );
		}
	}

	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		$db = $db_restore->advance( $job_id, 2 );
		if ( ! is_array( $db ) ) {
			throw new RuntimeException( 'Environment rewrite database staging returned no state.' );
		}
		if ( 'complete' === ( $db['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $db['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment rewrite database staging was blocked: ' . wp_json_encode( $db ) );
		}
	}

	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		$files = $file_restore->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $files ) ) {
			throw new RuntimeException( 'Environment rewrite file staging returned no state.' );
		}
		if ( 'complete' === ( $files['status'] ?? null ) ) {
			return;
		}
		if ( 'blocked' === ( $files['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment rewrite file staging was blocked.' );
		}
	}

	throw new RuntimeException( 'Environment rewrite fixture did not finish verified staging.' );
};

$source_job = 'clone-import-rewrite-source-0001';
$job_id     = 'clone-import-rewrite-good-0001';
$archive    = $build_archive( $source_job );
$prepare_import( $job_id, $archive );

$plan = $db_restore->staging_plan( $job_id );
if ( ! is_array( $plan ) ) {
	throw new RuntimeException( 'Environment rewrite staging plan unavailable.' );
}

$table_map = array();
foreach ( $plan['tables'] as $table ) {
	if ( is_array( $table ) && is_string( $table['source_table'] ?? null ) ) {
		$table_map[ $table['source_table'] ] = $table;
	}
}
if ( ! isset( $table_map[ $options_table ], $table_map[ $posts_table ] ) ) {
	throw new RuntimeException( 'Environment rewrite core staging tables missing.' );
}

$options_staging = (string) $table_map[ $options_table ]['staging_table'];
$posts_staging   = (string) $table_map[ $posts_table ]['staging_table'];
$quote = static fn( string $identifier ): string => chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $identifier ) . chr( 96 );
$q_options = $quote( $options_staging );
$q_posts   = $quote( $posts_staging );

$staged_file_before = $workspace->import_staged_file_info( $job_id, 'uploads', '2026/file.txt' );
$active_before      = get_option( $active_sentinel_name );

$good = null;
for ( $iteration = 0; $iteration < 120; ++$iteration ) {
	$good = $rewriter->advance( $job_id, 2 );
	if ( ! is_array( $good ) ) {
		throw new RuntimeException( 'Environment rewrite returned no state.' );
	}
	if ( in_array( $good['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}
if ( ! is_array( $good ) || 'complete' !== ( $good['status'] ?? null ) ) {
	throw new RuntimeException( 'Environment rewrite did not complete: ' . wp_json_encode( $good ) );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$options_rows = $wpdb->get_results( "SELECT option_id, option_name, option_value FROM {$q_options} ORDER BY option_id ASC", ARRAY_A );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$posts_rows = $wpdb->get_results( "SELECT ID, post_content, post_excerpt, post_content_filtered FROM {$q_posts} ORDER BY ID ASC", ARRAY_A );
if ( ! is_array( $options_rows ) || ! is_array( $posts_rows ) ) {
	throw new RuntimeException( 'Could not inspect rewritten staging rows.' );
}

$options = array();
foreach ( $options_rows as $row ) {
	$options[ (string) $row['option_name'] ] = (string) $row['option_value'];
}

$serialized_after = isset( $options['serialized_payload'] )
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Smoke validates that reserialized staging bytes remain structurally valid with classes disabled.
	? @unserialize( $options['serialized_payload'], array( 'allowed_classes' => false ) )
	: null;
$json_after = isset( $options['json_payload'] ) ? json_decode( $options['json_payload'], true ) : null;

$staged_file_after = $workspace->import_staged_file_info( $job_id, 'uploads', '2026/file.txt' );
$active_after      = get_option( $active_sentinel_name );

$finalize = null;
for ( $iteration = 0; $iteration < 160; ++$iteration ) {
	$finalize = $finalizer->advance( $job_id, 2, 1, 1048576 );
	if ( ! is_array( $finalize ) ) {
		throw new RuntimeException( 'Finalization preflight returned no state.' );
	}
	if ( in_array( $finalize['status'] ?? null, array( 'ready', 'blocked' ), true ) ) {
		break;
	}
}
if ( ! is_array( $finalize ) || 'ready' !== ( $finalize['status'] ?? null ) ) {
	throw new RuntimeException( 'Finalization preflight did not reach ready: ' . wp_json_encode( $finalize ) );
}

$staged_file_after_finalize = $workspace->import_staged_file_info( $job_id, 'uploads', '2026/file.txt' );
$active_after_finalize      = get_option( $active_sentinel_name );

// 10E.2A.4.6.2: prepare, atomically activate, verify and explicitly roll back
// the sandbox database while active wp-content remains untouched.
$activation_prepared = $activator->prepare( $job_id );
if ( ! is_array( $activation_prepared ) || 'prepared' !== ( $activation_prepared['status'] ?? null ) ) {
	throw new RuntimeException( 'Database activation preparation failed: ' . wp_json_encode( $activation_prepared ) );
}

$activation = $activator->activate( $job_id );
if ( ! is_array( $activation ) || 'activated' !== ( $activation['status'] ?? null ) ) {
	throw new RuntimeException( 'Database activation failed: ' . wp_json_encode( $activation ) );
}

$q_active_options = $quote( $wpdb->options );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$activation_blog_public = $wpdb->get_var( "SELECT option_value FROM {$q_active_options} WHERE option_name = 'blog_public' LIMIT 1" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$activation_template = $wpdb->get_var( "SELECT option_value FROM {$q_active_options} WHERE option_name = 'template' LIMIT 1" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$activation_stylesheet = $wpdb->get_var( "SELECT option_value FROM {$q_active_options} WHERE option_name = 'stylesheet' LIMIT 1" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$activation_control_plane = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT option_value FROM {$q_active_options} WHERE option_name = %s LIMIT 1",
		CloneJobStore::OPTION_NAME
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$activation_plugins_raw = $wpdb->get_var( "SELECT option_value FROM {$q_active_options} WHERE option_name = 'active_plugins' LIMIT 1" );
$activation_plugins = is_string( $activation_plugins_raw ) && is_serialized( $activation_plugins_raw, false )
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Test-only verification with classes disabled.
	? @unserialize( $activation_plugins_raw, array( 'allowed_classes' => false ) )
	: null;
$bridge_plugin = plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR . 'seo-geo-migration-bridge.php' );
$activation_bridge_active = is_array( $activation_plugins ) && in_array( $bridge_plugin, $activation_plugins, true );

// 10E.2A.4.6.3: build same-filesystem candidates, promote all active roots,
// verify the final target, enable handoff, then prove explicit filesystem rollback.
$promotion_prepared = $promoter->prepare( $job_id );
if ( ! is_array( $promotion_prepared ) || 'prepared' !== ( $promotion_prepared['status'] ?? null ) ) {
	throw new RuntimeException( 'File promotion preparation failed: ' . wp_json_encode( $promotion_prepared ) );
}

$promotion_candidates = null;
for ( $iteration = 0; $iteration < 160; ++$iteration ) {
	$promotion_candidates = $promoter->advance_candidates( $job_id, 1, 1048576 );
	if ( ! is_array( $promotion_candidates ) ) {
		throw new RuntimeException( 'File promotion candidate build returned no state.' );
	}
	if ( in_array( $promotion_candidates['status'] ?? null, array( 'candidate-ready', 'blocked' ), true ) ) {
		break;
	}
}
if ( ! is_array( $promotion_candidates ) || 'candidate-ready' !== ( $promotion_candidates['status'] ?? null ) ) {
	throw new RuntimeException( 'File promotion candidates did not reach ready: ' . wp_json_encode( $promotion_candidates ) );
}

$promotion = $promoter->promote( $job_id );
if ( ! is_array( $promotion ) || 'verifying' !== ( $promotion['status'] ?? null ) ) {
	throw new RuntimeException( 'File promotion swap failed: ' . wp_json_encode( $promotion ) );
}

$promotion_verified = null;
for ( $iteration = 0; $iteration < 160; ++$iteration ) {
	$promotion_verified = $promoter->advance_verification( $job_id, 1, 1048576 );
	if ( ! is_array( $promotion_verified ) ) {
		throw new RuntimeException( 'File promotion verification returned no state.' );
	}
	if ( in_array( $promotion_verified['status'] ?? null, array( 'verified', 'rolled-back', 'blocked' ), true ) ) {
		break;
	}
}
if ( ! is_array( $promotion_verified ) || 'verified' !== ( $promotion_verified['status'] ?? null ) ) {
	throw new RuntimeException( 'File promotion did not verify final target: ' . wp_json_encode( $promotion_verified ) );
}

$promotion_database_state = Plugin::clone_import_database_activation_state_store()?->get( $job_id );
$promotion_upload_active  = trailingslashit( $upload_dir['basedir'] ) . '2026/file.txt';
$promotion_bridge_active  = trailingslashit( WP_PLUGIN_DIR ) . $source_bridge_plugin;
$promotion_theme_active   = trailingslashit( get_theme_root() ) . $source_theme . '/style.css';
$promotion_runtime = array(
	'active_plugins' => get_option( 'active_plugins', array() ),
	'template'       => get_option( 'template', '' ),
	'stylesheet'     => get_option( 'stylesheet', '' ),
);
$promotion_upload_sentinel_absent = ! file_exists( $promotion_upload_sentinel );
$promotion_payload_files_present  = is_file( $promotion_upload_active )
	&& is_file( $promotion_bridge_active )
	&& is_file( $promotion_theme_active );

$promotion_rollback = $promoter->rollback( $job_id );
if ( ! is_array( $promotion_rollback ) || 'rolled-back' !== ( $promotion_rollback['status'] ?? null ) ) {
	throw new RuntimeException( 'File promotion rollback failed: ' . wp_json_encode( $promotion_rollback ) );
}
$promotion_runtime_after_rollback = array(
	'active_plugins' => get_option( 'active_plugins', array() ),
	'template'       => get_option( 'template', '' ),
	'stylesheet'     => get_option( 'stylesheet', '' ),
);
$promotion_upload_sentinel_after_hash = is_file( $promotion_upload_sentinel )
	? hash_file( 'sha256', $promotion_upload_sentinel )
	: false;
$promotion_bridge_restored = is_file( trailingslashit( WP_PLUGIN_DIR ) . $bridge_plugin );
$promotion_theme_restored  = is_dir( trailingslashit( get_theme_root() ) . $destination_stylesheet );

$activation_rollback = $activator->rollback( $job_id );
if ( ! is_array( $activation_rollback ) || 'rolled-back' !== ( $activation_rollback['status'] ?? null ) ) {
	throw new RuntimeException( 'Database activation rollback failed: ' . wp_json_encode( $activation_rollback ) );
}
$active_after_activation_rollback = get_option( $active_sentinel_name );

// The fixture source options table did not originally contain these activation
// overlay rows. Remove them after rollback so the later negative rewrite case
// still exercises the original source fixture and not the control-plane overlay.
foreach (
	array(
		CloneJobStore::OPTION_NAME,
		ImportStateStore::OPTION_NAME,
		ImportPayloadStateStore::OPTION_NAME,
		ImportDatabaseStateStore::OPTION_NAME,
		ImportFileStateStore::OPTION_NAME,
		ImportRewriteStateStore::OPTION_NAME,
		ImportFinalizeStateStore::OPTION_NAME,
		'blog_public',
		'active_plugins',
		'template',
		'stylesheet',
	) as $overlay_option
) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only cleanup of staging overlay rows.
	$wpdb->delete( $options_staging, array( 'option_name' => $overlay_option ), array( '%s' ) );
}

// Negative safety case: rerun from source core URLs but inject opaque serialized bytes containing the source environment.
$rewrite_store = new ImportRewriteStateStore();
$rewrite_store->delete( $job_id );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->update( $options_staging, array( 'option_value' => $source_home ), array( 'option_name' => 'home' ), array( '%s' ), array( '%s' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->update( $options_staging, array( 'option_value' => $source_site ), array( 'option_name' => 'siteurl' ), array( '%s' ), array( '%s' ) );
$opaque_source = 'a:1:{s:3:"url";s:99:"' . $source_home . 'opaque-source-url"';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->update( $options_staging, array( 'option_value' => $opaque_source ), array( 'option_name' => 'opaque_safe' ), array( '%s' ), array( '%s' ) );

$bad = null;
for ( $iteration = 0; $iteration < 60; ++$iteration ) {
	$bad = $rewriter->advance( $job_id, 2 );
	if ( ! is_array( $bad ) ) {
		throw new RuntimeException( 'Opaque-source rewrite safety case returned no state.' );
	}
	if ( in_array( $bad['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$staged_file_after_bad = $workspace->import_staged_file_info( $job_id, 'uploads', '2026/file.txt' );
$active_after_bad      = get_option( $active_sentinel_name );

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportRewriteStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'good'                     => $good,
		'finalize'                 => $finalize,
		'bad'                      => $bad,
		'options'                  => $options,
		'posts'                    => $posts_rows,
		'serialized_after'         => $serialized_after,
		'json_after'               => $json_after,
		'destination_home'         => $destination_home,
		'destination_site'         => $destination_site,
		'source_home'              => $source_home,
		'source_site'              => $source_site,
		'active_before'            => $active_before,
		'active_after'             => $active_after,
		'active_after_finalize'    => $active_after_finalize,
		'active_after_activation_rollback' => $active_after_activation_rollback,
		'activation_prepared'       => $activation_prepared,
		'activation'                => $activation,
		'activation_rollback'       => $activation_rollback,
		'activation_blog_public'    => $activation_blog_public,
		'destination_template'      => $destination_template,
		'destination_stylesheet'    => $destination_stylesheet,
		'activation_template'       => $activation_template,
		'activation_stylesheet'     => $activation_stylesheet,
		'activation_control_plane_present' => is_string( $activation_control_plane ) && '' !== $activation_control_plane,
		'activation_bridge_active'  => $activation_bridge_active,
		'promotion_prepared'       => $promotion_prepared,
		'promotion_candidates'     => $promotion_candidates,
		'promotion'                => $promotion,
		'promotion_verified'       => $promotion_verified,
		'promotion_database_state' => $promotion_database_state,
		'promotion_runtime'        => $promotion_runtime,
		'promotion_runtime_after_rollback' => $promotion_runtime_after_rollback,
		'promotion_upload_sentinel_absent' => $promotion_upload_sentinel_absent,
		'promotion_upload_sentinel_hash' => $promotion_upload_sentinel_hash,
		'promotion_upload_sentinel_after_hash' => $promotion_upload_sentinel_after_hash,
		'promotion_payload_files_present' => $promotion_payload_files_present,
		'promotion_bridge_restored' => $promotion_bridge_restored,
		'promotion_theme_restored'  => $promotion_theme_restored,
		'promotion_rollback'        => $promotion_rollback,
		'source_theme'              => $source_theme,
		'source_bridge_plugin'      => $source_bridge_plugin,
		'active_after_bad'         => $active_after_bad,
		'staged_file_before'       => $staged_file_before,
		'staged_file_after'        => $staged_file_after,
		'staged_file_after_finalize' => $staged_file_after_finalize,
		'staged_file_after_bad'    => $staged_file_after_bad,
		'rewrite_state_autoload'   => $autoload,
		'controller_registered'          => false !== has_action( 'admin_post_' . AdminCloneImportRewriteController::ACTION ),
		'public_controller_absent'       => false === has_action( 'admin_post_nopriv_' . AdminCloneImportRewriteController::ACTION ),
		'finalize_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneImportFinalizeController::ACTION ),
		'finalize_public_absent'         => false === has_action( 'admin_post_nopriv_' . AdminCloneImportFinalizeController::ACTION ),
		'database_activation_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneImportDatabaseActivationController::ACTION ),
		'database_activation_public_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportDatabaseActivationController::ACTION ),
		'file_promotion_controller_registered' => false !== has_action( 'admin_post_' . AdminCloneImportFilePromotionController::ACTION ),
		'file_promotion_public_absent' => false === has_action( 'admin_post_nopriv_' . AdminCloneImportFilePromotionController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

// Test-only cleanup of deterministic staging tables/workspaces.
foreach ( $plan['tables'] as $table ) {
	if ( ! is_array( $table ) || ! is_string( $table['staging_table'] ?? null ) ) {
		continue;
	}
	$staging = $quote( $table['staging_table'] );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test-only cleanup of deterministic job-owned staging tables.
	$wpdb->query( "DROP TABLE {$staging}" );
}

delete_option( $active_sentinel_name );
if ( is_file( $promotion_upload_sentinel ) ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-only active uploads sentinel cleanup.
	@unlink( $promotion_upload_sentinel );
}
if ( is_array( $promotion_rollback['roots'] ?? null ) ) {
	foreach ( $promotion_rollback['roots'] as $promotion_root ) {
		if ( ! is_array( $promotion_root ) || ! is_string( $promotion_root['candidate_path'] ?? null ) ) {
			continue;
		}
		$candidate = untrailingslashit( $promotion_root['candidate_path'] );
		if ( ! is_dir( $candidate ) || is_link( $candidate ) ) {
			continue;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $candidate, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test-only deterministic candidate cleanup.
				@rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-only deterministic candidate cleanup.
				@unlink( $item->getPathname() );
			}
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test-only deterministic candidate cleanup.
		@rmdir( $candidate );
	}
}
foreach ( array( $job_id, $source_job ) as $cleanup_job ) {
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
		ImportRewriteStateStore::OPTION_NAME,
	) as $option_name
) {
	delete_option( $option_name );
}
PHP

docker cp "$IMPORT_REWRITE_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-rewrite-runner.php \
  || fail_smoke "clone-import-rewrite-runner-copy" "Could not copy Portable Import environment rewrite runner" "runner copied" "docker cp failed"

if ! IMPORT_REWRITE_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-rewrite-runner.php 2>"$TMP_DIR/portable-clone-import-rewrite.stderr")"; then
  IMPORT_REWRITE_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-rewrite.stderr" | head -c 3600)"
  fail_smoke "clone-import-rewrite-runner" "Portable Import environment rewrite runner failed" "JSON rewrite report" "${IMPORT_REWRITE_ERROR:-wp eval-file failed}"
fi

printf '%s' "$IMPORT_REWRITE_JSON" >"$TMP_DIR/portable-clone-import-rewrite.json"

if ! IMPORT_REWRITE_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-rewrite.json" <<'PY'
import json
import re
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good"]
assert good["schema_version"] == 1
assert good["status"] == "complete"
assert good["stage"] == "complete"
assert good["rows_scanned"] >= 8
assert good["rows_changed"] >= 6
assert good["home_rewrites"] == 1
assert good["siteurl_rewrites"] == 1
assert good["same_origin_rewrites"] >= 3
assert good["upload_url_rewrites"] >= 1
assert good["serialized_values"] >= 1
assert good["json_values"] >= 1
assert good["credential_skips"] >= 1
assert good["opaque_serialized_skips"] >= 1
assert good["verify_source_urls"] == 0
assert good["active_tables_untouched"] is True
assert good["active_roots_untouched"] is True
assert good["blockers"] == []
assert "credential-values-kept-opaque" in good["advisories"]
assert "opaque-serialized-values-reviewed" in good["advisories"]

finalize = payload["finalize"]
assert finalize["schema_version"] == 1
assert finalize["status"] == "ready"
assert finalize["stage"] == "ready"
assert finalize["database_rows_hashed"] >= 8
assert re.fullmatch(r"[a-f0-9]{64}", finalize["database_fingerprint"])
assert finalize["files_hashed"] == finalize["expected_file_count"] == 3
assert finalize["file_bytes_hashed"] == finalize["expected_file_bytes"]
assert re.fullmatch(r"[a-f0-9]{64}", finalize["file_fingerprint"])
assert re.fullmatch(r"[a-f0-9]{64}", finalize["activation_plan_hash"])
assert finalize["sandbox_hardening_ready"] is True
assert finalize["rollback_plan_ready"] is True
assert finalize["activation_allowed"] is True
assert finalize["handoff_ready"] is False
assert finalize["active_tables_untouched"] is True
assert finalize["active_roots_untouched"] is True
assert finalize["blockers"] == []

prepared = payload["activation_prepared"]
assert prepared["schema_version"] == 1
assert prepared["status"] == "prepared"
assert prepared["database_swapped"] is False
assert prepared["rollback_available"] is True
assert prepared["active_files_untouched"] is True
assert prepared["handoff_ready"] is False
assert len(prepared["tables"]) == 2
assert prepared["control_options"] == []
assert re.fullmatch(r"[a-f0-9]{64}", prepared["activation_plan_hash"])

activation = payload["activation"]
assert activation["status"] == "activated"
assert activation["database_swapped"] is True
assert activation["rollback_available"] is True
assert activation["active_files_untouched"] is True
assert activation["handoff_ready"] is False
assert activation["blockers"] == []
assert len(activation["control_options"]) >= 11
assert payload["activation_blog_public"] == "0"
assert payload["activation_template"] == payload["destination_template"]
assert payload["activation_stylesheet"] == payload["destination_stylesheet"]
assert payload["activation_control_plane_present"] is True
assert payload["activation_bridge_active"] is True

promotion_prepared = payload["promotion_prepared"]
assert promotion_prepared["schema_version"] == 1
assert promotion_prepared["status"] == "prepared"
assert promotion_prepared["database_activated"] is True
assert promotion_prepared["rollback_available"] is True
assert promotion_prepared["handoff_ready"] is False
assert len(promotion_prepared["roots"]) == 3
assert re.fullmatch(r"[a-f0-9]{64}", promotion_prepared["activation_plan_hash"])
assert re.fullmatch(r"[a-f0-9]{64}", promotion_prepared["file_fingerprint"])

promotion_candidates = payload["promotion_candidates"]
assert promotion_candidates["status"] == "candidate-ready"
assert promotion_candidates["file_count"] == 3
assert promotion_candidates["verify_file_count"] == 0
assert promotion_candidates["handoff_ready"] is False
assert all(root["candidate_ready"] is True for root in promotion_candidates["roots"])

promotion = payload["promotion"]
assert promotion["status"] == "verifying"
assert promotion["handoff_ready"] is False
assert promotion["rollback_available"] is True

verified = payload["promotion_verified"]
assert verified["status"] == "verified"
assert verified["file_count"] == verified["verify_file_count"] == 3
assert verified["byte_count"] == verified["verify_byte_count"]
assert verified["handoff_ready"] is True
assert verified["rollback_available"] is True
assert verified["blockers"] == []
assert re.fullmatch(r"[a-f0-9]{64}", verified["active_fingerprint"])
assert verified["active_fingerprint"] == verified["file_fingerprint"]

promotion_db = payload["promotion_database_state"]
assert promotion_db["status"] == "activated"
assert promotion_db["database_swapped"] is True
assert promotion_db["handoff_ready"] is True
assert promotion_db["active_files_untouched"] is False

promotion_runtime = payload["promotion_runtime"]
assert payload["source_bridge_plugin"] in promotion_runtime["active_plugins"]
assert promotion_runtime["template"] == payload["source_theme"]
assert promotion_runtime["stylesheet"] == payload["source_theme"]
assert payload["promotion_upload_sentinel_absent"] is True
assert payload["promotion_payload_files_present"] is True

promotion_rollback = payload["promotion_rollback"]
assert promotion_rollback["status"] == "rolled-back"
assert promotion_rollback["handoff_ready"] is False
assert promotion_rollback["rollback_available"] is False
assert "operator-file-promotion-rollback" in promotion_rollback["blockers"]
assert payload["promotion_upload_sentinel_hash"] == payload["promotion_upload_sentinel_after_hash"]
assert payload["promotion_bridge_restored"] is True
assert payload["promotion_theme_restored"] is True
runtime_after_rollback = payload["promotion_runtime_after_rollback"]
assert runtime_after_rollback["template"] == payload["destination_template"]
assert runtime_after_rollback["stylesheet"] == payload["destination_stylesheet"]

rollback = payload["activation_rollback"]
assert rollback["status"] == "rolled-back"
assert rollback["database_swapped"] is False
assert rollback["active_files_untouched"] is True
assert rollback["handoff_ready"] is False
assert "operator-database-rollback" in rollback["blockers"]

options = payload["options"]
dest_home = payload["destination_home"].rstrip("/") + "/"
dest_site = payload["destination_site"].rstrip("/") + "/"
source_home = payload["source_home"]

assert options["home"].rstrip("/") == payload["destination_home"].rstrip("/")
assert options["siteurl"].rstrip("/") == payload["destination_site"].rstrip("/")
assert options["plain_url"].startswith(dest_home + "catalog/item")
assert source_home not in options["plain_url"]
assert options["api_token"] == "token-value::" + source_home + "credential-context"
assert options["opaque_safe"] == 'a:1:{s:3:"bad";s:4:"nope"'

serialized = payload["serialized_after"]
assert isinstance(serialized, dict)
assert serialized["url"].startswith(dest_home + "serialized")
assert serialized["asset"].startswith(dest_home + "wp-content/uploads/2026/file.txt")
assert source_home not in serialized["url"]
nested_json = json.loads(serialized["nested_json"])
assert nested_json["deep"].startswith(dest_site + "deep")

json_value = payload["json_after"]
assert isinstance(json_value, dict)
assert json_value["url"].startswith(dest_home + "json")
assert json_value["nested"]["site"].startswith(dest_site + "admin")

posts = payload["posts"]
assert len(posts) == 1
assert dest_home + "about" in posts[0]["post_content"]
assert "https://external.example.test/reference" in posts[0]["post_content"]
assert dest_home + "wp-content/uploads/2026/file.txt" in posts[0]["post_excerpt"]

assert payload["active_before"] == payload["active_after"] == payload["active_after_finalize"] == payload["active_after_activation_rollback"] == payload["active_after_bad"]
for key in ("staged_file_before", "staged_file_after", "staged_file_after_finalize", "staged_file_after_bad"):
    assert isinstance(payload[key], dict)
    assert re.fullmatch(r"[a-f0-9]{64}", payload[key]["sha256"])
assert payload["staged_file_before"] == payload["staged_file_after"] == payload["staged_file_after_finalize"] == payload["staged_file_after_bad"]

bad = payload["bad"]
assert bad["status"] == "blocked"
assert "import-rewrite-value-transform-failed" in bad["blockers"]
assert bad["active_tables_untouched"] is True
assert bad["active_roots_untouched"] is True

assert payload["rewrite_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True
assert payload["finalize_controller_registered"] is True
assert payload["finalize_public_absent"] is True
assert payload["database_activation_controller_registered"] is True
assert payload["database_activation_public_absent"] is True
assert payload["file_promotion_controller_registered"] is True
assert payload["file_promotion_public_absent"] is True
print("ok")
PY
)"; then
  fail_smoke "clone-import-rewrite-contract" "Portable Import environment rewrite contract is invalid" "structured serialized/JSON rewrite + credential opacity + idempotence + active/staged immutability + opaque-source blocker" "${IMPORT_REWRITE_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import environment rewrite + finalization + DB activation OK: staging rewrite stayed structured/idempotent; fingerprints reached ready; DB swap preserved noindex/control plane/Bridge and rolled back atomically; opaque source URL was blocked.\n'
