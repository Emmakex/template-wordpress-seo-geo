<?php
/**
 * Static contract for Manager Featured Media Workspace introduced in 0.3.63.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'main'       => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
	'plugin'     => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'bootstrap'  => $root . '/packages/seo-geo-manager/src/Admin/FeaturedMediaWorkspace.php',
	'reader'     => $root . '/packages/seo-geo-manager/src/Intelligence/FeaturedMediaReader.php',
	'media'      => $root . '/packages/seo-geo-manager/src/Intelligence/MediaReferenceReader.php',
	'adapter'    => $root . '/packages/seo-geo-manager/src/Changes/FeaturedMediaChangeAdapter.php',
	'controller' => $root . '/packages/seo-geo-manager/src/Rest/FeaturedMediaChangeController.php',
	'script'     => $root . '/packages/seo-geo-manager/assets/admin/featured-media-workspace.js',
	'style'      => $root . '/packages/seo-geo-manager/assets/admin/featured-media-workspace.css',
);

foreach ( $paths as $label => $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Featured Media Workspace contract missing {$label}: {$path}\n" );
		exit( 1 );
	}
}

$sources = array();
foreach ( $paths as $label => $path ) {
	$sources[ $label ] = (string) file_get_contents( $path );
}

$requirements = array(
	'plugin' => array(
		'use SeoGeo\\Manager\\Admin\\FeaturedMediaWorkspace;',
		'FeaturedMediaWorkspace::register();',
	),
	'bootstrap' => array(
		'seo-geo-manager-featured-media-workspace',
		'featured-media-workspace.js',
		'featured-media-workspace.css',
		"'contentPath' => '/seo-geo-manager/v1/content'",
		"'mediaPath'   => '/seo-geo-manager/v1/media'",
	),
	'reader' => array(
		"'featured_media_id'",
		"'expected_fingerprint_source'",
		"'raw_attachment_metadata'     => false",
		"'binary_mutation_supported'   => false",
		"'content_mutation_supported'  => false",
	),
	'media' => array(
		'private const MAX_MEDIA',
		"'mime_type'",
		"'alt'",
		"'dimensions'",
		"'fingerprint'",
		"'raw_attachment_metadata_returned' => false",
		"'exif_returned'",
	),
	'adapter' => array(
		"private const ACTION_REPLACE = 'replace'",
		"private const ACTION_REMOVE  = 'remove'",
		'normalize_action',
		"'media_action'",
		"'after_media_id'",
		"'remove_relationship_supported' => true",
		'expected_fingerprint',
		'allow_published_target',
		'idempotency_key',
		'OperationStore::reserve',
		'set_post_thumbnail',
		'delete_post_thumbnail',
		'seo_geo_manager_featured_media_rollback_stale',
	),
	'controller' => array(
		'/content/(?P<resource_id>\\d+)/featured-media/changes/preview',
		'/content/(?P<resource_id>\\d+)/featured-media/changes/apply',
		'/content/featured-media/changes/(?P<operation_id>[a-f0-9\\-]{36})/verify',
		'/content/featured-media/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback',
		'FeaturedMediaChangeAdapter::verify( $operation )',
		'EnvironmentPolicy::validate_payload',
		'EnvironmentPolicy::validate_operation_environment',
	),
	'script' => array(
		'Imagen destacada',
		'seo-geo-manager:content-selected',
		'media_action: state.action',
		"state.action = 'replace'",
		"state.action = 'remove'",
		'expected_fingerprint',
		'allow_published_target',
		'environment_fingerprint',
		'idempotency_key',
		'/verify',
		'/rollback',
		'mutationLocked()',
		'El rollback seguro permanece disponible.',
		"result.before_fingerprint || ''",
	),
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "Featured Media Workspace {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

if ( 1 !== preg_match( '/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $sources['main'], $version_match ) ) {
	fwrite( STDERR, "Featured Media Workspace contract could not read the Manager package version.\n" );
	exit( 1 );
}
$package_version = $version_match[1];
if ( version_compare( $package_version, '0.3.63', '<' ) ) {
	fwrite( STDERR, "Featured Media Workspace requires Manager >= 0.3.63; received {$package_version}.\n" );
	exit( 1 );
}
if ( false === strpos( $sources['main'], "SEO_GEO_MANAGER_VERSION', '{$package_version}'" ) ) {
	fwrite( STDERR, "Featured Media Workspace contract found a header/runtime version mismatch for {$package_version}.\n" );
	exit( 1 );
}

foreach ( array( 'bootstrap', 'controller', 'script' ) as $scope ) {
	if ( false !== strpos( $sources[ $scope ], 'emmake.com' ) ) {
		fwrite( STDERR, "Featured Media Workspace must not hardcode the EMMAKE domain.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $sources['script'], 'type="url"' ) || false !== strpos( $sources['script'], 'suggested_url' ) || false !== strpos( $sources['script'], 'target_url' ) ) {
	fwrite( STDERR, "Featured Media Workspace must not expose an arbitrary URL writer.\n" );
	exit( 1 );
}

foreach ( array( $sources['adapter'], $sources['script'] ) as $source ) {
	foreach ( array( 'wp_delete_attachment(', 'wp_handle_sideload(', 'wp_update_post(' ) as $forbidden ) {
		if ( false !== strpos( $source, $forbidden ) ) {
			fwrite( STDERR, "Featured Media Workspace contains forbidden content/binary mutation primitive: {$forbidden}\n" );
			exit( 1 );
		}
	}
}

if ( false !== strpos( $sources['script'], 'state.operation = null;\n\t\tpreviewButton.disabled' ) ) {
	fwrite( STDERR, "Featured Media Workspace must not discard an applied operation while resetting a pending preview.\n" );
	exit( 1 );
}

$apply_start    = strpos( $sources['script'], 'async function apply()' );
$rollback_start = strpos( $sources['script'], 'async function rollback()' );
if ( false === $apply_start || false === $rollback_start || $rollback_start <= $apply_start ) {
	fwrite( STDERR, "Featured Media Workspace apply/rollback functions are unavailable for contract inspection.\n" );
	exit( 1 );
}
$apply_body = substr( $sources['script'], $apply_start, $rollback_start - $apply_start );
if ( false !== strpos( $apply_body, 'inspectCurrent()' ) ) {
	fwrite( STDERR, "Featured Media Apply must keep the verified operation and rollback action authoritative until resolution.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                            => true,
			'package_version'               => $package_version,
			'minimum_version'               => '0.3.63',
			'bounded_existing_images'       => true,
			'image_attachment_only'         => true,
			'replace_supported'             => true,
			'remove_relationship_supported' => true,
			'binary_mutation'               => false,
			'content_mutation'              => false,
			'arbitrary_url_writer'          => false,
			'preview_before_apply'          => true,
			'exact_verify'                  => true,
			'idempotent_apply'              => true,
			'environment_bound'             => true,
			'stale_safe_rollback'           => true,
			'rollback_remains_authoritative'=> true,
			'no_client_hardcoding'          => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
