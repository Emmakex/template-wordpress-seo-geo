<?php
/**
 * Validate the Phase 8A/8B/8C/8D/8E/8F/8G/8H/8I Migration Bridge safety contract.
 */

declare(strict_types=1);

const MIGRATION_BRIDGE_DIR = 'packages/seo-geo-migration-bridge';

/**
 * Emit one structured actionable failure and stop.
 *
 * @param mixed $expected Expected value.
 * @param mixed $received Actual value.
 */
function fail_migration_bridge( string $code, string $message, string $file_line, mixed $expected, mixed $received ): never {
	$signature = substr( hash( 'sha256', $code . ':' . $file_line . ':' . $message ), 0, 12 );

	echo json_encode(
		array(
			'schema_version'    => 1,
			'pipeline'          => getenv( 'GITHUB_WORKFLOW' ) ?: 'foundation',
			'run_id'            => getenv( 'GITHUB_RUN_ID' ) ?: 'local',
			'run_attempt'       => getenv( 'GITHUB_RUN_ATTEMPT' ) ?: '1',
			'job'               => getenv( 'GITHUB_JOB' ) ?: 'foundation',
			'step'              => 'migration-bridge-contract',
			'command'           => 'php scripts/ci/validate-migration-bridge.php',
			'exit_code'         => 1,
			'primary_error'     => $message,
			'file_line'         => $file_line,
			'expected'          => is_scalar( $expected ) || null === $expected ? $expected : json_encode( $expected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'received'          => is_scalar( $received ) || null === $received ? $received : json_encode( $received, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'error_signature'   => $signature,
			'root_cause_status' => 'unknown',
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . PHP_EOL;

	exit( 1 );
}

$required = array(
	MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php',
	MIGRATION_BRIDGE_DIR . '/README.md',
	MIGRATION_BRIDGE_DIR . '/src/Plugin.php',
	MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php',
	MIGRATION_BRIDGE_DIR . '/src/PublicUrlInventory.php',
	MIGRATION_BRIDGE_DIR . '/src/HtmlSnapshotExtractor.php',
	MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotter.php',
	MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotStore.php',
	MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineStore.php',
	MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineCapture.php',
	MIGRATION_BRIDGE_DIR . '/src/Http/HttpClientInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Http/WordPressHttpClient.php',
	MIGRATION_BRIDGE_DIR . '/src/DependencyGraphBuilder.php',
	MIGRATION_BRIDGE_DIR . '/src/ProviderAuthorityResolver.php',
	MIGRATION_BRIDGE_DIR . '/src/Content/ContentDependencyDetectorInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Content/ContentDependencyScanner.php',
	MIGRATION_BRIDGE_DIR . '/src/Content/NativeBlocksContentDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Content/ElementorContentDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Content/DiviContentDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxGuard.php',
	MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxMigrationLab.php',
	MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxHandoffManifest.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/BuilderMigrationAdapterInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/ElementorMigrationAdapter.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/DiviMigrationAdapter.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationPresetResolver.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationEngine.php',
	MIGRATION_BRIDGE_DIR . '/src/Migration/AdminMigrationController.php',
	MIGRATION_BRIDGE_DIR . '/src/Parity/ParityAllowlist.php',
	MIGRATION_BRIDGE_DIR . '/src/Parity/SeoParityEngine.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/PublicSnapshotProviderInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/BaselinePublicSnapshotProvider.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/BackupEvidenceValidator.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/QualityEvidenceValidator.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverSnapshotStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverEngine.php',
	MIGRATION_BRIDGE_DIR . '/src/Cutover/AdminCutoverController.php',
	MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportEngine.php',
	MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorCopy.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorStatus.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/AdminBaselineCaptureController.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/AdminSandboxHandoffController.php',
	MIGRATION_BRIDGE_DIR . '/src/Review/DependencyReviewStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Review/AdminDependencyReviewController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/CloneJobStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/CloneManifest.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventoryStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventory.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/DestinationSafetyPlanner.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ExportStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/DatabaseExporter.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/FileExportStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/FileExporter.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/PackageStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/PackageBuilder.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/DeliveryStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/PackageDelivery.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadVerifier.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportPayloadController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseRestorer.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileRestorer.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFileController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportRewriteStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportRewriteController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizeStateStore.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFinalizeController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneInventoryController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDatabaseExportController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneFileExportController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminClonePackageController.php',
	MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDeliveryController.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/BuilderDetectorInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/NativeBlocksDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/ElementorDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/DiviDetector.php',
);

foreach ( $required as $path ) {
	if ( ! is_file( $path ) ) {
		fail_migration_bridge( 'missing-file', 'Migration Bridge required file is missing.', $path, 'file exists', 'missing' );
	}
}

$bootstrap = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php' );
foreach (
	array(
		'Plugin Name: SEO/GEO Migration Bridge',
		'Requires at least: 7.1',
		'Requires PHP: 8.2',
		'Text Domain: seo-geo-migration-bridge',
	) as $header
) {
	if ( ! str_contains( $bootstrap, $header ) ) {
		fail_migration_bridge( 'plugin-header', 'Migration Bridge plugin header is incomplete.', MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php', $header, 'missing' );
	}
}

if (
	1 !== preg_match( '/^ \* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $bootstrap, $version_match )
	|| 1 !== preg_match( "/define\(\s*'SEO_GEO_MIGRATION_BRIDGE_VERSION'\s*,\s*'([^']+)'\s*\)/", $bootstrap, $constant_match )
	|| $version_match[1] !== $constant_match[1]
) {
	fail_migration_bridge(
		'plugin-version',
		'Migration Bridge plugin header/runtime version metadata is missing or inconsistent.',
		MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php',
		'matching semantic versions in header and runtime constant',
		'version mismatch'
	);
}

$php_files = array_merge(
	array( MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php' ),
	glob( MIGRATION_BRIDGE_DIR . '/src/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Builders/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Http/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Content/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Sandbox/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Migration/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Parity/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Cutover/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Report/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Operator/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Review/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Clone/*.php' ) ?: array()
);

$destructive_calls = array(
	'delete_option',
	'set_theme_mod',
	'remove_theme_mod',
	'wp_insert_post',
	'wp_update_post',
	'wp_delete_post',
	'add_post_meta',
	'update_post_meta',
	'delete_post_meta',
	'wp_set_post_terms',
	'wp_insert_term',
	'wp_update_term',
	'wp_delete_term',
	'activate_plugin',
	'deactivate_plugins',
	'switch_theme',
	'delete_plugins',
	'delete_theme',
	'wp_schedule_event',
	'wp_schedule_single_event',
);

$authorized_mutation_calls = array(
	MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationEngine.php' => array(
		'wp_update_post',
		'add_post_meta',
		'update_post_meta',
		'delete_post_meta',
	),
	MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverEngine.php' => array(
		'activate_plugin',
		'deactivate_plugins',
		'switch_theme',
	),
);

foreach ( $php_files as $path ) {
	$source = (string) file_get_contents( $path );

	foreach ( $destructive_calls as $function_name ) {
		if ( 1 !== preg_match( '/\\b' . preg_quote( $function_name, '/' ) . '\\s*\\(/', $source ) ) {
			continue;
		}

		$allowed_calls = $authorized_mutation_calls[ $path ] ?? array();
		if ( ! in_array( $function_name, $allowed_calls, true ) ) {
			fail_migration_bridge(
				'destructive-api',
				'Mutation API exists outside an explicitly authorized migration/cutover engine boundary.',
				$path,
				'only approved mutation APIs in MigrationEngine.php or CutoverEngine.php',
				$function_name
			);
		}
	}
}

$read_only_files = array_merge(
	array(
		MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php',
		MIGRATION_BRIDGE_DIR . '/src/DependencyGraphBuilder.php',
		MIGRATION_BRIDGE_DIR . '/src/ProviderAuthorityResolver.php',
		MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportEngine.php',
		MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventory.php',
		MIGRATION_BRIDGE_DIR . '/src/Clone/DestinationSafetyPlanner.php',
	),
	glob( MIGRATION_BRIDGE_DIR . '/src/Builders/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Content/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Operator/*.php' ) ?: array()
);

foreach ( $read_only_files as $path ) {
	$source = (string) file_get_contents( $path );

	foreach ( array( 'add_option', 'update_option', 'delete_option' ) as $function_name ) {
		if ( 1 === preg_match( '/\\b' . preg_quote( $function_name, '/' ) . '\\s*\\(/', $source ) ) {
			fail_migration_bridge( 'analyzer-option-write', 'Phase 8A analyzer contains a forbidden option mutation call.', $path, 'strictly read-only analyzer', $function_name );
		}
	}

	if ( str_contains( $source, '->query(' ) || str_contains( $source, '->insert(' ) || str_contains( $source, '->update(' ) || str_contains( $source, '->delete(' ) ) {
		fail_migration_bridge( 'analyzer-database-mutation', 'Phase 8A analyzer contains a direct database mutation/query primitive.', $path, 'no direct database writes/queries', 'database method found' );
	}
}

$clone_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/CloneJobStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_clone_jobs_v1';",
		'public const SCHEMA_VERSION = 1;',
		"return array( 'local-clone', 'export', 'import' );",
		"add_option( self::OPTION_NAME, \$jobs, '', false )",
		'update_option( self::OPTION_NAME, $jobs, false )',
		"'failed-retryable'",
		"'failed-terminal'",
		"'rewrite-environment'",
		"'harden-sandbox'",
	) as $clone_store_guard
) {
	if ( ! str_contains( $clone_store, $clone_store_guard ) ) {
		fail_migration_bridge(
			'portable-clone-job-store',
			'Portable Clone job persistence must remain versioned, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/CloneJobStore.php',
			$clone_store_guard,
			'missing'
		);
	}
}

$clone_manifest = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/CloneManifest.php' );
foreach (
	array(
		"'mode'           => 'portable-clone-planning'",
		"'algorithm'        => 'sha256'",
		"'production_source_mutation_allowed'",
		"'production_database_restore_allowed'",
		"'third_party_clone_plugin_required'",
		"'payload_created_in_this_phase'",
		"'credentials_in_manifest'",
		"'private_payload_repository_safe'",
	) as $clone_manifest_guard
) {
	if ( ! str_contains( $clone_manifest, $clone_manifest_guard ) ) {
		fail_migration_bridge(
			'portable-clone-manifest',
			'Portable Clone planning manifest is missing a required privacy/safety boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/CloneManifest.php',
			$clone_manifest_guard,
			'missing'
		);
	}
}

$clone_paths = glob( MIGRATION_BRIDGE_DIR . '/src/Clone/*.php' ) ?: array();
foreach ( $clone_paths as $clone_path ) {
	if ( MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php' === $clone_path ) {
		continue;
	}
	$clone_source = (string) file_get_contents( $clone_path );
	foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(' ) as $filesystem_mutation ) {
		if ( str_contains( $clone_source, $filesystem_mutation ) ) {
			fail_migration_bridge(
				'portable-clone-filesystem-boundary',
				'Portable Clone filesystem writes must remain isolated behind ExportWorkspace.',
				$clone_path,
				'filesystem mutations only in ExportWorkspace.php',
				$filesystem_mutation
			);
		}
	}
}

$export_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ExportStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_export_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'database_manifest_hash'",
	) as $export_state_guard
) {
	if ( ! str_contains( $export_state_store, $export_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-export-state',
			'Portable Clone export progress must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ExportStateStore.php',
			$export_state_guard,
			'missing'
		);
	}
}

$export_workspace = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php' );
foreach (
	array(
		"'seo-geo-migration-bridge'",
		'get_temp_dir()',
		'file_put_contents(',
		'copy_file( string $job_id, string $relative, string $source )',
		"fopen( \$source, 'rb' )",
		"hash_init( 'sha256' )",
		'rename(',
		"hash_file( 'sha256'",
		'cleanup( string $job_id )',
		"public const DELIVERY_DIRECTORY_NAME = 'seo-geo-migration-bridge-delivery';",
		'reset_delivery_archive( string $job_id )',
		'append_delivery_archive_files( string $job_id, array $relative_paths )',
		'finalize_delivery_archive( string $job_id )',
		'delivery_archive_info( string $job_id )',
		'delete_delivery_archive( string $job_id )',
		'delivery_download_name( string $job_id )',
		'stage_import_archive( string $job_id, string $source )',
		'import_archive_info( string $job_id )',
		'delete_import_archive( string $job_id )',
		'prepare_file_promotion_candidate( string $candidate )',
		'copy_file_to_promotion_candidate( string $source, string $target )',
		'rename_file_promotion_path( string $from, string $to )',
		"'wp-admin/includes/class-pclzip.php'",
		'PCLZIP_OPT_REMOVE_PATH',
	) as $export_workspace_guard
) {
	if ( ! str_contains( $export_workspace, $export_workspace_guard ) ) {
		fail_migration_bridge(
			'portable-clone-export-workspace',
			'Portable Clone private export workspace is missing an atomic/private/cleanup guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php',
			$export_workspace_guard,
			'missing'
		);
	}
}

$database_exporter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/DatabaseExporter.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_ROWS = 100;',
		"'local-clone', 'export'",
		'SHOW CREATE TABLE',
		'SHOW COLUMNS FROM',
		'SHOW KEYS FROM',
		'SELECT * FROM',
		"'primary-key'",
		"'offset-fallback'",
		"'base64-or-null'",
		"'production_source_read_only' => true",
		"'credentials_in_payload'      => false",
		"'repository_safe'             => false",
		"'database/manifest.json'",
	) as $database_export_guard
) {
	if ( ! str_contains( $database_exporter, $database_export_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-export',
			'Portable Clone database export is missing a required resumability/privacy/integrity boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/DatabaseExporter.php',
			$database_export_guard,
			'missing'
		);
	}
}

