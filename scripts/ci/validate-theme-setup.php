<?php
/**
 * Validate the Phase 9A/9B/9C/9D/9E theme setup contract.
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
	$setup_dir . '/SetupOptionWriterInterface.php',
	$setup_dir . '/WordPressSetupOptionWriter.php',
	$setup_dir . '/SetupReportStore.php',
	$setup_dir . '/SetupRewriteMaintenance.php',
	$setup_dir . '/SetupExecutor.php',
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

$writer_path = $setup_dir . '/WordPressSetupOptionWriter.php';

foreach ( $php_files as $path ) {
	if ( $writer_path === $path ) {
		continue;
	}

	$source = (string) file_get_contents( $path );
	foreach ( $forbidden as $primitive ) {
		if ( str_contains( $source, $primitive ) ) {
			fwrite( STDERR, 'Phase 9A/9B/9C/9E non-writer setup code contains forbidden primitive ' . $primitive . ' in ' . $path . PHP_EOL );
			exit( 1 );
		}
	}
}

$writer_source = (string) file_get_contents( $writer_path );
foreach ( array( 'update_option(', 'delete_option(', "'exists'", "'value'" ) as $guard ) {
	if ( ! str_contains( $writer_source, $guard ) ) {
		fwrite( STDERR, 'Phase 9E option-writer guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}
foreach ( array( 'add_option(', 'wp_insert_post', 'activate_plugin', 'deactivate_plugins', 'wp_remote_post', '$_POST' ) as $forbidden_writer_primitive ) {
	if ( str_contains( $writer_source, $forbidden_writer_primitive ) ) {
		fwrite( STDERR, 'Phase 9E writer exceeds its option-only mutation boundary: ' . $forbidden_writer_primitive . PHP_EOL );
		exit( 1 );
	}
}

$rewrite_maintenance = (string) file_get_contents( $setup_dir . '/SetupRewriteMaintenance.php' );
foreach (
	array(
		"public const OPTION_NAME = 'seo_geo_theme_setup_rewrite_flush_v1';",
		"add_action( 'init'",
		'flush_rewrite_rules( false )',
		'$this->writer->delete( self::OPTION_NAME )',
	) as $guard
) {
	if ( ! str_contains( $rewrite_maintenance, $guard ) ) {
		fwrite( STDERR, 'Phase 9E rewrite-maintenance guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}
foreach ( array( 'update_option(', 'delete_option(', 'add_option(', 'wp_insert_post', 'activate_plugin', 'deactivate_plugins', 'wp_remote_post', '$_POST' ) as $forbidden_maintenance_primitive ) {
	if ( str_contains( $rewrite_maintenance, $forbidden_maintenance_primitive ) ) {
		fwrite( STDERR, 'Phase 9E rewrite maintenance exceeds its writer-only boundary: ' . $forbidden_maintenance_primitive . PHP_EOL );
		exit( 1 );
	}
}

$executor = (string) file_get_contents( $setup_dir . '/SetupExecutor.php' );
foreach (
	array(
		"'theme-setup-execution'",
		'SetupWizardPreview',
		'SetupConfigurationContract::OPTION_NAME',
		'SetupReportStore::OPTION_NAME',
		'NativeLanguageConfiguration::OPTION_NAME',
		'SchemaIdentityResolver::OPTION_NAME',
		'SchemaLocalBusinessResolver::OPTION_NAME',
		'CrawlerPolicyResolver::OPTION_NAME',
		'LlmsTxtResolver::OPTION_NAME',
		'MarkdownAlternateResolver::OPTION_NAME',
		"'setup-capability-required'",
		"'apply-confirmation-required'",
		"'migration-handoff-blocking-review'",
		'SetupRewriteMaintenance::OPTION_NAME',
		"'rewrite_flush_pending'",
		"'setup-write-failed:'",
		"'setup-rollback-failed:'",
		"'migration_bridge_loaded'",
		"'local_business_facts_in_report'",
	) as $guard
) {
	if ( ! str_contains( $executor, $guard ) ) {
		fwrite( STDERR, 'Phase 9E executor guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$report_store = (string) file_get_contents( $setup_dir . '/SetupReportStore.php' );
foreach ( array( "public const OPTION_NAME = 'seo_geo_theme_setup_report_v1';", "'theme-setup-report'", "'configuration_sha256'", "'report_sha256'" ) as $guard ) {
	if ( ! str_contains( $report_store, $guard ) ) {
		fwrite( STDERR, 'Phase 9E report-store guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
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
		'wp_verify_nonce(',
		'wp_nonce_field( self::NONCE_ACTION )',
		"'seo_geo_preview_confirm'",
		"'seo_geo_apply_confirm'",
		"'seo_geo_setup_action'",
		'SetupExecutor',
		'seo-geo-setup-results',
		'aria-live',
	) as $guard
) {
	if ( ! str_contains( $wizard_screen, $guard ) ) {
		fwrite( STDERR, 'Phase 9D admin wizard guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

$wizard_copy = (string) file_get_contents( $wizard_dir . '/SetupWizardCopy.php' );
foreach ( array( "'en' => array(", "'es' => array(", "'preview_confirm'", "'apply_confirm'", "'result_applied'", "'privacy_note'" ) as $guard ) {
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
if ( ! str_contains( $wizard_js, 'seo-geo-setup-results' ) || ! str_contains( $wizard_js, 'results.focus()' ) ) {
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
		'function seo_geo_theme_apply_setup( array $input, bool $confirmed ): array',
		'function seo_geo_theme_setup_report(): ?array',
		'function seo_geo_theme_setup_wizard(): \\SeoGeo\\Theme\\Wizard\\AdminSetupWizard',
		'SetupRewriteMaintenance() )->register()',
	) as $guard
) {
	if ( ! str_contains( $bootstrap, $guard ) ) {
		fwrite( STDERR, 'Phase 9 setup bootstrap guard missing: ' . $guard . PHP_EOL );
		exit( 1 );
	}
}

printf( "Phase 9A/9B/9C/9D/9E setup static contract OK: planning/validation remain authority-driven; option mutation is isolated; setup execution is rollback-capable/idempotent; the EN/ES wizard remains capability+nonce-gated.\n" );
