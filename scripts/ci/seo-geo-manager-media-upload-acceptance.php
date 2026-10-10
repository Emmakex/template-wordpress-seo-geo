<?php
/**
 * Runtime acceptance for C6.5 guarded direct image uploads.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_c6_upload_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @return array<string, mixed>
 */
function seo_geo_manager_c6_upload_image_file(): array {
	$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	seo_geo_manager_c6_upload_accept( is_string( $bytes ) && '' !== $bytes, 'Could not decode C6.5 PNG fixture.' );

	$tmp = wp_tempnam( 'c6-5-direct.png' );
	seo_geo_manager_c6_upload_accept( is_string( $tmp ) && '' !== $tmp, 'Could not create C6.5 PNG temp file.' );
	seo_geo_manager_c6_upload_accept( false !== file_put_contents( $tmp, $bytes ), 'Could not write C6.5 PNG fixture.' );

	return array(
		'name'     => 'c6-5-direct.png',
		'type'     => 'image/png',
		'tmp_name' => $tmp,
		'error'    => UPLOAD_ERR_OK,
		'size'     => strlen( $bytes ),
	);
}

/**
 * @return array<string, mixed>
 */
function seo_geo_manager_c6_upload_text_file(): array {
	$tmp = wp_tempnam( 'c6-5-not-image.txt' );
	seo_geo_manager_c6_upload_accept( is_string( $tmp ) && '' !== $tmp, 'Could not create C6.5 text temp file.' );
	$bytes = 'C6.5 is not an image.';
	seo_geo_manager_c6_upload_accept( false !== file_put_contents( $tmp, $bytes ), 'Could not write C6.5 text fixture.' );

	return array(
		'name'     => 'c6-5-not-image.txt',
		'type'     => 'text/plain',
		'tmp_name' => $tmp,
		'error'    => UPLOAD_ERR_OK,
		'size'     => strlen( $bytes ),
	);
}

/**
 * @param array<string, mixed> $file File params.
 */
function seo_geo_manager_c6_upload_cleanup_file( array $file ): void {
	$tmp = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	if ( '' !== $tmp && is_file( $tmp ) ) {
		unlink( $tmp );
	}
}

/**
 * @param array<string, mixed>      $payload Request body params.
 * @param array<string, mixed>|null $file Optional multipart file.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_c6_upload_request( string $method, string $route, array $payload = array(), ?array $file = null ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $payload ) {
		$request->set_body_params( $payload );
	}
	if ( is_array( $file ) ) {
		$request->set_file_params( array( 'file' => $file ) );
	}
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * @param mixed $value Response value.
 */
function seo_geo_manager_c6_upload_assert_bounded( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}

	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_c6_upload_accept(
				! in_array( $key, array( 'tmp_name', 'raw_bytes', 'contents', 'source_path', 'local_path' ), true ),
				'C6.5 response exposed local/raw upload data.'
			);
		}
		seo_geo_manager_c6_upload_assert_bounded( $child );
	}
}

function seo_geo_manager_c6_upload_attachment_count(): int {
	$query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	return count( $query->posts );
}

wp_set_current_user( 1 );
seo_geo_manager_c6_upload_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );
seo_geo_manager_c6_upload_accept( current_user_can( 'upload_files' ), 'Acceptance administrator cannot upload files.' );

$snapshot = seo_geo_manager_c6_upload_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_c6_upload_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_c6_upload_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$base_payload = array(
	'schema_version' => 1,
	'title'          => 'C6.5 Direct Upload',
	'alt'            => 'C6.5 descriptive alt',
	'caption'        => 'C6.5 caption',
	'description'    => '<p>C6.5 description</p>',
	'parent_id'      => 0,
);