foreach ( array( 'INSERT INTO ', 'UPDATE ', 'DELETE FROM ', 'REPLACE INTO ', 'DROP TABLE ', 'ALTER TABLE ', 'TRUNCATE TABLE ' ) as $database_mutation_sql ) {
	if ( str_contains( strtoupper( $database_exporter ), $database_mutation_sql ) ) {
		fail_migration_bridge(
			'portable-clone-source-database-read-only',
			'Portable Clone exporter must never mutate the production source database.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/DatabaseExporter.php',
			'SHOW/SELECT source access only',
			$database_mutation_sql
		);
	}
}

$database_export_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDatabaseExportController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_database_export';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->exporter->advance( $job_id, $batch_rows )',
	) as $database_export_controller_guard
) {
	if ( ! str_contains( $database_export_controller, $database_export_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-export-entrypoint',
			'Portable Clone database export endpoint must remain capability/nonce gated and advance one bounded batch.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDatabaseExportController.php',
			$database_export_controller_guard,
			'missing'
		);
	}
}


$file_export_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/FileExportStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_file_export_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'export_fingerprint'",
		"'files_manifest_hash'",
	) as $file_export_state_guard
) {
	if ( ! str_contains( $file_export_state_store, $file_export_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-export-state',
			'Portable Clone file export progress must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/FileExportStateStore.php',
			$file_export_state_guard,
			'missing'
		);
	}
}

$file_exporter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/FileExporter.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_FILES = 25;',
		'public const DEFAULT_BATCH_BYTES = 8388608;',
		"'local-clone', 'export'",
		'$this->workspace->copy_file(',
		"'source-files-changed-since-inventory'",
		"'files/manifest.json'",
		"'production_source_read_only' => true",
		"'credentials_in_payload'      => false",
		"'repository_safe'             => false",
		"'uploads', 'plugins', 'themes'",
	) as $file_export_guard
) {
	if ( ! str_contains( $file_exporter, $file_export_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-export',
			'Portable Clone file export is missing a required resumability/privacy/integrity boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/FileExporter.php',
			$file_export_guard,
			'missing'
		);
	}
}

foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(' ) as $file_export_mutation ) {
	if ( str_contains( $file_exporter, $file_export_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-file-export-workspace-boundary',
			'FileExporter must delegate every payload filesystem mutation to ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/FileExporter.php',
			'no direct filesystem mutation primitives',
			$file_export_mutation
		);
	}
}

$file_export_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneFileExportController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_file_export';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->exporter->advance( $job_id, $batch_files, $batch_bytes )',
	) as $file_export_controller_guard
) {
	if ( ! str_contains( $file_export_controller, $file_export_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-export-entrypoint',
			'Portable Clone file export endpoint must remain capability/nonce gated and advance one bounded batch.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneFileExportController.php',
			$file_export_controller_guard,
			'missing'
		);
	}
}


$package_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/PackageStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_package_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'package_checksum'",
		"'verification_checksum'",
		"'package_manifest_hash'",
	) as $package_state_guard
) {
	if ( ! str_contains( $package_state_store, $package_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-package-state',
			'Portable Clone package integrity progress must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/PackageStateStore.php',
			$package_state_guard,
			'missing'
		);
	}
}

$package_builder = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/PackageBuilder.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_FILES = 100;',
		'public const DEFAULT_BATCH_BYTES = 16777216;',
		'private function start_verification_pass(',
		"\$state['stage']                 = 'verify';",
		"\$state['stage']                 = 'complete';",
		"'package-exported-payload-mismatch'",
		"'package-integrity-verification-failed'",
		"'workspace-excluding-package-metadata'",
		"'verification_pass'  => true",
		"'verified'           => true",
		"'delivery_ready'              => false",
		"'package/manifest.json'",
	) as $package_builder_guard
) {
	if ( ! str_contains( $package_builder, $package_builder_guard ) ) {
		fail_migration_bridge(
			'portable-clone-package-integrity',
			'Portable Clone package builder is missing a required resumability/tamper/integrity boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/PackageBuilder.php',
			$package_builder_guard,
			'missing'
		);
	}
}

foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(' ) as $package_mutation ) {
	if ( str_contains( $package_builder, $package_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-package-workspace-boundary',
			'PackageBuilder must delegate every payload filesystem mutation to ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/PackageBuilder.php',
			'read/hash payload directly; writes only through ExportWorkspace',
			$package_mutation
		);
	}
}

$package_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminClonePackageController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_package_integrity';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->builder->advance( $job_id, $batch_files, $batch_bytes )',
	) as $package_controller_guard
) {
	if ( ! str_contains( $package_controller, $package_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-package-entrypoint',
			'Portable Clone package endpoint must remain capability/nonce gated and advance one bounded batch.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminClonePackageController.php',
			$package_controller_guard,
			'missing'
		);
	}
}


$delivery_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/DeliveryStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_delivery_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'ready', 'blocked', 'expired', 'cleaning', 'cleaned'",
		"'archive_sha256'",
		"'expires_at'",
		"'cleanup_deleted_count'",
	) as $delivery_state_guard
) {
	if ( ! str_contains( $delivery_state_store, $delivery_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-delivery-state',
			'Portable Clone delivery state must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/DeliveryStateStore.php',
			$delivery_state_guard,
			'missing'
		);
	}
}

$package_delivery = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/PackageDelivery.php' );
if (
	1 !== preg_match( '/public const\\s+RETENTION_HOURS\\s*=\\s*24;/', $package_delivery )
	|| 1 !== preg_match( '/public const\\s+DEFAULT_CLEANUP_BATCH\\s*=\\s*250;/', $package_delivery )
	|| 1 !== preg_match( "/'authenticated_only'\\s*=>\\s*true/", $package_delivery )
	|| 1 !== preg_match( "/'public_url'\\s*=>\\s*false/", $package_delivery )
) {
	fail_migration_bridge(
		'portable-clone-delivery-policy',
		'Portable Clone delivery must keep its fixed auth-only and 24-hour bounded retention policy.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/PackageDelivery.php',
		'auth-only ZIP delivery with 24-hour retention and bounded cleanup',
		'policy mismatch'
	);
}
foreach (
	array(
		"'delivery-package-integrity-mismatch'",
		'$this->workspace->cleanup_batch( $job_id, $limit )',
		'$this->workspace->append_delivery_archive_files( $job_id, $relative_paths )',
		'$this->workspace->finalize_delivery_archive( $job_id )',
		'$this->workspace->delete_delivery_archive( $job_id )',
		'$this->jobs->transition( $job_id, \'completed\' )',
	) as $package_delivery_guard
) {
	if ( ! str_contains( $package_delivery, $package_delivery_guard ) ) {
		fail_migration_bridge(
			'portable-clone-authenticated-delivery',
			'Portable Clone package delivery is missing a required integrity/retention boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/PackageDelivery.php',
			$package_delivery_guard,
			'missing'
		);
	}
}

