<?php
/**
 * Static contract for the provider-neutral SEO authority workspace.
 */
declare(strict_types=1);

$root       = dirname( __DIR__, 2 );
$plugin     = $root . '/packages/seo-geo-manager/src/Plugin.php';
$bootstrap  = $root . '/packages/seo-geo-manager/src/Admin/SeoAuthorityWorkspace.php';
$controller = $root . '/packages/seo-geo-manager/src/Rest/SeoAuthorityController.php';
$adapter    = $root . '/packages/seo-geo-manager/src/Changes/SeoAuthorityChangeAdapter.php';
$content_js = $root . '/packages/seo-geo-manager/assets/admin/content-workspace.js';
$script     = $root . '/packages/seo-geo-manager/assets/admin/seo-authority-workspace.js';
$style      = $root . '/packages/seo-geo-manager/assets/admin/seo-authority-workspace.css';

foreach ( array( $plugin, $bootstrap, $controller, $adapter, $content_js, $script, $style ) as $file ) {
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "Missing SEO authority workspace file: {$file}\n" );
		exit( 1 );
	}
}

$plugin_source     = (string) file_get_contents( $plugin );
$bootstrap_source  = (string) file_get_contents( $bootstrap );
$controller_source = (string) file_get_contents( $controller );
$adapter_source    = (string) file_get_contents( $adapter );
$content_source    = (string) file_get_contents( $content_js );
$script_source     = (string) file_get_contents( $script );

$requirements = array(
	'plugin' => array(
		'use SeoGeo\\Manager\\Admin\\SeoAuthorityWorkspace;',
		'use SeoGeo\\Manager\\Rest\\SeoAuthorityController;',
		'SeoAuthorityWorkspace::register();',
		'SeoAuthorityController::register_routes();',
	),
	'bootstrap' => array(
		'seo-geo-manager-seo-authority-workspace',
		'seo-authority-workspace.js',
		'seo-authority-workspace.css',
		'seo-geo-manager-content-workspace',
	),
	'controller' => array(
		'/content/(?P<resource_id>\\d+)/seo/authority',
		'/changes/preview',
		'/changes/apply',
		'/verify',
		'/rollback',
		'SeoAuthorityChangeAdapter::inspect',
		'SeoAuthorityChangeAdapter::preview',
		'SeoAuthorityChangeAdapter::apply',
		'SeoAuthorityChangeAdapter::verify',
		'SeoAuthorityChangeAdapter::rollback',
		'EnvironmentPolicy::validate_payload',
		'EnvironmentPolicy::validate_operation_environment',
	),
	'adapter' => array(
		'public static function inspect',
		'NativeSeoMetadataChangeAdapter::inspect',
		'YoastSeoMetadataChangeAdapter::inspect',
		'seo_authority',
	),
	'content' => array(
		'seo-geo-manager:content-selected',
		'CustomEvent',
	),
	'script' => array(
		'Autoridad SEO',
		'No se selecciona proveedor manualmente',
		'seo-geo-manager:content-selected',
		'theme-native',
		'yoast',
		'data-seo-field="title"',
		'data-seo-field="description"',
		'data-seo-field="canonical"',
		'data-seo-field="indexability"',
		'data-seo-field="noindex"',
		'data-seo-field="nofollow"',
		'data-seo-field="schema_page_type"',
		'data-seo-field="schema_article_type"',
		'expected_fingerprint',
		'allow_published_target',
		'environment_fingerprint',
		'idempotency_key',
		'Vista previa SEO',
		'Aplicar y verificar',
		'Rollback seguro',
		'/verify',
		'/rollback',
	),
);

$sources = array(
	'plugin' => $plugin_source,
	'bootstrap' => $bootstrap_source,
	'controller' => $controller_source,
	'adapter' => $adapter_source,
	'content' => $content_source,
	'script' => $script_source,
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "SEO authority {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

foreach ( array( $bootstrap_source, $controller_source, $adapter_source, $script_source ) as $source ) {
	if ( false !== strpos( $source, 'emmake.com' ) ) {
		fwrite( STDERR, "SEO authority workspace must not hardcode the EMMAKE domain.\n" );
		exit( 1 );
	}
	if ( false !== strpos( $source, '_yoast_wpseo_' ) ) {
		fwrite( STDERR, "SEO authority workspace must not write or reference Yoast private metadata.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $script_source, 'data-seo-provider' ) || false !== strpos( $script_source, 'name="seo-provider"' ) ) {
	fwrite( STDERR, "SEO authority workspace must not expose a provider selector.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                         => true,
			'provider_neutral'           => true,
			'authority_inspection'       => true,
			'content_selection_handoff'  => true,
			'native_fields'              => array( 'title', 'description', 'canonical', 'indexability' ),
			'yoast_public_fields'        => array( 'canonical', 'noindex', 'nofollow', 'schema_page_type', 'schema_article_type' ),
			'preview_apply_verify'       => true,
			'stale_safe_rollback'        => true,
			'published_ack'              => true,
			'environment_bound'          => true,
			'idempotent_apply'           => true,
			'private_provider_meta'      => false,
			'provider_selector_exposed'  => false,
			'no_client_hardcoding'       => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
