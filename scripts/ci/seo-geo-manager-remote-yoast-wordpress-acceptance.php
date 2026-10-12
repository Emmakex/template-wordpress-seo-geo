<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.3 with Yoast SEO 28.6.
 *
 * The remote caller never selects Yoast. Manager must resolve the active SEO
 * authority and delegate through Yoast's accepted public WordPress Abilities.
 */

use SeoGeo\Manager\Changes\YoastSeoMetadataChangeAdapter;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\YoastSeoAbilityBridge;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/** @return array<string, string> */
function sgm_r33_yoast_headers( string $credential, string $request_id, string $nonce ): array {
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
function sgm_r33_yoast_request( string $method, string $route, array $headers, array $body = array() ): WP_REST_Response {
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
		fwrite( STDERR, "R3.3 Yoast request did not return WP_REST_Response.\n" );
		exit( 1 );
	}

	return $response;
}

/** @return array<string, mixed> */
function sgm_r33_yoast_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_r33_yoast_expect( WP_REST_Response $response, int $expected, string $label ): void {
	$actual = $response->get_status();
	if ( $expected !== $actual ) {
		fwrite( STDERR, sprintf( "%s returned HTTP %d; expected %d.\n", $label, $actual, $expected ) );
		fwrite( STDERR, wp_json_encode( $response->get_data() ) . "\n" );
		exit( 1 );
	}
}

