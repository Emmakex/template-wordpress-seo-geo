#!/usr/bin/env bash
# Phase 10E.2A.4.5 serialization-safe staging environment rewrite acceptance.

printf '[smoke] Checking Portable Import serialization-safe environment rewrite.\n'

ENV_RUNNER="$TMP_DIR/portable-clone-import-environment-runner.php"
cat >"$ENV_RUNNER" <<'PHP'
<?php

use SeoGeo\MigrationBridge\Clone\AdminCloneImportEnvironmentController;
use SeoGeo\MigrationBridge\Clone\CloneJobStore;
use SeoGeo\MigrationBridge\Clone\ExportWorkspace;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseRestorer;
use SeoGeo\MigrationBridge\Clone\ImportDatabaseStateStore;
use SeoGeo\MigrationBridge\Clone\ImportEnvironmentRewriter;
use SeoGeo\MigrationBridge\Clone\ImportEnvironmentStateStore;
use SeoGeo\MigrationBridge\Clone\ImportFileRestorer;
use SeoGeo\MigrationBridge\Clone\ImportFileStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadStateStore;
use SeoGeo\MigrationBridge\Clone\ImportPayloadVerifier;
use SeoGeo\MigrationBridge\Clone\ImportPreflight;
use SeoGeo\MigrationBridge\Clone\ImportStateStore;
use SeoGeo\MigrationBridge\Clone\SerializationSafeRewriter;
use SeoGeo\MigrationBridge\Plugin;

