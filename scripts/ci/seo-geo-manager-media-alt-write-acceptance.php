<?php
/**
 * Runtime acceptance for C6.3/C6.4 guarded image media changes.
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
				'C6 public response exposed raw/private attachment metadata.'
			);
		}
		seo_geo_manager_c6_media_assert_bounded( $child );
	}
}

/**
 * Keep private rollback field snapshots out of REST responses.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_c6_media_assert_no_context_snapshots( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_c6_media_accept(
				! in_array( $key, array( 'before_fields', 'after_fields' ), true ),
				'C6.4 public response exposed private rollback field snapshots.'
			);
		}
		seo_geo_manager_c6_media_assert_no_context_snapshots( $child );
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
seo_geo_manager_c6_media_accept( ! is_wp_error( $media_id ) && 0 < (int) $media_id, 'Could not create C6 image attachment.' );
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

$apply_payload                             = $base_payload;
$apply_payload['allow_public_media']       = true;
$apply_payload['environment_fingerprint']  = $environment_fingerprint;
$apply_payload['idempotency_key']          = 'c6-3-safe-alt-apply';
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
$stale_source           = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
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

/* C6.4 — guarded title/caption/description context. */
$context_alt_before      = (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true );
$context_metadata_before = wp_get_attachment_metadata( $media_id );
$context_post_before     = get_post( $media_id );
seo_geo_manager_c6_media_accept( $context_post_before instanceof WP_Post, 'Could not load C6.4 context baseline.' );
$context_title_before       = (string) $context_post_before->post_title;
$context_caption_before     = (string) $context_post_before->post_excerpt;
$context_description_before = (string) $context_post_before->post_content;