function sgm_r33_yoast_fail( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
if ( ! $admin instanceof WP_User || ! user_can( $admin, 'manage_options' ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast acceptance administrator is missing or not authorised.' );
}
wp_set_current_user( (int) $admin->ID );

if ( ! current_user_can( 'wpseo_edit_advanced_metadata' ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast remote operator lacks wpseo_edit_advanced_metadata.' );
}
if ( ! YoastSeoAbilityBridge::available() ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast public WordPress Abilities are unavailable.' );
}

$credentials = new Credentials();
$tokens      = new TokenService( $credentials );
$audit       = new Audit();
$tokens->revoke();
$audit->clear();

$issued = $tokens->issue( Credentials::default_scopes(), (int) $admin->ID, 'CI R3.3 Yoast SEO bridge' );
if ( is_wp_error( $issued ) ) {
	sgm_r33_yoast_fail( 'Could not issue R3.3 Yoast credential: ' . $issued->get_error_message() );
}
$credential = isset( $issued['credential'] ) ? (string) $issued['credential'] : '';
if ( '' === $credential ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast credential is empty.' );
}

$target_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'R3.3 Yoast SEO target',
		'post_content' => 'Dedicated R3.3 Yoast SEO fixture.',
	),
	true
);
if ( is_wp_error( $target_id ) || 1 > (int) $target_id ) {
	sgm_r33_yoast_fail( 'Could not create dedicated R3.3 Yoast draft target.' );
}
$target_id = (int) $target_id;

$before = YoastSeoMetadataChangeAdapter::inspect( $target_id );
if ( is_wp_error( $before ) ) {
	sgm_r33_yoast_fail( 'Yoast SEO inspection failed: ' . $before->get_error_message() );
}
$before_values      = isset( $before['values'] ) && is_array( $before['values'] ) ? $before['values'] : array();
$before_fingerprint = isset( $before['fingerprint'] ) && is_string( $before['fingerprint'] ) ? $before['fingerprint'] : '';
$before_read_only   = isset( $before['read_only'] ) && is_array( $before['read_only'] ) ? $before['read_only'] : array();
if ( '' === $before_fingerprint || array() === $before_values ) {
	sgm_r33_yoast_fail( 'Yoast SEO inspection did not return complete state.' );
}

$after_values              = $before_values;
$after_values['canonical'] = home_url( '/r33-yoast-seo-proof/' );

$plan_body = array(
	'operation' => 'seo-authority-change',
	'payload'   => array(
		'resource_id'          => $target_id,
		'expected_fingerprint' => $before_fingerprint,
		'values'               => $after_values,
	),
);

if ( isset( $plan_body['payload']['provider'] ) || isset( $plan_body['payload']['adapter'] ) || isset( $plan_body['payload']['authority'] ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast fixture must not select a provider in the caller payload.' );
}

$plan = sgm_r33_yoast_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r33_yoast_headers( $credential, 'ci-r33-yoast-plan-0001', 'ci-r33-yoast-plan-nonce-0001' ),
	$plan_body
);
sgm_r33_yoast_expect( $plan, 200, 'R3.3 Yoast plan' );
$plan_data    = sgm_r33_yoast_data( $plan );
$plan_payload = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview      = is_array( $plan_payload['preview'] ?? null ) ? $plan_payload['preview'] : array();
$authority    = is_array( $preview['remote_seo_authority'] ?? null ) ? $preview['remote_seo_authority'] : array();
$plan_id      = (string) ( $plan_payload['plan_id'] ?? '' );
if (
	! str_starts_with( $plan_id, 'sgmp_' )
	|| 'seo-authority-change' !== ( $plan_payload['operation'] ?? '' )
	|| 'prepared' !== ( $plan_payload['status'] ?? '' )
	|| true !== ( $preview['has_changes'] ?? false )
	|| 'yoast' !== ( $authority['authority_id'] ?? '' )
	|| 'yoast-abilities' !== ( $authority['adapter'] ?? '' )
	|| 'yoast' !== ( $authority['provider'] ?? '' )
	|| array( 'canonical', 'robots', 'schema' ) !== ( $authority['writable_signals'] ?? array() )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast plan did not auto-resolve the exact accepted SEO authority.' );
}

$after_plan = YoastSeoMetadataChangeAdapter::inspect( $target_id );
if (
	is_wp_error( $after_plan )
	|| $before_fingerprint !== ( $after_plan['fingerprint'] ?? '' )
	|| $before_values !== ( $after_plan['values'] ?? null )
	|| $before_read_only !== ( $after_plan['read_only'] ?? null )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast Preview mutated provider state.' );
}

$environment             = EnvironmentPolicy::snapshot();
$environment_fingerprint = (string) ( $environment['fingerprint'] ?? '' );
$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
);
$apply_headers = sgm_r33_yoast_headers( $credential, 'ci-r33-yoast-apply-0001', 'ci-r33-yoast-apply-nonce-0001' );
$apply = sgm_r33_yoast_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_headers,
	$apply_body
);
sgm_r33_yoast_expect( $apply, 200, 'R3.3 Yoast apply' );
$apply_data   = sgm_r33_yoast_data( $apply );
$operation_id = (string) ( $apply_data['data']['operation_id'] ?? '' );
$apply_auth   = is_array( $apply_data['data']['seo_authority'] ?? null ) ? $apply_data['data']['seo_authority'] : array();
if (
	! preg_match( '/^[a-f0-9\-]{36}$/', $operation_id )
	|| 'applied' !== ( $apply_data['data']['status'] ?? '' )
	|| 'seo-authority-change' !== ( $apply_data['data']['operation'] ?? '' )
	|| 'yoast' !== ( $apply_auth['authority_id'] ?? '' )
	|| 'yoast-abilities' !== ( $apply_auth['adapter'] ?? '' )
	|| 'yoast' !== ( $apply_auth['provider'] ?? '' )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast Apply did not return bounded authority evidence.' );
}

