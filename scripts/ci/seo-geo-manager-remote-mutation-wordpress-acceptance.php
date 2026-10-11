<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3 content-change lifecycle.
 *
 * Executed through WP-CLI after the plugin is active in the pinned WordPress fixture.
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
function sgm_r3_headers( string $credential, string $request_id, string $nonce ): array {
	return array(
		'authorization'                => 'Bearer ' . $credential,
		'x-seo-geo-manager-request-id' => $request_id,
		'x-seo-geo-manager-nonce'      => $nonce,
		'x-seo-geo-manager-timestamp'  => (string) time(),
	);
}

/**
 * @param array<string, string> $headers Request headers.
 * @param array<string, mixed>  $body JSON request body.
 */
function sgm_r3_request( string $method, string $route, array $headers, array $body = array() ): WP_REST_Response {
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
		fwrite( STDERR, "R3 request did not return WP_REST_Response.\n" );
		exit( 1 );
	}

	return $response;
}

/** @return array<string, mixed> */
function sgm_r3_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_r3_expect( WP_REST_Response $response, int $expected, string $label ): void {
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
	fwrite( STDERR, "R3 acceptance administrator is missing or not authorised.\n" );
	exit( 1 );
}
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
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3 mutation bridge' );
if ( is_wp_error( $issued ) ) {
	fwrite( STDERR, 'Could not issue R3 credential: ' . $issued->get_error_message() . "\n" );
	exit( 1 );
}
$credential = isset( $issued['credential'] ) ? (string) $issued['credential'] : '';
if ( '' === $credential ) {
	fwrite( STDERR, "R3 credential is empty.\n" );
	exit( 1 );
}

$original_title   = 'R3 Original Title';
$original_content = 'R3 original body.';
$target_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => $original_title,
		'post_content' => $original_content,
	),
	true
);
if ( is_wp_error( $target_id ) || 1 > (int) $target_id ) {
	fwrite( STDERR, "Could not create dedicated R3 draft target.\n" );
	exit( 1 );
}
$target_id = (int) $target_id;
$before = get_post( $target_id );
if ( ! $before instanceof WP_Post ) {
	fwrite( STDERR, "Could not load R3 draft target.\n" );
	exit( 1 );
}
$before_fingerprint = ContentFingerprint::for_post( $before );

$changed_title   = 'R3 Changed Title';
$changed_content = 'R3 changed body.';
$plan_body = array(
	'operation' => 'content-change',
	'payload'   => array(
		'schema_version' => 1,
		'target' => array(
			'id'                   => $target_id,
			'expected_fingerprint' => $before_fingerprint,
		),
		'changes' => array(
			'title'   => $changed_title,
			'content' => $changed_content,
		),
	),
);

$plan = sgm_r3_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r3_headers( $credential, 'ci-r3-plan-0001', 'ci-r3-plan-nonce-0001' ),
	$plan_body
);
sgm_r3_expect( $plan, 200, 'R3 plan' );
$plan_data = sgm_r3_data( $plan );
$plan_payload = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$plan_id = (string) ( $plan_payload['plan_id'] ?? '' );
if ( ! str_starts_with( $plan_id, 'sgmp_' ) || 'prepared' !== ( $plan_payload['status'] ?? '' ) || empty( $plan_payload['preview']['has_changes'] ) ) {
	fwrite( STDERR, "R3 plan response is incomplete.\n" );
	exit( 1 );
}
$after_plan = get_post( $target_id );
if ( ! $after_plan instanceof WP_Post || $original_title !== $after_plan->post_title || $original_content !== $after_plan->post_content ) {
	fwrite( STDERR, "R3 plan mutated the draft target.\n" );
	exit( 1 );
}

$plan_read = sgm_r3_request(
	'GET',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id,
	sgm_r3_headers( $credential, 'ci-r3-plan-read-0001', 'ci-r3-plan-read-nonce-0001' )
);
sgm_r3_expect( $plan_read, 200, 'R3 plan read' );
$plan_read_data = sgm_r3_data( $plan_read );
if ( 'prepared' !== ( $plan_read_data['data']['status'] ?? '' ) ) {
	fwrite( STDERR, "R3 plan read did not preserve prepared state.\n" );
	exit( 1 );
}

