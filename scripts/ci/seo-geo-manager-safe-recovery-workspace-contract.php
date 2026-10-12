<?php
/**
 * Static contract for Manager 0.3.66 Safe Recovery / Readback integration.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'main'       => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
	'bootstrap'  => $root . '/packages/seo-geo-manager/src/Admin/OperationHistory.php',
	'controller' => $root . '/packages/seo-geo-manager/src/Rest/OperationHistoryController.php',
	'registry'   => $root . '/packages/seo-geo-manager/src/Support/OperationRecoveryRegistry.php',
	'evidence'   => $root . '/packages/seo-geo-manager/assets/admin/operation-history.js',
	'recovery'   => $root . '/packages/seo-geo-manager/assets/admin/operation-recovery.js',
	'acceptance' => $root . '/scripts/ci/seo-geo-manager-operation-history-acceptance.php',
);

foreach ( $paths as $label => $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Safe Recovery Workspace contract missing {$label}: {$path}\n" );
		exit( 1 );
	}
}

$sources = array();
foreach ( $paths as $label => $path ) {
	$sources[ $label ] = (string) file_get_contents( $path );
}

$requirements = array(
	'main' => array(
		'Version: 0.3.66',
		"SEO_GEO_MANAGER_VERSION', '0.3.66'",
	),
	'bootstrap' => array(
		'operation-recovery.css',
		'operation-recovery.js',
		"'operationsBase'  => '/seo-geo-manager/v1/operations'",
		"'environmentPath' => '/seo-geo-manager/v1/site/snapshot'",
	),
	'controller' => array(
		"'/operations/(?P<operation_id>[a-f0-9\\-]{36})/recovery'",
		"'methods'             => 'GET'",
		'OperationRecoveryRegistry::describe( $operation )',
		"'mode'         => 'operation-recovery-manifest'",
		"'privacy_safe' => true",
		"'generic_mutation_endpoint'        => false",
		"'generic_rollback_endpoint'        => false",
		"'private_fingerprints_excluded'",
		"'environment_fingerprint_excluded'",
		'private static function visible_row',
	),
	'registry' => array(
		"'seo-authority-change' === \$operation_type",
		"array( 'seo-geo-theme-native', 'yoast-abilities' )",
		"'contextual-link-change' === \$operation_type && 'contextual-link' === \$adapter",
		"'navigation-change' === \$operation_type && 'navigation-menu' === \$adapter",
		"'featured-media-change' === \$operation_type && 'featured-media' === \$adapter",
		"'media-alt-change' === \$operation_type && 'media-alt' === \$adapter",
		"'method'    => 'GET'",
		"'requires_environment_fingerprint' => true",
		"'generic_mutation_endpoint'        => false",
		"'generic_rollback_endpoint'        => false",
	),
	'evidence' => array(
		'Evidencia de operaciones',
		'refresh.dataset.operationEvidenceRefresh',
	),
	'recovery' => array(
		'Revisar recuperación',
		'operation-recovery-manifest',
		'manifest.recovery?.verify',
		'manifest.recovery?.rollback',
		"'GET' !== descriptor.method",
		"'POST' !== descriptor.method",
		"method: 'POST'",
		'environment_fingerprint: environmentFingerprint',
		'Verificado ahora contra el estado actual.',
		'Rollback específico completado y registrado.',
		'Acciones delegadas a endpoints específicos del adaptador.',
	),
	'acceptance' => array(
		'operation-recovery-manifest',
		'Media ALT operation was not allowlisted for recovery.',
		'Recovery manifest Verify route did not verify the applied operation.',
		'Recovery manifest rollback route did not restore the operation.',
		'Unsupported site-wide operation was incorrectly mapped to recovery endpoints.',
		'Editor could inspect recovery for a hidden site-wide operation.',
	),
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "Safe Recovery Workspace {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

if ( 1 !== preg_match( "/'method'\\s*=>\\s*'POST'/", $sources['registry'] ) ) {
	fwrite( STDERR, "Safe Recovery registry does not declare the type-specific rollback method as POST.\n" );
	exit( 1 );
}
if ( 1 !== preg_match( "/'private_operation_payload_returned'\\s*=>\\s*false/", $sources['registry'] ) ) {
	fwrite( STDERR, "Safe Recovery registry does not preserve the private-payload exclusion policy.\n" );
	exit( 1 );
}

if ( preg_match( '/WP_REST_Server::CREATABLE|WP_REST_Server::EDITABLE|\'POST\'/', $sources['controller'] ) ) {
	fwrite( STDERR, "Operation recovery manifest controller must remain GET-only.\n" );
	exit( 1 );
}

foreach ( array( '/operations/(?P<operation_id>[a-f0-9\\-]{36})/rollback', '/operations/(?P<operation_id>[a-f0-9\\-]{36})/apply', '/operations/(?P<operation_id>[a-f0-9\\-]{36})/verify' ) as $generic_route ) {
	if ( false !== strpos( $sources['controller'], $generic_route ) ) {
		fwrite( STDERR, "Safe Recovery must not register generic operation action route: {$generic_route}\n" );
		exit( 1 );
	}
}

foreach ( array( 'shell_exec(', 'exec(', 'system(', 'passthru(', 'eval(' ) as $forbidden_executor ) {
	if ( false !== strpos( $sources['registry'], $forbidden_executor ) || false !== strpos( $sources['controller'], $forbidden_executor ) ) {
		fwrite( STDERR, "Safe Recovery contains forbidden generic execution primitive: {$forbidden_executor}\n" );
		exit( 1 );
	}
}

foreach ( array( '/content/seo/authority/changes/', '/links/contextual/changes/', '/navigation/changes/', '/content/featured-media/changes/', '/media/alt/changes/' ) as $allowed_route ) {
	if ( false === strpos( $sources['registry'], $allowed_route ) ) {
		fwrite( STDERR, "Safe Recovery allowlist is missing type-specific route family: {$allowed_route}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $sources['recovery'], '/content/seo/authority/changes/' ) ||
	false !== strpos( $sources['recovery'], '/links/contextual/changes/' ) ||
	false !== strpos( $sources['recovery'], '/navigation/changes/' ) ||
	false !== strpos( $sources['recovery'], '/content/featured-media/changes/' ) ||
	false !== strpos( $sources['recovery'], '/media/alt/changes/' ) ) {
	fwrite( STDERR, "Recovery UI must consume server-provided allowlisted paths instead of hardcoding mutation routes.\n" );
	exit( 1 );
}

if ( false === strpos( $sources['recovery'], 'path: String( descriptor.path )' ) ) {
	fwrite( STDERR, "Recovery UI does not delegate actions through the server-provided descriptor path.\n" );
	exit( 1 );
}

if ( false !== strpos( $sources['evidence'], "method: 'POST'" ) || false !== strpos( $sources['evidence'], '/rollback' ) ) {
	fwrite( STDERR, "Operation Evidence core must remain read-only; recovery mutations belong only to the separate recovery module.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                           => true,
			'version'                      => '0.3.66',
			'manifest_get_only'            => true,
			'allowlisted_families'          => 5,
			'type_specific_verify'          => true,
			'type_specific_rollback'        => true,
			'generic_operation_mutation'    => false,
			'generic_operation_rollback'    => false,
			'private_payload_returned'      => false,
			'private_fingerprints_returned' => false,
			'environment_bound_rollback'    => true,
			'unsupported_routes_exposed'    => false,
			'evidence_core_read_only'       => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
