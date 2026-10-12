<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.2 Theme structured-content lifecycle.
 *
 * Executed through WP-CLI after the Manager and self-contained SEO/GEO Theme are active.
 */

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\ContentFingerprint;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/** @return array<string, string> */
function sgm_r32_headers( string $credential, string $request_id, string $nonce ): array {
	return array(
		'authorization'                => 'Bearer ' . $credential,
		'x-seo-geo-manager-request-id' => $request_id,
		'x-seo-geo-manager-nonce'      => $nonce,
		'x-seo-geo-manager-timestamp'  => (string) time(),
	);
}

/**
 * @param array<string, string> $headers Request headers.
 * @param array<string, mixed>  $body    JSON request body.
 */
function sgm_r32_request( string $method, string $route, array $headers, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) ?: '{}' );
	}

	$response = rest_do_request( $request );
	if ( ! $response instanceof WP_REST_Response ) {
		fwrite( STDERR, "R3.2 request did not return WP_REST_Response.\n" );
		exit( 1 );
	}

	return $response;
}

/** @return array<string, mixed> */
function sgm_r32_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_r32_expect( WP_REST_Response $response, int $expected, string $label ): void {
	$actual = $response->get_status();
	if ( $expected !== $actual ) {
		fwrite( STDERR, sprintf( "%s returned HTTP %d; expected %d.\n", $label, $actual, $expected ) );
		fwrite( STDERR, wp_json_encode( $response->get_data() ) . "\n" );
		exit( 1 );
	}
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
if ( ! $admin instanceof WP_User || ! user_can( $admin, 'manage_options' ) ) {
	fwrite( STDERR, "R3.2 acceptance administrator is missing or not authorised.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $admin->ID );

if ( 'seo-geo-theme' !== get_stylesheet() ) {
	fwrite( STDERR, "R3.2 acceptance requires the self-contained SEO/GEO Theme to be active.\n" );
	exit( 1 );
}

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
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.2 Theme structured bridge' );
if ( is_wp_error( $issued ) ) {
	fwrite( STDERR, 'Could not issue R3.2 credential: ' . $issued->get_error_message() . "\n" );
	exit( 1 );
}
$credential = isset( $issued['credential'] ) ? (string) $issued['credential'] : '';
if ( '' === $credential ) {
	fwrite( STDERR, "R3.2 credential is empty.\n" );
	exit( 1 );
}

$original_content = '<section class="seo-geo-corporate-contact">'
	. '<h2 class="seo-geo-content-slot--contact-heading seo-geo-placeholder--copy">Old contact heading</h2>'
	. '<p class="seo-geo-content-slot--contact-intro seo-geo-placeholder--copy">Old contact intro</p>'
	. '<a class="seo-geo-content-slot--contact-primary-cta seo-geo-placeholder--copy" href="/old-contact/">Old contact CTA</a>'
	. '<h2 class="seo-geo-content-slot--final-cta-heading seo-geo-placeholder--copy">Old final heading</h2>'
	. '<p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy">Old final body</p>'
	. '<a class="seo-geo-content-slot--final-cta-button seo-geo-placeholder--copy" href="/old-final/">Old final CTA</a>'
	. '</section>';

$target_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Contact',
		'post_name'    => 'contact',
		'post_content' => $original_content,
	),
	true
);
if ( is_wp_error( $target_id ) || 1 > (int) $target_id ) {
	fwrite( STDERR, "Could not create dedicated R3.2 Theme draft target.\n" );
	exit( 1 );
}
$target_id = (int) $target_id;
$before = get_post( $target_id );
if ( ! $before instanceof WP_Post ) {
	fwrite( STDERR, "Could not load R3.2 Theme draft target.\n" );
	exit( 1 );
}
$before_fingerprint = ContentFingerprint::for_post( $before );

$plan_body = array(
	'operation' => 'theme-structured-content',
	'payload'   => array(
		'schema_version' => 1,
		'model_id'       => 'corporate-contact-v1',
		'target'         => array(
			'id'                   => $target_id,
			'expected_fingerprint' => $before_fingerprint,
		),
		'slots'          => array(
			'contact-heading'     => 'Talk to our delivery team',
			'contact-primary-cta' => array(
				'text' => 'Book a working session',
				'url'  => '/contact/#form',
			),
		),
	),
);

$plan = sgm_r32_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r32_headers( $credential, 'ci-r32-plan-0001', 'ci-r32-plan-nonce-0001' ),
	$plan_body
);
sgm_r32_expect( $plan, 200, 'R3.2 Theme plan' );
$plan_data    = sgm_r32_data( $plan );
$plan_payload = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$plan_id      = (string) ( $plan_payload['plan_id'] ?? '' );
$preview      = is_array( $plan_payload['preview'] ?? null ) ? $plan_payload['preview'] : array();
if (
	! str_starts_with( $plan_id, 'sgmp_' )
	|| 'theme-structured-content' !== ( $plan_payload['operation'] ?? '' )
	|| 'prepared' !== ( $plan_payload['status'] ?? '' )
	|| 'corporate-contact-v1' !== ( $preview['model_id'] ?? '' )
	|| 'contact' !== ( $preview['page_key'] ?? '' )
	|| true !== ( $preview['policy']['layout_preserved'] ?? false )
	|| empty( $preview['has_changes'] )
) {
	fwrite( STDERR, "R3.2 Theme plan response is incomplete.\n" );
	exit( 1 );
}
$after_plan = get_post( $target_id );
if ( ! $after_plan instanceof WP_Post || $original_content !== $after_plan->post_content || 'draft' !== $after_plan->post_status ) {
	fwrite( STDERR, "R3.2 Theme plan mutated the draft target.\n" );
	exit( 1 );
}

