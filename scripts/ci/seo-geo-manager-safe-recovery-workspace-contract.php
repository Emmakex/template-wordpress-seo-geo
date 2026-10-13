<?php
/**
 * Static contract for Manager Safe Recovery / Readback integration.
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

if ( 1 !== preg_match( '/^ \* Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/m', $sources['main'], $header_match ) ) {
	fwrite( STDERR, "Safe Recovery Workspace could not resolve the Manager plugin header version.\n" );
	exit( 1 );
}
if ( 1 !== preg_match( "/SEO_GEO_MANAGER_VERSION',\\s*'([0-9]+\\.[0-9]+\\.[0-9]+)'/", $sources['main'], $constant_match ) ) {
	fwrite( STDERR, "Safe Recovery Workspace could not resolve SEO_GEO_MANAGER_VERSION.\n" );
	exit( 1 );
}

$manager_version = $header_match[1];
if ( $manager_version !== $constant_match[1] || version_compare( $manager_version, '0.3.66', '<' ) ) {
	fwrite( STDERR, "Safe Recovery Workspace requires matching Manager versions at or above 0.3.66.\n" );
	exit( 1 );
}

$requirements = array(
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
		"'theme-strategic-media-change' === \$operation_type && 'theme-strategic-media' === \$adapter",
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

$registry_contracts = array(
	"/'method'\\s*=>\\s*'GET'/"                              => 'type-specific Verify method GET',
	"/'method'\\s*=>\\s*'POST'/"                             => 'type-specific rollback method POST',
	"/'requires_environment_fingerprint'\\s*=>\\s*true/"     => 'environment fingerprint requirement',
	"/'generic_mutation_endpoint'\\s*=>\\s*false/"           => 'generic mutation disabled',
	"/'generic_rollback_endpoint'\\s*=>\\s*false/"           => 'generic rollback disabled',
	"/'private_operation_payload_returned'\\s*=>\\s*false/"  => 'private operation payload excluded',
);
foreach ( $registry_contracts as $pattern => $label ) {
	if ( 1 !== preg_match( $pattern, $sources['registry'] ) ) {
		fwrite( STDERR, "Safe Recovery registry is missing semantic contract: {$label}.\n" );
		exit( 1 );
	}
}

$controller_contracts = array(
	"/'generic_mutation_endpoint'\\s*=>\\s*false/"        => 'controller generic mutation disabled',
	"/'generic_rollback_endpoint'\\s*=>\\s*false/"        => 'controller generic rollback disabled',
	"/'private_fingerprints_excluded'\\s*=>\\s*true/"     => 'controller private fingerprints excluded',
	"/'environment_fingerprint_excluded'\\s*=>\\s*true/" => 'controller environment fingerprint excluded',
);
foreach ( $controller_contracts as $pattern => $label ) {
	if ( 1 !== preg_match( $pattern, $sources['controller'] ) ) {
		fwrite( STDERR, "Safe Recovery controller is missing semantic contract: {$label}.\n" );
		exit( 1 );
	}
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

$allowed_routes = array(
	'/content/seo/authority/changes/',
	'/links/contextual/changes/',
	'/navigation/changes/',
	'/content/featured-media/changes/',
	'/media/alt/changes/',
	'/theme/media-bindings/changes/',
);
foreach ( $allowed_routes as $allowed_route ) {
	if ( false === strpos( $sources['registry'], $allowed_route ) ) {
		fwrite( STDERR, "Safe Recovery allowlist is missing type-specific route family: {$allowed_route}\n" );
		exit( 1 );
	}
}

foreach ( $allowed_routes as $hardcoded_route ) {
	if ( false !== strpos( $sources['recovery'], $hardcoded_route ) ) {
		fwrite( STDERR, "Recovery UI must consume server-provided allowlisted paths instead of hardcoding mutation routes.\n" );
		exit( 1 );
	}
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
			'version'                      => $manager_version,
			'manifest_get_only'            => true,
			'allowlisted_families'          => count( $allowed_routes ),
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