$delivery_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDeliveryController.php' );
foreach (
	array(
		"public const BUILD_ACTION    = 'seo_geo_migration_clone_delivery_build';",
		"public const DOWNLOAD_ACTION = 'seo_geo_migration_clone_delivery_download';",
		"public const CLEANUP_ACTION  = 'seo_geo_migration_clone_delivery_cleanup';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':build:' . \$job_id )",
		"check_admin_referer( self::NONCE_ACTION . ':download:' . \$job_id )",
		"check_admin_referer( self::NONCE_ACTION . ':cleanup:' . \$job_id )",
		"header( 'Content-Type: application/zip' )",
		"'cleanup' !== \$confirmation",
	) as $delivery_controller_guard
) {
	if ( ! str_contains( $delivery_controller, $delivery_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-delivery-entrypoint',
			'Portable Clone delivery endpoints must remain administrator/nonce gated.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDeliveryController.php',
			$delivery_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $delivery_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-public-download',
		'Portable Clone packages must never expose an unauthenticated download endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneDeliveryController.php',
		'authenticated admin_post actions only',
		'admin_post_nopriv_'
	);
}

$import_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'preflight-ready'",
		"'full_payload_verified'",
		"'restore_allowed'",
	) as $import_state_guard
) {
	if ( ! str_contains( $import_state_store, $import_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-state',
			'Portable Import preflight state must remain bounded, versioned and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportStateStore.php',
			$import_state_guard,
			'missing'
		);
	}
}

$import_preflight = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php' );
foreach (
	array(
		"public const TARGET_AUTHORIZED_MARKER = 'SEO_GEO_MIGRATION_IMPORT_TARGET_AUTHORIZED';",
		"'lexicographic-bfs-path+bytes+sha256-v1'",
		"'import-archive-path-unsafe'",
		"'import-database-manifest-hash-mismatch'",
		"'import-files-manifest-hash-mismatch'",
		'SandboxGuard::enabled()',
		'SandboxGuard::storage_isolated()',
		'SandboxGuard::outbound_safe()',
		'SandboxGuard::backups_ready()',
		"get_option( 'blog_public', '1' )",
		'disk_free_space(',
		"'full_payload_verified'",
		"'restore_allowed'",
		"'restore-runtime-guard-required'",
		'$this->workspace->stage_import_archive( $job_id, $source )',
	) as $import_preflight_guard
) {
	if ( ! str_contains( $import_preflight, $import_preflight_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-preflight',
			'Portable Import preflight is missing a required archive/integrity/destination safety boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php',
			$import_preflight_guard,
			'missing'
		);
	}
}

if (
	1 !== preg_match( "/\\['full_payload_verified'\\]\\s*=\\s*false;/", $import_preflight )
	|| 1 !== preg_match( "/\\['restore_allowed'\\]\\s*=\\s*false;/", $import_preflight )
) {
	fail_migration_bridge(
		'portable-clone-import-restore-gate',
		'Import preflight alone must keep full payload verification and restore authorization disabled.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php',
		'full_payload_verified=false and restore_allowed=false',
		'restore gate mismatch'
	);
}

foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(', 'move_uploaded_file(' ) as $import_mutation ) {
	if ( str_contains( $import_preflight, $import_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-filesystem-boundary',
			'ImportPreflight must delegate every filesystem mutation to ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php',
			'read/inspect only; staged ZIP writes through ExportWorkspace',
			$import_mutation
		);
	}
}

foreach ( array( 'INSERT INTO ', 'UPDATE ', 'DELETE FROM ', 'REPLACE INTO ', 'DROP TABLE ', 'ALTER TABLE ', 'TRUNCATE TABLE ', 'CREATE TABLE ' ) as $import_sql_mutation ) {
	if ( str_contains( strtoupper( $import_preflight ), $import_sql_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-no-restore-yet',
			'10E.2A.4.1 import preflight must not restore or mutate destination database state.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPreflight.php',
			'no destination restore SQL in preflight',
			$import_sql_mutation
		);
	}
}

$import_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_preflight';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		"is_uploaded_file( \$tmp_name )",
		'$this->preflight->stage( $job_id, $source )',
		'$this->preflight->validate( $job_id )',
	) as $import_controller_guard
) {
	if ( ! str_contains( $import_controller, $import_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-entrypoint',
			'Portable Import preflight endpoint must remain administrator/job-nonce gated with validated upload intake.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportController.php',
			$import_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-public-endpoint',
		'Portable Import must never expose an unauthenticated upload/preflight endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$import_payload_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_payload_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'archive_cursor'",
		"'expected_checksum'",
		"'verification_checksum'",
		"'extract_file_count'",
		"'verify_file_count'",
	) as $import_payload_state_guard
) {
	if ( ! str_contains( $import_payload_state_store, $import_payload_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-payload-state',
			'Portable Import payload verification state must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadStateStore.php',
			$import_payload_state_guard,
			'missing'
		);
	}
}

$import_payload_verifier = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadVerifier.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_FILES = 50;',
		'public const DEFAULT_BATCH_BYTES = 8388608;',
		"'seo-geo-portable-clone-package-v1'",
		'$this->workspace->prepare_import_extraction( $job_id )',
		'$this->workspace->extract_import_archive_entry( $job_id, $name )',
		"'import-payload-archive-identity-changed'",
		"'import-payload-checksum-mismatch'",
		'$this->preflight->validate( $job_id )',
		"'payload-verified'",
		"'restore_allowed'",
		"'verify'",
	) as $import_payload_guard
) {
	if ( ! str_contains( $import_payload_verifier, $import_payload_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-payload-verification',
			'Portable Import payload verification is missing a required private extraction/checksum/fresh-preflight boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadVerifier.php',
			$import_payload_guard,
			'missing'
		);
	}
}

foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(', 'move_uploaded_file(' ) as $import_payload_mutation ) {
	if ( str_contains( $import_payload_verifier, $import_payload_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-payload-workspace-boundary',
			'ImportPayloadVerifier must delegate every extraction filesystem mutation to ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadVerifier.php',
			'no direct filesystem mutation primitives',
			$import_payload_mutation
		);
	}
}

foreach ( array( 'INSERT INTO ', 'UPDATE ', 'DELETE FROM ', 'REPLACE INTO ', 'DROP TABLE ', 'ALTER TABLE ', 'TRUNCATE TABLE ', 'CREATE TABLE ' ) as $import_payload_sql_mutation ) {
	if ( str_contains( strtoupper( $import_payload_verifier ), $import_payload_sql_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-payload-no-restore-yet',
			'10E.2A.4.2 may verify private payload but must not restore destination database state.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportPayloadVerifier.php',
			'no destination restore SQL',
			$import_payload_sql_mutation
		);
	}
}

$import_payload_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportPayloadController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_payload';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->verifier->advance( $job_id, $batch_files, $batch_bytes )',
	) as $import_payload_controller_guard
) {
	if ( ! str_contains( $import_payload_controller, $import_payload_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-payload-entrypoint',
			'Portable Import payload endpoint must remain administrator/job-nonce gated and bounded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportPayloadController.php',
			$import_payload_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_payload_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-payload-public-endpoint',
		'Portable Import payload verification must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportPayloadController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}

$export_workspace = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php' );
foreach (
	array(
		'prepare_import_extraction( string $job_id )',
		'import_extraction_root( string $job_id )',
		'import_archive_entries( string $job_id )',
		'extract_import_archive_entry( string $job_id, string $name )',
		'import_extracted_file_info( string $job_id, string $relative )',
		'read_import_extracted_file( string $job_id, string $relative )',
		'prepare_import_file_staging( string $job_id )',
		'import_file_staging_root( string $job_id )',
		'stage_import_payload_file( string $job_id, string $root_id, string $relative )',
		'import_staged_file_info( string $job_id, string $root_id, string $relative )',
	) as $import_workspace_guard
) {
	if ( ! str_contains( $export_workspace, $import_workspace_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-private-extraction-workspace',
			'ExportWorkspace must own all private Portable Import extraction operations.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ExportWorkspace.php',
			$import_workspace_guard,
			'missing'
		);
	}
}

$import_database_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_database_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'chunk_row_offset'",
		"'active_tables_untouched'",
		"'table-verify'",
	) as $import_database_state_guard
) {
	if ( ! str_contains( $import_database_state_store, $import_database_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-database-state',
			'Portable Import database restore state must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseStateStore.php',
			$import_database_state_guard,
			'missing'
		);
	}
}

$import_database_restorer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseRestorer.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_ROWS = 100;',
		'$this->preflight->validate( $job_id )',
		"'payload-verified'",
		"'restore_allowed'",
		'$this->workspace->read_import_extracted_file( $job_id, $path )',
		"'START TRANSACTION'",
		"'COMMIT'",
		"'ROLLBACK'",
		'$wpdb->insert( $staging_table, $row, $formats )',
		"'restore-database'",
		"'active_tables_untouched'",
		"'import-database-staging-engine-not-transactional'",
		"'import-database-runtime-guard-failed'",
		"'import-database-manifest-changed'",
		'FOREIGN\\s+KEY',
		'REFERENCES',
		'$this->options_table_transactional()',
	) as $import_database_restore_guard
) {
	if ( ! str_contains( $import_database_restorer, $import_database_restore_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-database-restore',
			'10E.2A.4.3 staging database restore is missing a required transaction/integrity/runtime safety boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseRestorer.php',
			$import_database_restore_guard,
			'missing'
		);
	}
}

foreach ( array( 'DROP TABLE', 'TRUNCATE TABLE', 'RENAME TABLE', 'ALTER TABLE' ) as $import_database_destructive_sql ) {
	if ( str_contains( strtoupper( $import_database_restorer ), $import_database_destructive_sql ) ) {
		fail_migration_bridge(
			'portable-clone-import-database-no-active-cutover',
			'10E.2A.4.3 must not drop, truncate, rename or alter active destination tables.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseRestorer.php',
			'job-owned staging CREATE/INSERT only',
			$import_database_destructive_sql
		);
	}
}

if (
	! str_contains( $import_database_restorer, "'sgm_' . substr( hash( 'sha256', \$job_id ), 0, 10 ) . '_'" )
	|| ! str_contains( $import_database_restorer, "return 'CREATE TABLE ' . \$this->quote_identifier( \$staging_table )" )
) {
	fail_migration_bridge(
		'portable-clone-import-database-staging-boundary',
		'Database restore must create only deterministic job-owned staging tables.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseRestorer.php',
		'deterministic sgm namespace + staging CREATE TABLE only',
		'staging boundary mismatch'
	);
}

