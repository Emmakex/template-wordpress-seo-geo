<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.7 remote media-alt control.
 */

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\MediaFingerprint;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function sgm_r37_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @return array<string, string> */
function sgm_r37_headers( string $credential, string $request_id, string $nonce ): array {
	return array(
		'authorization'                => 'Bearer ' . $credential,
		'x-seo-geo-manager-request-id' => $request_id,
		'x-seo-geo-manager-nonce'      => $nonce,
		'x-seo-geo-manager-timestamp'  => (string) time(),
	);
}

/**
 * @param array<string, string> $headers Request headers.
 * @param array<string, mixed>  $body    Optional JSON body.
 * @param array<string, mixed>  $params  Optional request parameters.
 */
function sgm_r37_request( string $method, string $route, array $headers, array $body = array(), array $params = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	foreach ( $params as $name => $value ) {
		$request->set_param( $name, $value );
	}
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) ?: '{}' );
	}

	$response = rest_do_request( $request );
	sgm_r37_assert( $response instanceof WP_REST_Response, 'Remote request did not return WP_REST_Response.' );

	return $response;
}

/** @return array<string, mixed> */
function sgm_r37_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_r37_status( WP_REST_Response $response, int $expected, string $label ): void {
	sgm_r37_assert(
		$expected === $response->get_status(),
		$label . ' returned ' . $response->get_status() . ': ' . wp_json_encode( $response->get_data() )
	);
}

function sgm_r37_create_image(): int {
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	sgm_r37_assert( is_string( $png ) && '' !== $png, 'Could not decode image fixture.' );

	$upload = wp_upload_bits( 'r37-media-alt.png', null, $png );
	sgm_r37_assert( is_array( $upload ) && empty( $upload['error'] ) && ! empty( $upload['file'] ), 'Could not create image fixture: ' . (string) ( $upload['error'] ?? '' ) );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'R3.7 Media Alt Fixture',
			'post_name'      => 'r37-media-alt-fixture',
			'post_excerpt'   => 'Caption must remain unchanged.',
			'post_content'   => 'Description must remain unchanged.',
			'post_status'    => 'inherit',
		),
		(string) $upload['file'],
		0,
		true
	);
	sgm_r37_assert( ! is_wp_error( $attachment_id ) && 0 < (int) $attachment_id, 'Could not insert R3.7 image attachment.' );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$metadata = wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
	}
	update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', 'Original R3.7 alt' );

	return (int) $attachment_id;
}

/** @return array<string, mixed> */
function sgm_r37_attachment_state( int $media_id ): array {
	$attachment = get_post( $media_id );
	sgm_r37_assert( $attachment instanceof WP_Post, 'R3.7 attachment is unavailable.' );
	$file = get_attached_file( $media_id );
	$file = is_string( $file ) ? $file : '';

	return array(
		'title'       => (string) $attachment->post_title,
		'slug'        => (string) $attachment->post_name,
		'caption'     => (string) $attachment->post_excerpt,
		'description' => (string) $attachment->post_content,
		'status'      => (string) $attachment->post_status,
		'mime'        => (string) $attachment->post_mime_type,
		'parent'      => (int) $attachment->post_parent,
		'url'         => (string) wp_get_attachment_url( $media_id ),
		'file_sha256' => '' !== $file && is_file( $file ) ? (string) hash_file( 'sha256', $file ) : '',
	);
}

