<?php
/**
 * Runtime acceptance for C6.6A featured-media discovery and guarded assignment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_featured_media_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param array<string, mixed> $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_featured_media_request( string $method, string $route, array $payload = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );
	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/** @return int */
function seo_geo_manager_featured_media_image( string $filename, string $title ) {
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	seo_geo_manager_featured_media_accept( is_string( $bytes ), 'Could not decode featured-media PNG fixture.' );
	$upload = wp_upload_bits( $filename, null, $bytes );
	seo_geo_manager_featured_media_accept( empty( $upload['error'] ) && is_string( $upload['file'] ?? null ), 'Could not write featured-media image fixture.' );
	$file = (string) $upload['file'];
	$type = wp_check_filetype( $filename, null );
	$mime = isset( $type['type'] ) && is_string( $type['type'] ) ? $type['type'] : 'image/png';
	$id   = wp_insert_attachment(
		array(
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_mime_type' => $mime,
		),
		$file,
		0,
		true
	);
	seo_geo_manager_featured_media_accept( ! is_wp_error( $id ) && 0 < (int) $id, 'Could not create featured-media attachment.' );
	$id       = (int) $id;
	$metadata = wp_generate_attachment_metadata( $id, $file );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $id, $metadata );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title . ' alt' );
	return $id;
}

/** @return string */
function seo_geo_manager_featured_media_binary_hash( int $media_id ): string {
	$file = get_attached_file( $media_id, true );
	seo_geo_manager_featured_media_accept( is_string( $file ) && is_readable( $file ), 'Featured-media binary fixture is unreadable.' );
	$hash = hash_file( 'sha256', (string) $file );
	seo_geo_manager_featured_media_accept( is_string( $hash ), 'Could not hash featured-media binary fixture.' );
	return $hash;
}

/** @param mixed $value Response value. */
function seo_geo_manager_featured_media_assert_bounded( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( 'raw_attachment_metadata' === $key ) {
			seo_geo_manager_featured_media_accept( false === $child, 'Featured-media raw metadata policy must remain false.' );
			continue;
		}
		if ( is_string( $key ) ) {
			seo_geo_manager_featured_media_accept(
				! in_array( $key, array( '_wp_attachment_metadata', 'image_meta', 'exif' ), true ),
				'Featured-media response exposed raw/private attachment metadata.'
			);
		}
		seo_geo_manager_featured_media_assert_bounded( $child );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_featured_media_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'C6.6A Featured Media Resource',
		'post_content' => '<p>Stable body that featured-media operations must not change.</p>',
	),
	true
);
seo_geo_manager_featured_media_accept( ! is_wp_error( $post_id ) && 0 < (int) $post_id, 'Could not create C6.6A resource.' );
$post_id = (int) $post_id;

$media_a = seo_geo_manager_featured_media_image( 'c6-6a-a.png', 'C6.6A Image A' );
$media_b = seo_geo_manager_featured_media_image( 'c6-6a-b.png', 'C6.6A Image B' );
$media_c = seo_geo_manager_featured_media_image( 'c6-6a-c.png', 'C6.6A Image C' );
seo_geo_manager_featured_media_accept( false !== set_post_thumbnail( $post_id, $media_a ), 'Could not establish initial featured media.' );

$content_before = (string) get_post_field( 'post_content', $post_id );
$hash_a_before  = seo_geo_manager_featured_media_binary_hash( $media_a );
$hash_b_before  = seo_geo_manager_featured_media_binary_hash( $media_b );
$hash_c_before  = seo_geo_manager_featured_media_binary_hash( $media_c );

$snapshot = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_featured_media_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_featured_media_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$capabilities = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_featured_media_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'Could not read Manager capabilities.' );
seo_geo_manager_featured_media_accept( true === ( $capabilities['data']['capabilities']['featured_media_read']['available'] ?? false ), 'Featured-media read capability missing.' );
seo_geo_manager_featured_media_accept( true === ( $capabilities['data']['capabilities']['featured_media_write']['available'] ?? false ), 'Featured-media write capability missing.' );
seo_geo_manager_featured_media_accept( 'GET' === ( $capabilities['data']['operations']['content.featured_media.read']['method'] ?? '' ), 'Featured-media read operation missing.' );