$context_read = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( 200 === $context_read['status'] && is_array( $context_read['data'] ), 'C6.4 media read failed.' );
$context_fingerprint = (string) ( $context_read['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_c6_media_accept( '' !== $context_fingerprint, 'C6.4 fingerprint missing.' );
seo_geo_manager_c6_media_accept( $context_caption_before === ( $context_read['data']['media']['caption'] ?? null ), 'C6.4 caption read mismatch.' );
seo_geo_manager_c6_media_accept( $context_description_before === ( $context_read['data']['media']['description'] ?? null ), 'C6.4 description read mismatch.' );
seo_geo_manager_c6_media_accept( true === ( $context_read['data']['policy']['context_mutation_available'] ?? false ), 'C6.4 context mutation availability missing.' );
seo_geo_manager_c6_media_accept( false === ( $context_read['data']['policy']['binary_mutation_supported'] ?? true ), 'C6.4 reader unexpectedly exposes binary mutation.' );

$context_fields = array(
	'title'       => 'C6.4 Editorial Image',
	'caption'     => 'C6.4 editorial caption',
	'description' => '<p>C6.4 editorial description with <strong>context</strong>.</p>',
);
$context_base = array(
	'schema_version'       => 1,
	'expected_fingerprint' => $context_fingerprint,
	'fields'               => $context_fields,
);

$context_preview = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/preview', $context_base );
seo_geo_manager_c6_media_accept( 200 === $context_preview['status'] && is_array( $context_preview['data'] ), 'C6.4 Preview failed.' );
seo_geo_manager_c6_media_accept( true === ( $context_preview['data']['has_changes'] ?? false ), 'C6.4 Preview reported no changes.' );
seo_geo_manager_c6_media_accept( true === ( $context_preview['data']['policy']['context_fields_only'] ?? false ), 'C6.4 context-only policy missing.' );
seo_geo_manager_c6_media_accept( false === ( $context_preview['data']['policy']['binary_mutation_supported'] ?? true ), 'C6.4 Preview unexpectedly exposes binary mutation.' );
seo_geo_manager_c6_media_accept( $context_title_before === ( $context_preview['data']['changes']['title']['before'] ?? null ), 'C6.4 title before diff mismatch.' );
seo_geo_manager_c6_media_accept( $context_fields['title'] === ( $context_preview['data']['changes']['title']['after'] ?? null ), 'C6.4 title after diff mismatch.' );
seo_geo_manager_c6_media_accept( $context_fields['caption'] === ( $context_preview['data']['changes']['caption']['after'] ?? null ), 'C6.4 caption after diff mismatch.' );
seo_geo_manager_c6_media_accept( $context_fields['description'] === ( $context_preview['data']['changes']['description']['after'] ?? null ), 'C6.4 description after diff mismatch.' );
seo_geo_manager_c6_media_assert_bounded( $context_preview['data'] );

$context_unsupported           = $context_base;
$context_unsupported['fields'] = array( 'filename' => 'forbidden.png' );
$unsupported = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/preview', $context_unsupported );
seo_geo_manager_c6_media_accept( 400 === $unsupported['status'], 'C6.4 accepted an unsupported media field.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_context_field_unsupported' === ( $unsupported['data']['code'] ?? '' ), 'Unexpected unsupported-field rejection code.' );

$context_stale                         = $context_base;
$context_stale['expected_fingerprint'] = str_repeat( 'c', 64 );
$context_stale_response = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/preview', $context_stale );
seo_geo_manager_c6_media_accept( 409 === $context_stale_response['status'], 'C6.4 accepted a stale context fingerprint.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_context_fingerprint_mismatch' === ( $context_stale_response['data']['code'] ?? '' ), 'Unexpected C6.4 stale fingerprint code.' );

$context_blocked                            = $context_base;
$context_blocked['environment_fingerprint'] = $environment_fingerprint;
$context_blocked['idempotency_key']         = 'c6-4-public-approval-block';
$blocked_context = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/apply', $context_blocked );
seo_geo_manager_c6_media_accept( 409 === $blocked_context['status'], 'C6.4 mutated context without explicit public approval.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_context_public_approval_required' === ( $blocked_context['data']['code'] ?? '' ), 'Unexpected C6.4 public approval rejection code.' );
seo_geo_manager_c6_media_accept( $context_title_before === get_the_title( $media_id ), 'Blocked C6.4 Apply changed title.' );

$context_apply_payload                             = $context_base;
$context_apply_payload['allow_public_media']       = true;
$context_apply_payload['environment_fingerprint']  = $environment_fingerprint;
$context_apply_payload['idempotency_key']          = 'c6-4-safe-context-apply';
$context_apply = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/apply', $context_apply_payload );
seo_geo_manager_c6_media_accept( 200 === $context_apply['status'] && is_array( $context_apply['data'] ), 'C6.4 Apply failed.' );
$context_operation_id = (string) ( $context_apply['data']['operation_id'] ?? '' );
seo_geo_manager_c6_media_accept( 36 === strlen( $context_operation_id ), 'C6.4 operation ID missing.' );
seo_geo_manager_c6_media_accept( 'applied' === ( $context_apply['data']['status'] ?? '' ), 'C6.4 operation status mismatch.' );
seo_geo_manager_c6_media_assert_no_context_snapshots( $context_apply['data'] );
seo_geo_manager_c6_media_accept( $context_fields['title'] === get_the_title( $media_id ), 'C6.4 Apply did not store title.' );
seo_geo_manager_c6_media_accept( $context_fields['caption'] === (string) get_post_field( 'post_excerpt', $media_id ), 'C6.4 Apply did not store caption.' );
seo_geo_manager_c6_media_accept( $context_fields['description'] === (string) get_post_field( 'post_content', $media_id ), 'C6.4 Apply did not store description.' );
seo_geo_manager_c6_media_accept( $context_alt_before === (string) get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'C6.4 Apply changed alt.' );
seo_geo_manager_c6_media_accept( $context_metadata_before === wp_get_attachment_metadata( $media_id ), 'C6.4 Apply changed attachment metadata.' );

$context_read_after = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( 200 === $context_read_after['status'] && is_array( $context_read_after['data'] ), 'C6.4 re-read failed.' );
$context_after_fingerprint = (string) ( $context_read_after['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_c6_media_accept( $context_fingerprint !== $context_after_fingerprint, 'C6.4 fingerprint did not change after context Apply.' );
seo_geo_manager_c6_media_accept( $context_fields['caption'] === ( $context_read_after['data']['media']['caption'] ?? null ), 'C6.4 caption readback mismatch.' );
seo_geo_manager_c6_media_accept( $context_fields['description'] === ( $context_read_after['data']['media']['description'] ?? null ), 'C6.4 description readback mismatch.' );

$context_replay = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/apply', $context_apply_payload );
seo_geo_manager_c6_media_accept( 200 === $context_replay['status'] && is_array( $context_replay['data'] ), 'C6.4 idempotent replay failed.' );
seo_geo_manager_c6_media_accept( $context_operation_id === ( $context_replay['data']['operation_id'] ?? '' ), 'C6.4 replay returned another operation.' );
seo_geo_manager_c6_media_accept( true === ( $context_replay['data']['idempotent_replay'] ?? false ), 'C6.4 replay marker missing.' );
seo_geo_manager_c6_media_assert_no_context_snapshots( $context_replay['data'] );

$context_conflict                    = $context_apply_payload;
$context_conflict['fields']['title'] = 'Different C6.4 title';
$context_conflict_response = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/apply', $context_conflict );
seo_geo_manager_c6_media_accept( 409 === $context_conflict_response['status'], 'C6.4 reused an idempotency key with a different payload.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_idempotency_conflict' === ( $context_conflict_response['data']['code'] ?? '' ), 'Unexpected C6.4 idempotency conflict code.' );

$context_rollback = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/context/changes/' . $context_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_media_accept( 200 === $context_rollback['status'] && is_array( $context_rollback['data'] ), 'C6.4 rollback failed.' );
seo_geo_manager_c6_media_accept( 'rolled-back' === ( $context_rollback['data']['status'] ?? '' ), 'C6.4 rollback status mismatch.' );
seo_geo_manager_c6_media_assert_no_context_snapshots( $context_rollback['data'] );
seo_geo_manager_c6_media_accept( $context_title_before === get_the_title( $media_id ), 'C6.4 rollback did not restore title.' );
seo_geo_manager_c6_media_accept( $context_caption_before === (string) get_post_field( 'post_excerpt', $media_id ), 'C6.4 rollback did not restore caption.' );
seo_geo_manager_c6_media_accept( $context_description_before === (string) get_post_field( 'post_content', $media_id ), 'C6.4 rollback did not restore description.' );
$context_read_restored = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_media_accept( $context_fingerprint === ( $context_read_restored['data']['media']['fingerprint'] ?? '' ), 'C6.4 rollback did not restore original semantic fingerprint.' );

wp_update_post(
	array(
		'ID'           => $media_id,
		'post_title'   => 'C6.4 stale baseline title',
		'post_excerpt' => 'C6.4 stale baseline caption',
	)
);
$context_stale_source = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
$context_stale_fingerprint = (string) ( $context_stale_source['data']['media']['fingerprint'] ?? '' );
$context_stale_apply_payload = array(
	'schema_version'          => 1,
	'expected_fingerprint'    => $context_stale_fingerprint,
	'fields'                  => array( 'title' => 'C6.4 Manager title before human edit' ),
	'allow_public_media'      => true,
	'environment_fingerprint' => $environment_fingerprint,
	'idempotency_key'         => 'c6-4-stale-rollback',
);
$context_stale_apply = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/apply', $context_stale_apply_payload );
seo_geo_manager_c6_media_accept( 200 === $context_stale_apply['status'] && is_array( $context_stale_apply['data'] ), 'C6.4 stale-test Apply failed.' );
$context_stale_operation_id = (string) ( $context_stale_apply['data']['operation_id'] ?? '' );
wp_update_post( array( 'ID' => $media_id, 'post_title' => 'Human C6.4 edit after Manager Apply' ) );
$context_stale_rollback = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/context/changes/' . $context_stale_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_media_accept( 409 === $context_stale_rollback['status'], 'C6.4 stale rollback overwrote a newer human edit.' );
seo_geo_manager_c6_media_accept( 'seo_geo_manager_media_context_rollback_stale' === ( $context_stale_rollback['data']['code'] ?? '' ), 'Unexpected C6.4 stale rollback code.' );
seo_geo_manager_c6_media_accept( 'Human C6.4 edit after Manager Apply' === get_the_title( $media_id ), 'C6.4 stale rollback changed the newer human title.' );

$non_image_id = wp_insert_attachment(
	array(
		'post_title'     => 'C6 Non Image',
		'post_status'    => 'inherit',
		'post_mime_type' => 'text/plain',
		'guid'           => home_url( '/wp-content/uploads/c6-non-image.txt' ),
	),
	false,
	0,
	true
);
seo_geo_manager_c6_media_accept( ! is_wp_error( $non_image_id ) && 0 < (int) $non_image_id, 'Could not create C6 non-image fixture.' );
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
$non_image_context = seo_geo_manager_c6_media_request(
	'POST',
	'/seo-geo-manager/v1/media/' . (int) $non_image_id . '/context/changes/preview',
	array(
		'schema_version'       => 1,
		'expected_fingerprint' => str_repeat( 'd', 64 ),
		'fields'               => array( 'title' => 'Should not apply' ),
	)
);
seo_geo_manager_c6_media_accept( 404 === $non_image_context['status'], 'C6.4 accepted a non-image attachment.' );

$capabilities = seo_geo_manager_c6_media_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_c6_media_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'C6 capability discovery failed.' );
seo_geo_manager_c6_media_accept( true === ( $capabilities['data']['capabilities']['media_alt_write']['available'] ?? false ), 'Media alt write capability missing.' );
seo_geo_manager_c6_media_accept( true === ( $capabilities['data']['capabilities']['media_context_write']['available'] ?? false ), 'Media context write capability missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/alt/changes/preview' === ( $capabilities['data']['operations']['media.alt.preview']['path'] ?? '' ), 'Media alt Preview operation missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/alt/changes/apply' === ( $capabilities['data']['operations']['media.alt.apply']['path'] ?? '' ), 'Media alt Apply operation missing.' );
seo_geo_manager_c6_media_accept( '/media/alt/changes/{operation_id}/rollback' === ( $capabilities['data']['operations']['media.alt.rollback']['path'] ?? '' ), 'Media alt rollback operation missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/context/changes/preview' === ( $capabilities['data']['operations']['media.context.preview']['path'] ?? '' ), 'Media context Preview operation missing.' );
seo_geo_manager_c6_media_accept( '/media/{media_id}/context/changes/apply' === ( $capabilities['data']['operations']['media.context.apply']['path'] ?? '' ), 'Media context Apply operation missing.' );
seo_geo_manager_c6_media_accept( '/media/context/changes/{operation_id}/rollback' === ( $capabilities['data']['operations']['media.context.rollback']['path'] ?? '' ), 'Media context rollback operation missing.' );

$subscriber_id = wp_create_user( 'manager-c6-media-subscriber', wp_generate_password( 32, true, true ), 'manager-c6-media-subscriber@example.test' );
seo_geo_manager_c6_media_accept( ! is_wp_error( $subscriber_id ), 'Could not create C6 subscriber fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$denied = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/preview', $base_payload );
seo_geo_manager_c6_media_accept( 403 === $denied['status'], 'Subscriber incorrectly received media alt Preview access.' );
$context_denied = seo_geo_manager_c6_media_request( 'POST', '/seo-geo-manager/v1/media/' . $media_id . '/context/changes/preview', $context_base );
seo_geo_manager_c6_media_accept( 403 === $context_denied['status'], 'Subscriber incorrectly received media context Preview access.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                                => true,
		'plugin_version'                    => SEO_GEO_MANAGER_VERSION,
		'alt_only'                          => true,
		'attachment_id_only'                => true,
		'public_approval_guard'             => true,
		'fingerprint_guard'                 => true,
		'idempotent_apply'                  => true,
		'idempotency_conflict_guard'        => true,
		'exact_missing_alt_rollback'        => true,
		'stale_rollback_guard'              => true,
		'context_fields_guarded'            => true,
		'context_private_snapshots_returned'=> false,
		'context_exact_rollback'            => true,
		'context_stale_rollback_guard'      => true,
		'binary_mutation_exposed'           => false,
		'fabricated_metadata'               => false,
		'least_privilege_enforced'          => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