$before_count = seo_geo_manager_c6_upload_attachment_count();
$preview_file = seo_geo_manager_c6_upload_image_file();
$preview      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/preview', $base_payload, $preview_file );
seo_geo_manager_c6_upload_cleanup_file( $preview_file );
seo_geo_manager_c6_upload_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'C6.5 Preview failed.' );
seo_geo_manager_c6_upload_accept( true === ( $preview['data']['will_create'] ?? false ), 'C6.5 Preview did not declare creation.' );
seo_geo_manager_c6_upload_accept( 'media-upload' === ( $preview['data']['operation_type'] ?? '' ), 'C6.5 Preview operation type mismatch.' );
seo_geo_manager_c6_upload_accept( true === ( $preview['data']['policy']['direct_upload_only'] ?? false ), 'C6.5 direct-upload policy missing.' );
seo_geo_manager_c6_upload_accept( false === ( $preview['data']['policy']['remote_fetch_supported'] ?? true ), 'C6.5 unexpectedly exposes remote fetch.' );
seo_geo_manager_c6_upload_accept( false === ( $preview['data']['policy']['rollback_supported'] ?? true ), 'C6.5 unexpectedly exposes upload rollback.' );
seo_geo_manager_c6_upload_accept( false === ( $preview['data']['policy']['existing_binary_replacement_supported'] ?? true ), 'C6.5 unexpectedly exposes binary replacement.' );
seo_geo_manager_c6_upload_accept( 'image/png' === ( $preview['data']['resource']['file']['mime_type'] ?? '' ), 'C6.5 Preview MIME mismatch.' );
$preview_hash = (string) ( $preview['data']['resource']['file']['sha256'] ?? '' );
seo_geo_manager_c6_upload_accept( 1 === preg_match( '/^[a-f0-9]{64}$/', $preview_hash ), 'C6.5 Preview SHA-256 missing.' );
seo_geo_manager_c6_upload_accept( 0 < (int) ( $preview['data']['resource']['file']['dimensions']['width'] ?? 0 ), 'C6.5 Preview width missing.' );
seo_geo_manager_c6_upload_accept( 0 < (int) ( $preview['data']['resource']['file']['dimensions']['height'] ?? 0 ), 'C6.5 Preview height missing.' );
seo_geo_manager_c6_upload_assert_bounded( $preview['data'] );
seo_geo_manager_c6_upload_accept( $before_count === seo_geo_manager_c6_upload_attachment_count(), 'C6.5 Preview created an attachment.' );

$blocked_payload = array_merge(
	$base_payload,
	array(
		'expected_file_sha256'  => $preview_hash,
		'environment_fingerprint' => $environment_fingerprint,
		'idempotency_key'       => 'c6-5-blocked-public-upload',
	)
);
$blocked_file = seo_geo_manager_c6_upload_image_file();
$blocked      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/apply', $blocked_payload, $blocked_file );
seo_geo_manager_c6_upload_cleanup_file( $blocked_file );
seo_geo_manager_c6_upload_accept( 409 === $blocked['status'], 'C6.5 created media without explicit public approval.' );
seo_geo_manager_c6_upload_accept( 'seo_geo_manager_media_upload_public_approval_required' === ( $blocked['data']['code'] ?? '' ), 'Unexpected C6.5 public approval rejection code.' );
seo_geo_manager_c6_upload_accept( $before_count === seo_geo_manager_c6_upload_attachment_count(), 'Blocked C6.5 Apply created an attachment.' );

$wrong_hash_payload = array_merge(
	$base_payload,
	array(
		'expected_file_sha256'   => str_repeat( 'a', 64 ),
		'allow_public_media'      => true,
		'environment_fingerprint' => $environment_fingerprint,
		'idempotency_key'         => 'c6-5-wrong-hash',
	)
);
$wrong_hash_file = seo_geo_manager_c6_upload_image_file();
$wrong_hash      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/apply', $wrong_hash_payload, $wrong_hash_file );
seo_geo_manager_c6_upload_cleanup_file( $wrong_hash_file );
seo_geo_manager_c6_upload_accept( 409 === $wrong_hash['status'], 'C6.5 accepted a file that did not match Preview SHA-256.' );
seo_geo_manager_c6_upload_accept( 'seo_geo_manager_media_upload_hash_mismatch' === ( $wrong_hash['data']['code'] ?? '' ), 'Unexpected C6.5 hash mismatch code.' );
seo_geo_manager_c6_upload_accept( $before_count === seo_geo_manager_c6_upload_attachment_count(), 'Hash-mismatch C6.5 Apply created an attachment.' );