$import_database_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_database_restore';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->restorer->advance( $job_id, $batch_rows )',
	) as $import_database_controller_guard
) {
	if ( ! str_contains( $import_database_controller, $import_database_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-database-entrypoint',
			'Portable Import database staging restore endpoint must remain administrator/job-nonce gated and bounded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseController.php',
			$import_database_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_database_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-database-public-endpoint',
		'Portable Import database restore must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$import_file_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_file_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'copy', 'verify', 'complete'",
		"'active_roots_untouched'",
		"'verify_file_count'",
		"'verify_byte_count'",
	) as $import_file_state_guard
) {
	if ( ! str_contains( $import_file_state_store, $import_file_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-file-state',
			'Portable Import file staging state must remain bounded, resumable, two-pass and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileStateStore.php',
			$import_file_state_guard,
			'missing'
		);
	}
}

$import_file_restorer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileRestorer.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_FILES = 10;',
		'$this->database_state->get( $job_id )',
		"'complete' !== ( \$db['status'] ?? null )",
		'$this->preflight->validate( $job_id )',
		"'payload-verified'",
		"'restore_allowed'",
		"'files-meta/' . \$root_id . '/' . hash( 'sha256', \$relative ) . '.json'",
		'$this->workspace->stage_import_payload_file( $job_id, $root_id, $relative )',
		'$this->workspace->import_staged_file_info( $job_id, $root_id, $relative )',
		"'copy' === ( \$state['stage'] ?? null )",
		"'verify'",
		"'restore-files'",
		"'active_roots_untouched'",
		"'import-files-record-missing'",
		"'import-files-verification-failed'",
		"'import-files-runtime-guard-failed'",
	) as $import_file_restore_guard
) {
	if ( ! str_contains( $import_file_restorer, $import_file_restore_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-file-restore',
			'10E.2A.4.4 staging file restore is missing a required sequence/integrity/runtime safety boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileRestorer.php',
			$import_file_restore_guard,
			'missing'
		);
	}
}

foreach ( array( 'copy(', 'file_put_contents(', 'fwrite(', 'unlink(', 'rename(', 'mkdir(', 'rmdir(' ) as $import_file_mutation ) {
	if ( str_contains( $import_file_restorer, $import_file_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-file-workspace-boundary',
			'ImportFileRestorer must delegate all filesystem mutation to ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileRestorer.php',
			'filesystem mutation only through ExportWorkspace staging methods',
			$import_file_mutation
		);
	}
}

if (
	str_contains( $import_file_restorer, 'wp_upload_dir(' )
	|| str_contains( $import_file_restorer, 'WP_PLUGIN_DIR' )
	|| str_contains( $import_file_restorer, 'get_theme_root(' )
) {
	fail_migration_bridge(
		'portable-clone-import-file-active-root-boundary',
		'10E.2A.4.4 must not resolve or write active uploads/plugins/themes roots.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFileRestorer.php',
		'job-owned import/staged-files tree only',
		'active wp-content root primitive found'
	);
}

$import_file_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFileController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_file_restore';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->restorer->advance( $job_id, $batch_files, $batch_megabytes * 1024 * 1024 )',
	) as $import_file_controller_guard
) {
	if ( ! str_contains( $import_file_controller, $import_file_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-file-entrypoint',
			'Portable Import staging-file restore endpoint must remain administrator/job-nonce gated and bounded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFileController.php',
			$import_file_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_file_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-file-public-endpoint',
		'Portable Import file restore must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFileController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}



$import_rewrite_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportRewriteStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_rewrite_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'rewrite', 'verify', 'complete'",
		"'active_tables_untouched'",
		"'active_roots_untouched'",
		"'opaque_serialized_skips'",
	) as $import_rewrite_state_guard
) {
	if ( ! str_contains( $import_rewrite_state_store, $import_rewrite_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-rewrite-state',
			'Portable Import environment rewrite state must remain bounded, resumable, two-pass and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportRewriteStateStore.php',
			$import_rewrite_state_guard,
			'missing'
		);
	}
}

$import_environment_rewriter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_ROWS = 100;',
		'$this->database_restorer->staging_plan( $job_id )',
		"'complete' !== ( \$db['status'] ?? null )",
		"'complete' !== ( \$files['status'] ?? null )",
		"'active_tables_untouched'",
		"'active_roots_untouched'",
		"'options'",
		"'posts'",
		"is_serialized( \$value, false )",
		"'allowed_classes' => false",
		'serialize( $nested[\'value\'] )',
		'json_decode( $trimmed, true )',
		'wp_json_encode( $nested[\'value\']',
		'$this->opaque_contains_source_environment( $value, $state )',
		"'import-rewrite-source-environment-remains'",
		"'credential-values-kept-opaque'",
		"'opaque-serialized-values-reviewed'",
		"'START TRANSACTION'",
		"'COMMIT'",
		"'ROLLBACK'",
		"'rewrite-environment'",
	) as $import_rewrite_guard
) {
	if ( ! str_contains( $import_environment_rewriter, $import_rewrite_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-environment-rewrite',
			'10E.2A.4.5 environment rewrite is missing a required staging/serialization/idempotence safety boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php',
			$import_rewrite_guard,
			'missing'
		);
	}
}

if (
	! str_contains( $import_environment_rewriter, '$wpdb->update(' )
	|| ! str_contains( $import_environment_rewriter, "(string) \$spec['staging_table']" )
	|| str_contains( $import_environment_rewriter, "(string) \$spec['target_table']," )
) {
	fail_migration_bridge(
		'portable-clone-import-rewrite-table-boundary',
		'Environment rewrite mutations must target only the validated job-owned staging table.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php',
		'wpdb update against spec staging_table only',
		'staging update boundary mismatch'
	);
}

