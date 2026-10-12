<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.6 remote featured-media control.
 */

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\FeaturedMediaFingerprint;
use SeoGeo\Manager\Support\MediaFingerprint;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function sgm_r36_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @return array<string, string> */
function sgm_r36_headers( string $credential, string $request_id, string $nonce ): array {
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
function sgm_r36_request( string $method, string $route, array $headers, array $body = array(), array $params = array() ): WP_REST_Response {
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
	sgm_r36_assert( $response instanceof WP_REST_Response, 'Remote request did not return WP_REST_Response.' );

	return $response;
}

/** @return array<string, mixed> */
function sgm_r36_data( WP_REST_Response $response ): array {
	$data = $response->get_data();

	return is_array( $data ) ? $data : array();
}

function sgm_r36_status( WP_REST_Response $response, int $expected, string $label ): void {
	sgm_r36_assert(
		$expected === $response->get_status(),
		$label . ' returned ' . $response->get_status() . ': ' . wp_json_encode( $response->get_data() )
	);
}

function sgm_r36_create_image( string $filename, string $title ): int {
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	sgm_r36_assert( is_string( $png ) && '' !== $png, 'Could not decode image fixture.' );

	$upload = wp_upload_bits( $filename, null, $png );
	sgm_r36_assert( is_array( $upload ) && empty( $upload['error'] ) && ! empty( $upload['file'] ), 'Could not create image fixture: ' . (string) ( $upload['error'] ?? '' ) );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		(string) $upload['file'],
		0,
		true
	);
	sgm_r36_assert( ! is_wp_error( $attachment_id ) && 0 < (int) $attachment_id, 'Could not insert image attachment.' );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$metadata = wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
	}

	return (int) $attachment_id;
}

function sgm_r36_media_fingerprint( int $media_id ): string {
	$media = get_post( $media_id );
	sgm_r36_assert( $media instanceof WP_Post, 'Image attachment is unavailable.' );

	return MediaFingerprint::for_attachment( $media );
}

/** @return array<string, string> */
function sgm_r36_resource_content_state( int $resource_id ): array {
	$post = get_post( $resource_id );
	sgm_r36_assert( $post instanceof WP_Post, 'Featured-media target is unavailable.' );

	return array(
		'title'   => (string) $post->post_title,
		'content' => (string) $post->post_content,
		'excerpt' => (string) $post->post_excerpt,
		'status'  => (string) $post->post_status,
	);
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
sgm_r36_assert(
	$admin instanceof WP_User && user_can( $admin, 'manage_options' ) && user_can( $admin, 'upload_files' ) && user_can( $admin, 'edit_posts' ),
	'R3.6 acceptance administrator is unavailable.'
);
wp_set_current_user( (int) $admin->ID );
add_theme_support( 'post-thumbnails' );
add_post_type_support( 'post', 'thumbnail' );

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
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.6 featured-media bridge' );
sgm_r36_assert( ! is_wp_error( $issued ), 'Could not issue R3.6 mutation credential.' );
$credential = (string) ( $issued['credential'] ?? '' );
sgm_r36_assert( '' !== $credential, 'R3.6 credential is empty.' );

$before_media_id = sgm_r36_create_image( 'r36-before.png', 'R3.6 Before Featured Image' );
$after_media_id  = sgm_r36_create_image( 'r36-after.png', 'R3.6 Candidate Featured Image' );
$before_media_fingerprint = sgm_r36_media_fingerprint( $before_media_id );
$after_media_fingerprint  = sgm_r36_media_fingerprint( $after_media_id );

$resource_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'R3.6 Published Featured Media Target',
		'post_name'    => 'r36-featured-media-target',
		'post_excerpt' => 'Featured media relation acceptance fixture.',
		'post_content' => '<p>This published content must remain byte-for-byte unchanged.</p>',
	),
	true
);
sgm_r36_assert( ! is_wp_error( $resource_id ) && 0 < (int) $resource_id, 'Could not create R3.6 published target.' );
$resource_id = (int) $resource_id;
sgm_r36_assert( false !== set_post_thumbnail( $resource_id, $before_media_id ), 'Could not assign initial featured image.' );
sgm_r36_assert( $before_media_id === (int) get_post_thumbnail_id( $resource_id ), 'Initial featured image assignment mismatch.' );

