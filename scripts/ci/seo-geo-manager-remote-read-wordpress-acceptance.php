<?php
/**
 * WordPress runtime acceptance for the SEO/GEO Manager-owned remote read bridge.
 *
 * Executed through WP-CLI after the plugin is active in the pinned WordPress fixture.
 */

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded.\n" );
	exit( 1 );
}

/** @param array<string, string> $headers Request headers. */
function sgm_remote_request( string $route, array $headers ): WP_REST_Response {
	$request = new WP_REST_Request( 'GET', $route );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	$response = rest_do_request( $request );
	if ( ! $response instanceof WP_REST_Response ) {
		fwrite( STDERR, "Remote request did not return WP_REST_Response.\n" );
		exit( 1 );
	}
	return $response;
}

/** @return array<string, string> */
function sgm_remote_headers( string $credential, string $request_id, string $nonce ): array {
	return array(
		'authorization'               => 'Bearer ' . $credential,
		'x-seo-geo-manager-request-id' => $request_id,
		'x-seo-geo-manager-nonce'      => $nonce,
		'x-seo-geo-manager-timestamp'  => (string) time(),
	);
}

/** @return array<string, mixed> */
function sgm_response_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_expect_status( WP_REST_Response $response, int $expected, string $label ): void {
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
	fwrite( STDERR, "Acceptance administrator is missing or not authorised.\n" );
	exit( 1 );
}

$credentials = new Credentials();
$tokens      = new TokenService( $credentials );
$audit       = new Audit();
$tokens->revoke();
$audit->clear();

$issued = $tokens->issue( Credentials::default_scopes(), (int) $admin->ID, 'CI remote bridge' );
if ( is_wp_error( $issued ) ) {
	fwrite( STDERR, 'Could not issue remote credential: ' . $issued->get_error_message() . "\n" );
	exit( 1 );
}
$credential = isset( $issued['credential'] ) ? (string) $issued['credential'] : '';
if ( '' === $credential || ! str_starts_with( $credential, 'sgm1_' ) ) {
	fwrite( STDERR, "Issued remote credential has an unexpected shape.\n" );
	exit( 1 );
}

$status = sgm_remote_request( '/seo-geo-manager/v1/remote/status', sgm_remote_headers( $credential, 'ci-status-0001', 'ci-nonce-status-0001' ) );
sgm_expect_status( $status, 200, 'remote status' );
$status_data = sgm_response_data( $status );
if ( true !== ( $status_data['ok'] ?? false ) || SEO_GEO_MANAGER_VERSION !== ( $status_data['data']['manager']['version'] ?? '' ) || 'R3' !== ( $status_data['data']['manager']['remote_milestone'] ?? '' ) ) {
	fwrite( STDERR, "Remote status did not return current R3 Manager identity.\n" );
	exit( 1 );
}

$capabilities = sgm_remote_request( '/seo-geo-manager/v1/remote/capabilities', sgm_remote_headers( $credential, 'ci-capabilities-0001', 'ci-nonce-capabilities-0001' ) );
sgm_expect_status( $capabilities, 200, 'remote capabilities' );
$capability_data = sgm_response_data( $capabilities );
$mutation = is_array( $capability_data['data']['mutation_transport'] ?? null ) ? $capability_data['data']['mutation_transport'] : array();
if ( true !== ( $capability_data['ok'] ?? false ) || true !== ( $mutation['available'] ?? false ) || array( 'content-change' ) !== ( $mutation['supported_operations'] ?? array() ) ) {
	fwrite( STDERR, "R3 capabilities did not expose only the accepted content-change mutation transport.\n" );
	exit( 1 );
}

$intelligence = sgm_remote_request( '/seo-geo-manager/v1/remote/site-intelligence', sgm_remote_headers( $credential, 'ci-intelligence-0001', 'ci-nonce-intelligence-0001' ) );
sgm_expect_status( $intelligence, 200, 'remote site intelligence' );

$scope_update = $credentials->set_scopes( array( 'site.read' ) );
if ( is_wp_error( $scope_update ) ) {
	fwrite( STDERR, 'Could not narrow remote scopes: ' . $scope_update->get_error_message() . "\n" );
	exit( 1 );
}
$scope_denied = sgm_remote_request( '/seo-geo-manager/v1/remote/site-intelligence', sgm_remote_headers( $credential, 'ci-scope-denied-0001', 'ci-nonce-scope-denied-0001' ) );
sgm_expect_status( $scope_denied, 403, 'scope denial' );
$scope_denied_data = sgm_response_data( $scope_denied );
if ( 'scope_denied' !== ( $scope_denied_data['code'] ?? '' ) ) {
	fwrite( STDERR, "Remote scope denial returned an unexpected error code.\n" );
	exit( 1 );
}

$scope_restore = $credentials->set_scopes( Credentials::default_scopes() );
if ( is_wp_error( $scope_restore ) ) {
	fwrite( STDERR, 'Could not restore remote scopes: ' . $scope_restore->get_error_message() . "\n" );
	exit( 1 );
}

$replay_headers = sgm_remote_headers( $credential, 'ci-replay-first-0001', 'ci-replay-nonce-0001' );
$replay_first = sgm_remote_request( '/seo-geo-manager/v1/remote/status', $replay_headers );
sgm_expect_status( $replay_first, 200, 'replay first request' );
$replay_headers['x-seo-geo-manager-request-id'] = 'ci-replay-second-0001';
$replay_second = sgm_remote_request( '/seo-geo-manager/v1/remote/status', $replay_headers );
sgm_expect_status( $replay_second, 409, 'replay rejection' );
$replay_data = sgm_response_data( $replay_second );
if ( 'validation_failed' !== ( $replay_data['code'] ?? '' ) ) {
	fwrite( STDERR, "Nonce replay returned an unexpected error code.\n" );
	exit( 1 );
}

$audit_payload = wp_json_encode( $audit->recent( 100 ) );
if ( false === $audit_payload || str_contains( $audit_payload, $credential ) ) {
	fwrite( STDERR, "Remote audit leaked the bearer credential.\n" );
	exit( 1 );
}

$revoked = $tokens->revoke();
if ( ! empty( $revoked['credential_present'] ) || ! empty( $revoked['enabled'] ) ) {
	fwrite( STDERR, "Remote credential revocation did not disable the connection.\n" );
	exit( 1 );
}
$post_revoke = sgm_remote_request( '/seo-geo-manager/v1/remote/status', sgm_remote_headers( $credential, 'ci-revoked-0001', 'ci-nonce-revoked-0001' ) );
sgm_expect_status( $post_revoke, 401, 'revoked credential' );
$post_revoke_data = sgm_response_data( $post_revoke );
if ( 'authentication_failed' !== ( $post_revoke_data['code'] ?? '' ) ) {
	fwrite( STDERR, "Revoked credential returned an unexpected error code.\n" );
	exit( 1 );
}

echo wp_json_encode(
	array(
		'ok'                        => true,
		'plugin_version'            => SEO_GEO_MANAGER_VERSION,
		'manager_owned_credential'  => true,
		'authenticated_read'        => true,
		'r3_capability_advertised'  => true,
		'r3_operation_allowlist'    => array( 'content-change' ),
		'scope_denial'              => true,
		'nonce_replay_rejected'     => true,
		'credential_revocation'     => true,
		'post_revoke_http_401'      => true,
		'audit_secret_free'         => true,
		'remote_mutation_performed' => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