foreach ( array( 'wp_upload_dir(', 'WP_PLUGIN_DIR', 'get_theme_root(', 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(' ) as $rewrite_forbidden ) {
	if ( str_contains( $import_environment_rewriter, $rewrite_forbidden ) ) {
		fail_migration_bridge(
			'portable-clone-import-rewrite-active-files-boundary',
			'10E.2A.4.5 must not resolve or mutate active/staged file roots.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php',
			'staging database values only',
			$rewrite_forbidden
		);
	}
}

if (
	1 === preg_match( '/str_replace\s*\([^;]+(?:serialize|serialized)/is', $import_environment_rewriter )
	|| str_contains( $import_environment_rewriter, 'SerializedValueRewriter' )
) {
	fail_migration_bridge(
		'portable-clone-import-rewrite-raw-serialized-replacement',
		'Environment rewrite must decode/re-encode supported serialized values and must not use a second raw serialized replacement path.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportEnvironmentRewriter.php',
		'one structured serialization-safe rewrite path',
		'raw or duplicate serialized replacement path found'
	);
}

$import_rewrite_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportRewriteController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_environment_rewrite';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->rewriter->advance( $job_id, $batch_rows )',
	) as $import_rewrite_controller_guard
) {
	if ( ! str_contains( $import_rewrite_controller, $import_rewrite_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-rewrite-entrypoint',
			'Portable Import environment rewrite endpoint must remain administrator/job-nonce gated and bounded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportRewriteController.php',
			$import_rewrite_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_rewrite_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-rewrite-public-endpoint',
		'Portable Import environment rewrite must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportRewriteController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$import_finalize_state_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizeStateStore.php' );
foreach (
	array(
		"public const OPTION_NAME    = 'seo_geo_migration_clone_import_finalize_state_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'database-fingerprint', 'file-fingerprint', 'ready'",
		"'activation_plan_hash'",
		"'activation_allowed'",
		"'handoff_ready'",
		"'active_tables_untouched'",
		"'active_roots_untouched'",
	) as $import_finalize_state_guard
) {
	if ( ! str_contains( $import_finalize_state_store, $import_finalize_state_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-finalize-state',
			'Portable Import finalization-preflight state must remain bounded, resumable and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizeStateStore.php',
			$import_finalize_state_guard,
			'missing'
		);
	}
}

$import_finalizer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php' );
foreach (
	array(
		"'seo-geo-import-finalize-db-v1'",
		"'seo-geo-import-finalize-files-v1'",
		'$this->database_restorer->staging_plan( $job_id )',
		"get_option( 'blog_public', '1' )",
		'SandboxGuard::outbound_safe()',
		'SandboxGuard::backups_ready()',
		'ImportPreflight::TARGET_AUTHORIZED_MARKER',
		"'activation_allowed'",
		"'handoff_ready'",
		"'active_tables_untouched'",
		"'active_roots_untouched'",
		"'import-finalize-staged-file-integrity-mismatch'",
		"'import-finalize-fingerprint-reconciliation-failed'",
	) as $import_finalize_guard
) {
	if ( ! str_contains( $import_finalizer, $import_finalize_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-finalize-preflight',
			'10E.2A.4.6.1 finalization preflight is missing a required fingerprint/sandbox/rollback boundary.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
			$import_finalize_guard,
			'missing'
		);
	}
}

if (
	1 !== preg_match( "/\\['activation_allowed'\\]\\s*=\\s*true;/", $import_finalizer )
	|| 1 !== preg_match( "/\\['handoff_ready'\\]\\s*=\\s*false;/", $import_finalizer )
	|| 1 !== preg_match( "/'active_mutation_in_this_phase'\\s*=>\\s*false/", $import_finalizer )
	|| 1 !== preg_match( "/'rollback_required_before_swap'\\s*=>\\s*true/", $import_finalizer )
	|| 1 !== preg_match( "/'final_handoff_ready'\\s*=>\\s*false/", $import_finalizer )
) {
	fail_migration_bridge(
		'portable-clone-import-finalize-promotion-gate',
		'10E.2A.4.6.1 must authorize a later promotion only after planning, while keeping current-phase mutation and final handoff disabled.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
		'activation_allowed=true; handoff_ready=false; read-only activation plan',
		'promotion gate mismatch'
	);
}

foreach ( array( 'file_put_contents(', 'fwrite(', 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(', 'wp_mkdir_p(' ) as $finalize_file_mutation ) {
	if ( str_contains( $import_finalizer, $finalize_file_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-import-finalize-no-file-mutation',
			'10E.2A.4.6.1 must fingerprint and plan only; active/staging filesystem mutation belongs to later promotion.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
			'no filesystem mutation primitive',
			$finalize_file_mutation
		);
	}
}

foreach ( array( '->insert(', '->update(', '->delete(', '->query(' ) as $finalize_database_mutation ) {
	if ( str_contains( strtolower( $import_finalizer ), strtolower( $finalize_database_mutation ) ) ) {
		fail_migration_bridge(
			'portable-clone-import-finalize-no-database-mutation',
			'10E.2A.4.6.1 must remain read-only against staging and active database tables.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
			'read-only SELECT/metadata queries only',
			$finalize_database_mutation
		);
	}
}
if ( 1 === preg_match( '/[\"\']\\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER|RENAME|TRUNCATE)\\b/i', $import_finalizer, $finalize_sql_match ) ) {
	fail_migration_bridge(
		'portable-clone-import-finalize-no-database-mutation',
		'10E.2A.4.6.1 must remain read-only against staging and active database tables.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
		'no mutating SQL literal',
		(string) ( $finalize_sql_match[0] ?? 'mutating SQL' )
	);
}

$import_finalize_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFinalizeController.php' );
foreach (
	array(
		"'seo_geo_migration_clone_import_finalize_preflight'",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->planner->advance(',
	) as $import_finalize_controller_guard
) {
	if ( ! str_contains( $import_finalize_controller, $import_finalize_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-import-finalize-entrypoint',
			'Portable Import finalization preflight endpoint must remain administrator/job-nonce gated and bounded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFinalizeController.php',
			$import_finalize_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $import_finalize_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-import-finalize-public-endpoint',
		'Portable Import finalization preflight must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFinalizeController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$import_finalizer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php' );
foreach (
	array(
		'public function activation_plan_snapshot( string $job_id ): ?array',
		"'ready' !== ( \$state['status'] ?? null )",
		"'activation_allowed'",
		"'handoff_ready'",
		'$this->runtime_gate( $job_id, $state )',
		"'activation_plan_hash'",
	) as $activation_plan_snapshot_guard
) {
	if ( ! str_contains( $import_finalizer, $activation_plan_snapshot_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-activation-plan-authority',
			'10E.2A.4.6.2 must consume only the accepted fresh finalization activation plan.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFinalizationPlanner.php',
			$activation_plan_snapshot_guard,
			'missing'
		);
	}
}

$database_activation_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseActivationStateStore.php' );
foreach (
	array(
		"public const RELATIVE_PATH  = 'import/activation/database-state.json';",
		'private ExportWorkspace $workspace;',
		'$this->workspace->read( $job_id, self::RELATIVE_PATH )',
		'$this->workspace->write( $job_id, self::RELATIVE_PATH',
		"'database_swapped'",
		"'rollback_available'",
		"'handoff_ready'",
	) as $database_activation_store_guard
) {
	if ( ! str_contains( $database_activation_store, $database_activation_store_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-activation-external-journal',
			'Database activation recovery state must live outside wp_options in the private job workspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseActivationStateStore.php',
			$database_activation_store_guard,
			'missing'
		);
	}
}

$database_activator = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseActivator.php' );
foreach (
	array(
		'public function prepare( string $job_id ): ?array',
		'public function activate( string $job_id ): ?array',
		'public function rollback( string $job_id ): ?array',
		'$this->finalizer->activation_plan_snapshot( $job_id )',
		'CloneJobStore::OPTION_NAME',
		'ImportFinalizeStateStore::OPTION_NAME',
		"'blog_public', '0'",
		"'active_plugins'",
		"'template'",
		"'stylesheet'",
		'plugin_basename( SEO_GEO_MIGRATION_BRIDGE_DIR',
		"'RENAME TABLE '",
		'wp_cache_flush()',
		'$this->rename_reverse( $state )',
		"'database-activation-verification-failed'",
		"'database-rollback-verification-failed'",
		"'handoff_ready'          => false",
	) as $database_activator_guard
) {
	if ( ! str_contains( $database_activator, $database_activator_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-activation-contract',
			'10E.2A.4.6.2 reversible database activation is missing a required control-plane/noindex/atomic-rollback guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseActivator.php',
			$database_activator_guard,
			'missing'
		);
	}
}

if (
	! str_contains( $database_activator, "SandboxGuard::outbound_safe()" )
	|| ! str_contains( $database_activator, "SandboxGuard::backups_ready()" )
	|| ! str_contains( $database_activator, 'ImportPreflight::TARGET_AUTHORIZED_MARKER' )
) {
	fail_migration_bridge(
		'portable-clone-database-activation-sandbox-guard',
		'Database activation must rerun sandbox, outbound, backup and explicit-target authorization guards.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/ImportDatabaseActivator.php',
		'fresh sandbox safety gate before activation/rollback',
		'missing sandbox activation guard'
	);
}

$database_activation_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseActivationController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_database_activation';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		"'ACTIVATE_DATABASE'",
		"'ROLLBACK_DATABASE'",
		'$this->activator->activate( $job_id )',
		'$this->activator->rollback( $job_id )',
	) as $database_activation_controller_guard
) {
	if ( ! str_contains( $database_activation_controller, $database_activation_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-database-activation-entrypoint',
			'Database activation endpoint must remain administrator/job-nonce/explicit-confirmation gated.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseActivationController.php',
			$database_activation_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $database_activation_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-database-activation-public-endpoint',
		'Reversible database activation must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportDatabaseActivationController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$file_promotion_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromotionStateStore.php' );
foreach (
	array(
		"public const RELATIVE_PATH  = 'import/activation/file-promotion-state.json';",
		"'candidate-ready'",
		"'rolling-back'",
		"'runtime_before'",
		"'runtime_target'",
		"'handoff_ready'",
	) as $file_promotion_store_guard
) {
	if ( ! str_contains( $file_promotion_store, $file_promotion_store_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-promotion-journal',
			'10E.2A.4.6.3 file promotion must persist external runtime/recovery state outside active WordPress roots.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromotionStateStore.php',
			$file_promotion_store_guard,
			'missing'
		);
	}
}

$file_promotion_planner = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromotionPlanner.php' );
foreach (
	array(
		'public function prepare( string $job_id ): ?array',
		"'.seo-geo-' . $key . '-candidate-'",
		"'.seo-geo-' . $key . '-rollback-'",
		"'database/manifest.json'",
		"'active_plugins'",
		"'template'",
		"'stylesheet'",
		"SandboxGuard::outbound_safe()",
		"SandboxGuard::backups_ready()",
		'ImportPreflight::TARGET_AUTHORIZED_MARKER',
		"'0' === (string) get_option( 'blog_public', '1' )",
	) as $file_promotion_planner_guard
) {
	if ( ! str_contains( $file_promotion_planner, $file_promotion_planner_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-promotion-plan',
			'File promotion planning is missing a same-filesystem/runtime/sandbox recovery guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromotionPlanner.php',
			$file_promotion_planner_guard,
			'missing'
		);
	}
}

$file_promoter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromoter.php' );
foreach (
	array(
		'public const DEFAULT_BATCH_FILES = 20;',
		'public function advance_candidates(',
		'public function promote( string $job_id ): ?array',
		'public function advance_verification(',
		'public function rollback( string $job_id ): ?array',
		'copy_file_to_promotion_candidate',
		'rename_file_promotion_path',
		"'file|' . (string) $root['id']",
		"'file-promotion-final-integrity-failed'",
		"'runtime_target'",
		"'runtime_before'",
		'update_option( \'active_plugins\'',
		"'handoff_ready']      = true",
		'$this->rollback_internal(',
	) as $file_promoter_guard
) {
	if ( ! str_contains( $file_promoter, $file_promoter_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-promotion-contract',
			'10E.2A.4.6.3 file promotion is missing a bounded fingerprint/runtime/rollback/final-handoff guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromoter.php',
			$file_promoter_guard,
			'missing'
		);
	}
}
foreach ( array( 'copy(', 'rename(', 'unlink(', 'mkdir(', 'rmdir(', 'file_put_contents(', 'fwrite(' ) as $promotion_mutation ) {
	if ( str_contains( $file_promoter, $promotion_mutation ) ) {
		fail_migration_bridge(
			'portable-clone-file-promotion-filesystem-boundary',
			'File promotion orchestration must leave all filesystem mutations behind ExportWorkspace.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/ImportFilePromoter.php',
			'ExportWorkspace mutation boundary only',
			$promotion_mutation
		);
	}
}

$file_promotion_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFilePromotionController.php' );
foreach (
	array(
		"public const ACTION       = 'seo_geo_migration_clone_import_file_promotion';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . $job_id )",
		"'PROMOTE_FILES'",
		"'ROLLBACK_FILES'",
		'$this->promoter->advance_candidates(',
		'$this->promoter->promote( $job_id )',
		'$this->promoter->advance_verification(',
		'$this->promoter->rollback( $job_id )',
	) as $file_promotion_controller_guard
) {
	if ( ! str_contains( $file_promotion_controller, $file_promotion_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-file-promotion-entrypoint',
			'File promotion endpoint must remain administrator/job-nonce/bounded-batch/explicit-confirmation gated.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFilePromotionController.php',
			$file_promotion_controller_guard,
			'missing'
		);
	}
}
if ( str_contains( $file_promotion_controller, 'admin_post_nopriv_' ) ) {
	fail_migration_bridge(
		'portable-clone-file-promotion-public-endpoint',
		'Reversible file promotion must never expose an unauthenticated endpoint.',
		MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneImportFilePromotionController.php',
		'authenticated admin_post action only',
		'admin_post_nopriv_'
	);
}


$clone_inventory_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventoryStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_clone_inventory_v1';",
		'public const SCHEMA_VERSION = 1;',
		"add_option( self::OPTION_NAME, \$states, '', false )",
		'update_option( self::OPTION_NAME, $states, false )',
		"'fingerprint_scope' => 'database-structure-estimates+file-content'",
	) as $clone_inventory_store_guard
) {
	if ( ! str_contains( $clone_inventory_store, $clone_inventory_store_guard ) ) {
		fail_migration_bridge(
			'portable-clone-inventory-store',
			'Portable Clone inventory state must remain versioned, bounded and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventoryStore.php',
			$clone_inventory_store_guard,
			'missing'
		);
	}
}

$clone_inventory = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventory.php' );
foreach (
	array(
		'public const MIN_BATCH_SIZE = 25;',
		'public const MAX_BATCH_SIZE = 500;',
		'public const DEFAULT_BATCH_SIZE = 100;',
		"'local-clone', 'export'",
		"'SHOW TABLE STATUS LIKE %s'",
		"'uploads'",
		"'plugins'",
		"'themes'",
		"hash_file( 'sha256', \$path )",
		"'source-directory-queue-limit'",
		"'file_count'",
		"'byte_count'",
		"'excluded_count'",
		"'symlink_count'",
		"'unreadable_count'",
	) as $clone_inventory_guard
) {
	if ( ! str_contains( $clone_inventory, $clone_inventory_guard ) ) {
		fail_migration_bridge(
			'portable-clone-source-inventory',
			'Portable Clone source inventory is missing a required read-only/resumable inventory guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventory.php',
			$clone_inventory_guard,
			'missing'
		);
	}
}

foreach ( array( 'copy(', 'file_put_contents(', 'fwrite(', 'unlink(', 'rename(', 'mkdir(', 'rmdir(' ) as $inventory_mutation_primitive ) {
	if ( str_contains( $clone_inventory, $inventory_mutation_primitive ) ) {
		fail_migration_bridge(
			'portable-clone-inventory-read-only',
			'10E.2A.2 source inventory must not create, copy, move or delete payload files.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/CloneInventory.php',
			'read-only source inventory',
			$inventory_mutation_primitive
		);
	}
}

$destination_planner = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/DestinationSafetyPlanner.php' );
foreach (
	array(
		"'mode'",
		"'read-only-destination-plan'",
		"'target-path-is-production'",
		"'target-inside-source-'",
		"'target-url-is-production'",
		"'same-origin-target-path-not-isolated'",
		"'target-table-prefix-not-isolated'",
		"'target-free-space-insufficient'",
		"'mutations_performed'",
	) as $destination_planner_guard
) {
	if ( ! str_contains( $destination_planner, $destination_planner_guard ) ) {
		fail_migration_bridge(
			'portable-clone-destination-plan',
			'Local clone destination planner is missing a required isolation/safety guard.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/DestinationSafetyPlanner.php',
			$destination_planner_guard,
			'missing'
		);
	}
}

$clone_inventory_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneInventoryController.php' );
foreach (
	array(
		"public const ACTION = 'seo_geo_migration_clone_inventory';",
		"current_user_can( 'manage_options' )",
		"check_admin_referer( self::NONCE_ACTION . ':' . \$job_id )",
		'$this->inventory->advance( $job_id, $batch_size )',
	) as $clone_inventory_controller_guard
) {
	if ( ! str_contains( $clone_inventory_controller, $clone_inventory_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-inventory-entrypoint',
			'Portable Clone inventory endpoint must remain capability/nonce gated and advance only one bounded batch.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneInventoryController.php',
			$clone_inventory_controller_guard,
			'missing'
		);
	}
}

$clone_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneController.php' );
foreach (
	array(
		"public const ACTION = 'seo_geo_migration_clone_create_job';",
		"current_user_can( 'manage_options' )",
		'check_admin_referer( self::NONCE_ACTION )',
		'CloneJobStore::allowed_operations()',
		'$this->store->create( $operation )',
	) as $clone_controller_guard
) {
	if ( ! str_contains( $clone_controller, $clone_controller_guard ) ) {
		fail_migration_bridge(
			'portable-clone-entrypoint',
			'Portable Clone planning endpoint must remain capability/nonce gated and create bounded jobs only.',
			MIGRATION_BRIDGE_DIR . '/src/Clone/AdminCloneController.php',
			$clone_controller_guard,
			'missing'
		);
	}
}

$site_analyzer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'read-only'/",
		"/'mutations_performed'\\s*=>\\s*false/",
		"/'content_scan_performed'\\s*=>\\s*false/",
		"/'credentials_collected'\\s*=>\\s*false/",
		"/'option_values_exported'\\s*=>\\s*false/",
	) as $guard
) {
	if ( 1 !== preg_match( $guard, $site_analyzer ) ) {
		fail_migration_bridge( 'analyzer-safety-report', 'Migration analyzer report is missing a required read-only safety flag.', MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php', $guard, 'missing' );
	}
}

$store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_baseline_v1';",
		'add_option( self::OPTION_NAME',
		'update_option( self::OPTION_NAME',
		"add_option( self::OPTION_NAME, \$envelope, '', false )",
		'update_option( self::OPTION_NAME, $envelope, false )',
	) as $storage_guard
) {
	if ( ! str_contains( $store, $storage_guard ) ) {
		fail_migration_bridge( 'baseline-storage-boundary', 'Phase 8B persistence escaped the dedicated non-autoloaded option contract.', MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotStore.php', $storage_guard, 'missing' );
	}
}

$snapshotter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotter.php' );
foreach (
	array(
		"'same_origin_only'           => true",
		"'authenticated_requests'     => false",
		"'private_content_collected'  => false",
		"'body_content_persisted'     => false",
		"'legacy_output_is_authority' => false",
		"'mode'    => 'observed-during-baseline'",
	) as $guard
) {
	if ( ! str_contains( $snapshotter, $guard ) ) {
		fail_migration_bridge( 'baseline-safety-report', 'Phase 8B snapshot is missing a required safety/authority declaration.', MIGRATION_BRIDGE_DIR . '/src/BaselineSnapshotter.php', $guard, 'missing' );
	}
}

$incremental_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_baseline_progress_v1';",
		"add_option( self::OPTION_NAME, \$state, '', false )",
		'update_option( self::OPTION_NAME, $state, false )',
	) as $incremental_store_guard
) {
	if ( ! str_contains( $incremental_store, $incremental_store_guard ) ) {
		fail_migration_bridge(
			'incremental-baseline-storage',
			'Incremental baseline progress must remain dedicated and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineStore.php',
			$incremental_store_guard,
			'missing'
		);
	}
}

$incremental_capture = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineCapture.php' );
foreach (
	array(
		'public const MIN_BATCH_SIZE = 1;',
		'public const MAX_BATCH_SIZE = 20;',
		'public const DEFAULT_BATCH_SIZE = 10;',
		"'batch_size'       =>",
		'$this->normalize_batch_size(',
		'$this->progress_store->save( $state );',
		'$this->baseline_store->save( $snapshot, false );',
		"'incremental_capture'        => true",
		"'resumable_progress'         => true",
		"'same_origin_only'           => true",
		"'authenticated_requests'     => false",
		"'body_content_persisted'     => false",
	) as $incremental_capture_guard
) {
	if ( ! str_contains( $incremental_capture, $incremental_capture_guard ) ) {
		fail_migration_bridge(
			'incremental-baseline-contract',
			'Incremental baseline capture is missing its bounded/resumable/privacy contract.',
			MIGRATION_BRIDGE_DIR . '/src/IncrementalBaselineCapture.php',
			$incremental_capture_guard,
			'missing'
		);
	}
}

$dependency_graph = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/DependencyGraphBuilder.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'read-only-planning'/",
		"/'mutations_performed'\\s*=>\\s*false/",
		"/'plugin_removal_performed'\\s*=>\\s*false/",
		"/'theme_switch_performed'\\s*=>\\s*false/",
		"/'raw_content_exported'\\s*=>\\s*false/",
		"/'builder_payload_exported'\\s*=>\\s*false/",
		"/'automatic_removal_allowed'\\s*=>\\s*false/",
		"/'auto_remove'\\s*=>\\s*false/",
	) as $graph_guard
) {
	if ( 1 !== preg_match( $graph_guard, $dependency_graph ) ) {
		fail_migration_bridge( 'dependency-graph-safety', 'Phase 8C dependency graph is missing a required non-destructive planning guard.', MIGRATION_BRIDGE_DIR . '/src/DependencyGraphBuilder.php', $graph_guard, 'missing' );
	}
}

