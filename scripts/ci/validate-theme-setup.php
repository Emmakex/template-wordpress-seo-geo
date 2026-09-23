<?php
/**
 * Validate the Phase 9A theme setup foundation contract.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$setup_dir = $root . '/packages/seo-geo-theme/inc/Setup';

$required = array(
	$root . '/packages/seo-geo-theme/inc/setup.php',
	$setup_dir . '/SetupConfigurationContract.php',
	$setup_dir . '/MigrationHandoffReader.php',
	$setup_dir . '/SetupCompatibilityDetector.php',
	$setup_dir . '/SetupPlanner.php',
);

foreach ( $required as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, 'Phase 9A required path missing: ' . $path . PHP_EOL );
		exit( 1 );
	}
}

$php_files = glob( $setup_dir . '/*.php' ) ?: array();
$forbidden = array(
	'add_option',
	'update_option',
	'delete_option',
	'wp_insert_post',
	'wp_update_post',
	'wp_delete_post',
	'activate_plugin',
	'deactivate_plugins',
	'delete_plugins',
	'switch_theme',
	'wp_remote_post',
	'wp_schedule_event',
	'wp_schedule_single_event',
	'$_POST',
	'admin_post_',
);

foreach ( $php_files as $path ) {
	$source = (string) file_get_contents( $path );
	foreach ( $forbidden as $primitive ) {
		if ( str_contains( $source, $primitive ) ) {
			fwrite( STDERR, 'Phase 9A read-only setup code contains forbidden primitive ' . $primitive . ' in ' . $path . PHP_EOL );
			exit( 1 );
		}
	}
}

$contract = (string) file_get_contents( $setup_dir . '/SetupConfigurationContract.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_theme_setup_v1';",
		'public const SCHEMA_VERSION = 1;',
		"'seo_geo_plugin_required'",
		"'migration_bridge_required'",
		"'external_credentials_stored'",
	) as $guard
) {
	if ( ! str_contains( $contract, $guard ) ) {
		fwrite( STDERR, 'Phase 9A configuration contract guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$handoff = (string) file_get_contents( $setup_dir . '/MigrationHandoffReader.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_migration_report_v1';",
		"'migration-report'",
		"'ready_for_handoff'",
		"'report_is_runtime_dependency'",
		"'migration-bridge-handoff-v1'",
	) as $guard
) {
	if ( ! str_contains( $handoff, $guard ) ) {
		fwrite( STDERR, 'Phase 9A migration handoff guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$planner = (string) file_get_contents( $setup_dir . '/SetupPlanner.php' );
foreach (
	array(
		"'theme-setup-plan-read-only'",
		'seo_geo_theme_preset_ids()',
		'NativeLanguageConfiguration::from_wordpress()',
		"'setup_mutations_performed'",
		"'pages_created'",
		"'plugins_installed'",
		"'plugins_activated'",
		"'plugins_deactivated'",
		"'migration_bridge_loaded'",
	) as $guard
) {
	if ( ! str_contains( $planner, $guard ) ) {
		fwrite( STDERR, 'Phase 9A setup planner guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$bootstrap = (string) file_get_contents( $root . '/packages/seo-geo-theme/inc/setup.php' );
if ( ! str_contains( $bootstrap, 'function seo_geo_theme_setup_plan(): array' ) ) {
	fwrite( STDERR, 'Phase 9A setup-plan public function is missing.' . PHP_EOL );
	exit( 1 );
}

printf( "Phase 9A setup foundation static contract OK: versioned, handoff-aware, preset/language-authoritative and read-only.\n" );