$read = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( 200 === $read['status'] && is_array( $read['data'] ), 'C6.6A featured-media read failed.' );
seo_geo_manager_featured_media_accept( $media_a === (int) ( $read['data']['resource']['featured_media_id'] ?? 0 ), 'Initial featured-media readback mismatch.' );
seo_geo_manager_featured_media_accept( $media_a === (int) ( $read['data']['media']['id'] ?? 0 ), 'Initial featured image summary mismatch.' );
$fingerprint = (string) ( $read['data']['resource']['fingerprint'] ?? '' );
seo_geo_manager_featured_media_accept( 64 === strlen( $fingerprint ), 'Featured-media state fingerprint missing.' );
seo_geo_manager_featured_media_accept( 'resource.fingerprint' === ( $read['data']['policy']['expected_fingerprint_source'] ?? '' ), 'Featured-media read does not identify its Preview fingerprint source.' );
seo_geo_manager_featured_media_assert_bounded( $read['data'] );

$base_payload = array(
	'schema_version'       => 1,
	'media_id'             => $media_b,
	'expected_fingerprint' => $fingerprint,
);
$preview = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/preview', $base_payload );
seo_geo_manager_featured_media_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'C6.6A Preview failed.' );
seo_geo_manager_featured_media_accept( $media_a === (int) ( $preview['data']['change']['before_media_id'] ?? 0 ), 'C6.6A Preview before-media mismatch.' );
seo_geo_manager_featured_media_accept( $media_b === (int) ( $preview['data']['change']['after_media_id'] ?? 0 ), 'C6.6A Preview after-media mismatch.' );
seo_geo_manager_featured_media_accept( $media_a === (int) get_post_thumbnail_id( $post_id ), 'C6.6A Preview mutated WordPress.' );
seo_geo_manager_featured_media_accept( false === ( $preview['data']['policy']['binary_mutation_supported'] ?? true ), 'C6.6A Preview unexpectedly allows binary mutation.' );
seo_geo_manager_featured_media_accept( false === ( $preview['data']['policy']['content_mutation_supported'] ?? true ), 'C6.6A Preview unexpectedly allows content mutation.' );

$stale_payload                         = $base_payload;
$stale_payload['expected_fingerprint'] = str_repeat( 'a', 64 );
$stale = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/preview', $stale_payload );
seo_geo_manager_featured_media_accept( 409 === $stale['status'], 'C6.6A accepted a stale featured-media fingerprint.' );
seo_geo_manager_featured_media_accept( 'seo_geo_manager_featured_media_fingerprint_mismatch' === ( $stale['data']['code'] ?? '' ), 'Unexpected C6.6A stale fingerprint code.' );

$blocked_payload                            = $base_payload;
$blocked_payload['environment_fingerprint'] = $environment_fingerprint;
$blocked_payload['idempotency_key']         = 'c6-6a-blocked-published';
$blocked = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $blocked_payload );
seo_geo_manager_featured_media_accept( 409 === $blocked['status'], 'C6.6A mutated a published resource without explicit approval.' );
seo_geo_manager_featured_media_accept( 'seo_geo_manager_featured_media_public_approval_required' === ( $blocked['data']['code'] ?? '' ), 'Unexpected C6.6A published approval code.' );
seo_geo_manager_featured_media_accept( $media_a === (int) get_post_thumbnail_id( $post_id ), 'Blocked C6.6A Apply changed featured media.' );

$apply_payload                            = $base_payload;
$apply_payload['allow_published_target']  = true;
$apply_payload['environment_fingerprint'] = $environment_fingerprint;
$apply_payload['idempotency_key']         = 'c6-6a-safe-featured-media';
$apply = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $apply_payload );
seo_geo_manager_featured_media_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'C6.6A Apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_featured_media_accept( 36 === strlen( $operation_id ), 'C6.6A operation ID missing.' );
seo_geo_manager_featured_media_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'C6.6A operation status mismatch.' );
seo_geo_manager_featured_media_accept( $media_b === (int) get_post_thumbnail_id( $post_id ), 'C6.6A Apply did not assign the selected image.' );
seo_geo_manager_featured_media_accept( $content_before === (string) get_post_field( 'post_content', $post_id ), 'C6.6A Apply changed post content.' );
seo_geo_manager_featured_media_accept( $hash_a_before === seo_geo_manager_featured_media_binary_hash( $media_a ), 'C6.6A Apply changed previous media bytes.' );
seo_geo_manager_featured_media_accept( $hash_b_before === seo_geo_manager_featured_media_binary_hash( $media_b ), 'C6.6A Apply changed selected media bytes.' );