$content_scanner = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Content/ContentDependencyScanner.php' );
foreach (
	array(
		"/'raw_content_exported'\\s*=>\\s*false/",
		"/'builder_payload_exported'\\s*=>\\s*false/",
		"/'private_body_exported'\\s*=>\\s*false/",
	) as $scanner_guard
) {
	if ( 1 !== preg_match( $scanner_guard, $content_scanner ) ) {
		fail_migration_bridge( 'dependency-scan-privacy', 'Phase 8C content scanner is missing a required privacy guard.', MIGRATION_BRIDGE_DIR . '/src/Content/ContentDependencyScanner.php', $scanner_guard, 'missing' );
	}
}

$sandbox_guard = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxGuard.php' );
foreach (
	array(
		"/public const MARKER = 'SEO_GEO_MIGRATION_SANDBOX'/",
		"/public const OUTBOUND_SAFE_MARKER = 'SEO_GEO_MIGRATION_OUTBOUND_SAFE'/",
		"/public const BACKUPS_READY_MARKER = 'SEO_GEO_MIGRATION_BACKUPS_READY'/",
		"/constant_enabled\\( self::MARKER \\)/",
		"/constant_enabled\\( self::OUTBOUND_SAFE_MARKER \\)/",
		"/constant_enabled\\( self::BACKUPS_READY_MARKER \\)/",
		"/'noindex'\]\\s*=\\s*true/",
		"/'nofollow'\]\\s*=\\s*true/",
		"/X-Robots-Tag/",
	) as $sandbox_guard_rule
) {
	if ( 1 !== preg_match( $sandbox_guard_rule, $sandbox_guard ) ) {
		fail_migration_bridge( 'sandbox-indexing-guard', 'Phase 8D sandbox guard is missing an explicit marker or indexing defense.', MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxGuard.php', $sandbox_guard_rule, 'missing' );
	}
}

$sandbox_lab = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxMigrationLab.php' );
foreach (
	array(
		"/'production_cutover_allowed'\\s*=>\\s*false/",
		"/'production_mutation_allowed'\\s*=>\\s*false/",
		"/'indexing_allowed'\\s*=>\\s*false/",
		"/'canonical_competition_allowed'\\s*=>\\s*false/",
		"/'baseline_is_reference_only'\\s*=>\\s*true/",
		"/'review_decisions_are_planning'\\s*=>\\s*true/",
		"/'sandbox-origin-not-distinct-from-baseline'/",
		"/'outbound-safety-not-confirmed'/",
		"/'fresh-backups-not-confirmed'/",
		"/'dependency-review-incomplete'/",
		"/SandboxGuard::outbound_safe\\(\\)/",
		"/SandboxGuard::backups_ready\\(\\)/",
	) as $sandbox_rule
) {
	if ( 1 !== preg_match( $sandbox_rule, $sandbox_lab ) ) {
		fail_migration_bridge( 'sandbox-lab-safety', 'Phase 8D sandbox lab is missing a non-production safety declaration.', MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxMigrationLab.php', $sandbox_rule, 'missing' );
	}
}

$migration_engine = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationEngine.php' );
foreach (
	array(
		"current_user_can( 'manage_options' )",
		'current_user_can( \'edit_post\', $object_id )',
		'wp_verify_nonce(',
		'if ( ! $confirmed )',
		'public const BACKUP_META',
		'new BaselineSnapshotStore()',
		"is_array( \$baseline['snapshot'] ?? null ) ? \$baseline['snapshot'] : null",
		'build( $analysis, $baseline_snapshot )',
		'add_post_meta( $post->ID, self::BACKUP_META',
	) as $engine_guard
) {
	if ( ! str_contains( $migration_engine, $engine_guard ) ) {
		fail_migration_bridge( 'migration-engine-safety', 'Phase 8E Migration Engine is missing an authorization or backup guard.', MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationEngine.php', $engine_guard, 'missing' );
	}
}

foreach (
	array(
		"/'sandbox_only'\\s*=>\\s*true/",
		"/'plugin_mutation_allowed'\\s*=>\\s*false/",
		"/'theme_mutation_allowed'\\s*=>\\s*false/",
		"/'url_change_allowed'\\s*=>\\s*false/",
		"/'object_id_change_allowed'\\s*=>\\s*false/",
		"/'unsupported_content_dropped'\\s*=>\\s*false/",
	) as $engine_safety_guard
) {
	if ( 1 !== preg_match( $engine_safety_guard, $migration_engine ) ) {
		fail_migration_bridge( 'migration-engine-safety', 'Phase 8E Migration Engine is missing a sandbox/preservation safety declaration.', MIGRATION_BRIDGE_DIR . '/src/Migration/MigrationEngine.php', $engine_safety_guard, 'missing' );
	}
}

$admin_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Migration/AdminMigrationController.php' );
foreach (
	array(
		"admin_post_",
		"current_user_can( 'manage_options' )",
		"check_admin_referer(",
		'\'migrate\' !== $confirm',
	) as $controller_guard
) {
	if ( ! str_contains( $admin_controller, $controller_guard ) ) {
		fail_migration_bridge( 'migration-admin-entrypoint', 'Phase 8E administrator entrypoint is missing capability, nonce or explicit-confirmation enforcement.', MIGRATION_BRIDGE_DIR . '/src/Migration/AdminMigrationController.php', $controller_guard, 'missing' );
	}
}

$elementor_adapter = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Migration/ElementorMigrationAdapter.php' );
$divi_adapter      = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Migration/DiviMigrationAdapter.php' );

foreach (
	array(
		'unsupported-elementor-widget:',
		'elementor-no-supported-widgets',
	) as $adapter_guard
) {
	if ( ! str_contains( $elementor_adapter, $adapter_guard ) ) {
		fail_migration_bridge( 'elementor-adapter-blocker', 'Elementor adapter must expose unsupported content as blockers.', MIGRATION_BRIDGE_DIR . '/src/Migration/ElementorMigrationAdapter.php', $adapter_guard, 'missing' );
	}
}

if ( ! str_contains( $divi_adapter, 'unsupported-divi-module:' ) ) {
	fail_migration_bridge( 'divi-adapter-blocker', 'Divi adapter must expose unsupported modules as blockers.', MIGRATION_BRIDGE_DIR . '/src/Migration/DiviMigrationAdapter.php', 'unsupported-divi-module:', 'missing' );
}

$parity_engine = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Parity/SeoParityEngine.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'seo-geo-parity'/",
		"/'mutations_performed'\\s*=>\\s*false/",
		"/'production_cutover_allowed'\\s*=>\\s*false/",
		"/'allowlist_requires_exact_fingerprints'\\s*=>\\s*true/",
		"/'legacy_output_is_authority'\\s*=>\\s*false/",
		"/'canonical_robots'\\s*=>\\s*true/",
		"/'schema_conflicts'\\s*=>\\s*true/",
		"/'redirect_map'\\s*=>\\s*true/",
		"/'internal_links'\\s*=>\\s*true/",
		"/'sitemap_consistency'\\s*=>\\s*true/",
	) as $parity_guard
) {
	if ( 1 !== preg_match( $parity_guard, $parity_engine ) ) {
		fail_migration_bridge( 'parity-engine-safety', 'Phase 8F parity engine is missing a required comparison or non-cutover guard.', MIGRATION_BRIDGE_DIR . '/src/Parity/SeoParityEngine.php', $parity_guard, 'missing' );
	}
}

foreach (
	array(
		"'canonical-owner-conflict'",
		"'robots-owner-conflict'",
		"'hreflang-owner-conflict'",
		"'schema-duplicate-blocks'",
		"'candidate-internal-link-target-is-broken'",
	) as $hard_regression_guard
) {
	if ( ! str_contains( $parity_engine, $hard_regression_guard ) ) {
		fail_migration_bridge( 'parity-hard-regression', 'Phase 8F parity engine is missing a required non-allowable regression class.', MIGRATION_BRIDGE_DIR . '/src/Parity/SeoParityEngine.php', $hard_regression_guard, 'missing' );
	}
}

$parity_allowlist = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Parity/ParityAllowlist.php' );
foreach (
	array(
		"before_sha256",
		"after_sha256",
		"hash_equals(",
		"reason",
	) as $allowlist_guard
) {
	if ( ! str_contains( $parity_allowlist, $allowlist_guard ) ) {
		fail_migration_bridge( 'parity-allowlist', 'Phase 8F allowlist must remain exact, fingerprint-bound and reasoned.', MIGRATION_BRIDGE_DIR . '/src/Parity/ParityAllowlist.php', $allowlist_guard, 'missing' );
	}
}

$html_extractor = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/HtmlSnapshotExtractor.php' );
foreach (
	array(
		"'canonical_count'",
		"'robots_count'",
		"'meta_description_count'",
		"'hreflang_duplicates'",
	) as $ownership_guard
) {
	if ( ! str_contains( $html_extractor, $ownership_guard ) ) {
		fail_migration_bridge( 'parity-ownership-evidence', 'HTML snapshot extractor is missing Phase 8F ownership evidence.', MIGRATION_BRIDGE_DIR . '/src/HtmlSnapshotExtractor.php', $ownership_guard, 'missing' );
	}
}

$cutover_engine = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverEngine.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'production-cutover-plan'/",
		"/'plugin_deletion_allowed'\\s*=>\\s*false/",
		"/'theme_deletion_allowed'\\s*=>\\s*false/",
		"/'database_reset_allowed'\\s*=>\\s*false/",
		"/'uploads_reset_allowed'\\s*=>\\s*false/",
		"/'bridge_deactivation_allowed'\\s*=>\\s*false/",
		"/'rollback_required_until_acceptance'\\s*=>\\s*true/",
		"/backup_verified_before_mutation/",
		"/post_cutover_parity_passed/",
		"/rolled-back-auto/",
		"/rollback_state_drift/",
	) as $cutover_guard
) {
	if ( 1 !== preg_match( $cutover_guard, $cutover_engine ) ) {
		fail_migration_bridge(
			'cutover-safety',
			'Phase 8G cutover engine is missing a required backup, rollback or destructive-action guard.',
			MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverEngine.php',
			$cutover_guard,
			'missing'
		);
	}
}

