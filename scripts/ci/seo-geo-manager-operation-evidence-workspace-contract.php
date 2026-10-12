<?php
/**
 * Static contract for Manager 0.3.65 Operation Evidence Workspace.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'main'       => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
	'bootstrap'  => $root . '/packages/seo-geo-manager/src/Admin/OperationHistory.php',
	'controller' => $root . '/packages/seo-geo-manager/src/Rest/OperationHistoryController.php',
	'store'      => $root . '/packages/seo-geo-manager/src/Changes/OperationStore.php',
	'script'     => $root . '/packages/seo-geo-manager/assets/admin/operation-history.js',
	'style'      => $root . '/packages/seo-geo-manager/assets/admin/operation-evidence.css',
);

foreach ( $paths as $label => $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Operation Evidence Workspace contract missing {$label}: {$path}\n" );
		exit( 1 );
	}
}

$sources = array();
foreach ( $paths as $label => $path ) {
	$sources[ $label ] = (string) file_get_contents( $path );
}

$requirements = array(
	'main' => array(
		'Version: 0.3.65',
		"SEO_GEO_MANAGER_VERSION', '0.3.65'",
	),
	'bootstrap' => array(
		'operation-evidence.css',
		'operation-history.js',
		"'operationsPath' => '/seo-geo-manager/v1/operations?per_page=50'",
		'wp_add_inline_script',
	),
	'controller' => array(
		"'/operations'",
		"'methods'             => 'GET'",
		"'mode'         => 'operation-history-summary'",
		"'privacy_safe' => true",
		"'bounded_index'",
		"'full_operation_payloads_excluded'",
		"'content_values_excluded'",
		"'payload_hashes_excluded'",
		"'environment_fingerprints_excluded'",
		'current_user_can( \'edit_posts\' )',
		'current_user_can( \'edit_post\', $target_id )',
	),
	'store' => array(
		'private const HISTORY_LIMIT      = 100;',
		'public static function recent_summaries',
		'private static function summary',
		"'rollback_state'",
		"'rendered_verification_status'",
		"'environment_type'",
	),
	'script' => array(
		'Evidencia de operaciones',
		"'[data-seo-geo-media-alt-workspace]'",
		"'[data-seo-geo-featured-media-workspace]'",
		"'[data-seo-geo-navigation-workspace]'",
		"'seo-authority-change': 'Autoridad SEO'",
		"'contextual-link-change': 'Enlace contextual'",
		"'featured-media-change': 'Imagen destacada'",
		"'media-alt-change': 'ALT de imagen'",
		'Operaciones visibles',
		'Rollback protegido',
		'Requieren atención',
		'Todos los tipos',
		'Todos los estados',
		'Estado persistido · Verify no indexado',
		"'passed' === rendered",
		'response.privacy_safe',
		"'operation-history-summary' !== response.mode",
		'payloads, valores anteriores y fingerprints fuera del navegador',
	),
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "Operation Evidence Workspace {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

if ( false !== strpos( $sources['script'], '.seo-geo-manager-admin__technical-body' ) ) {
	fwrite( STDERR, "Operation Evidence Workspace must not remain hidden inside technical details.\n" );
	exit( 1 );
}

foreach ( array( "method: 'POST'", 'method: "POST"', '/rollback', '/apply', '/preview' ) as $forbidden_mutation ) {
	if ( false !== strpos( $sources['script'], $forbidden_mutation ) ) {
		fwrite( STDERR, "Operation Evidence Workspace must remain read-only; found {$forbidden_mutation}.\n" );
		exit( 1 );
	}
}

if ( preg_match( '/WP_REST_Server::CREATABLE|WP_REST_Server::EDITABLE|\'POST\'/', $sources['controller'] ) ) {
	fwrite( STDERR, "Operation history REST controller must remain read-only.\n" );
	exit( 1 );
}

$summary_start = strpos( $sources['store'], 'private static function summary' );
$summary_end   = strpos( $sources['store'], 'private static function idempotency_option_name', $summary_start ?: 0 );
if ( false === $summary_start || false === $summary_end || $summary_end <= $summary_start ) {
	fwrite( STDERR, "Operation Evidence Workspace could not inspect the privacy-bounded summary builder.\n" );
	exit( 1 );
}
$summary_source = substr( $sources['store'], $summary_start, $summary_end - $summary_start );
foreach ( array( 'before_fields', 'after_fields', 'before_content', 'after_content', 'payload_hash', 'environment_fingerprint', 'before_fingerprint', 'after_fingerprint' ) as $private_key ) {
	if ( false !== strpos( $summary_source, "'{$private_key}'" ) ) {
		fwrite( STDERR, "Operation history summary exposes forbidden private key: {$private_key}\n" );
		exit( 1 );
	}
}

if ( false === strpos( $sources['script'], "return { label: 'Frontend verificado', className: 'is-success' };" ) ) {
	fwrite( STDERR, "Operation Evidence Workspace must only label persisted rendered status 'passed' as verified.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                              => true,
			'version'                         => '0.3.65',
			'max_visible_rows'                => 50,
			'private_history_limit'           => 100,
			'main_workflow_surface'           => true,
			'filters'                         => true,
			'operational_metrics'             => true,
			'read_only'                       => true,
			'generic_rollback'                => false,
			'generic_mutation'                => false,
			'privacy_safe_summary'            => true,
			'private_payloads_exposed'        => false,
			'private_fingerprints_exposed'    => false,
			'verify_success_inferred'         => false,
			'persisted_rendered_pass_honored' => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
