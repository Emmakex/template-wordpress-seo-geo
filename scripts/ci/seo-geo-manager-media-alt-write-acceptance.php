<?php
/**
 * Runtime acceptance for C6.3 guarded image-alt changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_c6_media_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param array<string, mixed> $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_c6_media_request( string $method, string $route, array $payload = array() ): array {
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

/**
 * Reject raw/private attachment metadata from public responses.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_c6_media_assert_bounded( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_c6_media_accept(
				! in_array( $key, array( '_wp_attachment_metadata', 'image_meta', 'exif', 'raw_attachment_metadata' ), true ),
				'C6.3 public response exposed raw/private attachment metadata.'
			);
		}
		seo_geo_manager_c6_media_assert_bounded( $child );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_c6_media_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$media_id = wp_insert_attachment(
	array(
		'post_title'     => 'C6.3 Alt Image',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/png',
		'guid'           => home_url( '/wp-content/uploads/c6-3-alt-image.png' ),
	),
	false,
	0,
	true
);
seo_geo_manager_c6_media_accept( ! is_wp_error( $media_id ) && 0 < (int) $media_id, 'Could not create C6.3 image attachment.' );
$media_id = (int) $media_id;
wp_update_attachment_metadata(
	$media_id,
	array(
		'width'  => 1200,
		'height' => 800,
		'file'   => 'c6-3-alt-image.png',
	)
);
delete_post_meta( $media_id, '_wp_attachment_image_alt' );

$metadata_before = wp_get_attachment_metadata( $media_id );
$title_before    = get_the_title( $media_id );

$snapshot = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_c6_media_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_c6_media_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$read = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( 200 === $read['status'] && is_array( $read['data'] ), 'Could not inspect C6.3 image.' );
$fingerprint = (string) ( $read['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_c6_media_accept( '' !== $fingerprint, 'C6.3 media fingerprint missing.' );
seo_geo_manager_c6_media_accept( false === metadata_exists( 'post', $media_id, '_wp_attachment_image_alt' ), 'C6.3 fixture unexpectedly has alt metadata.' );

$base_payload = array(
	'schema_version'       => 1,
	'expected_fingerprint' => $fingerprint,
	'alt'                  => 'Descriptive C6.3 image alt',
);

$preview = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/preview', $base_payload );
seo_geo_manager_c6_media_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'C6.3 Preview failed.' );
seo_geo_manager_c6_media_accept( true === ( $preview['data']['has_changes'] ?? false ), 'C6.3 Preview reported no change.' );
seo_geo_manager_c6_media_accept( '' === ( $preview['data']['before_alt'] ?? null ), 'C6.3 Preview before alt mismatch.' );
seo_geo_manager_c6_media_accept( 'Descriptive C6.3 image alt' === ( $preview['data']['after_alt'] ?? '' ), 'C6.3 Preview after alt mismatch.' );
seo_geo_manager_c6_media_accept( true === ( $preview['data']['policy']['alt_only'] ?? false ), 'C6.3 alt-only policy missing.' );
seo_geo_manager_c6_media_accept( true === ( $preview['data']['policy']['attachment_id_only'] ?? false ), 'C6.3 attachment-ID-only policy missing.' );
seo_geo_manager_c6_media_accept( false === ( $preview['data']['policy']['fabricated_metadata'] ?? true ), 'C6.3 fabrication policy mismatch.' );
seo_geo_manager_c6_media_accept( false === ( $preview['data']['policy']['binary_mutation_supported'] ?? true ), 'C6.3 unexpectedly exposes binary mutation.' );
seo_geo_manager_c6_media_accept( true === ( $preview['data']['policy']['public_media_blocked'] ?? false ), 'C6.3 public-media approval gate missing.' );
seo_geo_manager_c6_media_assert_bounded( $preview['data'] );

$stale_payload                         = $base_payload;
$stale_payload['expected_fingerprint'] = str_repeat( 'a', 64 );
$stale = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/preview', $stale_payload );
seo_geo_manager_c6_media_accept( 409 === $stale['status'], 'C6.3 accepted a stale media fingerprint.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_alt_fingerprint_mismatch' === ( $stale['data']['code'] ?? '' ), 'Unexpected stale media rejection code.' );

$blocked_apply                            = $base_payload;
$blocked_apply['environment_fingerprint'] = $environment_fingerprint;
$blocked_apply['idempotency_key']         = 'c6-3-public-approval-block';
$blocked = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply', $blocked_apply );
seo_geo_manager_c6_media_accept( 409 === $blocked['status'], 'C6.3 mutated media without explicit public approval.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_alt_public_approval_required' === ( $blocked['data']['code'] ?? '' ), 'Unexpected public-media approval rejection code.' );
seo_geo_manager_c6_media_accept( false === metadata_exists( 'post', $media_id, '_wp_attachment_image_alt' ), 'Blocked C6.3 Apply changed alt metadata.' );

$apply_payload                            = $base_payload;
$apply_payload['allow_public_media']      = true;
$apply_payload['environment_fingerprint'] = $environment_fingerprint;
$apply_payload['idempotency_key']         = 'c6-3-safe-alt-apply';
$apply = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply', $apply_payload );
seo_geo_manager_c6_media_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'C6.3 Apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_c6_media_accept( 36 === strlen( $operation_id ), 'C6.3 operation ID missing.' );
seo_geo_manager_c6_media_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'C6.3 operation status mismatch.' );
seo_geo_manager_c6_media_accept( 'Descriptive C6.3 image alt' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'C6.3 Apply did not store alt text.' );
seo_geo_manager_c6_media_accept( $metadata_before === wp_get_attachment_metadata( $media_id ), 'C6.3 Apply changed attachment metadata.' );
seo_geo_manager_c6_media_accept( $title_before === get_the_title( $media_id ), 'C6.3 Apply changed attachment title.' );
seo_geo_manager_c6_media_assert_bounded( $apply['data'] );

$read_after = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( 200 === $read_after['status'] && is_array( $read_after['data'] ), 'C6.3 re-read after Apply failed.' );
$after_fingerprint = (string) ( $read_after['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_c6_media_accept( $fingerprint !== $after_fingerprint, 'C6.3 media fingerprint did not change after alt Apply.' );
seo_geo_manager_c6_media_accept( 'Descriptive C6.3 image alt' === ( $read_after['data']['media']['alt'] ?? '' ), 'C6.3 readback alt mismatch.' );

$replay = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply', $apply_payload );
seo_geo_manager_c6_media_accept( 200 === $replay['status'] && is_array( $replay['data'] ), 'C6.3 idempotent replay failed.' );
seo_geo_manager_c6_media_accept( $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'C6.3 replay returned another operation.' );
seo_geo_manager_c6_media_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'C6.3 replay marker missing.' );

$conflict_payload        = $apply_payload;
$conflict_payload['alt'] = 'Different C6.3 alt';
$conflict = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply', $conflict_payload );
seo_geo_manager_c6_media_accept( 409 === $conflict['status'], 'C6.3 reused an idempotency key with a different payload.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_idempotency_conflict' === ( $conflict['data']['code'] ?? '' ), 'Unexpected media idempotency conflict code.' );

$rollback = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/alt/changes/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_media_accept( 200 === $rollback['status'] && is_array( $rollback['data'] ), 'C6.3 rollback failed.' );
seo_geo_manager_c6_media_accept( 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'C6.3 rollback status mismatch.' );
seo_geo_manager_c6_media_accept( false === metadata_exists( 'post', $media_id, '_wp_attachment_image_alt' ), 'C6.3 rollback did not restore missing-alt state exactly.' );

$read_restored = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( $fingerprint === ( $read_restored['data']['media']['fingerprint'] ?? '' ), 'C6.3 rollback did not restore original fingerprint.' );

update_post_meta( $media_id, '_wp_attachment_image_alt', 'Baseline alt before stale test' );
$stale_source = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
$stale_base_fingerprint = (string) ( $stale_source['data']['media']['fingerprint'] ?? '' );
$stale_apply_payload = array(
	'schema_version'          => 1,
	'expected_fingerprint'    => $stale_base_fingerprint,
	'alt'                     => 'Applied alt before human edit',
	'allow_public_media'      => true,
	'environment_fingerprint' => $environment_fingerprint,
	'idempotency_key'         => 'c6-3-stale-rollback',
);
$stale_apply = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply', $stale_apply_payload );
seo_geo_manager_c6_media_accept( 200 === $stale_apply['status'] && is_array( $stale_apply['data'] ), 'C6.3 stale-test Apply failed.' );
$stale_operation_id = (string) ( $stale_apply['data']['operation_id'] ?? '' );
update_post_meta( $media_id, '_wp_attachment_image_alt', 'Human edit after Manager Apply' );
$stale_rollback = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/alt/changes/' . $stale_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_media_accept( 409 === $stale_rollback['status'], 'C6.3 stale rollback overwrote a newer human edit.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_alt_rollback_stale' === ( $stale_rollback['data']['code'] ?? '' ), 'Unexpected stale media rollback code.' );
seo_geo_manager_c6_media_accept( 'Human edit after Manager Apply' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'C6.3 stale rollback changed the newer human alt.' );

$non_image_id = wp_insert_attachment(
	array(
		'post_title'     => 'C6.3 Non Image',
		'post_status'    => 'inherit',
		'post_mime_type' => 'text/plain',
		'guid'           => home_url( '/wp-content/uploads/c6-3-non-image.txt' ),
	),
	false,
	0,
	true
);
seo_geo_manager_c6_media_accept( ! is_wp_error( $non_image_id ) && 0 < (int) $non_image_id, 'Could not create C6.3 non-image fixture.' );
$non_image_preview = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/' . (int) $non_image_id . '/alt/changes/preview',
	array(
		'schema_version'       => 1,
		'expected_fingerprint' => str_repeat( 'b', 64 ),
		'alt'                  => 'Should not apply',
	)
);
seo_geo_manager_c6_media_accept( 404 === $non_image_preview['status'], 'C6.3 accepted a non-image attachment.' );

$capabilities = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_c6_media_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'C6.3 capability discovery failed.' );
seo_geo_manager_c6_media_accept( true === ( $capabilities['data']['capabilities']['media_alt_write']['available'] ?? false ), 'Media alt write capability missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/alt/changes/preview' === ( $capabilities['data']['operations']['media.alt.preview']['path'] ?? '' ), 'Media alt Preview operation missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/alt/changes/apply' === ( $capabilities['data']['operations']['media.alt.apply']['path'] ?? '' ), 'Media alt Apply operation missing.' );
seo_geo_manager_c6_media_accept( '/media/alt/changes/{operation_id}/rollback' === ( $capabilities['data']['operations']['media.alt.rollback']['path'] ?? '' ), 'Media alt rollback operation missing.' );

$subscriber_id = wp_create_user( 'manager-c6-media-subscriber', wp_generate_password( 32, true, true ), 'manager-c6-media-subscriber@example.test' );
seo_geo_manager_c6_media_accept( ! is_wp_error( $subscriber_id ), 'Could not create C6.3 subscriber fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$denied = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/preview', $base_payload );
seo_geo_manager_c6_media_accept( 403 === $denied['status'], 'Subscriber incorrectly received media alt Preview access.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                          => true,
		'plugin_version'              => SEO_GEO_MANAGER_VERSION,
		'alt_only'                    => true,
		'attachment_id_only'          => true,
		'public_approval_guard'       => true,
		'fingerprint_guard'           => true,
		'idempotent_apply'            => true,
		'idempotency_conflict_guard'  => true,
		'exact_missing_alt_rollback'  => true,
		'stale_rollback_guard'        => true,
		'binary_mutation_exposed'     => false,
		'fabricated_metadata'         => false,
		'least_privilege_enforced'    => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