function sgm_r37_fingerprint( int $media_id ): string {
	$attachment = get_post( $media_id );
	sgm_r37_assert( $attachment instanceof WP_Post, 'R3.7 attachment cannot be fingerprinted.' );
	return MediaFingerprint::for_attachment( $attachment );
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
sgm_r37_assert(
	$admin instanceof WP_User && user_can( $admin, 'manage_options' ) && user_can( $admin, 'upload_files' ) && user_can( $admin, 'edit_posts' ),
	'R3.7 acceptance administrator is unavailable.'
);
wp_set_current_user( (int) $admin->ID );

$credentials = new Credentials();
$tokens      = new TokenService( $credentials );
$audit       = new Audit();
$tokens->revoke();
$audit->clear();

$scopes = array(
	'site.read',
	'site.diagnostics',
	'content.write',
	'operations.read',
	'operations.apply',
	'operations.rollback',
);
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.7 media-alt bridge' );
sgm_r37_assert( ! is_wp_error( $issued ), 'Could not issue R3.7 mutation credential.' );
$credential = (string) ( $issued['credential'] ?? '' );
sgm_r37_assert( '' !== $credential, 'R3.7 credential is empty.' );

$media_id            = sgm_r37_create_image();
$initial_state       = sgm_r37_attachment_state( $media_id );
$initial_fingerprint = sgm_r37_fingerprint( $media_id );
$new_alt             = 'Updated R3.7 remote alt';

$capabilities = sgm_r37_request(
	'GET',
	'/seo-geo-manager/v1/remote/capabilities',
	sgm_r37_headers( $credential, 'r37-capabilities-1', 'r37-capabilities-nonce-1' )
);
sgm_r37_status( $capabilities, 200, 'R3.7 capabilities' );
$capability_data = sgm_r37_data( $capabilities );
$remote_data     = is_array( $capability_data['data'] ?? null ) ? $capability_data['data'] : array();
sgm_r37_assert( 'R3.7' === ( $remote_data['mutation_transport']['milestone'] ?? '' ), 'R3.7 milestone is not advertised.' );
sgm_r37_assert( in_array( 'media-alt-change', $remote_data['mutation_transport']['supported_operations'] ?? array(), true ), 'Media-alt mutation is not advertised.' );
sgm_r37_assert( 'site.diagnostics' === ( $remote_data['read_surface']['/remote/media'] ?? '' ), 'Remote media inspection route is not advertised.' );

$inspection = sgm_r37_request(
	'GET',
	'/seo-geo-manager/v1/remote/media',
	sgm_r37_headers( $credential, 'r37-media-read-1', 'r37-media-read-nonce-1' ),
	array(),
	array( 'media_id' => $media_id )
);
sgm_r37_status( $inspection, 200, 'R3.7 remote media inspection' );
$inspection_data = sgm_r37_data( $inspection );
$media_read      = is_array( $inspection_data['data'] ?? null ) ? $inspection_data['data'] : array();
$media_state     = is_array( $media_read['media'] ?? null ) ? $media_read['media'] : array();
$read_fingerprint = (string) ( $media_state['fingerprint'] ?? '' );
sgm_r37_assert( true === ( $media_read['bounded'] ?? false ), 'R3.7 media inspection is not bounded.' );
sgm_r37_assert( $media_id === (int) ( $media_state['id'] ?? 0 ), 'R3.7 media inspection ID mismatch.' );
sgm_r37_assert( 'Original R3.7 alt' === ( $media_state['alt'] ?? '' ), 'R3.7 media inspection alt mismatch.' );
sgm_r37_assert( $initial_fingerprint === $read_fingerprint, 'R3.7 media reader does not share the canonical fingerprint.' );
sgm_r37_assert( false === ( $media_read['policy']['raw_attachment_metadata_returned'] ?? true ), 'R3.7 media inspection leaked raw attachment metadata.' );
sgm_r37_assert( false === ( $media_read['policy']['binary_mutation_supported'] ?? true ), 'R3.7 media inspection unexpectedly advertises binary mutation.' );

$plan = sgm_r37_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r37_headers( $credential, 'r37-plan-1', 'r37-plan-nonce-1' ),
	array(
		'operation' => 'media-alt-change',
		'payload'   => array(
			'schema_version'       => 1,
			'media_id'             => $media_id,
			'alt'                  => $new_alt,
			'expected_fingerprint' => $read_fingerprint,
			'allow_public_media'   => true,
		),
	)
);
sgm_r37_status( $plan, 200, 'R3.7 media-alt plan' );
$plan_data = sgm_r37_data( $plan );
$plan_row  = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview   = is_array( $plan_row['preview'] ?? null ) ? $plan_row['preview'] : array();
$plan_id   = (string) ( $plan_row['plan_id'] ?? '' );
sgm_r37_assert( str_starts_with( $plan_id, 'sgmp_' ) && 'prepared' === ( $plan_row['status'] ?? '' ), 'R3.7 media-alt plan is incomplete.' );
sgm_r37_assert( 'media-alt-change' === ( $plan_row['operation'] ?? '' ), 'R3.7 media-alt plan operation mismatch.' );
sgm_r37_assert( true === ( $preview['has_changes'] ?? false ), 'R3.7 media-alt Preview did not report a change.' );
sgm_r37_assert( 'Original R3.7 alt' === ( $preview['before_alt'] ?? '' ) && $new_alt === ( $preview['after_alt'] ?? '' ), 'R3.7 media-alt Preview diff mismatch.' );
sgm_r37_assert( false === ( $preview['policy']['binary_mutation_supported'] ?? true ), 'R3.7 media-alt Preview unexpectedly permits binary mutation.' );
sgm_r37_assert( 'Original R3.7 alt' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'R3.7 media-alt Preview mutated alt.' );
sgm_r37_assert( $initial_state === sgm_r37_attachment_state( $media_id ), 'R3.7 media-alt Preview mutated attachment state.' );
sgm_r37_assert( $initial_fingerprint === sgm_r37_fingerprint( $media_id ), 'R3.7 media-alt Preview changed the fingerprint.' );

