<?php
/**
 * Validate the Phase 8A/8B/8C/8D Migration Bridge safety contract.
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
		'Version: 0.4.0',
		'Requires at least: 7.1',
		'Requires PHP: 8.2',
		'Text Domain: seo-geo-migration-bridge',
	) as $header
) {
	if ( ! str_contains( $bootstrap, $header ) ) {
		fail_migration_bridge( 'plugin-header', 'Migration Bridge plugin header is incomplete.', MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php', $header, 'missing' );
	}
}

$php_files = array_merge(
	array( MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php' ),
	glob( MIGRATION_BRIDGE_DIR . '/src/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Builders/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Http/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Content/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Sandbox/*.php' ) ?: array()
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
	'wp_schedule_event',
	'wp_schedule_single_event',
);

foreach ( $php_files as $path ) {
	$source = (string) file_get_contents( $path );

	foreach ( $destructive_calls as $function_name ) {
		if ( 1 === preg_match( '/\\b' . preg_quote( $function_name, '/' ) . '\\s*\\(/', $source ) ) {
			fail_migration_bridge( 'destructive-api', 'Migration Bridge contains a forbidden production mutation call.', $path, 'non-destructive migration boundary', $function_name );
		}
	}
}

$read_only_files = array_merge(
	array(
		MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php',
		MIGRATION_BRIDGE_DIR . '/src/DependencyGraphBuilder.php',
		MIGRATION_BRIDGE_DIR . '/src/ProviderAuthorityResolver.php',
	),
	glob( MIGRATION_BRIDGE_DIR . '/src/Builders/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Content/*.php' ) ?: array()
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
		"/defined\( self::MARKER \)/",
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
	) as $sandbox_rule
) {
	if ( 1 !== preg_match( $sandbox_rule, $sandbox_lab ) ) {
		fail_migration_bridge( 'sandbox-lab-safety', 'Phase 8D sandbox lab is missing a non-production safety declaration.', MIGRATION_BRIDGE_DIR . '/src/Sandbox/SandboxMigrationLab.php', $sandbox_rule, 'missing' );
	}
}

$http_client = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/Http/WordPressHttpClient.php' );
if ( ! str_contains( $http_client, "'redirection' => 0" ) || ! str_contains( $http_client, "'cookies'     => array()" ) ) {
	fail_migration_bridge( 'baseline-http-boundary', 'Default baseline HTTP transport must remain anonymous and must not follow redirects.', MIGRATION_BRIDGE_DIR . '/src/Http/WordPressHttpClient.php', 'redirection=0 and empty cookies', 'guard missing' );
}

printf( "Migration Bridge static contract OK: Phase 8A is read-only; 8B baseline is bounded; 8C planning is non-destructive; 8D sandbox requires an explicit marker and blocks indexing/cutover.\n" );