$resource = get_post( $resource_id );
sgm_r36_assert( $resource instanceof WP_Post, 'Could not reload R3.6 published target.' );
$initial_fingerprint = FeaturedMediaFingerprint::for_post( $resource );
$original_content    = sgm_r36_resource_content_state( $resource_id );

$capabilities = sgm_r36_request(
	'GET',
	'/seo-geo-manager/v1/remote/capabilities',
	sgm_r36_headers( $credential, 'r36-capabilities-1', 'r36-capabilities-nonce-1' )
);
sgm_r36_status( $capabilities, 200, 'R3.6 capabilities' );
$capability_data = sgm_r36_data( $capabilities );
$remote_data     = is_array( $capability_data['data'] ?? null ) ? $capability_data['data'] : array();
sgm_r36_assert( 'R3.6' === ( $remote_data['mutation_transport']['milestone'] ?? '' ), 'R3.6 milestone is not advertised.' );
sgm_r36_assert( in_array( 'featured-media-change', $remote_data['mutation_transport']['supported_operations'] ?? array(), true ), 'Featured-media mutation is not advertised.' );
sgm_r36_assert( 'site.diagnostics' === ( $remote_data['read_surface']['/remote/featured-media'] ?? '' ), 'Featured-media inspection route is not advertised.' );

$inspection = sgm_r36_request(
	'GET',
	'/seo-geo-manager/v1/remote/featured-media',
	sgm_r36_headers( $credential, 'r36-featured-read-1', 'r36-featured-read-nonce-1' ),
	array(),
	array( 'resource_id' => $resource_id )
);
sgm_r36_status( $inspection, 200, 'R3.6 featured-media inspection' );
$inspection_data = sgm_r36_data( $inspection );
$featured_state  = is_array( $inspection_data['data'] ?? null ) ? $inspection_data['data'] : array();
$resource_state  = is_array( $featured_state['resource'] ?? null ) ? $featured_state['resource'] : array();
$media_state     = is_array( $featured_state['media'] ?? null ) ? $featured_state['media'] : array();
$read_fingerprint = (string) ( $resource_state['fingerprint'] ?? '' );
sgm_r36_assert( true === ( $featured_state['policy']['bounded'] ?? false ), 'Featured-media inspection is not bounded.' );
sgm_r36_assert( $resource_id === (int) ( $resource_state['id'] ?? 0 ), 'Featured-media inspection resource mismatch.' );
sgm_r36_assert( $before_media_id === (int) ( $resource_state['featured_media_id'] ?? 0 ), 'Featured-media inspection binding mismatch.' );
sgm_r36_assert( $before_media_id === (int) ( $media_state['id'] ?? 0 ), 'Featured-media inspection media mismatch.' );
sgm_r36_assert( $initial_fingerprint === $read_fingerprint, 'Featured-media reader does not share the canonical fingerprint.' );
sgm_r36_assert( false === ( $featured_state['policy']['raw_attachment_metadata'] ?? true ), 'Featured-media inspection leaked raw attachment metadata.' );
sgm_r36_assert( false === ( $featured_state['policy']['binary_mutation_supported'] ?? true ), 'Featured-media inspection unexpectedly advertises binary mutation.' );
sgm_r36_assert( false === ( $featured_state['policy']['content_mutation_supported'] ?? true ), 'Featured-media inspection unexpectedly advertises content mutation.' );

