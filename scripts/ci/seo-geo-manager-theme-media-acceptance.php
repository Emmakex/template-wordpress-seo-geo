<?php
/** Runtime acceptance for C6.6B Theme strategic-media orchestration. */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_theme_media_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @param array<string, mixed> $payload @return array{status:int,data:mixed} */
function seo_geo_manager_theme_media_request( string $method, string $route, array $payload = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );
	return array( 'status' => $response->get_status(), 'data' => $response->get_data() );
}

function seo_geo_manager_theme_media_image( string $filename, string $title ): int {
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	seo_geo_manager_theme_media_accept( is_string( $bytes ), 'Could not decode strategic-media PNG fixture.' );
	$upload = wp_upload_bits( $filename, null, $bytes );
	seo_geo_manager_theme_media_accept( empty( $upload['error'] ) && is_string( $upload['file'] ?? null ), 'Could not write strategic-media image fixture.' );
	$file = (string) $upload['file'];
	$id   = wp_insert_attachment(
		array( 'post_title' => $title, 'post_status' => 'inherit', 'post_mime_type' => 'image/png' ),
		$file,
		0,
		true
	);
	seo_geo_manager_theme_media_accept( ! is_wp_error( $id ) && 0 < (int) $id, 'Could not create strategic-media image attachment.' );
	$id       = (int) $id;
	$metadata = wp_generate_attachment_metadata( $id, $file );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $id, $metadata );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title . ' alt' );
	return $id;
}

wp_set_current_user( 1 );
seo_geo_manager_theme_media_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );
seo_geo_manager_theme_media_accept( function_exists( 'seo_geo_theme_strategic_media_contract' ), 'Theme strategic-media contract is not active.' );

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6.6B Strategic Media Home',
		'post_content' => '<p>Stable strategic content that media binding must never rewrite.</p>',
	),
	true
);
seo_geo_manager_theme_media_accept( ! is_wp_error( $page_id ) && 0 < (int) $page_id, 'Could not create C6.6B page.' );
$page_id = (int) $page_id;
$content_before = (string) get_post_field( 'post_content', $page_id );
$media_a = seo_geo_manager_theme_media_image( 'c6-6b-a.png', 'C6.6B Hero A' );
$media_b = seo_geo_manager_theme_media_image( 'c6-6b-b.png', 'C6.6B Hero B' );

$document_id = wp_insert_attachment(
	array( 'post_title' => 'C6.6B Not Image', 'post_status' => 'inherit', 'post_mime_type' => 'text/plain' ),
	'',
	0,
	true
);
seo_geo_manager_theme_media_accept( ! is_wp_error( $document_id ), 'Could not create non-image fixture.' );
$document_id = (int) $document_id;

$snapshot = seo_geo_manager_theme_media_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_theme_media_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_theme_media_accept( '' !== $environment, 'Environment fingerprint missing.' );

$capabilities = seo_geo_manager_theme_media_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_theme_media_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'Could not read capabilities.' );
seo_geo_manager_theme_media_accept( true === ( $capabilities['data']['capabilities']['theme_media_binding_read']['available'] ?? false ), 'Theme media read capability missing.' );
seo_geo_manager_theme_media_accept( true === ( $capabilities['data']['capabilities']['theme_media_binding_write']['available'] ?? false ), 'Theme media write capability missing.' );
seo_geo_manager_theme_media_accept( 'POST' === ( $capabilities['data']['operations']['theme.media_binding.apply']['method'] ?? '' ), 'Theme media Apply operation missing.' );

$base = '/seo-geo-manager/v1/theme/media-bindings/' . $page_id . '/corporate-home-v1/hero-media';
$read = seo_geo_manager_theme_media_request( 'GET', $base );
seo_geo_manager_theme_media_accept( 200 === $read['status'] && is_array( $read['data'] ), 'Initial strategic-media read failed.' );
seo_geo_manager_theme_media_accept( 0 === (int) ( $read['data']['binding']['attachment_id'] ?? -1 ), 'Initial strategic binding should use Theme fallback.' );
$fingerprint_zero = (string) ( $read['data']['binding']['fingerprint'] ?? '' );
seo_geo_manager_theme_media_accept( 64 === strlen( $fingerprint_zero ), 'Initial strategic binding fingerprint missing.' );
seo_geo_manager_theme_media_accept( 'binding.fingerprint' === ( $read['data']['policy']['expected_fingerprint_source'] ?? '' ), 'Reader did not expose binding fingerprint source.' );

