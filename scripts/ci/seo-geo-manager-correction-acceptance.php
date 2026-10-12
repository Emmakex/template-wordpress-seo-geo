<?php
/** Runtime acceptance for the environment-link correction flow. */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_correction_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function seo_geo_correction_request( string $method, string $route, ?array $payload = null ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );
	return array( 'status' => $response->get_status(), 'data' => $response->get_data() );
}

wp_set_current_user( 1 );
seo_geo_correction_accept( current_user_can( 'manage_options' ), 'Acceptance administrator unavailable.' );
$site_root = untrailingslashit( site_url( '/' ) );
update_option( 'home', $site_root . '/nuevaweb' );

$source_id = wp_insert_post(
	array(
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => 'Environment link source',
		'post_name' => 'environment-link-source',
		'post_content' => '<p><a href="' . esc_url( $site_root . '/blog-transformacion-digital/' ) . '">Blog</a></p>',
	),
	true
);
seo_geo_correction_accept( ! is_wp_error( $source_id ), 'Could not create source fixture.' );

$snapshot = seo_geo_correction_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_correction_accept( 200 === $snapshot['status'], 'Snapshot failed.' );
$environment = (array) ( $snapshot['data']['environment'] ?? array() );
$fingerprint = (string) ( $environment['fingerprint'] ?? '' );
seo_geo_correction_accept( '' !== $fingerprint, 'Environment fingerprint missing.' );

$resource = seo_geo_correction_request( 'GET', '/seo-geo-manager/v1/content/' . (int) $source_id );
seo_geo_correction_accept( 200 === $resource['status'], 'Resource read failed.' );
$old_url = $site_root . '/blog-transformacion-digital/';
$new_url = $site_root . '/nuevaweb/blog-transformacion-digital/';
$content = (string) ( $resource['data']['content'] ?? '' );
seo_geo_correction_accept( str_contains( $content, $old_url ), 'Fixture leakage missing.' );

$payload = array(
	'schema_version' => 1,
	'target' => array(
		'id' => (int) $source_id,
		'expected_fingerprint' => (string) ( $resource['data']['fingerprint'] ?? '' ),
	),
	'changes' => array( 'content' => str_replace( $old_url, $new_url, $content ) ),
	'allow_published_target' => false,
);
$preview = seo_geo_correction_request( 'POST', '/seo-geo-manager/v1/changes/preview', $payload );
seo_geo_correction_accept( 200 === $preview['status'] && true === ( $preview['data']['has_changes'] ?? false ), 'Correction preview failed.' );

$blocked = $payload;
$blocked['idempotency_key'] = 'correction-published-guard';
$blocked['environment_fingerprint'] = $fingerprint;
$blocked_apply = seo_geo_correction_request( 'POST', '/seo-geo-manager/v1/changes/apply', $blocked );
seo_geo_correction_accept( 409 === $blocked_apply['status'], 'Published-target guard was bypassed.' );

$payload['allow_published_target'] = true;
$payload['idempotency_key'] = 'correction-apply-1';
$payload['environment_fingerprint'] = $fingerprint;
$apply = seo_geo_correction_request( 'POST', '/seo-geo-manager/v1/changes/apply', $payload );
seo_geo_correction_accept( 200 === $apply['status'] && 'applied' === ( $apply['data']['status'] ?? '' ), 'Correction apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_correction_accept( '' !== $operation_id, 'Operation ID missing.' );

$after = seo_geo_correction_request( 'GET', '/seo-geo-manager/v1/content/' . (int) $source_id );
seo_geo_correction_accept( str_contains( (string) ( $after['data']['content'] ?? '' ), $new_url ), 'Corrected URL not persisted.' );
$operation = seo_geo_correction_request( 'GET', '/seo-geo-manager/v1/changes/' . $operation_id );
seo_geo_correction_accept( 200 === $operation['status'], 'Operation read failed.' );
seo_geo_correction_accept( (string) ( $after['data']['fingerprint'] ?? '' ) === (string) ( $operation['data']['after_fingerprint'] ?? '' ), 'Verify fingerprint mismatch.' );

$rollback = seo_geo_correction_request( 'POST', '/seo-geo-manager/v1/changes/' . $operation_id . '/rollback', array( 'environment_fingerprint' => $fingerprint ) );
seo_geo_correction_accept( 200 === $rollback['status'] && 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'Rollback failed.' );
$restored = seo_geo_correction_request( 'GET', '/seo-geo-manager/v1/content/' . (int) $source_id );
seo_geo_correction_accept( str_contains( (string) ( $restored['data']['content'] ?? '' ), $old_url ), 'Rollback did not restore original URL.' );

echo "SEO/GEO Manager correction acceptance: OK\n";