$plan_body = array(
	'operation' => 'featured-media-change',
	'payload'   => array(
		'schema_version'        => 1,
		'resource_id'           => $resource_id,
		'media_id'              => $after_media_id,
		'expected_fingerprint'  => $read_fingerprint,
		'allow_published_target' => true,
	),
);
$plan = sgm_r36_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r36_headers( $credential, 'r36-plan-1', 'r36-plan-nonce-1' ),
	$plan_body
);
sgm_r36_status( $plan, 200, 'R3.6 featured-media plan' );
$plan_data = sgm_r36_data( $plan );
$plan_row  = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview   = is_array( $plan_row['preview'] ?? null ) ? $plan_row['preview'] : array();
$plan_id   = (string) ( $plan_row['plan_id'] ?? '' );
sgm_r36_assert( str_starts_with( $plan_id, 'sgmp_' ) && 'prepared' === ( $plan_row['status'] ?? '' ), 'Featured-media plan is incomplete.' );
sgm_r36_assert( 'featured-media-change' === ( $plan_row['operation'] ?? '' ), 'Featured-media plan operation mismatch.' );
sgm_r36_assert( true === ( $preview['has_changes'] ?? false ), 'Featured-media Preview did not report a change.' );
sgm_r36_assert( true === ( $preview['policy']['published_approval_required'] ?? false ), 'Featured-media Preview did not require published approval.' );
sgm_r36_assert( false === ( $preview['policy']['binary_mutation_supported'] ?? true ), 'Featured-media Preview unexpectedly permits binary mutation.' );
sgm_r36_assert( false === ( $preview['policy']['content_mutation_supported'] ?? true ), 'Featured-media Preview unexpectedly permits content mutation.' );
sgm_r36_assert( $before_media_id === (int) ( $preview['change']['before_media_id'] ?? 0 ), 'Featured-media Preview before binding mismatch.' );
sgm_r36_assert( $after_media_id === (int) ( $preview['change']['after_media_id'] ?? 0 ), 'Featured-media Preview after binding mismatch.' );
sgm_r36_assert( $before_media_id === (int) get_post_thumbnail_id( $resource_id ), 'Featured-media Preview mutated the target.' );
sgm_r36_assert( $original_content === sgm_r36_resource_content_state( $resource_id ), 'Featured-media Preview changed resource content.' );

$environment_fingerprint = (string) ( EnvironmentPolicy::snapshot()['fingerprint'] ?? '' );
$blocked_apply = sgm_r36_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	sgm_r36_headers( $credential, 'r36-apply-blocked-1', 'r36-apply-blocked-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r36_status( $blocked_apply, 409, 'R3.6 featured-media apply without published approval' );
$blocked_data = sgm_r36_data( $blocked_apply );
sgm_r36_assert( 'seo_geo_manager_featured_media_public_approval_required' === ( $blocked_data['code'] ?? '' ), 'Plan-level featured-media preauthorization was not overridden at Apply.' );
sgm_r36_assert( $before_media_id === (int) get_post_thumbnail_id( $resource_id ), 'Blocked featured-media Apply mutated the target.' );
sgm_r36_assert( $original_content === sgm_r36_resource_content_state( $resource_id ), 'Blocked featured-media Apply changed resource content.' );

$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
	'allow_published_target'  => true,
);
$apply_headers = sgm_r36_headers( $credential, 'r36-apply-1', 'r36-apply-nonce-1' );
$apply = sgm_r36_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_headers, $apply_body );
sgm_r36_status( $apply, 200, 'R3.6 featured-media apply' );
$apply_data   = sgm_r36_data( $apply );
$applied      = is_array( $apply_data['data'] ?? null ) ? $apply_data['data'] : array();
$operation_id = (string) ( $applied['operation_id'] ?? '' );
sgm_r36_assert( preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) === 1 && 'applied' === ( $applied['status'] ?? '' ), 'Featured-media Apply operation missing.' );
sgm_r36_assert( 'featured-media-change' === ( $applied['operation'] ?? '' ), 'Featured-media Apply operation type mismatch.' );
sgm_r36_assert( 'post' === ( $applied['target_type'] ?? '' ), 'Featured-media Apply target type mismatch.' );
sgm_r36_assert( array( 'featured_media' ) === ( $applied['changed_fields'] ?? array() ), 'Featured-media Apply changed-fields boundary mismatch.' );
sgm_r36_assert( $after_media_id === (int) get_post_thumbnail_id( $resource_id ), 'Featured-media Apply did not assign the selected image.' );
sgm_r36_assert( $original_content === sgm_r36_resource_content_state( $resource_id ), 'Featured-media Apply changed resource content.' );
sgm_r36_assert( $before_media_fingerprint === sgm_r36_media_fingerprint( $before_media_id ), 'Featured-media Apply modified the previous attachment.' );
sgm_r36_assert( $after_media_fingerprint === sgm_r36_media_fingerprint( $after_media_id ), 'Featured-media Apply modified the selected attachment.' );

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'r36-apply-nonce-2';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r36_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_replay_headers, $apply_body );
sgm_r36_status( $apply_replay, 200, 'R3.6 featured-media Apply replay' );
$replay_data = sgm_r36_data( $apply_replay );
sgm_r36_assert( true === ( $replay_data['idempotent_replay'] ?? false ), 'Featured-media Apply replay was not idempotent.' );
sgm_r36_assert( $operation_id === ( $replay_data['data']['operation_id'] ?? '' ), 'Featured-media Apply replay operation mismatch.' );