$cutover_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverSnapshotStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_cutover_history_v1';",
		'add_option( self::OPTION_NAME',
		"'', false )",
		'update_option(',
		"'prepared'",
		"'cutover-active'",
		"'status'",
	) as $cutover_store_guard
) {
	if ( ! str_contains( $cutover_store, $cutover_store_guard ) ) {
		fail_migration_bridge(
			'cutover-history',
			'Phase 8G cutover history must remain persistent, non-autoloaded and stateful.',
			MIGRATION_BRIDGE_DIR . '/src/Cutover/CutoverSnapshotStore.php',
			$cutover_store_guard,
			'missing'
		);
	}
}

$backup_validator = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Cutover/BackupEvidenceValidator.php' );
foreach (
	array(
		"'database' => 'full-database'",
		"'uploads'  => 'uploads-tree'",
		'backup-sha256-invalid:',
		'backup-created-at-invalid-or-stale:',
	) as $backup_guard
) {
	if ( ! str_contains( $backup_validator, $backup_guard ) ) {
		fail_migration_bridge(
			'cutover-backup-evidence',
			'Phase 8G requires recent hash-bound database and uploads backup evidence.',
			MIGRATION_BRIDGE_DIR . '/src/Cutover/BackupEvidenceValidator.php',
			$backup_guard,
			'missing'
		);
	}
}

$quality_validator = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Cutover/QualityEvidenceValidator.php' );
foreach (
	array(
		"'accessibility'",
		"'performance'",
		'quality-gate-not-passed:',
		'quality-created-at-invalid-or-stale:',
		"'passed'",
	) as $quality_guard
) {
	if ( ! str_contains( $quality_validator, $quality_guard ) ) {
		fail_migration_bridge(
			'cutover-quality-evidence',
			'Phase 8G requires recent passed accessibility/performance evidence.',
			MIGRATION_BRIDGE_DIR . '/src/Cutover/QualityEvidenceValidator.php',
			$quality_guard,
			'missing'
		);
	}
}

$cutover_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Cutover/AdminCutoverController.php' );
foreach (
	array(
		'admin_post_',
		"current_user_can( 'manage_options' )",
		'check_admin_referer(',
		"'cutover' !==",
		"'rollback' !==",
		"'accept' !==",
	) as $controller_guard
) {
	if ( ! str_contains( $cutover_controller, $controller_guard ) ) {
		fail_migration_bridge(
			'cutover-admin-entrypoint',
			'Phase 8G administrator entrypoints are missing capability, nonce or explicit-confirmation enforcement.',
			MIGRATION_BRIDGE_DIR . '/src/Cutover/AdminCutoverController.php',
			$controller_guard,
			'missing'
		);
	}
}

$report_engine = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportEngine.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'migration-report'/",
		"/'ready_for_handoff'/",
		"/'mutations_performed'\\s*=>\\s*false/",
		"/'private_content_exported'\\s*=>\\s*false/",
		"/'raw_backup_content_exported'\\s*=>\\s*false/",
		"/'report_is_runtime_dependency'\\s*=>\\s*false/",
		"/'replaced_deactivated'/",
		"/'intentional_improvements'/",
		"/'bridge_disposition'/",
	) as $report_guard
) {
	if ( 1 !== preg_match( $report_guard, $report_engine ) ) {
		fail_migration_bridge(
			'migration-report-contract',
			'Phase 8H report engine is missing a required handoff, privacy or disposition contract.',
			MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportEngine.php',
			$report_guard,
			'missing'
		);
	}
}

