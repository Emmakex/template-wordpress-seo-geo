<?php
/**
 * Static acceptance for SEO/GEO Manager capability discovery.
 *
 * This complements runtime WordPress acceptance by guarding the product-level
 * endpoint and safety contract against accidental removal. The capability
 * contract was introduced in 0.3.34 and remains mandatory for later versions.
 */

declare(strict_types=1);

$root = dirname( __DIR__, 2 );

$controller_path = $root . '/packages/seo-geo-manager/src/Rest/CapabilitiesController.php';
$manifest_path   = $root . '/packages/seo-geo-manager/src/Support/CapabilityManifest.php';
$plugin_path     = $root . '/packages/seo-geo-manager/src/Plugin.php';
$main_path       = $root . '/packages/seo-geo-manager/seo-geo-manager.php';
$doc_path        = $root . '/docs/SEO_GEO_MANAGER_0.3.34_CAPABILITY_DISCOVERY.md';

foreach ( array( $controller_path, $manifest_path, $plugin_path, $main_path, $doc_path ) as $required_path ) {
	if ( ! is_file( $required_path ) ) {
		throw new RuntimeException( 'Missing capability-contract path: ' . $required_path );
	}
}

$controller = (string) file_get_contents( $controller_path );
$manifest   = (string) file_get_contents( $manifest_path );
$plugin     = (string) file_get_contents( $plugin_path );
$main       = (string) file_get_contents( $main_path );
$doc        = (string) file_get_contents( $doc_path );

$required_controller_markers = array(
	"'/capabilities'",
	"'methods'             => 'GET'",
	"'permission_callback' => array( self::class, 'can_read' )",
	'CapabilityManifest::build()',
);

foreach ( $required_controller_markers as $marker ) {
	if ( ! str_contains( $controller, $marker ) ) {
		throw new RuntimeException( 'Capability controller contract marker missing: ' . $marker );
	}
}

$required_manifest_markers = array(
	"'schema_version'",
	"'remote_transport_requires_https'",
	"'application_passwords_supported'",
	"'application_passwords_available'",
	"'revocable_identity_required'",
	"'secrets_returned'",
	"'generic_remote_shell'",
	"'environment'",
	'EnvironmentPolicy::snapshot()',
	"'site_intelligence'",
	"'content_change_set'",
	"'theme_structured_content'",
	"'navigation_change_set'",
	"'contextual_link_read'",
	"'contextual_link_write'",
	"'media_read'",
	"'media_upload'",
	"'media_alt_write'",
	"'media_context_write'",
	"'media.upload.preview'",
	"'media.upload.apply'",
	"'media.alt.preview'",
	"'media.alt.apply'",
	"'media.alt.rollback'",
	"'media.context.preview'",
	"'media.context.apply'",
	"'media.context.rollback'",
	"'permalink_administration'",
	"'expected_fingerprint_supported'",
	"'stale_safe_rollback_supported'",
	"'remote_media_fetch_supported'",
	"'existing_media_binary_replacement_supported'",
);

foreach ( $required_manifest_markers as $marker ) {
	if ( ! str_contains( $manifest, $marker ) ) {
		throw new RuntimeException( 'Capability manifest contract marker missing: ' . $marker );
	}
}

$required_manifest_values = array(
	"/'schema_version'\s*=>\s*1/" => 'schema_version=1',
	"/'remote_transport_requires_https'\s*=>\s*true/" => 'remote_transport_requires_https=true',
	"/'revocable_identity_required'\s*=>\s*true/" => 'revocable_identity_required=true',
	"/'secrets_returned'\s*=>\s*false/" => 'secrets_returned=false',
	"/'generic_remote_shell'\s*=>\s*false/" => 'generic_remote_shell=false',
	"/'expected_fingerprint_supported'\s*=>\s*true/" => 'expected_fingerprint_supported=true',
	"/'stale_safe_rollback_supported'\s*=>\s*true/" => 'stale_safe_rollback_supported=true',
	"/'remote_media_fetch_supported'\s*=>\s*false/" => 'remote_media_fetch_supported=false',
	"/'existing_media_binary_replacement_supported'\s*=>\s*false/" => 'existing_media_binary_replacement_supported=false',
);

foreach ( $required_manifest_values as $pattern => $description ) {
	if ( 1 !== preg_match( $pattern, $manifest ) ) {
		throw new RuntimeException( 'Capability manifest semantic contract missing: ' . $description );
	}
}

if ( ! str_contains( $plugin, 'CapabilitiesController::register_routes();' ) ) {
	throw new RuntimeException( 'Capability controller is not registered by Manager bootstrap.' );
}
if ( ! str_contains( $plugin, 'MediaAltChangeController::register_routes();' ) ) {
	throw new RuntimeException( 'Media alt controller is not registered by Manager bootstrap.' );
}
if ( ! str_contains( $plugin, 'MediaContextChangeController::register_routes();' ) ) {
	throw new RuntimeException( 'Media context controller is not registered by Manager bootstrap.' );
}
if ( ! str_contains( $plugin, 'MediaUploadController::register_routes();' ) ) {
	throw new RuntimeException( 'Media upload controller is not registered by Manager bootstrap.' );
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
if ( $header_version !== $constant_version ) {
	throw new RuntimeException( 'Manager header/runtime versions are not aligned.' );
}
if ( version_compare( $header_version, '0.3.34', '<' ) ) {
	throw new RuntimeException( 'Capability discovery requires Manager 0.3.34 or later.' );
}

$required_doc_markers = array(
	'GET /wp-json/seo-geo-manager/v1/capabilities',
	'least-privilege',
	'No generic shell',
	'Client agnostic',
	'C2 — Authentication / capability contract',
);

foreach ( $required_doc_markers as $marker ) {
	if ( ! str_contains( $doc, $marker ) ) {
		throw new RuntimeException( 'Capability documentation marker missing: ' . $marker );
	}
}

echo 'SEO/GEO Manager capability contract OK: version=' . $header_version . ".\n";