$verify = sgm_r36_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r36_headers( $credential, 'r36-verify-1', 'r36-verify-nonce-1' ),
	array( 'verify' => true )
);
sgm_r36_status( $verify, 200, 'R3.6 featured-media verify' );
$verify_data = sgm_r36_data( $verify );
$verified    = is_array( $verify_data['data'] ?? null ) ? $verify_data['data'] : array();
sgm_r36_assert( true === ( $verified['verified'] ?? false ), 'Featured-media Verify did not pass.' );
sgm_r36_assert( ( $applied['after_fingerprint'] ?? '' ) === ( $verified['current_fingerprint'] ?? '' ), 'Featured-media Verify fingerprint mismatch.' );

$operation_read = sgm_r36_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r36_headers( $credential, 'r36-operation-read-1', 'r36-operation-read-nonce-1' )
);
sgm_r36_status( $operation_read, 200, 'R3.6 featured-media operation read' );
$operation_data = sgm_r36_data( $operation_read );
$bounded        = is_array( $operation_data['data'] ?? null ) ? $operation_data['data'] : array();
sgm_r36_assert( 'featured-media-change' === ( $bounded['operation'] ?? '' ), 'Bounded featured-media operation type missing.' );
sgm_r36_assert( array( 'featured_media' ) === ( $bounded['changed_fields'] ?? array() ), 'Bounded featured-media changed fields mismatch.' );
sgm_r36_assert( ! isset( $bounded['before_media_id'], $bounded['after_media_id'], $bounded['idempotency_key'], $bounded['payload_hash'], $bounded['changes'] ), 'Remote featured-media operation leaked private mutation state.' );

$after_read = sgm_r36_request(
	'GET',
	'/seo-geo-manager/v1/remote/featured-media',
	sgm_r36_headers( $credential, 'r36-featured-read-2', 'r36-featured-read-nonce-2' ),
	array(),
	array( 'resource_id' => $resource_id )
);
sgm_r36_status( $after_read, 200, 'R3.6 featured-media readback' );
$after_read_data = sgm_r36_data( $after_read );
$after_featured  = is_array( $after_read_data['data'] ?? null ) ? $after_read_data['data'] : array();
sgm_r36_assert( $after_media_id === (int) ( $after_featured['resource']['featured_media_id'] ?? 0 ), 'Featured-media readback binding mismatch.' );
sgm_r36_assert( ( $applied['after_fingerprint'] ?? '' ) === ( $after_featured['resource']['fingerprint'] ?? '' ), 'Featured-media readback fingerprint mismatch.' );