$report_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_report_v1';",
		'add_option( self::OPTION_NAME',
		"'', false )",
		'update_option( self::OPTION_NAME',
		"'report-not-ready-for-handoff'",
		"'report-exists'",
	) as $report_store_guard
) {
	if ( ! str_contains( $report_store, $report_store_guard ) ) {
		fail_migration_bridge(
			'migration-report-storage',
			'Phase 8H report persistence must remain explicit, final-only and non-autoloaded.',
			MIGRATION_BRIDGE_DIR . '/src/Report/MigrationReportStore.php',
			$report_store_guard,
			'missing'
		);
	}
}

$operator_status = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorStatus.php' );
foreach (
	array(
		"/'mode'\\s*=>\\s*'operator-status-read-only'/",
		"/'mutations_performed'\\s*=>\\s*false/",
		"/'private_content_exported'\\s*=>\\s*false/",
		"/'builder_payload_exported'\\s*=>\\s*false/",
		"/'credentials_exported'\\s*=>\\s*false/",
		"/'raw_recovery_exported'\\s*=>\\s*false/",
		"/'page_render_executes_actions'\\s*=>\\s*false/",
	) as $operator_status_guard
) {
	if ( 1 !== preg_match( $operator_status_guard, $operator_status ) ) {
		fail_migration_bridge(
			'operator-status-safety',
			'Phase 8I operator status is missing a required read-only/privacy declaration.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorStatus.php',
			$operator_status_guard,
			'missing'
		);
	}
}

$operator_screen = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php' );
foreach (
	array(
		'add_management_page(',
		"current_user_can( 'manage_options' )",
		"public const PAGE_SLUG = 'seo-geo-migration-bridge';",
	) as $operator_screen_guard
) {
	if ( ! str_contains( $operator_screen, $operator_screen_guard ) ) {
		fail_migration_bridge(
			'operator-screen-contract',
			'Phase 8I operator screen is missing its Tools registration or capability guard.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php',
			$operator_screen_guard,
			'missing'
		);
	}
}

$strictly_read_only_operator_files = array(
	MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorCopy.php',
	MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorStatus.php',
);

foreach ( $strictly_read_only_operator_files as $operator_path ) {
	$operator_source = (string) file_get_contents( $operator_path );
	foreach ( array( '$_POST', 'admin_post_', 'wp_nonce_field(', '<form' ) as $forbidden_operator_primitive ) {
		if ( str_contains( $operator_source, $forbidden_operator_primitive ) ) {
			fail_migration_bridge(
				'operator-status-mutation-entrypoint',
				'Read-only operator status/copy code must not expose submission primitives.',
				$operator_path,
				'no POST/admin_post/nonce form primitives',
				$forbidden_operator_primitive
			);
		}
	}
}

foreach (
	array(
		'AdminBaselineCaptureController::ACTION',
		'wp_nonce_field( AdminBaselineCaptureController::NONCE_ACTION )',
		'<form id="seo-geo-baseline-capture-form" method="post"',
		"true !== ( \$status['baseline']['available'] ?? false )",
		"render_baseline_capture_form( \$status )",
		'name="batch_size"',
		'IncrementalBaselineCapture::MIN_BATCH_SIZE',
		'IncrementalBaselineCapture::MAX_BATCH_SIZE',
	) as $baseline_form_guard
) {
	if ( ! str_contains( $operator_screen, $baseline_form_guard ) ) {
		fail_migration_bridge(
			'operator-baseline-action',
			'Operator UI must expose the explicit baseline action only behind the missing-baseline branch and nonce form.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php',
			$baseline_form_guard,
			'missing'
		);
	}
}

if ( str_contains( $operator_screen, 'window.setTimeout(function ()' ) ) {
	fail_migration_bridge(
		'operator-baseline-autosubmit',
		'Operator batch selector must remain user-controlled and must not auto-submit before the operator can change the batch size.',
		MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php',
		'no automatic baseline form submission',
		'window.setTimeout auto-submit found'
	);
}

$baseline_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/AdminBaselineCaptureController.php' );
foreach (
	array(
		"public const ACTION = 'seo_geo_migration_capture_baseline';",
		"current_user_can( 'manage_options' )",
		'check_admin_referer( self::NONCE_ACTION )',
		'isset( $_POST[\'batch_size\'] )',
		'IncrementalBaselineCapture::DEFAULT_BATCH_SIZE',
		'$result = $this->capture->advance( $batch_size );',
		"'progress'",
	) as $baseline_controller_guard
) {
	if ( ! str_contains( $baseline_controller, $baseline_controller_guard ) ) {
		fail_migration_bridge(
			'baseline-admin-entrypoint',
			'Baseline capture entrypoint must remain capability/nonce gated and advance only one resumable step.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/AdminBaselineCaptureController.php',
			$baseline_controller_guard,
			'missing'
		);
	}
}

$review_store = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Review/DependencyReviewStore.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_dependency_reviews_v1';",
		"return array( 'KEEP', 'REPLACE', 'MIGRATE', 'OPTIONAL', 'REMOVE-CANDIDATE' );",
		"add_option( self::OPTION_NAME, \$all, '', false )",
		"update_option( self::OPTION_NAME, \$all, false )",
		"'operator-review-retain-operational'",
		"'operator-review-remove-candidate-after-sandbox'",
	) as $review_store_guard
) {
	if ( ! str_contains( $review_store, $review_store_guard ) ) {
		fail_migration_bridge(
			'dependency-review-store',
			'Dependency review store must remain bounded, non-autoloaded and classification-only.',
			MIGRATION_BRIDGE_DIR . '/src/Review/DependencyReviewStore.php',
			$review_store_guard,
			'missing'
		);
	}
}

$review_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Review/AdminDependencyReviewController.php' );
foreach (
	array(
		"public const ACTION = 'seo_geo_migration_review_dependency';",
		"current_user_can( 'manage_options' )",
		'check_admin_referer( self::NONCE_ACTION . \':\' . $component_id )',
		"'UNKNOWN' === ( \$component['classification'] ?? null )",
		"'UNREVIEWED' === \$decision",
	) as $review_controller_guard
) {
	if ( ! str_contains( $review_controller, $review_controller_guard ) ) {
		fail_migration_bridge(
			'dependency-review-entrypoint',
			'Dependency review must remain capability/nonce gated and limited to current UNKNOWN components.',
			MIGRATION_BRIDGE_DIR . '/src/Review/AdminDependencyReviewController.php',
			$review_controller_guard,
			'missing'
		);
	}
}

$handoff_manifest = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxHandoffManifest.php' );
foreach (
	array(
		"'mode'           => 'seo-geo-sandbox-handoff'",
		"'post_bodies_exported'               => false",
		"'builder_payloads_exported'          => false",
		"'credentials_exported'               => false",
		"'option_values_exported'             => false",
		"'raw_database_exported'              => false",
		"'raw_uploads_exported'               => false",
		"'customer_data_exported'             => false",
		"'accepted_modes'",
		"array( 'origin', 'subdirectory' )",
		"SandboxGuard::MODE_MARKER",
		"'origin_mode_requires_distinct_origin'",
		"'subdirectory_requires_storage_isolation'",
		"SandboxGuard::STORAGE_ISOLATED_MARKER",
		"SandboxGuard::MARKER",
		"SandboxGuard::OUTBOUND_SAFE_MARKER",
		"SandboxGuard::BACKUPS_READY_MARKER",
		"'production_mutation_allowed'",
		"'review_decisions_execute_mutations' => false",
	) as $handoff_guard
) {
	if ( ! str_contains( $handoff_manifest, $handoff_guard ) ) {
		fail_migration_bridge(
			'sandbox-handoff-contract',
			'Sandbox handoff manifest is missing a required privacy or isolation guard.',
			MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxHandoffManifest.php',
			$handoff_guard,
			'missing'
		);
	}
}

$handoff_controller = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/AdminSandboxHandoffController.php' );
foreach (
	array(
		"public const ACTION = 'seo_geo_migration_sandbox_handoff';",
		"current_user_can( 'manage_options' )",
		'check_admin_referer( self::NONCE_ACTION )',
		"Content-Type: application/json",
		"Content-Disposition: attachment;",
	) as $handoff_controller_guard
) {
	if ( ! str_contains( $handoff_controller, $handoff_controller_guard ) ) {
		fail_migration_bridge(
			'sandbox-handoff-entrypoint',
			'Sandbox handoff download must remain capability/nonce gated and JSON-only.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/AdminSandboxHandoffController.php',
			$handoff_controller_guard,
			'missing'
		);
	}
}

foreach (
	array(
		'AdminSandboxHandoffController::ACTION',
		'wp_nonce_field( AdminSandboxHandoffController::NONCE_ACTION )',
		"'dependency_detail_heading'",
		"'sandbox_handoff_heading'",
		'AdminDependencyReviewController::ACTION',
		'AdminDependencyReviewController::NONCE_ACTION',
		"'dependency_review_help'",
	) as $handoff_ui_guard
) {
	if ( ! str_contains( $operator_screen, $handoff_ui_guard ) && ! str_contains( (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorCopy.php' ), $handoff_ui_guard ) ) {
		fail_migration_bridge(
			'sandbox-handoff-operator-ui',
			'Operator UI must expose bounded dependency detail and authenticated sandbox handoff.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/AdminOperatorScreen.php',
			$handoff_ui_guard,
			'missing'
		);
	}
}

$operator_copy = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorCopy.php' );
foreach ( array( "'en' => array(", "'es' => array(", "'next_remove_bridge'", "'privacy_text'" ) as $operator_copy_guard ) {
	if ( ! str_contains( $operator_copy, $operator_copy_guard ) ) {
		fail_migration_bridge(
			'operator-copy-contract',
			'Phase 8I must ship built-in English and Spanish operator copy together.',
			MIGRATION_BRIDGE_DIR . '/src/Operator/OperatorCopy.php',
			$operator_copy_guard,
			'missing'
		);
	}
}

$http_client = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Http/WordPressHttpClient.php' );
if ( ! str_contains( $http_client, "'redirection' => 0" ) || ! str_contains( $http_client, "'cookies'     => array()" ) ) {
	fail_migration_bridge(
		'baseline-http-boundary',
		'Default baseline HTTP transport must remain anonymous and must not follow redirects.',
		MIGRATION_BRIDGE_DIR . '/src/Http/WordPressHttpClient.php',
		'redirection=0 and empty cookies',
		'guard missing'
	);
}

printf( "Migration Bridge static contract OK: 8A read-only; 8B baseline bounded; 8C planning non-destructive; 8D sandbox isolated; 8E migration backed-up; 8F parity strict; 8G cutover backup/quality/parity-gated, reversible and acceptance-locked.\\n" );
