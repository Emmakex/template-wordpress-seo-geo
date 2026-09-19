<?php
/**
 * Validate the Phase 8A Migration Bridge safety contract.
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
	MIGRATION_BRIDGE_DIR . '/src/Builders/BuilderDetectorInterface.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/NativeBlocksDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/ElementorDetector.php',
	MIGRATION_BRIDGE_DIR . '/src/Builders/DiviDetector.php',
);

foreach ( $required as $path ) {
	if ( ! is_file( $path ) ) {
		fail_migration_bridge( 'missing-file', 'Phase 8A Migration Bridge file is missing.', $path, 'file exists', 'missing' );
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

$forbidden_calls = array(
	'add_option',
	'delete_option',
	'update_option',
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

$php_files = array_merge(
	array( MIGRATION_BRIDGE_DIR . '/seo-geo-migration-bridge.php' ),
	glob( MIGRATION_BRIDGE_DIR . '/src/*.php' ) ?: array(),
	glob( MIGRATION_BRIDGE_DIR . '/src/Builders/*.php' ) ?: array()
);

foreach ( $php_files as $path ) {
	$source = (string) file_get_contents( $path );

	foreach ( $forbidden_calls as $function_name ) {
		if ( 1 === preg_match( '/\\b' . preg_quote( $function_name, '/' ) . '\\s*\\(/', $source ) ) {
			fail_migration_bridge( 'mutation-api', 'Phase 8A analyzer contains a forbidden WordPress mutation call.', $path, 'read-only API surface', $function_name );
		}
	}

	if ( str_contains( $source, '->query(' ) || str_contains( $source, '->insert(' ) || str_contains( $source, '->update(' ) || str_contains( $source, '->delete(' ) ) {
		fail_migration_bridge( 'database-mutation', 'Phase 8A analyzer contains a direct database mutation/query primitive.', $path, 'no direct database writes/queries', 'database method found' );
	}
}

$site_analyzer = (string) file_get_contents( MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php' );
foreach (
	array(
		"'mode'           => 'read-only'",
		"'mutations_performed'   => false",
		"'content_scan_performed' => false",
		"'credentials_collected' => false",
		"'option_values_exported' => false",
	) as $guard
) {
	if ( ! str_contains( $site_analyzer, $guard ) ) {
		fail_migration_bridge( 'safety-report', 'Migration report is missing a required read-only safety flag.', MIGRATION_BRIDGE_DIR . '/src/SiteAnalyzer.php', $guard, 'missing' );
	}
}

printf( "Migration Bridge static contract OK: read-only package boundary and mutation guard pass.\n" );