$apply_payload = array_merge(
	$base_payload,
	array(
		'expected_file_sha256'   => $preview_hash,
		'allow_public_media'      => true,
		'environment_fingerprint' => $environment_fingerprint,
		'idempotency_key'         => 'c6-5-direct-upload',
	)
);
$apply_file = seo_geo_manager_c6_upload_image_file();
$apply      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/apply', $apply_payload, $apply_file );
seo_geo_manager_c6_upload_cleanup_file( $apply_file );
seo_geo_manager_c6_upload_accept( 201 === $apply['status'] && is_array( $apply['data'] ), 'C6.5 Apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
$media_id     = (int) ( $apply['data']['target_id'] ?? 0 );
seo_geo_manager_c6_upload_accept( 36 === strlen( $operation_id ), 'C6.5 operation ID missing.' );
seo_geo_manager_c6_upload_accept( 0 < $media_id, 'C6.5 attachment ID missing.' );
seo_geo_manager_c6_upload_accept( 'created' === ( $apply['data']['status'] ?? '' ), 'C6.5 operation status mismatch.' );
seo_geo_manager_c6_upload_accept( 'media-upload' === ( $apply['data']['operation_type'] ?? '' ), 'C6.5 Apply operation type mismatch.' );
seo_geo_manager_c6_upload_accept( $before_count + 1 === seo_geo_manager_c6_upload_attachment_count(), 'C6.5 Apply did not create exactly one attachment.' );
seo_geo_manager_c6_upload_assert_bounded( $apply['data'] );

$attachment = get_post( $media_id );
seo_geo_manager_c6_upload_accept( $attachment instanceof WP_Post && 'attachment' === $attachment->post_type, 'C6.5 created resource is not an attachment.' );
seo_geo_manager_c6_upload_accept( 'C6.5 Direct Upload' === $attachment->post_title, 'C6.5 stored title mismatch.' );
seo_geo_manager_c6_upload_accept( 'C6.5 caption' === $attachment->post_excerpt, 'C6.5 stored caption mismatch.' );
seo_geo_manager_c6_upload_accept( '<p>C6.5 description</p>' === $attachment->post_content, 'C6.5 stored description mismatch.' );
seo_geo_manager_c6_upload_accept( 'image/png' === $attachment->post_mime_type, 'C6.5 stored MIME mismatch.' );
seo_geo_manager_c6_upload_accept( 'C6.5 descriptive alt' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'C6.5 stored alt mismatch.' );
$stored_file = get_attached_file( $media_id, true );
seo_geo_manager_c6_upload_accept( is_string( $stored_file ) && is_file( $stored_file ), 'C6.5 stored binary missing.' );
$stored_hash = hash_file( 'sha256', $stored_file );
seo_geo_manager_c6_upload_accept( is_string( $stored_hash ) && hash_equals( $preview_hash, $stored_hash ), 'C6.5 stored binary SHA-256 mismatch.' );

$read = seo_geo_manager_c6_upload_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_upload_accept( 200 === $read['status'] && is_array( $read['data'] ), 'C6.5 created image could not be read through Manager.' );
seo_geo_manager_c6_upload_accept( 'C6.5 Direct Upload' === ( $read['data']['media']['title'] ?? '' ), 'C6.5 Manager readback title mismatch.' );
seo_geo_manager_c6_upload_accept( 'C6.5 caption' === ( $read['data']['media']['caption'] ?? '' ), 'C6.5 Manager readback caption mismatch.' );
seo_geo_manager_c6_upload_accept( '<p>C6.5 description</p>' === ( $read['data']['media']['description'] ?? '' ), 'C6.5 Manager readback description mismatch.' );
seo_geo_manager_c6_upload_accept( 'C6.5 descriptive alt' === ( $read['data']['media']['alt'] ?? '' ), 'C6.5 Manager readback alt mismatch.' );
seo_geo_manager_c6_upload_assert_bounded( $read['data'] );

$replay_file = seo_geo_manager_c6_upload_image_file();
$replay      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/apply', $apply_payload, $replay_file );
seo_geo_manager_c6_upload_cleanup_file( $replay_file );
seo_geo_manager_c6_upload_accept( 201 === $replay['status'] && is_array( $replay['data'] ), 'C6.5 idempotent replay failed.' );
seo_geo_manager_c6_upload_accept( $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'C6.5 replay returned a new operation.' );
seo_geo_manager_c6_upload_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'C6.5 replay marker missing.' );
seo_geo_manager_c6_upload_accept( $before_count + 1 === seo_geo_manager_c6_upload_attachment_count(), 'C6.5 replay created a duplicate attachment.' );

