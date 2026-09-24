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
	glob( MIGRATION_BRIDGE_DIR . '/src/Review/*.php' ) ?: array()
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
		"'requires_distinct_origin'       => true",
		"'requires_marker'                => SandboxGuard::MARKER",
		"'requires_outbound_marker'       => SandboxGuard::OUTBOUND_SAFE_MARKER",
		"'requires_backup_marker'         => SandboxGuard::BACKUPS_READY_MARKER",
		"'production_mutation_allowed'    => false",
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