$verify = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/featured-media/changes/' . $operation_id . '/verify' );
seo_geo_manager_featured_media_accept( 200 === $verify['status'] && is_array( $verify['data'] ), 'C6.6A explicit Verify failed.' );
seo_geo_manager_featured_media_accept( true === ( $verify['data']['verified'] ?? false ), 'C6.6A explicit Verify did not confirm the operation.' );
seo_geo_manager_featured_media_accept( $media_b === (int) ( $verify['data']['featured_media_id'] ?? 0 ), 'C6.6A explicit Verify returned the wrong image.' );

$read_after = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( 200 === $read_after['status'] && is_array( $read_after['data'] ), 'C6.6A readback after Apply failed.' );
seo_geo_manager_featured_media_accept( $media_b === (int) ( $read_after['data']['resource']['featured_media_id'] ?? 0 ), 'C6.6A readback does not show applied media.' );
$after_fingerprint = (string) ( $read_after['data']['resource']['fingerprint'] ?? '' );
seo_geo_manager_featured_media_accept( $fingerprint !== $after_fingerprint, 'C6.6A assignment fingerprint did not change after Apply.' );
seo_geo_manager_featured_media_accept( $after_fingerprint === ( $verify['data']['current_fingerprint'] ?? '' ), 'C6.6A explicit Verify fingerprint differs from readback.' );

$replay = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $apply_payload );
seo_geo_manager_featured_media_accept( 200 === $replay['status'] && is_array( $replay['data'] ), 'C6.6A idempotent replay failed.' );
seo_geo_manager_featured_media_accept( $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'C6.6A replay returned another operation.' );
seo_geo_manager_featured_media_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'C6.6A replay marker missing.' );

$conflict_payload             = $apply_payload;
$conflict_payload['media_id'] = $media_a;
$conflict = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $conflict_payload );
seo_geo_manager_featured_media_accept( 409 === $conflict['status'], 'C6.6A reused an idempotency key with different media.' );
seo_geo_manager_featured_media_accept( 'seo_geo_manager_idempotency_conflict' === ( $conflict['data']['code'] ?? '' ), 'Unexpected C6.6A idempotency conflict code.' );