$conflict_payload          = $apply_payload;
$conflict_payload['title'] = 'Different C6.5 upload title';
$conflict_file             = seo_geo_manager_c6_upload_image_file();
$conflict                  = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/apply', $conflict_payload, $conflict_file );
seo_geo_manager_c6_upload_cleanup_file( $conflict_file );
seo_geo_manager_c6_upload_accept( 409 === $conflict['status'], 'C6.5 reused an idempotency key with a different payload.' );
seo_geo_manager_c6_upload_accept( 'seo_geo_manager_idempotency_conflict' === ( $conflict['data']['code'] ?? '' ), 'Unexpected C6.5 idempotency conflict code.' );
seo_geo_manager_c6_upload_accept( $before_count + 1 === seo_geo_manager_c6_upload_attachment_count(), 'C6.5 idempotency conflict created another attachment.' );

$remote_payload               = $base_payload;
$remote_payload['remote_url'] = 'http://127.0.0.1/private-target.png';
$remote_file                  = seo_geo_manager_c6_upload_image_file();
$remote                       = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/preview', $remote_payload, $remote_file );
seo_geo_manager_c6_upload_cleanup_file( $remote_file );
seo_geo_manager_c6_upload_accept( 400 === $remote['status'], 'C6.5 accepted remote URL media fetching.' );
seo_geo_manager_c6_upload_accept( 'seo_geo_manager_media_upload_remote_fetch_unsupported' === ( $remote['data']['code'] ?? '' ), 'Unexpected C6.5 remote-fetch rejection code.' );

$text_file = seo_geo_manager_c6_upload_text_file();
$not_image = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/preview', $base_payload, $text_file );
seo_geo_manager_c6_upload_cleanup_file( $text_file );
seo_geo_manager_c6_upload_accept( 415 === $not_image['status'], 'C6.5 accepted a non-image upload.' );
seo_geo_manager_c6_upload_accept( 'seo_geo_manager_media_upload_type_invalid' === ( $not_image['data']['code'] ?? '' ), 'Unexpected C6.5 non-image rejection code.' );

$capabilities = seo_geo_manager_c6_upload_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_c6_upload_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'C6.5 capability discovery failed.' );
seo_geo_manager_c6_upload_accept( true === ( $capabilities['data']['capabilities']['media_upload']['available'] ?? false ), 'C6.5 media_upload capability missing.' );
seo_geo_manager_c6_upload_accept( '/media/uploads/preview' === ( $capabilities['data']['operations']['media.upload.preview']['path'] ?? '' ), 'C6.5 media upload Preview operation missing.' );
seo_geo_manager_c6_upload_accept( '/media/uploads/apply' === ( $capabilities['data']['operations']['media.upload.apply']['path'] ?? '' ), 'C6.5 media upload Apply operation missing.' );
seo_geo_manager_c6_upload_accept( false === ( $capabilities['data']['safety']['remote_media_fetch_supported'] ?? true ), 'C6.5 capability manifest exposes remote media fetch.' );
seo_geo_manager_c6_upload_accept( false === ( $capabilities['data']['safety']['existing_media_binary_replacement_supported'] ?? true ), 'C6.5 capability manifest exposes existing binary replacement.' );

$subscriber_id = wp_create_user( 'manager-c6-upload-subscriber', wp_generate_password( 32, true, true ), 'manager-c6-upload-subscriber@example.test' );
seo_geo_manager_c6_upload_accept( ! is_wp_error( $subscriber_id ), 'Could not create C6.5 subscriber fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$denied_file = seo_geo_manager_c6_upload_image_file();
$denied      = seo_geo_manager_c6_upload_request( 'POST', '/seo-geo-manager/v1/media/uploads/preview', $base_payload, $denied_file );
seo_geo_manager_c6_upload_cleanup_file( $denied_file );
seo_geo_manager_c6_upload_accept( 403 === $denied['status'], 'Subscriber incorrectly received C6.5 media upload Preview access.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                                  => true,
		'plugin_version'                      => SEO_GEO_MANAGER_VERSION,
		'direct_upload_only'                  => true,
		'remote_fetch_supported'              => false,
		'image_only'                          => true,
		'sha256_guard'                        => true,
		'public_approval_guard'               => true,
		'idempotent_apply'                    => true,
		'rollback_exposed'                    => false,
		'existing_binary_replacement_exposed' => false,
		'least_privilege_enforced'            => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
