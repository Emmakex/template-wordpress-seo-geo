<?php
/**
 * Runtime acceptance for the guarded permalink 301 engine.
 */

use SeoGeo\Manager\Support\PermalinkRedirectRuntime;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_redirect_runtime_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_redirect_runtime_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$initial = PermalinkRedirectRuntime::snapshot();
seo_geo_manager_redirect_runtime_accept( false === ( $initial['active'] ?? true ), 'Redirect runtime must start inactive.' );

$plan_fingerprint = hash( 'sha256', 'seo-geo-manager-redirect-runtime-acceptance-plan' );
$redirects = array(
	array(
		'post_id'     => 101,
		'source_path' => '/legacy-one/',
		'target_path' => '/new-one/',
		'status'      => 301,
	),
	array(
		'post_id'     => 102,
		'source_path' => '/legacy-two/',
		'target_path' => '/new-two/',
		'status'      => 301,
	),
);

$preview = PermalinkRedirectRuntime::preview( $redirects, $plan_fingerprint );
seo_geo_manager_redirect_runtime_accept( false === ( $preview['write_performed'] ?? true ), 'Redirect preview unexpectedly reported a write.' );
seo_geo_manager_redirect_runtime_accept( true === ( $preview['safe_to_activate'] ?? false ), 'Valid one-hop redirect map was not accepted by preview.' );
seo_geo_manager_redirect_runtime_accept( 2 === (int) ( $preview['redirect_count'] ?? 0 ), 'Redirect preview count mismatch.' );
seo_geo_manager_redirect_runtime_accept( '' !== (string) ( $preview['fingerprint'] ?? '' ), 'Redirect preview fingerprint missing.' );
seo_geo_manager_redirect_runtime_accept( false === ( PermalinkRedirectRuntime::snapshot()['active'] ?? true ), 'Redirect preview mutated runtime state.' );

$chain_preview = PermalinkRedirectRuntime::preview(
	array(
		array( 'source_path' => '/chain-a/', 'target_path' => '/chain-b/' ),
		array( 'source_path' => '/chain-b/', 'target_path' => '/chain-c/' ),
	),
	$plan_fingerprint
);
seo_geo_manager_redirect_runtime_accept( false === ( $chain_preview['safe_to_activate'] ?? true ), 'Redirect chain was not blocked.' );

$duplicate_preview = PermalinkRedirectRuntime::preview(
	array(
		array( 'source_path' => '/duplicate/', 'target_path' => '/target-a/' ),
		array( 'source_path' => '/duplicate/', 'target_path' => '/target-b/' ),
	),
	$plan_fingerprint
);
seo_geo_manager_redirect_runtime_accept( false === ( $duplicate_preview['safe_to_activate'] ?? true ), 'Duplicate redirect source was not blocked.' );

$operation_id = wp_generate_uuid4();
$active = PermalinkRedirectRuntime::activate( $redirects, $operation_id, $plan_fingerprint );
seo_geo_manager_redirect_runtime_accept( ! is_wp_error( $active ), 'Redirect runtime activation failed.' );
seo_geo_manager_redirect_runtime_accept( true === ( $active['active'] ?? false ), 'Redirect runtime did not become active.' );
seo_geo_manager_redirect_runtime_accept( $operation_id === ( $active['operation_id'] ?? '' ), 'Redirect runtime operation binding mismatch.' );
seo_geo_manager_redirect_runtime_accept( 2 === (int) ( $active['redirect_count'] ?? 0 ), 'Active redirect runtime count mismatch.' );
$runtime_fingerprint = (string) ( $active['fingerprint'] ?? '' );
seo_geo_manager_redirect_runtime_accept( '' !== $runtime_fingerprint, 'Active redirect runtime fingerprint missing.' );

$resolved = PermalinkRedirectRuntime::resolve( '/legacy-one/?utm_source=manager-ci' );
seo_geo_manager_redirect_runtime_accept( is_array( $resolved ), 'Known historical source did not resolve.' );
seo_geo_manager_redirect_runtime_accept( 301 === (int) ( $resolved['status'] ?? 0 ), 'Resolved redirect did not use HTTP 301.' );
seo_geo_manager_redirect_runtime_accept( '/legacy-one/' === ( $resolved['source_path'] ?? '' ), 'Resolved redirect source mismatch.' );
seo_geo_manager_redirect_runtime_accept( '/new-one/' === ( $resolved['target_path'] ?? '' ), 'Resolved redirect target mismatch.' );
seo_geo_manager_redirect_runtime_accept( false !== strpos( (string) ( $resolved['target_url'] ?? '' ), '/new-one/?utm_source=manager-ci' ), 'Resolved redirect did not preserve the request query string.' );
seo_geo_manager_redirect_runtime_accept( null === PermalinkRedirectRuntime::resolve( '/not-in-map/' ), 'Unknown request path unexpectedly resolved.' );

$rest_request  = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/permalinks/redirect-runtime' );
$rest_response = rest_do_request( $rest_request );
$rest_data     = $rest_response->get_data();
seo_geo_manager_redirect_runtime_accept( 200 === $rest_response->get_status() && is_array( $rest_data ), 'Redirect runtime REST status endpoint failed.' );
seo_geo_manager_redirect_runtime_accept( $runtime_fingerprint === ( $rest_data['fingerprint'] ?? '' ), 'REST runtime snapshot fingerprint mismatch.' );

$conflict = PermalinkRedirectRuntime::activate( $redirects, wp_generate_uuid4(), $plan_fingerprint );
seo_geo_manager_redirect_runtime_accept( is_wp_error( $conflict ), 'A second redirect runtime unexpectedly replaced the active runtime.' );
seo_geo_manager_redirect_runtime_accept( 'seo_geo_manager_redirect_runtime_conflict' === $conflict->get_error_code(), 'Unexpected active-runtime conflict code.' );

$stale_deactivate = PermalinkRedirectRuntime::deactivate( $operation_id, str_repeat( '0', 64 ) );
seo_geo_manager_redirect_runtime_accept( is_wp_error( $stale_deactivate ), 'Stale runtime fingerprint unexpectedly allowed cleanup.' );
seo_geo_manager_redirect_runtime_accept( 'seo_geo_manager_redirect_runtime_stale' === $stale_deactivate->get_error_code(), 'Unexpected stale runtime cleanup code.' );
seo_geo_manager_redirect_runtime_accept( true === ( PermalinkRedirectRuntime::snapshot()['active'] ?? false ), 'Stale cleanup removed the runtime.' );

$inactive = PermalinkRedirectRuntime::deactivate( $operation_id, $runtime_fingerprint );
seo_geo_manager_redirect_runtime_accept( ! is_wp_error( $inactive ), 'Owned redirect runtime cleanup failed.' );
seo_geo_manager_redirect_runtime_accept( false === ( $inactive['active'] ?? true ), 'Redirect runtime remained active after cleanup.' );
seo_geo_manager_redirect_runtime_accept( null === PermalinkRedirectRuntime::resolve( '/legacy-one/' ), 'Inactive redirect runtime still resolved a historical path.' );

echo wp_json_encode(
	array(
		'ok'                       => true,
		'preview_only_guard'       => true,
		'one_hop_guard'            => true,
		'duplicate_source_guard'   => true,
		'activation_guard'         => true,
		'query_preservation'       => true,
		'operation_binding_guard'  => true,
		'stale_cleanup_guard'      => true,
		'reversible_runtime'       => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