$environment_fingerprint = (string) ( EnvironmentPolicy::snapshot()['fingerprint'] ?? '' );
$blocked_apply = sgm_r37_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	sgm_r37_headers( $credential, 'r37-apply-blocked-1', 'r37-apply-blocked-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r37_status( $blocked_apply, 409, 'R3.7 media-alt apply without public-media approval' );
$blocked_data = sgm_r37_data( $blocked_apply );
sgm_r37_assert( 'seo_geo_manager_media_alt_public_approval_required' === ( $blocked_data['code'] ?? '' ), 'Plan-level media-alt preauthorization was not overridden at Apply.' );
sgm_r37_assert( 'Original R3.7 alt' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'Blocked R3.7 Apply mutated alt.' );
sgm_r37_assert( $initial_state === sgm_r37_attachment_state( $media_id ), 'Blocked R3.7 Apply mutated attachment state.' );

$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
	'allow_public_media'      => true,
);
$apply_headers = sgm_r37_headers( $credential, 'r37-apply-1', 'r37-apply-nonce-1' );
$apply = sgm_r37_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_headers, $apply_body );
sgm_r37_status( $apply, 200, 'R3.7 media-alt apply' );
$apply_data   = sgm_r37_data( $apply );
$applied      = is_array( $apply_data['data'] ?? null ) ? $apply_data['data'] : array();
$operation_id = (string) ( $applied['operation_id'] ?? '' );
sgm_r37_assert( preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) === 1 && 'applied' === ( $applied['status'] ?? '' ), 'R3.7 media-alt Apply operation missing.' );
sgm_r37_assert( 'media-alt-change' === ( $applied['operation'] ?? '' ), 'R3.7 media-alt Apply operation type mismatch.' );
sgm_r37_assert( 'attachment' === ( $applied['target_type'] ?? '' ), 'R3.7 media-alt Apply target type mismatch.' );
sgm_r37_assert( array( 'image_alt' ) === ( $applied['changed_fields'] ?? array() ), 'R3.7 media-alt Apply changed-fields boundary mismatch.' );
sgm_r37_assert( $new_alt === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'R3.7 media-alt Apply did not set the exact alt.' );
sgm_r37_assert( $initial_state === sgm_r37_attachment_state( $media_id ), 'R3.7 media-alt Apply modified non-alt attachment state or binary.' );
$applied_fingerprint = sgm_r37_fingerprint( $media_id );
sgm_r37_assert( $initial_fingerprint !== $applied_fingerprint && $applied_fingerprint === ( $applied['after_fingerprint'] ?? '' ), 'R3.7 media-alt Apply fingerprint mismatch.' );

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'r37-apply-nonce-2';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r37_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_replay_headers, $apply_body );
sgm_r37_status( $apply_replay, 200, 'R3.7 media-alt Apply replay' );
$replay_data = sgm_r37_data( $apply_replay );
sgm_r37_assert( true === ( $replay_data['idempotent_replay'] ?? false ), 'R3.7 media-alt Apply replay was not idempotent.' );
sgm_r37_assert( $operation_id === ( $replay_data['data']['operation_id'] ?? '' ), 'R3.7 media-alt Apply replay operation mismatch.' );

$verify = sgm_r37_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r37_headers( $credential, 'r37-verify-1', 'r37-verify-nonce-1' ),
	array( 'verify' => true )
);
sgm_r37_status( $verify, 200, 'R3.7 media-alt verify' );
$verify_data = sgm_r37_data( $verify );
$verified    = is_array( $verify_data['data'] ?? null ) ? $verify_data['data'] : array();
sgm_r37_assert( true === ( $verified['verified'] ?? false ), 'R3.7 media-alt Verify did not pass.' );
sgm_r37_assert( $applied_fingerprint === ( $verified['current_fingerprint'] ?? '' ), 'R3.7 media-alt Verify fingerprint mismatch.' );