foreach (
	array(
		CloneJobStore::OPTION_NAME,
		ImportStateStore::OPTION_NAME,
		ImportPayloadStateStore::OPTION_NAME,
		ImportDatabaseStateStore::OPTION_NAME,
		ImportFileStateStore::OPTION_NAME,
		ImportEnvironmentStateStore::OPTION_NAME,
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
$environment  = Plugin::clone_import_environment_rewriter();
$workspace    = new ExportWorkspace();

if (
	! $jobs instanceof CloneJobStore
	|| ! $preflight instanceof ImportPreflight
	|| ! $verifier instanceof ImportPayloadVerifier
	|| ! $db_restore instanceof ImportDatabaseRestorer
	|| ! $file_restore instanceof ImportFileRestorer
	|| ! $environment instanceof ImportEnvironmentRewriter
) {
	throw new RuntimeException( 'Portable Import environment rewrite services are unavailable.' );
}

global $wpdb;
if ( ! $wpdb instanceof wpdb ) {
	throw new RuntimeException( 'WordPress database runtime is unavailable.' );
}

$source_prefix = 'src_';
$source_url    = 'https://source.example.test/';
$destination   = home_url( '/' );

update_option( 'seo_geo_environment_active_sentinel', $source_url . 'active' );
$active_before = get_option( 'seo_geo_environment_active_sentinel' );

/**
 * Encode one export row.
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
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe migration fixture.
			return base64_encode( $value );
		},
		$values
	);
};

/**
 * Replay exact package checksum contract.
 *
 * @return array{checksum:string,files:int,bytes:int}
 */
$workspace_checksum = static function ( string $root ): array {
	$checksum = hash( 'sha256', 'seo-geo-portable-clone-package-v1' );
	$pending  = array( '' );
	$files    = 0;
	$bytes    = 0;

	while ( array() !== $pending ) {
		$current  = (string) array_shift( $pending );
		$absolute = rtrim( wp_normalize_path( $root ), '/' ) . ( '' === $current ? '' : '/' . $current );
		$entries  = scandir( $absolute, SCANDIR_SORT_ASCENDING );
		if ( false === $entries ) {
			throw new RuntimeException( 'Could not scan environment fixture workspace.' );
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$relative = '' === $current ? $entry : $current . '/' . $entry;
			if ( 'package' === $relative || str_starts_with( $relative, 'package/' ) ) {
				continue;
			}
			$path = rtrim( wp_normalize_path( $root ), '/' ) . '/' . $relative;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$pending[] = $relative;
				continue;
			}
			$size = filesize( $path );
			$hash = hash_file( 'sha256', $path );
			if ( false === $size || false === $hash ) {
				throw new RuntimeException( 'Could not hash environment fixture payload.' );
			}
			$record   = 'payload|' . wp_normalize_path( $relative ) . '|' . (string) (int) $size . '|' . $hash;
			$checksum = hash( 'sha256', $checksum . "\n" . $record );
			++$files;
			$bytes += (int) $size;
		}
	}

	return array( 'checksum' => $checksum, 'files' => $files, 'bytes' => $bytes );
};

/**
 * Build one valid package containing WordPress-like option/post/usermeta rows.
 *
 * @return array{path:string,sha256:string,bytes:int}
 */
$build_archive = static function ( string $source_job_id ) use (
	$workspace,
	$workspace_checksum,
	$encode_row,
	$source_prefix,
	$source_url
): array {
	$workspace->cleanup( $source_job_id );
	$workspace->delete_delivery_archive( $source_job_id );

	$roles = serialize(
		array(
			'administrator' => array(
				'name' => 'Administrator',
				'help' => $source_url . 'role-help',
			),
		)
	);
	$object = serialize( (object) array( 'url' => $source_url . 'object' ) );

	$specs = array(
		array(
			'name'    => 'src_options',
			'schema'  => 'CREATE TABLE `src_options` (`option_id` bigint unsigned NOT NULL, `option_name` varchar(191) NOT NULL, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT \'yes\', PRIMARY KEY (`option_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
			'columns' => array( 'option_id', 'option_name', 'option_value', 'autoload' ),
			'rows'    => array(
				array( '1', 'home', $source_url, 'yes' ),
				array( '2', 'siteurl', $source_url, 'yes' ),
				array( '3', 'src_user_roles', $roles, 'yes' ),
				array( '4', 'plain_url', $source_url . 'plain', 'no' ),
				array( '5', 'serialized_object', $object, 'no' ),
			),
		),
		array(
			'name'    => 'src_posts',
			'schema'  => 'CREATE TABLE `src_posts` (`ID` bigint unsigned NOT NULL, `post_content` longtext NOT NULL, `guid` varchar(255) NOT NULL, PRIMARY KEY (`ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
			'columns' => array( 'ID', 'post_content', 'guid' ),
			'rows'    => array(
				array( '1', '<a href="' . $source_url . 'article">Article</a>', $source_url . '?p=1' ),
			),
		),
		array(
			'name'    => 'src_usermeta',
			'schema'  => 'CREATE TABLE `src_usermeta` (`umeta_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL, `meta_key` varchar(255) NOT NULL, `meta_value` longtext NOT NULL, PRIMARY KEY (`umeta_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
			'columns' => array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ),
			'rows'    => array(
				array( '1', '1', 'src_capabilities', serialize( array( 'administrator' => true ) ) ),
				array( '2', '1', 'src_user_level', '10' ),
				array( '3', '1', 'profile_url', $source_url . 'profile' ),
			),
		),
	);

	$table_meta   = array();
	$total_rows   = 0;
	$total_chunks = 0;
	$total_bytes  = 0;

	foreach ( $specs as $table_index => $spec ) {
		$name      = $spec['name'];
		$table_dir = 'database/tables/' . substr( hash( 'sha256', $name ), 0, 20 );
		$schema    = $workspace->write( $source_job_id, $table_dir . '/schema.sql', $spec['schema'] . "\n" );
		if ( ! is_array( $schema ) ) {
			throw new RuntimeException( 'Could not write environment fixture schema.' );
		}

		$encoded_rows = array_map( $encode_row, $spec['rows'] );
		$chunk        = array(
			'schema_version'   => 1,
			'table'            => $name,
			'strategy'         => 'primary-key',
			'cursor_column'    => $spec['columns'][0],
			'cursor_start_b64' => '',
			'offset_start'     => 0,
			'chunk_index'      => 0,
			'row_count'        => count( $encoded_rows ),
			'columns'          => $spec['columns'],
			'value_encoding'   => 'base64-or-null',
			'rows'             => $encoded_rows,
		);
		$chunk_json = wp_json_encode( $chunk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$chunk_path = $table_dir . '/chunks/000000.json';
		$written    = is_string( $chunk_json ) ? $workspace->write( $source_job_id, $chunk_path, $chunk_json ) : null;
		if ( ! is_array( $written ) ) {
			throw new RuntimeException( 'Could not write environment fixture chunk.' );
		}

		$rows = count( $encoded_rows );
		$table_meta[] = array(
			'schema_version' => 1,
			'name'           => $name,
			'slug'           => substr( hash( 'sha256', $name ), 0, 20 ),
			'schema'         => array(
				'path'   => $table_dir . '/schema.sql',
				'bytes'  => (int) $schema['bytes'],
				'sha256' => (string) $schema['sha256'],
			),
			'strategy'       => 'primary-key',
			'cursor_column'  => $spec['columns'][0],
			'order_columns'  => array(),
			'columns'        => $spec['columns'],
			'chunks'         => array(
				array(
					'index'      => 0,
					'path'       => $chunk_path,
					'row_count'  => $rows,
					'byte_count' => (int) $written['bytes'],
					'sha256'     => (string) $written['sha256'],
				),
			),
			'chunk_count'    => 1,
			'row_count'      => $rows,
			'byte_count'     => (int) $written['bytes'],
			'complete'       => true,
		);
		$total_rows   += $rows;
		++$total_chunks;
		$total_bytes += (int) $written['bytes'];
	}

	$database_manifest = array(
		'schema_version'              => 1,
		'package_id'                  => $source_job_id,
		'payload_class'               => 'database',
		'source'                      => array(
			'home_url'          => $source_url,
			'site_url'          => $source_url,
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'table_prefix'      => $source_prefix,
		),
		'tables'                      => $table_meta,
		'table_count'                 => count( $table_meta ),
		'row_count'                   => $total_rows,
		'payload_bytes'               => $total_bytes,
		'chunk_count'                 => $total_chunks,
		'production_source_read_only' => true,
		'credentials_in_payload'      => false,
		'contains_private_site_data'  => true,
		'repository_safe'             => false,
		'generated_at'                => gmdate( DATE_ATOM ),
	);
	$db_json = wp_json_encode( $database_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	$db      = $workspace->write( $source_job_id, 'database/manifest.json', $db_json );
	if ( ! is_array( $db ) ) {
		throw new RuntimeException( 'Could not write environment database manifest.' );
	}

	$files_manifest = array(
		'schema_version'              => 1,
		'payload_class'               => 'files',
		'file_count'                  => 0,
		'payload_bytes'               => 0,
		'roots'                       => array(),
		'source_fingerprint'          => hash( 'sha256', 'environment-fixture-' . $source_job_id ),
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
		throw new RuntimeException( 'Could not write environment files manifest.' );
	}

	$root = $workspace->root_path( $source_job_id );
	if ( null === $root ) {
		throw new RuntimeException( 'Environment fixture workspace unavailable.' );
	}
	$integrity = $workspace_checksum( $root );

	$package = array(
		'schema_version' => 1,
		'mode'           => 'portable-clone-package',
		'package_id'     => $source_job_id,
		'operation'      => 'export',
		'source'         => array(
			'home_url'           => $source_url,
			'site_url'           => $source_url,
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'source_fingerprint' => hash( 'sha256', 'environment-fixture-' . $source_job_id ),
		),
		'payload'        => array(
			'database' => array(
				'manifest_path'   => 'database/manifest.json',
				'manifest_sha256' => (string) $db['sha256'],
				'row_count'       => $total_rows,
				'chunk_count'     => $total_chunks,
				'payload_bytes'   => $total_bytes,
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
	$package_json = wp_json_encode( $package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
	if ( ! is_array( $workspace->write( $source_job_id, 'package/manifest.json', $package_json ) ) ) {
		throw new RuntimeException( 'Could not write environment package manifest.' );
	}

	$archive_files = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $iterator as $item ) {
		if ( $item->isFile() && ! $item->isLink() ) {
			$absolute = wp_normalize_path( $item->getPathname() );
			$relative = ltrim( substr( $absolute, strlen( trailingslashit( wp_normalize_path( $root ) ) ) ), '/' );
			if ( '' !== $relative ) {
				$archive_files[] = $relative;
			}
		}
	}
	sort( $archive_files, SORT_STRING );

	if ( ! $workspace->reset_delivery_archive( $source_job_id ) ) {
		throw new RuntimeException( 'Could not reset environment fixture archive.' );
	}
	foreach ( array_chunk( $archive_files, 5 ) as $batch ) {
		if ( ! $workspace->append_delivery_archive_files( $source_job_id, $batch ) ) {
			throw new RuntimeException( 'Could not append environment fixture archive.' );
		}
	}

	$archive = $workspace->finalize_delivery_archive( $source_job_id );
	if ( ! is_array( $archive ) ) {
		throw new RuntimeException( 'Could not finalize environment fixture archive.' );
	}

	return $archive;
};

/**
 * Prepare one import job through DB + file staging.
 */
$prepare = static function ( string $job_id, array $archive ) use (
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
		throw new RuntimeException( 'Could not stage environment import fixture.' );
	}

	$pre = $preflight->validate( $job_id );
	if ( ! is_array( $pre ) || 'preflight-ready' !== ( $pre['status'] ?? null ) ) {
		throw new RuntimeException( 'Environment fixture did not pass preflight.' );
	}

	for ( $i = 0; $i < 180; ++$i ) {
		$payload = $verifier->advance( $job_id, 2, 1048576 );
		if ( ! is_array( $payload ) ) {
			throw new RuntimeException( 'Environment payload verifier returned no state.' );
		}
		if ( 'complete' === ( $payload['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $payload['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment payload verifier was blocked.' );
		}
	}

	for ( $i = 0; $i < 120; ++$i ) {
		$db = $db_restore->advance( $job_id, 2 );
		if ( ! is_array( $db ) ) {
			throw new RuntimeException( 'Environment DB staging returned no state.' );
		}
		if ( 'complete' === ( $db['status'] ?? null ) ) {
			break;
		}
		if ( 'blocked' === ( $db['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment DB staging was blocked: ' . wp_json_encode( $db ) );
		}
	}

	for ( $i = 0; $i < 10; ++$i ) {
		$files = $file_restore->advance( $job_id, 1, 1048576 );
		if ( ! is_array( $files ) ) {
			throw new RuntimeException( 'Environment file staging returned no state.' );
		}
		if ( 'complete' === ( $files['status'] ?? null ) ) {
			return;
		}
		if ( 'blocked' === ( $files['status'] ?? null ) ) {
			throw new RuntimeException( 'Environment file staging was blocked.' );
		}
	}

	throw new RuntimeException( 'Environment fixture did not finish file staging.' );
};

$archive = $build_archive( 'clone-import-env-source-0001' );

$good_job = 'clone-import-env-good-0001';
$prepare( $good_job, $archive );
$good = null;
for ( $i = 0; $i < 160; ++$i ) {
	$good = $environment->advance( $good_job, 2 );
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

$namespace = (string) $good['staging_namespace'];
$table_name = static fn( string $source ): string => $namespace . substr( hash( 'sha256', $source ), 0, 16 );
$quote = static fn( string $name ): string => chr( 96 ) . str_replace( chr( 96 ), chr( 96 ) . chr( 96 ), $name ) . chr( 96 );

$options_table  = $quote( $table_name( 'src_options' ) );
$posts_table    = $quote( $table_name( 'src_posts' ) );
$usermeta_table = $quote( $table_name( 'src_usermeta' ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$options_rows = $wpdb->get_results( "SELECT option_id, option_name, option_value FROM {$options_table} ORDER BY option_id ASC", ARRAY_A );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$posts_rows = $wpdb->get_results( "SELECT ID, post_content, guid FROM {$posts_table} ORDER BY ID ASC", ARRAY_A );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$usermeta_rows = $wpdb->get_results( "SELECT umeta_id, meta_key, meta_value FROM {$usermeta_table} ORDER BY umeta_id ASC", ARRAY_A );

$roles_value  = is_array( $options_rows ) && isset( $options_rows[2]['option_value'] ) ? (string) $options_rows[2]['option_value'] : '';
$object_value = is_array( $options_rows ) && isset( $options_rows[4]['option_value'] ) ? (string) $options_rows[4]['option_value'] : '';

$bad_job = 'clone-import-env-opaque-0001';
$prepare( $bad_job, $archive );
$bad_db        = ( new ImportDatabaseStateStore() )->get( $bad_job );
$bad_namespace = is_array( $bad_db ) ? (string) ( $bad_db['staging_namespace'] ?? '' ) : '';
$bad_options   = $bad_namespace . substr( hash( 'sha256', 'src_options' ), 0, 16 );
$opaque_data   = $source_url . 'opaque';
$opaque        = 'C:4:"Demo":' . strlen( $opaque_data ) . ':{' . $opaque_data . '}';
$wpdb->update( $bad_options, array( 'option_value' => $opaque ), array( 'option_id' => 4 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

$bad = null;
for ( $i = 0; $i < 20; ++$i ) {
	$bad = $environment->advance( $bad_job, 2 );
	if ( ! is_array( $bad ) ) {
		throw new RuntimeException( 'Opaque serialization fixture returned no state.' );
	}
	if ( in_array( $bad['status'] ?? null, array( 'complete', 'blocked' ), true ) ) {
		break;
	}
}

$direct = new SerializationSafeRewriter();
$direct_serialized = serialize( array( 'url' => $source_url . 'direct' ) );
$direct_result = $direct->rewrite( $direct_serialized, array( $source_url => $destination ) );

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		ImportEnvironmentStateStore::OPTION_NAME
	)
);

echo wp_json_encode(
	array(
		'good'                      => $good,
		'bad'                       => $bad,
		'options_rows'              => $options_rows,
		'posts_rows'                => $posts_rows,
		'usermeta_rows'             => $usermeta_rows,
		'roles_serialized_valid'    => is_serialized( $roles_value, true ),
		'object_serialized_valid'   => is_serialized( $object_value, true ),
		'roles_contains_source'     => str_contains( $roles_value, $source_url ),
		'object_contains_source'    => str_contains( $object_value, $source_url ),
		'active_before'             => $active_before,
		'active_after'              => get_option( 'seo_geo_environment_active_sentinel' ),
		'destination'               => $destination,
		'source_url'                => $source_url,
		'direct_serialized_valid'   => is_serialized( $direct_result['value'], true ),
		'direct_contains_source'    => str_contains( $direct_result['value'], $source_url ),
		'direct_changed'            => $direct_result['changed'],
		'environment_state_autoload'=> $autoload,
		'controller_registered'     => false !== has_action( 'admin_post_' . AdminCloneImportEnvironmentController::ACTION ),
		'public_controller_absent'  => false === has_action( 'admin_post_nopriv_' . AdminCloneImportEnvironmentController::ACTION ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

delete_option( 'seo_geo_environment_active_sentinel' );

foreach ( array( $good_job, $bad_job ) as $import_job ) {
	$db_state = ( new ImportDatabaseStateStore() )->get( $import_job );
	$ns = is_array( $db_state ) ? (string) ( $db_state['staging_namespace'] ?? '' ) : '';
	if ( '' !== $ns ) {
		$pattern = $wpdb->esc_like( $ns ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( is_array( $tables ) ? $tables : array() as $table ) {
			if ( ! is_string( $table ) || ! str_starts_with( $table, $ns ) ) {
				continue;
			}
			$quoted = $quote( $table );
			$wpdb->query( "DROP TABLE {$quoted}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}
}

foreach ( array( $good_job, $bad_job, 'clone-import-env-source-0001' ) as $cleanup_job ) {
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
		ImportEnvironmentStateStore::OPTION_NAME,
	) as $option_name
) {
	delete_option( $option_name );
}
PHP

docker cp "$ENV_RUNNER" "$WP_CONTAINER":/var/www/html/wp-content/portable-clone-import-environment-runner.php   || fail_smoke "clone-import-environment-runner-copy" "Could not copy Portable Import environment runner" "runner copied" "docker cp failed"

if ! ENV_JSON="$(wp_cli eval-file /var/www/html/wp-content/portable-clone-import-environment-runner.php 2>"$TMP_DIR/portable-clone-import-environment.stderr")"; then
  ENV_ERROR="$(tr -d '\r' <"$TMP_DIR/portable-clone-import-environment.stderr" | head -c 3600)"
  fail_smoke "clone-import-environment-runner" "Portable Import environment rewrite runner failed" "JSON environment report" "${ENV_ERROR:-wp eval-file failed}"
fi

printf '%s' "$ENV_JSON" >"$TMP_DIR/portable-clone-import-environment.json"

if ! ENV_ASSERTION="$(python3 - "$TMP_DIR/portable-clone-import-environment.json" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

good = payload["good"]
assert good["status"] == "complete"
assert good["stage"] == "complete"
assert good["table_count"] == 3
assert good["rows_scanned"] == 9
assert good["rows_changed"] >= 6
assert good["values_changed"] >= 8
assert good["serialized_values_changed"] >= 2
assert good["prefix_keys_changed"] >= 3
assert good["verify_rows_scanned"] == 9
assert good["remaining_source_values"] == 0
assert good["active_tables_untouched"] is True
assert good["blockers"] == []

destination = payload["destination"].rstrip("/")
source = payload["source_url"]

options = payload["options_rows"]
by_name = {row["option_name"]: row["option_value"] for row in options}
assert by_name["home"].rstrip("/") == destination
assert by_name["siteurl"].rstrip("/") == destination
assert "wp_user_roles" in by_name
assert by_name["plain_url"].startswith(destination)
assert payload["roles_serialized_valid"] is True
assert payload["object_serialized_valid"] is True
assert payload["roles_contains_source"] is False
assert payload["object_contains_source"] is False

posts = payload["posts_rows"]
assert destination in posts[0]["post_content"]
assert posts[0]["guid"].startswith(source)

usermeta = {row["meta_key"]: row["meta_value"] for row in payload["usermeta_rows"]}
assert "wp_capabilities" in usermeta
assert "wp_user_level" in usermeta
assert usermeta["profile_url"].startswith(destination)

assert payload["active_before"] == source + "active"
assert payload["active_after"] == source + "active"
assert payload["direct_serialized_valid"] is True
assert payload["direct_contains_source"] is False
assert payload["direct_changed"] is True
assert payload["environment_state_autoload"] in ("off", "no", "auto-off")
assert payload["controller_registered"] is True
assert payload["public_controller_absent"] is True

bad = payload["bad"]
assert bad["status"] == "blocked"
assert "import-environment-serialized-value-unsupported" in bad["blockers"]
assert bad["active_tables_untouched"] is True

print("ok")
PY
)"; then
  fail_smoke "clone-import-environment-contract" "Portable Import environment rewrite contract is invalid" "staging-only serialization-safe rewrite + second verification + opaque serialization rejection" "${ENV_ASSERTION:-python assertion failed}"
fi

printf '[smoke] Portable Import environment rewrite OK: raw/serialized URLs and prefix-sensitive keys rewrote only in staging, GUID and active option stayed unchanged, second verification passed, opaque custom serialization was blocked.\n'