$environment             = EnvironmentPolicy::snapshot();
$environment_fingerprint = (string) ( $environment['fingerprint'] ?? '' );
$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
);
$apply_headers = sgm_r32_headers( $credential, 'ci-r32-apply-0001', 'ci-r32-apply-nonce-0001' );
$apply = sgm_r32_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_headers,
	$apply_body
);
sgm_r32_expect( $apply, 200, 'R3.2 Theme apply' );
$apply_data      = sgm_r32_data( $apply );
$operation_id    = (string) ( $apply_data['data']['operation_id'] ?? '' );
$structured_meta = is_array( $apply_data['data']['structured_model'] ?? null ) ? $apply_data['data']['structured_model'] : array();
if (
	! preg_match( '/^[a-f0-9\-]{36}$/', $operation_id )
	|| 'applied' !== ( $apply_data['data']['status'] ?? '' )
	|| 'theme-structured-content' !== ( $apply_data['data']['operation'] ?? '' )
	|| 'corporate-contact-v1' !== ( $structured_meta['model_id'] ?? '' )
	|| 'contact' !== ( $structured_meta['page_key'] ?? '' )
	|| true !== ( $structured_meta['layout_preserved'] ?? false )
	|| isset( $structured_meta['slots'] )
) {
	fwrite( STDERR, "R3.2 Theme apply did not return bounded semantic evidence.\n" );
	exit( 1 );
}

$stored_content = (string) get_post_field( 'post_content', $target_id );
if (
	! str_contains( $stored_content, '>Talk to our delivery team</h2>' )
	|| ! str_contains( $stored_content, 'href="/contact/#form">Book a working session</a>' )
	|| ! str_contains( $stored_content, 'Old contact intro' )
	|| ! str_contains( $stored_content, 'seo-geo-corporate-contact' )
	|| 'draft' !== get_post_status( $target_id )
) {
	fwrite( STDERR, "R3.2 Theme apply did not preserve the exact semantic/layout contract.\n" );
	exit( 1 );
}

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'ci-r32-apply-nonce-replay-0001';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r32_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_replay_headers,
	$apply_body
);
sgm_r32_expect( $apply_replay, 200, 'R3.2 Theme apply idempotent replay' );
$apply_replay_data = sgm_r32_data( $apply_replay );
if ( true !== ( $apply_replay_data['idempotent_replay'] ?? false ) || $operation_id !== ( $apply_replay_data['data']['operation_id'] ?? '' ) ) {
	fwrite( STDERR, "R3.2 Theme apply replay was not idempotent.\n" );
	exit( 1 );
}