$rollback = seo_geo_manager_featured_media_request(
	'POST',
	'/seo-geo-manager/v1/content/featured-media/changes/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_featured_media_accept( 200 === $rollback['status'] && is_array( $rollback['data'] ), 'C6.6A rollback failed.' );
seo_geo_manager_featured_media_accept( 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'C6.6A rollback status mismatch.' );
seo_geo_manager_featured_media_accept( $media_a === (int) get_post_thumbnail_id( $post_id ), 'C6.6A rollback did not restore initial featured media.' );

$restored = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( $fingerprint === ( $restored['data']['resource']['fingerprint'] ?? '' ), 'C6.6A rollback did not restore original assignment fingerprint.' );

$remove_payload = array(
	'schema_version'          => 1,
	'media_action'            => 'remove',
	'media_id'                => 0,
	'expected_fingerprint'    => $fingerprint,
	'allow_published_target'  => true,
	'environment_fingerprint' => $environment_fingerprint,
	'idempotency_key'         => 'c6-6a-remove-featured-media',
);
$remove_preview = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/preview', $remove_payload );
seo_geo_manager_featured_media_accept( 200 === $remove_preview['status'] && is_array( $remove_preview['data'] ), 'C6.6A remove Preview failed.' );
seo_geo_manager_featured_media_accept( 'remove' === ( $remove_preview['data']['change']['action'] ?? '' ), 'C6.6A remove Preview action mismatch.' );
seo_geo_manager_featured_media_accept( $media_a === (int) ( $remove_preview['data']['change']['before_media_id'] ?? 0 ), 'C6.6A remove Preview before-media mismatch.' );
seo_geo_manager_featured_media_accept( 0 === (int) ( $remove_preview['data']['change']['after_media_id'] ?? -1 ), 'C6.6A remove Preview after-media mismatch.' );
seo_geo_manager_featured_media_accept( array_key_exists( 'media', $remove_preview['data'] ) && null === $remove_preview['data']['media'], 'C6.6A remove Preview fabricated a replacement image.' );
seo_geo_manager_featured_media_accept( $media_a === (int) get_post_thumbnail_id( $post_id ), 'C6.6A remove Preview mutated WordPress.' );

$remove_apply = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $remove_payload );
seo_geo_manager_featured_media_accept( 200 === $remove_apply['status'] && is_array( $remove_apply['data'] ), 'C6.6A remove Apply failed.' );
$remove_operation_id = (string) ( $remove_apply['data']['operation_id'] ?? '' );
seo_geo_manager_featured_media_accept( 36 === strlen( $remove_operation_id ), 'C6.6A remove operation ID missing.' );
seo_geo_manager_featured_media_accept( 'remove' === ( $remove_apply['data']['media_action'] ?? '' ), 'C6.6A remove operation action mismatch.' );
seo_geo_manager_featured_media_accept( 0 === (int) get_post_thumbnail_id( $post_id ), 'C6.6A remove Apply did not clear the featured-media relationship.' );
seo_geo_manager_featured_media_accept( $content_before === (string) get_post_field( 'post_content', $post_id ), 'C6.6A remove Apply changed post content.' );
seo_geo_manager_featured_media_accept( $hash_a_before === seo_geo_manager_featured_media_binary_hash( $media_a ), 'C6.6A remove Apply changed media A bytes.' );
seo_geo_manager_featured_media_accept( $hash_b_before === seo_geo_manager_featured_media_binary_hash( $media_b ), 'C6.6A remove Apply changed media B bytes.' );
seo_geo_manager_featured_media_accept( $hash_c_before === seo_geo_manager_featured_media_binary_hash( $media_c ), 'C6.6A remove Apply changed media C bytes.' );

$remove_verify = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/featured-media/changes/' . $remove_operation_id . '/verify' );
seo_geo_manager_featured_media_accept( 200 === $remove_verify['status'] && is_array( $remove_verify['data'] ), 'C6.6A remove Verify failed.' );
seo_geo_manager_featured_media_accept( true === ( $remove_verify['data']['verified'] ?? false ), 'C6.6A remove Verify did not confirm the operation.' );
seo_geo_manager_featured_media_accept( 0 === (int) ( $remove_verify['data']['featured_media_id'] ?? -1 ), 'C6.6A remove Verify returned a featured image.' );

$removed_readback = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( 200 === $removed_readback['status'] && is_array( $removed_readback['data'] ), 'C6.6A remove readback failed.' );
seo_geo_manager_featured_media_accept( 0 === (int) ( $removed_readback['data']['resource']['featured_media_id'] ?? -1 ), 'C6.6A remove readback retained a featured image.' );
seo_geo_manager_featured_media_accept( array_key_exists( 'media', $removed_readback['data'] ) && null === $removed_readback['data']['media'], 'C6.6A remove readback returned media details.' );

