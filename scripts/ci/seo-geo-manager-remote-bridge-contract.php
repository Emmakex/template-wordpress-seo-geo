<?php
/**
 * Offline contract for the SEO/GEO Manager-owned remote bridge through R3.7.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$paths = array(
	'credentials'         => 'packages/seo-geo-manager/src/Remote/Credentials.php',
	'tokens'              => 'packages/seo-geo-manager/src/Remote/TokenService.php',
	'guard'               => 'packages/seo-geo-manager/src/Remote/RequestGuard.php',
	'plans'               => 'packages/seo-geo-manager/src/Remote/PlanStore.php',
	'operations'          => 'packages/seo-geo-manager/src/Remote/MutationOperations.php',
	'seo_authority'       => 'packages/seo-geo-manager/src/Changes/SeoAuthorityChangeAdapter.php',
	'contextual_links'    => 'packages/seo-geo-manager/src/Changes/ContextualLinkChangeAdapter.php',
	'navigation'          => 'packages/seo-geo-manager/src/Changes/NavigationChangeAdapter.php',
	'featured_media'      => 'packages/seo-geo-manager/src/Changes/FeaturedMediaChangeAdapter.php',
	'media_alt'           => 'packages/seo-geo-manager/src/Changes/MediaAltChangeAdapter.php',
	'contextual_reader'   => 'packages/seo-geo-manager/src/Intelligence/ContextualLinkReader.php',
	'featured_reader'     => 'packages/seo-geo-manager/src/Intelligence/FeaturedMediaReader.php',
	'media_reader'        => 'packages/seo-geo-manager/src/Intelligence/MediaReferenceReader.php',
	'read_controller'     => 'packages/seo-geo-manager/src/Rest/RemoteReadController.php',
	'mutation_controller' => 'packages/seo-geo-manager/src/Rest/RemoteMutationController.php',
	'package'             => 'packages/seo-geo-manager/seo-geo-manager.php',
);

$sources = array();
foreach ( $paths as $key => $relative ) {
	$path = $root . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Remote bridge contract missing {$key}: {$path}\n" );
		exit( 1 );
	}
	$sources[ $key ] = (string) file_get_contents( $path );
}

$required = array(
	'credentials' => array( "'site.read'", "'site.diagnostics'", "'content.write'", "'operations.read'", "'operations.apply'", "'operations.rollback'", "'credential_hash'" ),
	'tokens' => array( "'sgm1_'", 'random_bytes( 32 )', 'wp_hash_password( $token )', 'wp_check_password( $token', 'function revoke()' ),
	'guard' => array( 'x-seo-geo-manager-request-id', 'x-seo-geo-manager-nonce', 'x-seo-geo-manager-timestamp', 'validate_idempotency' ),
	'plans' => array( "'sgmp_'", 'APPLY_TTL_SECONDS   = 15 * MINUTE_IN_SECONDS', 'MutationOperations::supports( $operation )', 'EnvironmentPolicy::snapshot()' ),
	'operations' => array(
		"'content-change'", "'theme-structured-content'", "'seo-authority-change'", "'contextual-link-change'", "'navigation-change'", "'featured-media-change'", "'media-alt-change'",
		'SeoAuthorityChangeAdapter::preview( $payload )', 'SeoAuthorityChangeAdapter::apply( $payload )', 'SeoAuthorityChangeAdapter::verify( $stored )', 'SeoAuthorityChangeAdapter::rollback( $operation_id )',
		'ContextualLinkChangeAdapter::preview( $payload )', 'ContextualLinkChangeAdapter::apply( $payload )', 'ContextualLinkChangeAdapter::rollback( $operation_id )',
		'NavigationChangeAdapter::preview( $payload )', 'NavigationChangeAdapter::apply( $payload )', 'NavigationChangeAdapter::verify( $stored )', 'NavigationChangeAdapter::rollback( $operation_id )',
		'FeaturedMediaChangeAdapter::preview( $payload )', 'FeaturedMediaChangeAdapter::apply( $payload )', 'FeaturedMediaChangeAdapter::verify( $stored )', 'FeaturedMediaChangeAdapter::rollback( $operation_id )',
		'MediaAltChangeAdapter::preview( $payload )', 'MediaAltChangeAdapter::apply( $payload )', 'MediaAltChangeAdapter::verify( $stored )', 'MediaAltChangeAdapter::rollback( $operation_id )',
		'Remote R3.7 mutations do not yet expose rendered verification.',
	),
	'seo_authority' => array( 'SeoAuthorityScanner::scan()', 'NativeSeoMetadataChangeAdapter::preview( $payload )', 'NativeSeoMetadataChangeAdapter::apply( $payload )', 'YoastSeoMetadataChangeAdapter::preview( $payload )', 'YoastSeoMetadataChangeAdapter::apply( $payload )', "'seo-geo-theme'", "'seo-geo-theme-native'", "'yoast'", "'yoast-abilities'" ),
	'contextual_links' => array( "'operation_type'", "'contextual-link-change'", "'href_only'", "'contextual_href'", 'ContentFingerprint::for_post( $source )', "'target_resource_id'" ),
	'navigation' => array( 'public static function inspect( int $menu_id )', 'public static function verify( array $operation )', 'self::menu_fingerprint( $menu_id )', "'operation_type'     => 'navigation-change'", "'target_type'        => 'navigation-menu'", "'changes'            => array( 'navigation_url' => true )", "'allow_public_navigation'", "'raw_menu_item_meta_returned'" ),
	'featured_media' => array( 'public static function preview( array $payload )', 'public static function apply( array $payload )', 'public static function verify( array $operation )', 'public static function rollback( string $operation_id )', 'FeaturedMediaFingerprint::for_post( $resource )', "'operation_type'     => 'featured-media-change'", "'changes'            => array( 'featured_media' => true )", "'allow_published_target'", "'binary_mutation_supported'", "'content_mutation_supported'" ),
	'media_alt' => array( 'public static function preview( array $payload )', 'public static function apply( array $payload )', 'public static function verify( array $operation )', 'public static function rollback( string $operation_id )', 'MediaFingerprint::for_attachment( $attachment )', "'operation_type'     => 'media-alt-change'", "'changes'            => array( 'image_alt' => true )", "'allow_public_media'", "'binary_mutation_supported'" ),
	'contextual_reader' => array( "'edge_id'", "'fingerprint'", "'raw_post_content_returned'", "'max_links'" ),
	'featured_reader' => array( 'FeaturedMediaFingerprint::for_post( $post )', "'featured_media_id'", "'raw_attachment_metadata'", "'binary_mutation_supported'", "'content_mutation_supported'" ),
	'media_reader' => array( 'public static function read_media( int $media_id )', 'MediaFingerprint::for_attachment( $attachment )', "'alt'", "'fingerprint'" ),
	'read_controller' => array( "'/remote/contextual-links'", "'/remote/navigation'", "'/remote/featured-media'", "'/remote/media'", "'remote_milestone' => 'R3.7'", "'milestone'            => 'R3.7'", "'supported_operations' => MutationOperations::supported()", "'generic_proxy'        => false", "'raw_metadata_write'   => false", 'ContextualLinkReader::read( $source_id )', 'NavigationChangeAdapter::inspect( $menu_id )', 'FeaturedMediaReader::read( $resource_id )', 'MediaReferenceReader::read_media( $media_id )' ),
	'mutation_controller' => array( 'MutationOperations::preview( $operation, $payload )', 'MutationOperations::apply( $operation_type, $payload )', 'MutationOperations::verify( $operation_type, $operation )', 'MutationOperations::rollback( $operation_type, $operation_id )', "'remote_seo_authority'", 'bounded_seo_authority', 'EnvironmentPolicy::validate_payload( $params )', 'EnvironmentPolicy::validate_operation_environment( $operation )', '\'navigation-change\' === $operation_type', '$payload[\'allow_public_navigation\']', '$params[\'allow_public_navigation\']', '\'featured-media-change\' === $operation_type', '$payload[\'allow_published_target\']', '$params[\'allow_published_target\']', '\'media-alt-change\' === $operation_type', '$payload[\'allow_public_media\']', '$params[\'allow_public_media\']' ),
);

foreach ( $required as $source_key => $markers ) {
	foreach ( $markers as $marker ) {
		if ( false === strpos( $sources[ $source_key ], $marker ) ) {
			fwrite( STDERR, "Remote bridge contract missing {$source_key} marker: {$marker}\n" );
			exit( 1 );
		}
	}
}

if ( 1 !== preg_match( '/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $sources['package'], $version_match ) ) {
	fwrite( STDERR, "Remote bridge contract could not read the Manager package version.\n" );
	exit( 1 );
}
$package_version = $version_match[1];
if ( version_compare( $package_version, '0.3.57', '<' ) ) {
	fwrite( STDERR, "Remote bridge R3.7 requires Manager >= 0.3.57; received {$package_version}.\n" );
	exit( 1 );
}
if ( false === strpos( $sources['package'], "SEO_GEO_MANAGER_VERSION', '{$package_version}'" ) ) {
	fwrite( STDERR, "Remote bridge contract found a header/runtime version mismatch for {$package_version}.\n" );
	exit( 1 );
}

if ( preg_match( '/register_rest_route[\s\S]*WP_REST_Server::CREATABLE/', $sources['read_controller'] ) ) {
	fwrite( STDERR, "Remote read controller must remain read-only.\n" );
	exit( 1 );
}

foreach ( array( 'seo.write', 'media.write', 'navigation.write', 'links.write' ) as $speculative_scope ) {
	if ( false !== strpos( $sources['mutation_controller'], "'{$speculative_scope}'" ) ) {
		fwrite( STDERR, "R3.7 must not silently widen the remote scope model with {$speculative_scope}.\n" );
		exit( 1 );
	}
}

$surface = strtolower( $sources['mutation_controller'] . $sources['operations'] . $sources['seo_authority'] . $sources['contextual_links'] . $sources['navigation'] . $sources['featured_media'] . $sources['media_alt'] );
foreach ( array( 'generic_shell', 'generic_rest_proxy', 'raw_metadata_write' ) as $forbidden ) {
	if ( false !== strpos( $surface, $forbidden ) ) {
		fwrite( STDERR, "R3.7 mutation surface contains forbidden generic transport concept: {$forbidden}\n" );
		exit( 1 );
	}
}

$allowlist = array( 'content-change', 'theme-structured-content', 'seo-authority-change', 'contextual-link-change', 'navigation-change', 'featured-media-change', 'media-alt-change' );
foreach ( $allowlist as $operation ) {
	if ( false === strpos( $sources['operations'], "'{$operation}'" ) ) {
		fwrite( STDERR, "R3.7 operation allowlist is missing {$operation}.\n" );
		exit( 1 );
	}
}

$manager_root = $root . '/packages/seo-geo-manager';
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $manager_root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
		continue;
	}
	$contents = (string) file_get_contents( $file->getPathname() );
	if ( false !== strpos( $contents, '_yoast_wpseo_' ) ) {
		fwrite( STDERR, 'Manager source contains forbidden Yoast private-meta dependency: ' . $file->getPathname() . "\n" );
		exit( 1 );
	}
}

echo json_encode(
	array(
		'ok'                                     => true,
		'package_version'                        => $package_version,
		'minimum_version'                        => '0.3.57',
		'milestone'                              => 'R3.7',
		'manager_owned_credential'               => true,
		'replay_rate_idempotency_guards'        => true,
		'r3_operation_allowlist'                 => $allowlist,
		'plan_connection_binding'                => true,
		'plan_environment_binding'               => true,
		'plan_ttl_seconds'                       => 900,
		'delegates_to_change_set_engine'         => true,
		'delegates_to_theme_semantic_c5'         => true,
		'delegates_to_seo_authority_c7'          => true,
		'delegates_to_contextual_link_c6'        => true,
		'delegates_to_navigation_c6'             => true,
		'delegates_to_featured_media_c6'         => true,
		'delegates_to_media_alt_c6'              => true,
		'contextual_edge_discovery'              => true,
		'contextual_href_only'                   => true,
		'contextual_runtime_required'            => true,
		'navigation_read_surface'                => true,
		'navigation_canonical_fingerprint'       => true,
		'navigation_post_preview_approval'       => true,
		'navigation_runtime_required'            => true,
		'featured_media_read_surface'            => true,
		'featured_media_canonical_fingerprint'   => true,
		'featured_media_post_preview_approval'   => true,
		'featured_media_runtime_required'        => true,
		'featured_media_binary_mutation'         => false,
		'featured_media_content_mutation'        => false,
		'media_alt_read_surface'                 => true,
		'media_alt_canonical_fingerprint'        => true,
		'media_alt_post_preview_approval'        => true,
		'media_alt_runtime_required'             => true,
		'media_alt_binary_mutation'              => false,
		'authority_auto_resolution'              => true,
		'theme_native_runtime_required'          => true,
		'yoast_28_6_runtime_required'            => true,
		'private_yoast_meta'                     => false,
		'provider_selection_by_caller'           => false,
		'generic_proxy'                          => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