$verify = sgm_r32_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r32_headers( $credential, 'ci-r32-verify-0001', 'ci-r32-verify-nonce-0001' ),
	array( 'verify' => true )
);
sgm_r32_expect( $verify, 200, 'R3.2 Theme verify' );
$verify_data = sgm_r32_data( $verify );
if ( true !== ( $verify_data['data']['verified'] ?? false ) || (string) ( $verify_data['data']['after_fingerprint'] ?? '' ) !== (string) ( $verify_data['data']['current_fingerprint'] ?? '' ) ) {
	fwrite( STDERR, "R3.2 Theme verify did not prove current stored state.\n" );
	exit( 1 );
}

$operation_read = sgm_r32_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r32_headers( $credential, 'ci-r32-operation-read-0001', 'ci-r32-operation-read-nonce-0001' )
);
sgm_r32_expect( $operation_read, 200, 'R3.2 Theme operation read' );
$operation_read_data = sgm_r32_data( $operation_read );
$bounded_operation   = is_array( $operation_read_data['data'] ?? null ) ? $operation_read_data['data'] : array();
$bounded_model       = is_array( $bounded_operation['structured_model'] ?? null ) ? $bounded_operation['structured_model'] : array();
if (
	array( 'content' ) !== ( $bounded_operation['changed_fields'] ?? array() )
	|| 'theme-structured-content' !== ( $bounded_operation['operation'] ?? '' )
	|| 'corporate-contact-v1' !== ( $bounded_model['model_id'] ?? '' )
	|| isset( $bounded_model['slots'] )
	|| isset( $bounded_operation['before'] )
	|| isset( $bounded_operation['changes'] )
) {
	fwrite( STDERR, "R3.2 Theme operation read is not privacy-bounded.\n" );
	exit( 1 );
}

$rollback = sgm_r32_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r32_headers( $credential, 'ci-r32-rollback-0001', 'ci-r32-rollback-nonce-0001' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r32_expect( $rollback, 200, 'R3.2 Theme rollback' );
$rollback_data = sgm_r32_data( $rollback );
if ( 'rolled-back' !== ( $rollback_data['data']['status'] ?? '' ) ) {
	fwrite( STDERR, "R3.2 Theme rollback did not return rolled-back state.\n" );
	exit( 1 );
}
$restored = get_post( $target_id );
if ( ! $restored instanceof WP_Post || $original_content !== $restored->post_content || 'draft' !== $restored->post_status ) {
	fwrite( STDERR, "R3.2 Theme rollback did not restore the exact original draft.\n" );
	exit( 1 );
}

$audit_rows = $audit->recent( 100 );
$audit_json = wp_json_encode( $audit_rows );
if ( false === $audit_json || str_contains( $audit_json, $credential ) || ! str_contains( $audit_json, $plan_id ) || ! str_contains( $audit_json, $operation_id ) ) {
	fwrite( STDERR, "R3.2 Theme audit evidence is incomplete or leaked the bearer credential.\n" );
	exit( 1 );
}

wp_delete_post( $target_id, true );
$tokens->revoke();

echo wp_json_encode(
	array(
		'ok'                      => true,
		'plugin_version'          => SEO_GEO_MANAGER_VERSION,
		'operation'               => 'theme-structured-content',
		'theme_active'            => true,
		'plan_created'            => true,
		'preview_no_mutation'     => true,
		'semantic_model_matched'  => true,
		'layout_preserved'        => true,
		'apply_exact'             => true,
		'apply_idempotent_replay' => true,
		'verify_exact'            => true,
		'structured_evidence_bounded' => true,
		'rollback_exact'          => true,
		'audit_secret_free'       => true,
		'generic_proxy'           => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