$rollback = sgm_r36_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r36_headers( $credential, 'r36-rollback-1', 'r36-rollback-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r36_status( $rollback, 200, 'R3.6 featured-media rollback' );
$rollback_data = sgm_r36_data( $rollback );
$rolled_back   = is_array( $rollback_data['data'] ?? null ) ? $rollback_data['data'] : array();
sgm_r36_assert( 'rolled-back' === ( $rolled_back['status'] ?? '' ), 'Featured-media Rollback status mismatch.' );
sgm_r36_assert( $before_media_id === (int) get_post_thumbnail_id( $resource_id ), 'Featured-media Rollback did not restore the original image.' );
sgm_r36_assert( $original_content === sgm_r36_resource_content_state( $resource_id ), 'Featured-media Rollback changed resource content.' );
sgm_r36_assert( $before_media_fingerprint === sgm_r36_media_fingerprint( $before_media_id ), 'Featured-media Rollback modified the original attachment.' );
sgm_r36_assert( $after_media_fingerprint === sgm_r36_media_fingerprint( $after_media_id ), 'Featured-media Rollback modified the candidate attachment.' );

$restored = get_post( $resource_id );
sgm_r36_assert( $restored instanceof WP_Post, 'Could not reload rolled-back featured-media target.' );
$rollback_fingerprint = FeaturedMediaFingerprint::for_post( $restored );
sgm_r36_assert( $initial_fingerprint === $rollback_fingerprint, 'Featured-media Rollback did not restore the canonical fingerprint.' );

$rollback_read = sgm_r36_request(
	'GET',
	'/seo-geo-manager/v1/remote/featured-media',
	sgm_r36_headers( $credential, 'r36-featured-read-3', 'r36-featured-read-nonce-3' ),
	array(),
	array( 'resource_id' => $resource_id )
);
sgm_r36_status( $rollback_read, 200, 'R3.6 featured-media rollback readback' );
$rollback_read_data = sgm_r36_data( $rollback_read );
$restored_featured  = is_array( $rollback_read_data['data'] ?? null ) ? $rollback_read_data['data'] : array();
sgm_r36_assert( $before_media_id === (int) ( $restored_featured['resource']['featured_media_id'] ?? 0 ), 'Featured-media rollback readback binding mismatch.' );
sgm_r36_assert( $initial_fingerprint === ( $restored_featured['resource']['fingerprint'] ?? '' ), 'Featured-media rollback readback fingerprint mismatch.' );

$audit_json = wp_json_encode( $audit->recent( 100 ) );
sgm_r36_assert( false !== $audit_json && ! str_contains( $audit_json, $credential ) && str_contains( $audit_json, $plan_id ) && str_contains( $audit_json, $operation_id ), 'Featured-media audit evidence is incomplete or secret-bearing.' );

$tokens->revoke();
wp_delete_post( $resource_id, true );
wp_delete_attachment( $before_media_id, true );
wp_delete_attachment( $after_media_id, true );

echo wp_json_encode(
	array(
		'ok'                                => true,
		'plugin_version'                    => SEO_GEO_MANAGER_VERSION,
		'operation'                         => 'featured-media-change',
		'featured_media_inspected_remotely' => true,
		'canonical_fingerprint_shared'      => true,
		'preview_no_mutation'               => true,
		'published_approval_required'       => true,
		'preauthorization_ignored'          => true,
		'relationship_only'                 => true,
		'binary_mutation'                   => false,
		'content_mutation'                  => false,
		'attachment_state_preserved'        => true,
		'resource_content_preserved'        => true,
		'apply_exact'                       => true,
		'apply_idempotent_replay'           => true,
		'verify_exact'                      => true,
		'operation_bounded'                 => true,
		'rollback_exact'                    => true,
		'rollback_fingerprint_exact'        => true,
		'audit_secret_free'                 => true,
		'generic_proxy'                     => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