$after = YoastSeoMetadataChangeAdapter::inspect( $target_id );
if (
	is_wp_error( $after )
	|| $after_values !== ( $after['values'] ?? null )
	|| $before_read_only !== ( $after['read_only'] ?? null )
	|| (string) ( $apply_data['data']['after_fingerprint'] ?? '' ) !== (string) ( $after['fingerprint'] ?? '' )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast Apply did not persist exact public-ability state.' );
}

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'ci-r33-yoast-apply-nonce-replay-0001';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r33_yoast_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	$apply_replay_headers,
	$apply_body
);
sgm_r33_yoast_expect( $apply_replay, 200, 'R3.3 Yoast apply replay' );
$apply_replay_data = sgm_r33_yoast_data( $apply_replay );
if ( true !== ( $apply_replay_data['idempotent_replay'] ?? false ) || $operation_id !== ( $apply_replay_data['data']['operation_id'] ?? '' ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast Apply replay was not idempotent.' );
}

$verify = sgm_r33_yoast_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r33_yoast_headers( $credential, 'ci-r33-yoast-verify-0001', 'ci-r33-yoast-verify-nonce-0001' ),
	array( 'verify' => true )
);
sgm_r33_yoast_expect( $verify, 200, 'R3.3 Yoast verify' );
$verify_data = sgm_r33_yoast_data( $verify );
if (
	true !== ( $verify_data['data']['verified'] ?? false )
	|| (string) ( $verify_data['data']['after_fingerprint'] ?? '' ) !== (string) ( $verify_data['data']['current_fingerprint'] ?? '' )
	|| 'yoast' !== ( $verify_data['data']['seo_authority']['authority_id'] ?? '' )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast Verify did not prove exact provider-owned SEO state.' );
}

$operation_read = sgm_r33_yoast_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r33_yoast_headers( $credential, 'ci-r33-yoast-operation-read-0001', 'ci-r33-yoast-operation-read-nonce-0001' )
);
sgm_r33_yoast_expect( $operation_read, 200, 'R3.3 Yoast operation read' );
$operation_data = sgm_r33_yoast_data( $operation_read );
$bounded        = is_array( $operation_data['data'] ?? null ) ? $operation_data['data'] : array();
if (
	'seo-authority-change' !== ( $bounded['operation'] ?? '' )
	|| 'yoast' !== ( $bounded['seo_authority']['authority_id'] ?? '' )
	|| isset( $bounded['before_values'] )
	|| isset( $bounded['after_values'] )
	|| isset( $bounded['values'] )
	|| isset( $bounded['changes'] )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast operation evidence is not privacy-bounded.' );
}

$rollback = sgm_r33_yoast_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r33_yoast_headers( $credential, 'ci-r33-yoast-rollback-0001', 'ci-r33-yoast-rollback-nonce-0001' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r33_yoast_expect( $rollback, 200, 'R3.3 Yoast rollback' );
$rollback_data = sgm_r33_yoast_data( $rollback );
if ( 'rolled-back' !== ( $rollback_data['data']['status'] ?? '' ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast rollback did not return rolled-back state.' );
}

$restored = YoastSeoMetadataChangeAdapter::inspect( $target_id );
if (
	is_wp_error( $restored )
	|| $before_values !== ( $restored['values'] ?? null )
	|| $before_read_only !== ( $restored['read_only'] ?? null )
	|| $before_fingerprint !== ( $restored['fingerprint'] ?? '' )
) {
	sgm_r33_yoast_fail( 'R3.3 Yoast rollback did not restore exact initial provider state.' );
}

$audit_json = wp_json_encode( $audit->recent( 100 ) );
if ( false === $audit_json || str_contains( $audit_json, $credential ) || ! str_contains( $audit_json, $plan_id ) || ! str_contains( $audit_json, $operation_id ) ) {
	sgm_r33_yoast_fail( 'R3.3 Yoast audit is incomplete or leaked the bearer credential.' );
}

wp_delete_post( $target_id, true );
$tokens->revoke();

echo wp_json_encode(
	array(
		'ok'                      => true,
		'plugin_version'          => SEO_GEO_MANAGER_VERSION,
		'operation'               => 'seo-authority-change',
		'authority_auto_resolved' => true,
		'authority'               => 'yoast',
		'adapter'                 => 'yoast-abilities',
		'provider'                => 'yoast',
		'provider_selected_by_caller' => false,
		'public_abilities_only'   => true,
		'private_yoast_meta'      => false,
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