$remove_rollback = seo_geo_manager_featured_media_request(
	'POST',
	'/seo-geo-manager/v1/content/featured-media/changes/' . $remove_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_featured_media_accept( 200 === $remove_rollback['status'] && is_array( $remove_rollback['data'] ), 'C6.6A remove rollback failed.' );
seo_geo_manager_featured_media_accept( 'rolled-back' === ( $remove_rollback['data']['status'] ?? '' ), 'C6.6A remove rollback status mismatch.' );
seo_geo_manager_featured_media_accept( $media_a === (int) get_post_thumbnail_id( $post_id ), 'C6.6A remove rollback did not restore the original image.' );
$remove_restored = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( $fingerprint === ( $remove_restored['data']['resource']['fingerprint'] ?? '' ), 'C6.6A remove rollback did not restore the original fingerprint.' );

$stale_apply_payload = array(
	'schema_version'          => 1,
	'media_id'                => $media_b,
	'expected_fingerprint'    => $fingerprint,
	'allow_published_target'  => true,
	'environment_fingerprint' => $environment_fingerprint,
	'idempotency_key'         => 'c6-6a-stale-rollback',
);
$stale_apply = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $stale_apply_payload );
seo_geo_manager_featured_media_accept( 200 === $stale_apply['status'] && is_array( $stale_apply['data'] ), 'C6.6A stale-test Apply failed.' );
$stale_operation_id = (string) ( $stale_apply['data']['operation_id'] ?? '' );
seo_geo_manager_featured_media_accept( false !== set_post_thumbnail( $post_id, $media_c ), 'Could not simulate a newer human featured-media edit.' );
$stale_rollback = seo_geo_manager_featured_media_request(
	'POST',
	'/seo-geo-manager/v1/content/featured-media/changes/' . $stale_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_featured_media_accept( 409 === $stale_rollback['status'], 'C6.6A stale rollback overwrote a newer featured-media edit.' );
seo_geo_manager_featured_media_accept( 'seo_geo_manager_featured_media_rollback_stale' === ( $stale_rollback['data']['code'] ?? '' ), 'Unexpected C6.6A stale rollback code.' );
seo_geo_manager_featured_media_accept( $media_c === (int) get_post_thumbnail_id( $post_id ), 'C6.6A stale rollback changed the newer featured image.' );
seo_geo_manager_featured_media_accept( $hash_c_before === seo_geo_manager_featured_media_binary_hash( $media_c ), 'C6.6A stale scenario changed newer media bytes.' );

$subscriber_id = wp_insert_user(
	array(
		'user_login' => 'c66a-subscriber',
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => 'c66a-subscriber@example.test',
		'role'       => 'subscriber',
	)
);
seo_geo_manager_featured_media_accept( ! is_wp_error( $subscriber_id ) && 0 < (int) $subscriber_id, 'Could not create least-privilege subscriber.' );
wp_set_current_user( (int) $subscriber_id );
$subscriber_read = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media' );
seo_geo_manager_featured_media_accept( 403 === $subscriber_read['status'], 'Subscriber could inspect featured-media state.' );
$subscriber_apply = seo_geo_manager_featured_media_request( 'POST', '/seo-geo-manager/v1/content/' . $post_id . '/featured-media/changes/apply', $apply_payload );
seo_geo_manager_featured_media_accept( 403 === $subscriber_apply['status'], 'Subscriber could mutate featured media.' );
$subscriber_verify = seo_geo_manager_featured_media_request( 'GET', '/seo-geo-manager/v1/content/featured-media/changes/' . $stale_operation_id . '/verify' );
seo_geo_manager_featured_media_accept( 403 === $subscriber_verify['status'], 'Subscriber could verify privileged featured-media operations.' );

wp_set_current_user( 1 );
seo_geo_manager_featured_media_accept( $content_before === (string) get_post_field( 'post_content', $post_id ), 'C6.6A acceptance changed post content.' );
seo_geo_manager_featured_media_accept( $hash_a_before === seo_geo_manager_featured_media_binary_hash( $media_a ), 'C6.6A acceptance changed image A bytes.' );
seo_geo_manager_featured_media_accept( $hash_b_before === seo_geo_manager_featured_media_binary_hash( $media_b ), 'C6.6A acceptance changed image B bytes.' );
seo_geo_manager_featured_media_accept( $hash_c_before === seo_geo_manager_featured_media_binary_hash( $media_c ), 'C6.6A acceptance changed image C bytes.' );

echo (string) wp_json_encode(
	array(
		'ok'                  => true,
		'plugin_version'      => SEO_GEO_MANAGER_VERSION,
		'resource_id'         => $post_id,
		'final_media_id'      => (int) get_post_thumbnail_id( $post_id ),
		'operation_id'        => $operation_id,
		'remove_operation_id' => $remove_operation_id,
		'stale_operation_id'  => $stale_operation_id,
		'content_unchanged'   => true,
		'binaries_unchanged'  => true,
		'least_privilege_ok'  => true,
		'explicit_verify_ok'  => true,
		'remove_roundtrip_ok' => true,
	)
) . "\n";
