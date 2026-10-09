<?php
/**
 * Runtime acceptance for the read-only permalink repair preview.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_permalink_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function seo_geo_manager_permalink_request(): array {
	$request  = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/permalinks/preview' );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

wp_set_current_user( 1 );
seo_geo_manager_permalink_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$original = (string) get_option( 'permalink_structure', '' );
$token    = '{d276abfeceab40cca0e158fc6217176554b8e54a1f85b6eb004941797db52171}';
$broken   = '/' . $token . 'category' . $token . '/' . $token . 'postname' . $token . '/';
update_option( 'permalink_structure', $broken );

$preview = seo_geo_manager_permalink_request();
seo_geo_manager_permalink_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Permalink preview endpoint failed.' );
seo_geo_manager_permalink_accept( $broken === ( $preview['data']['current_structure'] ?? '' ), 'Permalink preview did not expose the exact current structure.' );
seo_geo_manager_permalink_accept( '/%category%/%postname%/' === ( $preview['data']['proposed_structure'] ?? '' ), 'Permalink preview did not restore known placeholder tokens.' );
seo_geo_manager_permalink_accept( true === ( $preview['data']['safe_candidate'] ?? false ), 'Deterministic permalink repair candidate was not classified as safe.' );
seo_geo_manager_permalink_accept( true === ( $preview['data']['apply_blocked'] ?? false ), 'Permalink preview must never enable Apply in this microphase.' );
seo_geo_manager_permalink_accept( false === ( $preview['data']['write_performed'] ?? true ), 'Permalink preview unexpectedly reported a write.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Permalink preview mutated the WordPress option.' );

update_option( 'permalink_structure', $original );

echo wp_json_encode(
	array(
		'ok'                 => true,
		'preview_only'       => true,
		'proposed_structure' => $preview['data']['proposed_structure'] ?? '',
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
