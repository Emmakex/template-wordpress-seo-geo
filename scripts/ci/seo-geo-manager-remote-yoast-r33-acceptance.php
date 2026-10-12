<?php
/** Corrected R3.3 Yoast 28.6 remote SEO acceptance fixture. */

use SeoGeo\Manager\Changes\YoastSeoMetadataChangeAdapter;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use SeoGeo\Manager\Support\YoastSeoAbilityBridge;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function sgm_r33y_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @return array<string,string> */
function sgm_r33y_headers( string $credential, string $request_id, string $nonce ): array {
	return array(
		'authorization'                => 'Bearer ' . $credential,
		'x-seo-geo-manager-request-id' => $request_id,
		'x-seo-geo-manager-nonce'      => $nonce,
		'x-seo-geo-manager-timestamp'  => (string) time(),
	);
}

/** @param array<string,string> $headers @param array<string,mixed> $body */
function sgm_r33y_request( string $method, string $route, array $headers, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) ?: '{}' );
	}
	$response = rest_do_request( $request );
	sgm_r33y_assert( $response instanceof WP_REST_Response, 'Remote request did not return WP_REST_Response.' );
	return $response;
}

/** @return array<string,mixed> */
function sgm_r33y_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	return is_array( $data ) ? $data : array();
}

function sgm_r33y_status( WP_REST_Response $response, int $expected, string $label ): void {
	sgm_r33y_assert( $expected === $response->get_status(), $label . ' returned ' . $response->get_status() . ': ' . wp_json_encode( $response->get_data() ) );
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';
$admin = get_user_by( 'login', 'manageradmin' );
sgm_r33y_assert( $admin instanceof WP_User && user_can( $admin, 'manage_options' ), 'Acceptance administrator missing.' );
wp_set_current_user( (int) $admin->ID );
sgm_r33y_assert( current_user_can( 'wpseo_edit_advanced_metadata' ), 'Remote operator lacks Yoast advanced metadata capability.' );
sgm_r33y_assert( YoastSeoAbilityBridge::available(), 'Yoast public WordPress Abilities unavailable.' );

$credentials = new Credentials();
$tokens      = new TokenService( $credentials );
$audit       = new Audit();
$tokens->revoke();
$audit->clear();
$scopes = array( 'site.read', 'site.diagnostics', 'content.write', 'operations.read', 'operations.apply', 'operations.rollback' );
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.3 Yoast SEO' );
sgm_r33y_assert( ! is_wp_error( $issued ), 'Could not issue mutation credential.' );
$credential = (string) ( $issued['credential'] ?? '' );
sgm_r33y_assert( '' !== $credential, 'Mutation credential missing.' );

$target_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'R3.3 Yoast SEO target' ), true );
sgm_r33y_assert( ! is_wp_error( $target_id ) && 0 < (int) $target_id, 'Could not create Yoast target.' );
$target_id = (int) $target_id;
$before = YoastSeoMetadataChangeAdapter::inspect( $target_id );
sgm_r33y_assert( ! is_wp_error( $before ), 'Yoast SEO inspection failed.' );
$before_values    = is_array( $before['values'] ?? null ) ? $before['values'] : array();
$before_read_only = is_array( $before['read_only'] ?? null ) ? $before['read_only'] : array();
$before_fp        = (string) ( $before['fingerprint'] ?? '' );
$after_values     = $before_values;
$after_values['canonical'] = home_url( '/r33-yoast-seo-proof/' );

$plan_payload = array( 'resource_id' => $target_id, 'expected_fingerprint' => $before_fp, 'values' => $after_values );
sgm_r33y_assert( ! isset( $plan_payload['provider'], $plan_payload['adapter'], $plan_payload['authority'] ), 'Caller payload must be provider-neutral.' );
$plan = sgm_r33y_request(
	'POST', '/seo-geo-manager/v1/remote/plans',
	sgm_r33y_headers( $credential, 'r33y-plan-1', 'r33y-plan-nonce-1' ),
	array( 'operation' => 'seo-authority-change', 'payload' => $plan_payload )
);
sgm_r33y_status( $plan, 200, 'Plan' );
$plan_data = sgm_r33y_data( $plan );
$plan_row  = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview   = is_array( $plan_row['preview'] ?? null ) ? $plan_row['preview'] : array();
$authority = is_array( $preview['remote_seo_authority'] ?? null ) ? $preview['remote_seo_authority'] : array();
$plan_id   = (string) ( $plan_row['plan_id'] ?? '' );
sgm_r33y_assert( str_starts_with( $plan_id, 'sgmp_' ) && 'prepared' === ( $plan_row['status'] ?? '' ), 'Plan state invalid.' );
sgm_r33y_assert( 'yoast' === ( $authority['authority_id'] ?? '' ) && 'yoast-abilities' === ( $authority['adapter'] ?? '' ) && 'yoast' === ( $authority['provider'] ?? '' ), 'Yoast authority not auto-resolved.' );
sgm_r33y_assert( array( 'canonical', 'robots', 'schema' ) === ( $authority['writable_signals'] ?? array() ), 'Yoast writable signals mismatch.' );
$unchanged = YoastSeoMetadataChangeAdapter::inspect( $target_id );
sgm_r33y_assert( ! is_wp_error( $unchanged ) && $before_fp === ( $unchanged['fingerprint'] ?? '' ) && $before_values === ( $unchanged['values'] ?? null ), 'Preview mutated Yoast state.' );