$operation_read = sgm_r37_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r37_headers( $credential, 'r37-operation-read-1', 'r37-operation-read-nonce-1' )
);
sgm_r37_status( $operation_read, 200, 'R3.7 media-alt operation read' );
$operation_data = sgm_r37_data( $operation_read );
$bounded        = is_array( $operation_data['data'] ?? null ) ? $operation_data['data'] : array();
sgm_r37_assert( 'media-alt-change' === ( $bounded['operation'] ?? '' ), 'Bounded R3.7 media-alt operation type missing.' );
sgm_r37_assert( array( 'image_alt' ) === ( $bounded['changed_fields'] ?? array() ), 'Bounded R3.7 media-alt changed fields mismatch.' );
sgm_r37_assert( ! isset( $bounded['before_alt'], $bounded['after_alt'], $bounded['idempotency_key'], $bounded['payload_hash'], $bounded['changes'] ), 'Remote R3.7 media-alt operation leaked private mutation state.' );

$after_read = sgm_r37_request(
	'GET',
	'/seo-geo-manager/v1/remote/media',
	sgm_r37_headers( $credential, 'r37-media-read-2', 'r37-media-read-nonce-2' ),
	array(),
	array( 'media_id' => $media_id )
);
sgm_r37_status( $after_read, 200, 'R3.7 media-alt readback' );
$after_read_data = sgm_r37_data( $after_read );
$after_media     = is_array( $after_read_data['data']['media'] ?? null ) ? $after_read_data['data']['media'] : array();
sgm_r37_assert( $new_alt === ( $after_media['alt'] ?? '' ), 'R3.7 media-alt readback alt mismatch.' );
sgm_r37_assert( $applied_fingerprint === ( $after_media['fingerprint'] ?? '' ), 'R3.7 media-alt readback fingerprint mismatch.' );

$rollback = sgm_r37_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r37_headers( $credential, 'r37-rollback-1', 'r37-rollback-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r37_status( $rollback, 200, 'R3.7 media-alt rollback' );
$rollback_data = sgm_r37_data( $rollback );
$rolled_back   = is_array( $rollback_data['data'] ?? null ) ? $rollback_data['data'] : array();
sgm_r37_assert( 'rolled-back' === ( $rolled_back['status'] ?? '' ), 'R3.7 media-alt Rollback status mismatch.' );
sgm_r37_assert( 'Original R3.7 alt' === get_post_meta( $media_id, '_wp_attachment_image_alt', true ), 'R3.7 media-alt Rollback did not restore original alt.' );
sgm_r37_assert( $initial_state === sgm_r37_attachment_state( $media_id ), 'R3.7 media-alt Rollback modified non-alt attachment state or binary.' );
sgm_r37_assert( $initial_fingerprint === sgm_r37_fingerprint( $media_id ), 'R3.7 media-alt Rollback did not restore canonical fingerprint.' );

$rollback_read = sgm_r37_request(
	'GET',
	'/seo-geo-manager/v1/remote/media',
	sgm_r37_headers( $credential, 'r37-media-read-3', 'r37-media-read-nonce-3' ),
	array(),
	array( 'media_id' => $media_id )
);
sgm_r37_status( $rollback_read, 200, 'R3.7 media-alt rollback readback' );
$rollback_read_data = sgm_r37_data( $rollback_read );
$restored_media     = is_array( $rollback_read_data['data']['media'] ?? null ) ? $rollback_read_data['data']['media'] : array();
sgm_r37_assert( 'Original R3.7 alt' === ( $restored_media['alt'] ?? '' ), 'R3.7 media-alt rollback readback alt mismatch.' );
sgm_r37_assert( $initial_fingerprint === ( $restored_media['fingerprint'] ?? '' ), 'R3.7 media-alt rollback readback fingerprint mismatch.' );

$audit_json = wp_json_encode( $audit->recent( 100 ) );
sgm_r37_assert( false !== $audit_json && ! str_contains( $audit_json, $credential ) && str_contains( $audit_json, $plan_id ) && str_contains( $audit_json, $operation_id ), 'R3.7 media-alt audit evidence is incomplete or secret-bearing.' );

$tokens->revoke();
wp_delete_attachment( $media_id, true );

echo wp_json_encode(
	array(
		'ok'                           => true,
		'plugin_version'               => SEO_GEO_MANAGER_VERSION,
		'operation'                    => 'media-alt-change',
		'media_inspected_remotely'     => true,
		'canonical_fingerprint_shared' => true,
		'preview_no_mutation'          => true,
		'public_approval_required'     => true,
		'preauthorization_ignored'     => true,
		'alt_only'                     => true,
		'binary_mutation'              => false,
		'attachment_state_preserved'   => true,
		'apply_exact'                  => true,
		'apply_idempotent_replay'      => true,
		'verify_exact'                 => true,
		'operation_bounded'            => true,
		'rollback_exact'               => true,
		'rollback_fingerprint_exact'   => true,
		'audit_secret_free'            => true,
		'generic_proxy'                => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
