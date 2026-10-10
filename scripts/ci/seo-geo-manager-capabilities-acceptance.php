<?php
/**
 * Runtime acceptance for SEO/GEO Manager capability discovery.
 *
 * Executed with WP-CLI eval-file inside the Manager WordPress fixture.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_capabilities_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_capabilities_request(): array {
	$request  = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/capabilities' );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * Recursively reject secret-like field names in a public/authenticated
 * capability manifest. Values are intentionally ignored: the contract is that
 * secret material has no field in this response at all.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_capabilities_assert_no_secret_keys( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}

	$forbidden = array(
		'password',
		'application_password',
		'application_password_value',
		'nonce',
		'cookie',
		'secret',
		'token',
		'authorization',
	);

	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			$normalized = strtolower( $key );
			foreach ( $forbidden as $needle ) {
				seo_geo_manager_capabilities_accept(
					! str_contains( $normalized, $needle ) || in_array(
						$normalized,
						array(
							'application_passwords_supported',
							'application_passwords_available',
							'browser_session_nonce',
							'secrets_returned',
						),
						true
					),
					'Capability manifest exposes a forbidden secret-like field: ' . $key
				);
			}
		}
		seo_geo_manager_capabilities_assert_no_secret_keys( $child );
	}
}

// Anonymous callers must not receive the capability manifest.
wp_set_current_user( 0 );
$anonymous = seo_geo_manager_capabilities_request();
seo_geo_manager_capabilities_accept( 401 === $anonymous['status'], 'Anonymous capability discovery was not rejected with 401.' );

// Subscriber represents an authenticated identity with no editorial control.
$subscriber_id = wp_create_user( 'manager-subscriber', wp_generate_password( 32, true, true ), 'manager-subscriber@example.test' );
seo_geo_manager_capabilities_accept( ! is_wp_error( $subscriber_id ), 'Could not create subscriber capability fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$subscriber_response = seo_geo_manager_capabilities_request();
seo_geo_manager_capabilities_accept( 403 === $subscriber_response['status'], 'Subscriber capability discovery was not rejected with 403.' );

// Editor may operate content but must not inherit site-wide administration.
$editor_id = wp_create_user( 'manager-editor', wp_generate_password( 32, true, true ), 'manager-editor@example.test' );
seo_geo_manager_capabilities_accept( ! is_wp_error( $editor_id ), 'Could not create editor capability fixture.' );
$editor = new WP_User( (int) $editor_id );
$editor->set_role( 'editor' );
wp_set_current_user( (int) $editor_id );
$editor_response = seo_geo_manager_capabilities_request();
seo_geo_manager_capabilities_accept( 200 === $editor_response['status'], 'Editor capability discovery failed.' );
seo_geo_manager_capabilities_accept( is_array( $editor_response['data'] ), 'Editor capability manifest is invalid.' );
$editor_data = $editor_response['data'];
seo_geo_manager_capabilities_accept( (int) $editor_id === (int) ( $editor_data['principal']['user_id'] ?? 0 ), 'Editor principal ID mismatch.' );
seo_geo_manager_capabilities_accept( true === ( $editor_data['principal']['authenticated'] ?? false ), 'Editor principal is not marked authenticated.' );
seo_geo_manager_capabilities_accept( true === ( $editor_data['capabilities']['content_change_set']['available'] ?? false ), 'Editor did not receive content change-set capability.' );
seo_geo_manager_capabilities_accept( true === ( $editor_data['capabilities']['post_publish']['available'] ?? false ), 'Editor did not receive post publishing capability.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['capabilities']['site_wide_operations']['available'] ?? true ), 'Editor incorrectly received site-wide administration.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['capabilities']['permalink_administration']['available'] ?? true ), 'Editor incorrectly received permalink administration.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['operations']['field_gate.inspect']['available'] ?? true ), 'Editor incorrectly received administrator Field Gate access.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['operations']['permalinks.admin']['available'] ?? true ), 'Editor incorrectly received guarded permalink operations.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['authentication']['secrets_returned'] ?? true ), 'Editor manifest does not declare secret reflection disabled.' );
seo_geo_manager_capabilities_accept( false === ( $editor_data['authentication']['generic_remote_shell'] ?? true ), 'Editor manifest incorrectly exposes generic remote shell.' );
seo_geo_manager_capabilities_assert_no_secret_keys( $editor_data );

// Administrator receives the site-wide operation families, but still no secret material.
wp_set_current_user( 1 );
seo_geo_manager_capabilities_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );
$admin_response = seo_geo_manager_capabilities_request();
seo_geo_manager_capabilities_accept( 200 === $admin_response['status'], 'Administrator capability discovery failed.' );
seo_geo_manager_capabilities_accept( is_array( $admin_response['data'] ), 'Administrator capability manifest is invalid.' );
$admin_data = $admin_response['data'];
seo_geo_manager_capabilities_accept( true === ( $admin_data['capabilities']['site_wide_operations']['available'] ?? false ), 'Administrator site-wide capability is missing.' );
seo_geo_manager_capabilities_accept( true === ( $admin_data['capabilities']['permalink_administration']['available'] ?? false ), 'Administrator permalink capability is missing.' );
seo_geo_manager_capabilities_accept( true === ( $admin_data['operations']['field_gate.inspect']['available'] ?? false ), 'Administrator Field Gate capability is missing.' );
seo_geo_manager_capabilities_accept( true === ( $admin_data['operations']['permalinks.admin']['available'] ?? false ), 'Administrator permalink operation discovery is missing.' );
seo_geo_manager_capabilities_accept( is_bool( $admin_data['authentication']['application_passwords_supported'] ?? null ), 'Application Password support state is not boolean.' );
seo_geo_manager_capabilities_accept( is_bool( $admin_data['authentication']['application_passwords_available'] ?? null ), 'Application Password availability state is not boolean.' );
seo_geo_manager_capabilities_assert_no_secret_keys( $admin_data );

echo wp_json_encode(
	array(
		'ok'               => true,
		'schema_version'   => 1,
		'anonymous_status' => $anonymous['status'],
		'subscriber_status'=> $subscriber_response['status'],
		'editor_status'    => $editor_response['status'],
		'admin_status'     => $admin_response['status'],
		'least_privilege'  => true,
		'secrets_returned' => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
