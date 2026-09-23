<?php
/**
 * Validate the Phase 9A/9B/9C/9D theme setup contract.
 *
 * @package SeoGeoTheme
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$setup_dir  = $root . '/packages/seo-geo-theme/inc/Setup';
$wizard_dir = $root . '/packages/seo-geo-theme/inc/Wizard';

$required = array(
	$root . '/packages/seo-geo-theme/inc/setup.php',
	$setup_dir . '/SetupConfigurationContract.php',
	$setup_dir . '/MigrationHandoffReader.php',
	$setup_dir . '/SetupCompatibilityDetector.php',
	$setup_dir . '/SetupPlanner.php',
	$setup_dir . '/PresetLanguageValidator.php',
	$setup_dir . '/EntityGeoValidator.php',
	$wizard_dir . '/SetupWizardCopy.php',
	$wizard_dir . '/SetupWizardPreview.php',
	$wizard_dir . '/AdminSetupWizard.php',
	$root . '/packages/seo-geo-theme/assets/admin/setup-wizard.css',
	$root . '/packages/seo-geo-theme/assets/admin/setup-wizard.js',
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
			fwrite( STDERR, 'Phase 9A/9B/9C read-only setup code contains forbidden primitive ' . $primitive . ' in ' . $path . PHP_EOL );
			exit( 1 );
		}
	}
}

$wizard_files = glob( $wizard_dir . '/*.php' ) ?: array();
$wizard_forbidden = array(
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
);

foreach ( $wizard_files as $path ) {
	$source = (string) file_get_contents( $path );
	foreach ( $wizard_forbidden as $primitive ) {
		if ( str_contains( $source, $primitive ) ) {
			fwrite( STDERR, 'Phase 9D wizard contains forbidden mutation primitive ' . $primitive . ' in ' . $path . PHP_EOL );
			exit( 1 );
		}
	}
}

$wizard_preview = (string) file_get_contents( $wizard_dir . '/SetupWizardPreview.php' );
foreach (
	array(
		"'theme-setup-wizard-preview'",
		'PresetLanguageValidator',
		'EntityGeoValidator',
		"'options_persisted'",
		"'pages_created'",
		"'plugins_mutated'",
		"'credentials_requested'",
		"'outbound_requests_made'",
	) as $guard
) {
	if ( ! str_contains( $wizard_preview, $guard ) ) {
		fwrite( STDERR, 'Phase 9D preview guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard_screen = (string) file_get_contents( $wizard_dir . '/AdminSetupWizard.php' );
foreach (
	array(
		'add_theme_page(',
		"current_user_can( 'manage_options' )",
		'check_admin_referer( self::NONCE_ACTION )',
		'wp_nonce_field( self::NONCE_ACTION )',
		"'seo_geo_preview_confirm'",
		"'seo_geo_validate'",
		"'seo-geo-setup-results'",
		"'aria-live'",
	) as $guard
) {
	if ( ! str_contains( $wizard_screen, $guard ) ) {
		fwrite( STDERR, 'Phase 9D admin wizard guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard_copy = (string) file_get_contents( $wizard_dir . '/SetupWizardCopy.php' );
foreach ( array( "'en' => array(", "'es' => array(", "'preview_confirm'", "'privacy_note'" ) as $guard ) {
	if ( ! str_contains( $wizard_copy, $guard ) ) {
		fwrite( STDERR, 'Phase 9D EN/ES copy guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard_css = (string) file_get_contents( $root . '/packages/seo-geo-theme/assets/admin/setup-wizard.css' );
foreach ( array( '@media (max-width: 782px)', 'grid-template-columns: 1fr' ) as $guard ) {
	if ( ! str_contains( $wizard_css, $guard ) ) {
		fwrite( STDERR, 'Phase 9D responsive CSS guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard_js = (string) file_get_contents( $root . '/packages/seo-geo-theme/assets/admin/setup-wizard.js' );
if ( ! str_contains( $wizard_js, "getElementById('seo-geo-setup-results')" ) || ! str_contains( $wizard_js, 'results.focus()' ) ) {
	fwrite( STDERR, 'Phase 9D focus-management guard is missing.' . PHP_EOL );
	exit( 1 );
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

$preset_language = (string) file_get_contents( $setup_dir . '/PresetLanguageValidator.php' );
foreach (
	array(
		"'preset-language-validation'",
		'seo_geo_theme_preset_ids()',
		'NativeLanguageConfiguration::from_array',
		"'prefix-routing-requires-multiple-languages'",
		"'translations_created'",
		"'routes_created'",
		"'provider_state_mutated'",
	) as $guard
) {
	if ( ! str_contains( $preset_language, $guard ) ) {
		fwrite( STDERR, 'Phase 9B preset/language guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$entity_geo = (string) file_get_contents( $setup_dir . '/EntityGeoValidator.php' );
foreach (
	array(
		"'entity-geo-validation'",
		'SchemaIdentityResolver::normalize_site_entity_type',
		'SchemaLocalBusinessResolver::supported_types',
		'normalize_address_candidate',
		'normalize_geo_candidate',
		'sanitize_configuration',
		"'visible_fact_gate_required'",
		"'schema_output_ready'",
		"'content_provenance'",
		"'entity_facts_inferred'",
		"'crawler_guarantees_made'",
	) as $guard
) {
	if ( ! str_contains( $entity_geo, $guard ) ) {
		fwrite( STDERR, 'Phase 9C entity/GEO guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$identity_authority = (string) file_get_contents( $root . '/packages/seo-geo-core/src/Schema/SchemaIdentityResolver.php' );
foreach ( array( 'public static function supported_site_entity_types(): array', 'public static function normalize_site_entity_type(' ) as $guard ) {
	if ( ! str_contains( $identity_authority, $guard ) ) {
		fwrite( STDERR, 'Phase 9C Schema identity authority guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$local_business_authority = (string) file_get_contents( $root . '/packages/seo-geo-core/src/Schema/SchemaLocalBusinessResolver.php' );
foreach (
	array(
		'public static function supported_types(): array',
		'public function normalize_address_candidate(',
		'public function normalize_geo_candidate(',
	) as $guard
) {
	if ( ! str_contains( $local_business_authority, $guard ) ) {
		fwrite( STDERR, 'Phase 9C LocalBusiness authority guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$language_config = (string) file_get_contents( $root . '/packages/seo-geo-core/src/Language/NativeLanguageConfiguration.php' );
foreach ( array( 'public static function from_array(', 'public function to_array(): array' ) as $guard ) {
	if ( ! str_contains( $language_config, $guard ) ) {
		fwrite( STDERR, 'Phase 9B native language authority guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$entity_geo = (string) file_get_contents( $setup_dir . '/EntityGeoValidator.php' );
foreach (
	array(
		"'entity-geo-validation'",
		'SchemaIdentityResolver::normalize_site_entity_type',
		'SchemaLocalBusinessResolver::supported_types',
		'normalize_address_candidate',
		'normalize_geo_candidate',
		'CrawlerPolicyResolver::OPTION_NAME',
		'LlmsTxtResolver::OPTION_NAME',
		'MarkdownAlternateResolver::OPTION_NAME',
		"'visible_fact_gate_required'",
		"'schema_output_ready'",
		"'entity_facts_inferred'",
		"'address_inferred'",
		"'coordinates_inferred'",
		"'ratings_reviews_inferred'",
		"'crawler_guarantees_made'",
	) as $guard
) {
	if ( ! str_contains( $entity_geo, $guard ) ) {
		fwrite( STDERR, 'Phase 9C entity/GEO guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$identity_resolver = (string) file_get_contents( $root . '/packages/seo-geo-core/src/Schema/SchemaIdentityResolver.php' );
foreach ( array( 'public static function supported_site_entity_types(): array', 'public static function normalize_site_entity_type(' ) as $guard ) {
	if ( ! str_contains( $identity_resolver, $guard ) ) {
		fwrite( STDERR, 'Phase 9C identity authority guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$local_business_resolver = (string) file_get_contents( $root . '/packages/seo-geo-core/src/Schema/SchemaLocalBusinessResolver.php' );
foreach ( array( 'public static function supported_types(): array', 'public function normalize_address_candidate(', 'public function normalize_geo_candidate(' ) as $guard ) {
	if ( ! str_contains( $local_business_resolver, $guard ) ) {
		fwrite( STDERR, 'Phase 9C LocalBusiness authority guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$bootstrap = (string) file_get_contents( $root . '/packages/seo-geo-theme/inc/setup.php' );
foreach (
	array(
		'function seo_geo_theme_setup_plan(): array',
		'function seo_geo_theme_validate_preset_language_setup( array $input ): array',
		'function seo_geo_theme_validate_entity_geo_setup( array $input ): array',
		'function seo_geo_theme_setup_wizard(): \\SeoGeo\\Theme\\Wizard\\AdminSetupWizard',
	) as $guard
) {
	if ( ! str_contains( $bootstrap, $guard ) ) {
		fwrite( STDERR, 'Phase 9 setup bootstrap guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

printf( "Phase 9A/9B/9C/9D setup static contract OK: planning/validation remain non-persistent and the EN/ES wizard is capability+nonce-gated, responsive and preview-only.\n" );