$environment_fp = (string) ( EnvironmentPolicy::snapshot()['fingerprint'] ?? '' );
$apply_body = array( 'confirm' => true, 'environment_fingerprint' => $environment_fp );
$apply_headers = sgm_r33y_headers( $credential, 'r33y-apply-1', 'r33y-apply-nonce-1' );
$apply = sgm_r33y_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_headers, $apply_body );
sgm_r33y_status( $apply, 200, 'Apply' );
$apply_data = sgm_r33y_data( $apply );
$applied    = is_array( $apply_data['data'] ?? null ) ? $apply_data['data'] : array();
$operation_id = (string) ( $applied['operation_id'] ?? '' );
sgm_r33y_assert( preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) === 1 && 'applied' === ( $applied['status'] ?? '' ), 'Apply operation invalid.' );
sgm_r33y_assert( 'yoast' === ( $applied['seo_authority']['authority_id'] ?? '' ), 'Apply authority evidence missing.' );
$after = YoastSeoMetadataChangeAdapter::inspect( $target_id );
sgm_r33y_assert( ! is_wp_error( $after ) && $after_values === ( $after['values'] ?? null ) && $before_read_only === ( $after['read_only'] ?? null ) && ( $applied['after_fingerprint'] ?? '' ) === ( $after['fingerprint'] ?? '' ), 'Apply did not preserve exact Yoast public state.' );

$replay_headers = $apply_headers;
$replay_headers['x-seo-geo-manager-nonce'] = 'r33y-apply-nonce-2';
$replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$replay = sgm_r33y_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $replay_headers, $apply_body );
sgm_r33y_status( $replay, 200, 'Apply replay' );
$replay_data = sgm_r33y_data( $replay );
sgm_r33y_assert( true === ( $replay_data['idempotent_replay'] ?? false ) && $operation_id === ( $replay_data['data']['operation_id'] ?? '' ), 'Apply replay not idempotent.' );

$verify = sgm_r33y_request( 'POST', '/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify', sgm_r33y_headers( $credential, 'r33y-verify-1', 'r33y-verify-nonce-1' ), array( 'verify' => true ) );
sgm_r33y_status( $verify, 200, 'Verify' );
$verify_data = sgm_r33y_data( $verify );
sgm_r33y_assert( true === ( $verify_data['data']['verified'] ?? false ) && ( $verify_data['data']['after_fingerprint'] ?? '' ) === ( $verify_data['data']['current_fingerprint'] ?? '' ), 'Exact Yoast provider verification failed.' );

$read = sgm_r33y_request( 'GET', '/seo-geo-manager/v1/remote/operations/' . $operation_id, sgm_r33y_headers( $credential, 'r33y-read-1', 'r33y-read-nonce-1' ) );
sgm_r33y_status( $read, 200, 'Operation read' );
$read_data = sgm_r33y_data( $read );
$bounded   = is_array( $read_data['data'] ?? null ) ? $read_data['data'] : array();
sgm_r33y_assert( 'seo-authority-change' === ( $bounded['operation'] ?? '' ) && 'yoast' === ( $bounded['seo_authority']['authority_id'] ?? '' ), 'Bounded Yoast authority evidence missing.' );
sgm_r33y_assert( ! isset( $bounded['before_values'], $bounded['after_values'], $bounded['values'], $bounded['changes'] ), 'Operation response leaked provider values.' );

$rollback = sgm_r33y_request( 'POST', '/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback', sgm_r33y_headers( $credential, 'r33y-rollback-1', 'r33y-rollback-nonce-1' ), array( 'confirm' => true, 'environment_fingerprint' => $environment_fp ) );
sgm_r33y_status( $rollback, 200, 'Rollback' );
$rollback_data = sgm_r33y_data( $rollback );
sgm_r33y_assert( 'rolled-back' === ( $rollback_data['data']['status'] ?? '' ), 'Rollback status mismatch.' );
$restored = YoastSeoMetadataChangeAdapter::inspect( $target_id );
sgm_r33y_assert( ! is_wp_error( $restored ) && $before_values === ( $restored['values'] ?? null ) && $before_read_only === ( $restored['read_only'] ?? null ) && $before_fp === ( $restored['fingerprint'] ?? '' ), 'Rollback did not restore exact Yoast state.' );

$audit_json = wp_json_encode( $audit->recent( 100 ) );
sgm_r33y_assert( false !== $audit_json && ! str_contains( $audit_json, $credential ), 'Audit leaked credential.' );
$tokens->revoke();
wp_delete_post( $target_id, true );

echo wp_json_encode( array( 'ok' => true, 'plugin_version' => SEO_GEO_MANAGER_VERSION, 'operation' => 'seo-authority-change', 'authority_auto_resolved' => true, 'authority' => 'yoast', 'adapter' => 'yoast-abilities', 'provider_selected_by_caller' => false, 'public_abilities_only' => true, 'private_yoast_meta' => false, 'preview_no_mutation' => true, 'apply_exact' => true, 'apply_idempotent_replay' => true, 'verify_exact' => true, 'operation_bounded' => true, 'rollback_exact' => true, 'audit_secret_free' => true, 'generic_proxy' => false ), JSON_UNESCAPED_SLASHES ) . PHP_EOL;
