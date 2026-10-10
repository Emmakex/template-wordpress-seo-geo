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
	"'schema_version' => 1",
	"'remote_transport_requires_https' => true",
	"'application_passwords_supported'",
	"'application_passwords_available'",
	"'revocable_identity_required'",
	"'secrets_returned'                => false",
	"'generic_remote_shell'            => false",
	"'environment'    => EnvironmentPolicy::snapshot()",
	"'site_intelligence'",
	"'content_change_set'",
	"'theme_structured_content'",
	"'navigation_change_set'",
	"'permalink_administration'",
	"'expected_fingerprint_supported'",
	"'stale_safe_rollback_supported'",
);

foreach ( $required_manifest_markers as $marker ) {
	if ( ! str_contains( $manifest, $marker ) ) {
		throw new RuntimeException( 'Capability manifest contract marker missing: ' . $marker );
	}
}

if ( ! str_contains( $plugin, 'CapabilitiesController::register_routes();' ) ) {
	throw new RuntimeException( 'Capability controller is not registered by Manager bootstrap.' );
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