$payload = array( 'schema_version' => 1, 'attachment_id' => $media_a, 'expected_fingerprint' => $fingerprint_zero );
$preview = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/preview', $payload );
seo_geo_manager_theme_media_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Strategic-media Preview failed.' );
seo_geo_manager_theme_media_accept( 0 === (int) ( $preview['data']['change']['before_attachment_id'] ?? -1 ), 'Preview before binding mismatch.' );
seo_geo_manager_theme_media_accept( $media_a === (int) ( $preview['data']['change']['after_attachment_id'] ?? 0 ), 'Preview target binding mismatch.' );
seo_geo_manager_theme_media_accept( 0 === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Preview mutated Theme state.' );
seo_geo_manager_theme_media_accept( $content_before === (string) get_post_field( 'post_content', $page_id ), 'Preview changed page content.' );

$stale_payload = $payload;
$stale_payload['expected_fingerprint'] = str_repeat( 'a', 64 );
$stale = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/preview', $stale_payload );
seo_geo_manager_theme_media_accept( 409 === $stale['status'], 'Stale strategic fingerprint was accepted.' );

$invalid_payload = array( 'attachment_id' => $document_id, 'expected_fingerprint' => $fingerprint_zero );
$invalid = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/preview', $invalid_payload );
seo_geo_manager_theme_media_accept( 404 === $invalid['status'], 'Non-image strategic media was accepted.' );

$blocked = $payload;
$blocked['environment_fingerprint'] = $environment;
$blocked['idempotency_key'] = 'c6-6b-published-block';
$blocked_result = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/apply', $blocked );
seo_geo_manager_theme_media_accept( 409 === $blocked_result['status'], 'Published strategic page changed without explicit approval.' );

$apply_payload = $payload;
$apply_payload['allow_published_target'] = true;
$apply_payload['environment_fingerprint'] = $environment;
$apply_payload['idempotency_key'] = 'c6-6b-hero-a';
$apply = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/apply', $apply_payload );
seo_geo_manager_theme_media_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Strategic-media Apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_theme_media_accept( 36 === strlen( $operation_id ), 'Strategic-media operation ID missing.' );
seo_geo_manager_theme_media_accept( $media_a === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Apply did not persist strategic media.' );
seo_geo_manager_theme_media_accept( $content_before === (string) get_post_field( 'post_content', $page_id ), 'Apply changed page content.' );

$read_a = seo_geo_manager_theme_media_request( 'GET', $base );
$fingerprint_a = (string) ( $read_a['data']['binding']['fingerprint'] ?? '' );
seo_geo_manager_theme_media_accept( $fingerprint_zero !== $fingerprint_a, 'Apply did not change strategic binding fingerprint.' );

$replay = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/apply', $apply_payload );
seo_geo_manager_theme_media_accept( 200 === $replay['status'] && $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'Idempotent strategic-media replay failed.' );
seo_geo_manager_theme_media_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'Strategic-media replay marker missing.' );

$rollback = seo_geo_manager_theme_media_request( 'POST', '/seo-geo-manager/v1/theme/media-bindings/changes/' . $operation_id . '/rollback', array( 'environment_fingerprint' => $environment ) );
seo_geo_manager_theme_media_accept( 200 === $rollback['status'] && 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'Strategic-media rollback failed.' );
seo_geo_manager_theme_media_accept( 0 === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Rollback did not restore Theme fallback.' );
seo_geo_manager_theme_media_accept( $fingerprint_zero === seo_geo_theme_strategic_media_fingerprint( $page_id, 'corporate-home-v1', 'hero-media' ), 'Rollback did not restore original fingerprint.' );

$apply_stale_payload = $apply_payload;
$apply_stale_payload['idempotency_key'] = 'c6-6b-stale-rollback';
$apply_stale = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/apply', $apply_stale_payload );
seo_geo_manager_theme_media_accept( 200 === $apply_stale['status'], 'Could not establish stale-rollback operation.' );
$stale_operation_id = (string) ( $apply_stale['data']['operation_id'] ?? '' );
seo_geo_manager_theme_media_accept( true === seo_geo_theme_set_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media', $media_b ), 'Could not simulate newer Theme edit.' );
$stale_rollback = seo_geo_manager_theme_media_request( 'POST', '/seo-geo-manager/v1/theme/media-bindings/changes/' . $stale_operation_id . '/rollback', array( 'environment_fingerprint' => $environment ) );
seo_geo_manager_theme_media_accept( 409 === $stale_rollback['status'], 'Stale strategic rollback overwrote a newer edit.' );
seo_geo_manager_theme_media_accept( $media_b === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Stale rollback changed the newer Theme edit.' );

$fingerprint_b = seo_geo_theme_strategic_media_fingerprint( $page_id, 'corporate-home-v1', 'hero-media' );
$remove_payload = array( 'attachment_id' => 0, 'expected_fingerprint' => $fingerprint_b, 'allow_published_target' => true, 'environment_fingerprint' => $environment, 'idempotency_key' => 'c6-6b-remove' );
$remove = seo_geo_manager_theme_media_request( 'POST', $base . '/changes/apply', $remove_payload );
seo_geo_manager_theme_media_accept( 200 === $remove['status'], 'Strategic binding removal failed.' );
$remove_operation_id = (string) ( $remove['data']['operation_id'] ?? '' );
seo_geo_manager_theme_media_accept( 0 === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Removal did not restore fallback.' );
$remove_rollback = seo_geo_manager_theme_media_request( 'POST', '/seo-geo-manager/v1/theme/media-bindings/changes/' . $remove_operation_id . '/rollback', array( 'environment_fingerprint' => $environment ) );
seo_geo_manager_theme_media_accept( 200 === $remove_rollback['status'], 'Removal rollback failed.' );
seo_geo_manager_theme_media_accept( $media_b === seo_geo_theme_strategic_media_slot( $page_id, 'corporate-home-v1', 'hero-media' ), 'Removal rollback did not restore image binding.' );
seo_geo_manager_theme_media_accept( $content_before === (string) get_post_field( 'post_content', $page_id ), 'Strategic media lifecycle changed page content.' );

echo wp_json_encode( array( 'ok' => true, 'plugin_version' => SEO_GEO_MANAGER_VERSION, 'page_id' => $page_id, 'operation_id' => $operation_id ) ) . PHP_EOL;
