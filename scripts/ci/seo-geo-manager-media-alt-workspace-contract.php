<?php
/**
 * Static contract for Manager 0.3.64 Media ALT Workspace.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'main'       => $root . '/packages/seo-geo-manager/seo-geo-manager.php',
	'plugin'     => $root . '/packages/seo-geo-manager/src/Plugin.php',
	'bootstrap'  => $root . '/packages/seo-geo-manager/src/Admin/MediaAltWorkspace.php',
	'media'      => $root . '/packages/seo-geo-manager/src/Intelligence/MediaReferenceReader.php',
	'adapter'    => $root . '/packages/seo-geo-manager/src/Changes/MediaAltChangeAdapter.php',
	'controller' => $root . '/packages/seo-geo-manager/src/Rest/MediaAltChangeController.php',
	'script'     => $root . '/packages/seo-geo-manager/assets/admin/media-alt-workspace.js',
	'style'      => $root . '/packages/seo-geo-manager/assets/admin/media-alt-workspace.css',
);

foreach ( $paths as $label => $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Media ALT Workspace contract missing {$label}: {$path}\n" );
		exit( 1 );
	}
}

$sources = array();
foreach ( $paths as $label => $path ) {
	$sources[ $label ] = (string) file_get_contents( $path );
}

$requirements = array(
	'main' => array(
		'Version: 0.3.64',
		"SEO_GEO_MANAGER_VERSION', '0.3.64'",
	),
	'plugin' => array(
		'use SeoGeo\\Manager\\Admin\\MediaAltWorkspace;',
		'MediaAltWorkspace::register();',
	),
	'bootstrap' => array(
		'seo-geo-manager-media-alt-workspace',
		'media-alt-workspace.js',
		'media-alt-workspace.css',
		"'mediaPath' => '/seo-geo-manager/v1/media'",
	),
	'media' => array(
		'private const MAX_MEDIA',
		"'alt'",
		"'fingerprint'",
		"'raw_attachment_metadata_returned' => false",
		"'exif_returned'",
	),
	'adapter' => array(
		"private const ADAPTER        = 'media-alt'",
		"'alt_only'                  => true",
		"'attachment_id_only'        => true",
		"'binary_mutation_supported' => false",
		'expected_fingerprint',
		'allow_public_media',
		'idempotency_key',
		'OperationStore::reserve',
		'update_post_meta',
		'public static function verify( array $operation )',
		'seo_geo_manager_media_alt_rollback_stale',
	),
	'controller' => array(
		'/media/(?P<media_id>\\d+)/alt/changes/preview',
		'/media/(?P<media_id>\\d+)/alt/changes/apply',
		'/media/alt/changes/(?P<operation_id>[a-f0-9\\-]{36})/verify',
		'/media/alt/changes/(?P<operation_id>[a-f0-9\\-]{36})/rollback',
		'MediaAltChangeAdapter::verify( $operation )',
		'EnvironmentPolicy::validate_payload',
		'EnvironmentPolicy::validate_operation_environment',
	),
	'script' => array(
		'ALT de imágenes existentes',
		'expected_fingerprint',
		'allow_public_media',
		'environment_fingerprint',
		'idempotency_key',
		'state.idempotencyKey',
		'/verify',
		'/rollback',
		'state.operation = {',
		'La operación aplicada conserva rollback seguro.',
		'Rollback verificado: se restauró exactamente el ALT original.',
	),
);

foreach ( $requirements as $scope => $needles ) {
	foreach ( $needles as $needle ) {
		if ( false === strpos( $sources[ $scope ], $needle ) ) {
			fwrite( STDERR, "Media ALT Workspace {$scope} contract missing: {$needle}\n" );
			exit( 1 );
		}
	}
}

foreach ( array( 'bootstrap', 'controller', 'script' ) as $scope ) {
	if ( false !== strpos( $sources[ $scope ], 'emmake.com' ) ) {
		fwrite( STDERR, "Media ALT Workspace must not hardcode the EMMAKE domain.\n" );
		exit( 1 );
	}
}

foreach ( array( 'type="url"', 'suggested_url', 'target_url', 'external_url', 'remote_url' ) as $forbidden_url ) {
	if ( false !== strpos( $sources['script'], $forbidden_url ) ) {
		fwrite( STDERR, "Media ALT Workspace must not expose an arbitrary URL writer: {$forbidden_url}\n" );
		exit( 1 );
	}
}

foreach ( array( $sources['adapter'], $sources['controller'], $sources['script'] ) as $source ) {
	foreach ( array( 'wp_delete_attachment(', 'wp_handle_sideload(', 'media_sideload_image(', 'update_attached_file(', 'wp_update_attachment_metadata(', 'file_put_contents(', 'wp_update_post(' ) as $forbidden ) {
		if ( false !== strpos( $source, $forbidden ) ) {
			fwrite( STDERR, "Media ALT Workspace contains forbidden content/binary mutation primitive: {$forbidden}\n" );
			exit( 1 );
		}
	}
}

if ( false !== strpos( $sources['script'], 'state.operation = null;\n\t\tstate.preview' ) ) {
	fwrite( STDERR, "Media ALT Workspace must not discard an applied operation while resetting a preview.\n" );
	exit( 1 );
}

$apply_start    = strpos( $sources['script'], 'async function apply()' );
$rollback_start = strpos( $sources['script'], 'async function rollback()' );
if ( false === $apply_start || false === $rollback_start || $rollback_start <= $apply_start ) {
	fwrite( STDERR, "Media ALT Workspace apply/rollback functions are unavailable for contract inspection.\n" );
	exit( 1 );
}

$apply_body = substr( $sources['script'], $apply_start, $rollback_start - $apply_start );
$operation_assignment = strpos( $apply_body, 'state.operation = {' );
$verify_request       = strpos( $apply_body, '/verify' );
if ( false === $operation_assignment || false === $verify_request || $operation_assignment > $verify_request ) {
	fwrite( STDERR, "Media ALT Workspace must persist operation ownership before post-Apply Verify.\n" );
	exit( 1 );
}

if ( false === strpos( $apply_body, 'state.idempotencyKey || idempotencyKey()' ) ) {
	fwrite( STDERR, "Media ALT Workspace must retain one idempotency key across uncertain Apply retries.\n" );
	exit( 1 );
}

fwrite(
	STDOUT,
	json_encode(
		array(
			'ok'                              => true,
			'version'                         => '0.3.64',
			'bounded_existing_images'          => true,
			'alt_only'                         => true,
			'attachment_id_only'               => true,
			'binary_mutation'                  => false,
			'content_mutation'                 => false,
			'arbitrary_url_writer'             => false,
			'preview_before_apply'             => true,
			'operation_owned_before_verify'    => true,
			'exact_verify'                     => true,
			'idempotent_apply'                 => true,
			'idempotency_retry_stable'         => true,
			'environment_bound'                => true,
			'stale_safe_rollback'              => true,
			'rollback_remains_authoritative'   => true,
			'no_client_hardcoding'             => true,
		),
		JSON_UNESCAPED_SLASHES
	) . PHP_EOL
);
