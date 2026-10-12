<?php
/**
 * Static release contract for C6.6A featured-media orchestration.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'reader'      => $root . '/packages/seo-geo-manager/src/Intelligence/FeaturedMediaReader.php',
	'read_rest'   => $root . '/packages/seo-geo-manager/src/Rest/FeaturedMediaController.php',
	'change_rest' => $root . '/packages/seo-geo-manager/src/Rest/FeaturedMediaChangeController.php',
	'adapter'     => $root . '/packages/seo-geo-manager/src/Changes/FeaturedMediaChangeAdapter.php',
	'fingerprint' => $root . '/packages/seo-geo-manager/src/Support/FeaturedMediaFingerprint.php',
	'manifest'    => $root . '/packages/seo-geo-manager/src/Support/CapabilityManifest.php',
	'plugin'      => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'main'        => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
);

foreach ( $paths as $name => $path ) {
	if ( ! is_file( $path ) ) {
		throw new RuntimeException( 'Missing C6.6A contract file: ' . $name );
	}
}

$reader      = (string) file_get_contents( $paths['reader'] );
$read_rest   = (string) file_get_contents( $paths['read_rest'] );
$change_rest = (string) file_get_contents( $paths['change_rest'] );
$adapter     = (string) file_get_contents( $paths['adapter'] );
$fingerprint = (string) file_get_contents( $paths['fingerprint'] );
$manifest    = (string) file_get_contents( $paths['manifest'] );
$plugin      = (string) file_get_contents( $paths['plugin'] );
$main        = (string) file_get_contents( $paths['main'] );

$required = array(
	'reader' => array(
		'FeaturedMediaFingerprint::for_post',
		"'featured_media_id'",
		"'expected_fingerprint_source'",
		"'raw_attachment_metadata'     => false",
		"'binary_mutation_supported'   => false",
		"'content_mutation_supported'  => false",
	),
	'read_rest' => array(
		"'/content/(?P<resource_id>\\d+)/featured-media'",
		"'methods'             => 'GET'",
		'FeaturedMediaReader::read',
		"current_user_can( 'upload_files' )",
	),
	'change_rest' => array(
		"'/content/(?P<resource_id>\\d+)/featured-media/changes/preview'",
		"'/content/(?P<resource_id>\\d+)/featured-media/changes/apply'",
		"'/content/featured-media/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback'",
		'EnvironmentPolicy::validate_payload',
		'EnvironmentPolicy::validate_operation_environment',
	),
	'adapter' => array(
		"private const ADAPTER        = 'featured-media'",
		'expected_fingerprint',
		'allow_published_target',
		'idempotency_key',
		'OperationStore::reserve',
		'set_post_thumbnail',
		'delete_post_thumbnail',
		'seo_geo_manager_featured_media_rollback_stale',
	),
	'fingerprint' => array(
		'ContentFingerprint::for_post',
		'get_post_thumbnail_id',
	),
	'manifest' => array(
		"'featured_media_read'",
		"'featured_media_write'",
		"'content.featured_media.read'",
		"'content.featured_media.preview'",
		"'content.featured_media.apply'",
		"'content.featured_media.rollback'",
		"'existing_media_binary_replacement_supported' => false",
	),
	'plugin' => array(
		'FeaturedMediaController::register_routes();',
		'FeaturedMediaChangeController::register_routes();',
	),
);

$contents = array(
	'reader'      => $reader,
	'read_rest'   => $read_rest,
	'change_rest' => $change_rest,
	'adapter'     => $adapter,
	'fingerprint' => $fingerprint,
	'manifest'    => $manifest,
	'plugin'      => $plugin,
);

foreach ( $required as $group => $markers ) {
	foreach ( $markers as $marker ) {
		if ( ! str_contains( $contents[ $group ], $marker ) ) {
			throw new RuntimeException( 'C6.6A contract marker missing from ' . $group . ': ' . $marker );
		}
	}
}

if ( str_contains( $adapter, 'wp_update_post(' ) || str_contains( $adapter, 'wp_delete_attachment(' ) || str_contains( $adapter, 'wp_handle_sideload(' ) ) {
	throw new RuntimeException( 'C6.6A adapter contains a forbidden content/binary mutation primitive.' );
}

$header_match   = array();
$constant_match = array();
if ( 1 !== preg_match( '/^ \* Version:\s*([^\r\n]+)/m', $main, $header_match ) ) {
	throw new RuntimeException( 'Manager plugin header version is missing.' );
}
if ( 1 !== preg_match( "/SEO_GEO_MANAGER_VERSION'\s*,\s*'([^']+)'/", $main, $constant_match ) ) {
	throw new RuntimeException( 'Manager runtime version constant is missing.' );
}
$header_version   = trim( (string) $header_match[1] );
$constant_version = trim( (string) $constant_match[1] );
if ( $header_version !== $constant_version || version_compare( $header_version, '0.3.42', '<' ) ) {
	throw new RuntimeException( 'C6.6A requires aligned Manager version 0.3.42 or later.' );
}

echo 'SEO/GEO Manager C6.6A featured-media contract OK: version=' . $header_version . ".\n";