$environment = EnvironmentPolicy::snapshot();
$environment_fingerprint = (string) ( $environment['fingerprint'] ?? '' );
$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
);
$apply_headers = sgm_r3_headers( $credential, 'ci-r3-apply-0001', 'ci-r3-apply-nonce-0001' );
$apply = sgm_r3_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_headers,
	$apply_body
);
sgm_r3_expect( $apply, 200, 'R3 apply' );
$apply_data = sgm_r3_data( $apply );
$operation_id = (string) ( $apply_data['data']['operation_id'] ?? '' );
if ( ! preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) || 'applied' !== ( $apply_data['data']['status'] ?? '' ) ) {
	fwrite( STDERR, "R3 apply did not return an applied operation.\n" );
	exit( 1 );
}
$after_apply = get_post( $target_id );
if ( ! $after_apply instanceof WP_Post || $changed_title !== $after_apply->post_title || $changed_content !== $after_apply->post_content || 'draft' !== $after_apply->post_status ) {
	fwrite( STDERR, "R3 apply did not persist the exact expected draft values.\n" );
	exit( 1 );
}

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce'] = 'ci-r3-apply-nonce-replay-0001';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r3_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_replay_headers,
	$apply_body
);
sgm_r3_expect( $apply_replay, 200, 'R3 apply idempotent replay' );
$apply_replay_data = sgm_r3_data( $apply_replay );
if ( true !== ( $apply_replay_data['idempotent_replay'] ?? false ) || $operation_id !== ( $apply_replay_data['data']['operation_id'] ?? '' ) ) {
	fwrite( STDERR, "R3 apply request replay was not idempotent.\n" );
	exit( 1 );
}

$verify = sgm_r3_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r3_headers( $credential, 'ci-r3-verify-0001', 'ci-r3-verify-nonce-0001' ),
	array( 'verify' => true )
);
sgm_r3_expect( $verify, 200, 'R3 verify' );
$verify_data = sgm_r3_data( $verify );
if ( true !== ( $verify_data['data']['verified'] ?? false ) || (string) ( $verify_data['data']['after_fingerprint'] ?? '' ) !== (string) ( $verify_data['data']['current_fingerprint'] ?? '' ) ) {
	fwrite( STDERR, "R3 verify did not prove current stored state.\n" );
	exit( 1 );
}

$operation_read = sgm_r3_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r3_headers( $credential, 'ci-r3-operation-read-0001', 'ci-r3-operation-read-nonce-0001' )
);
sgm_r3_expect( $operation_read, 200, 'R3 operation read' );
$operation_read_data = sgm_r3_data( $operation_read );
$bounded_operation = is_array( $operation_read_data['data'] ?? null ) ? $operation_read_data['data'] : array();
if ( array( 'title', 'content' ) !== ( $bounded_operation['changed_fields'] ?? array() ) || isset( $bounded_operation['before'] ) || isset( $bounded_operation['changes'] ) ) {
	fwrite( STDERR, "R3 operation read is not privacy-bounded.\n" );
	exit( 1 );
}

$rollback_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
);
$rollback = sgm_r3_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r3_headers( $credential, 'ci-r3-rollback-0001', 'ci-r3-rollback-nonce-0001' ),
	$rollback_body
);
sgm_r3_expect( $rollback, 200, 'R3 rollback' );
$rollback_data = sgm_r3_data( $rollback );
if ( 'rolled-back' !== ( $rollback_data['data']['status'] ?? '' ) ) {
	fwrite( STDERR, "R3 rollback did not return rolled-back state.\n" );
	exit( 1 );
}
$restored = get_post( $target_id );
if ( ! $restored instanceof WP_Post || $original_title !== $restored->post_title || $original_content !== $restored->post_content || 'draft' !== $restored->post_status ) {
	fwrite( STDERR, "R3 rollback did not restore exact original draft values.\n" );
	exit( 1 );
}

$audit_rows = $audit->recent( 100 );
$audit_json = wp_json_encode( $audit_rows );
if ( false === $audit_json || str_contains( $audit_json, $credential ) || ! str_contains( $audit_json, $plan_id ) || ! str_contains( $audit_json, $operation_id ) ) {
	fwrite( STDERR, "R3 audit evidence is incomplete or leaked the bearer credential.\n" );
	exit( 1 );
}

wp_delete_post( $target_id, true );
$tokens->revoke();

echo wp_json_encode(
	array(
		'ok'                      => true,
		'plugin_version'          => SEO_GEO_MANAGER_VERSION,
		'operation'               => 'content-change',
		'plan_created'            => true,
		'preview_no_mutation'     => true,
		'apply_exact'             => true,
		'apply_idempotent_replay' => true,
		'verify_exact'            => true,
		'operation_bounded'       => true,
		'rollback_exact'          => true,
		'audit_secret_free'       => true,
		'generic_proxy'           => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
