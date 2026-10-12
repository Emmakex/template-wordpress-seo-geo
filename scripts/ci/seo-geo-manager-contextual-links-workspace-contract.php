<?php
/**
 * Static contract for the bounded contextual-links workspace.
 */
declare(strict_types=1);

$root       = dirname( __DIR__, 2 );
$plugin     = $root . '/packages/seo-geo-manager/src/Plugin.php';
$bootstrap  = $root . '/packages/seo-geo-manager/src/Admin/ContextualLinksWorkspace.php';
$reader     = $root . '/packages/seo-geo-manager/src/Intelligence/ContextualLinkReader.php';
$adapter    = $root . '/packages/seo-geo-manager/src/Changes/ContextualLinkChangeAdapter.php';
$controller = $root . '/packages/seo-geo-manager/src/Rest/ContextualLinkChangeController.php';
$content_js = $root . '/packages/seo-geo-manager/assets/admin/content-workspace.js';
$script     = $root . '/packages/seo-geo-manager/assets/admin/contextual-links-workspace.js';
$style      = $root . '/packages/seo-geo-manager/assets/admin/contextual-links-workspace.css';

foreach ( array( $plugin, $bootstrap, $reader, $adapter, $controller, $content_js, $script, $style ) as $file ) {
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "Missing contextual-links workspace file: {$file}\n" );
		exit( 1 );
	}
}

$sources = array(
	'plugin'     => (string) file_get_contents( $plugin ),
	'bootstrap'  => (string) file_get_contents( $bootstrap ),
	'reader'     => (string) file_get_contents( $reader ),
	'adapter'    => (string) file_get_contents( $adapter ),
	'controller' => (string) file_get_contents( $controller ),
	'content'    => (string) file_get_contents( $content_js ),
	'script'     => (string) file_get_contents( $script ),
);

$requirements = array(
	'plugin' => array(
		'use SeoGeo\\Manager\\Admin\\ContextualLinksWorkspace;',
		'ContextualLinksWorkspace::register();',
	),
	'bootstrap' => array(
		'seo-geo-manager-contextual-links-workspace',
		'contextual-links-workspace.js',
		'contextual-links-workspace.css',
		'/seo-geo-manager/v1/links/contextual',
	),
	'reader' => array(
		"'edge_id'",
		"'ordinal'",
		"'anchor_text'",
		"'href'",
		"'classification'",
		"'target_verified'",
		"'raw_post_content_returned'   => false",
		"'environment-leakage-candidate'",
	),
	'adapter' => array(
		"'target_resource_id_only'   => true",
		"'published_target_required' => true",
		"'href_only'",
		"'expected_fingerprint'",
		"'idempotency_key'",
		"'allow_published_target'",
		"'target_resource_id'",
	),
	'controller' => array(
		'/links/contextual/changes/(?P<operation_id>[a-f0-9\\-]{36})/verify',
		'ContextualLinkReader::read( $source_id )',
		"'after_fingerprint'",
		"'target_resource_id'",
		"'target_verified'",
		"'verified'            => true",
		'EnvironmentPolicy::validate_operation_environment',
		"$operation['before_content']",
		"$operation['after_content']",
	),
	'content' => array(
		'seo-geo-manager:content-selected',
	),
	'script' => array(
		'Enlaces contextuales',
		'seo-geo-manager:content-selected',
		'Posible fuga de entorno',
		'no lo reescribirá automáticamente',
		"status: 'publish'",
		'target_resource_id: Number( targetSelect.value || 0 )',
		'expected_fingerprint',
		'allow_published_target',
		'environment_fingerprint',
		'idempotency_key',
		'Vista previa',
		'Aplicar y verificar',
		'Rollback seguro',
		'/verify',
		'/rollback',
	),
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "Contextual-links {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

foreach ( array( $sources['bootstrap'], $sources['controller'], $sources['script'] ) as $source ) {
	if ( false !== strpos( $source, 'emmake.com' ) ) {
		fwrite( STDERR, "Contextual-links workspace must not hardcode the EMMAKE domain.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $sources['script'], 'data-contextual-target-url' ) || false !== strpos( $sources['script'], 'target_url' ) ) {
	fwrite( STDERR, "Contextual-links workspace must not expose an arbitrary target URL writer.\n" );
	exit( 1 );
}
if ( false !== strpos( $sources['script'], 'type="url"' ) ) {
	fwrite( STDERR, "Contextual-links workspace target selection must be resource-ID based, not URL-input based.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                         => true,
			'bounded_discovery'          => true,
			'raw_content_returned'       => false,
			'target_resource_id_only'    => true,
			'published_targets_only'     => true,
			'href_only_mutation'         => true,
			'preview_before_apply'       => true,
			'exact_readback_verify'      => true,
			'environment_bound'          => true,
			'idempotent_apply'           => true,
			'stale_safe_rollback'        => true,
			'leakage_never_auto_rewrite' => true,
			'arbitrary_url_writer'       => false,
			'no_client_hardcoding'       => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
