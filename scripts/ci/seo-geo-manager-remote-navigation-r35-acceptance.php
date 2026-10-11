<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.5 remote navigation control.
 */

use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function sgm_r35_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @return array<string, string> */
function sgm_r35_headers( string $credential, string $request_id, string $nonce ): array {
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
function sgm_r35_request( string $method, string $route, array $headers, array $body = array(), array $params = array() ): WP_REST_Response {
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
	sgm_r35_assert( $response instanceof WP_REST_Response, 'Remote request did not return WP_REST_Response.' );

	return $response;
}

/** @return array<string, mixed> */
function sgm_r35_data( WP_REST_Response $response ): array {
	$data = $response->get_data();

	return is_array( $data ) ? $data : array();
}

function sgm_r35_status( WP_REST_Response $response, int $expected, string $label ): void {
	sgm_r35_assert(
		$expected === $response->get_status(),
		$label . ' returned ' . $response->get_status() . ': ' . wp_json_encode( $response->get_data() )
	);
}

/**
 * @return object
 */
function sgm_r35_menu_item( int $menu_id, int $item_id ) {
	$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
	$items = is_array( $items ) ? $items : array();
	foreach ( $items as $item ) {
		if ( is_object( $item ) && (int) ( $item->ID ?? 0 ) === $item_id ) {
			return $item;
		}
	}
	throw new RuntimeException( 'Navigation item ' . $item_id . ' is unavailable.' );
}

/** @return array<string, mixed> */
function sgm_r35_item_state( object $item ): array {
	return array(
		'id'          => (int) ( $item->ID ?? 0 ),
		'order'       => (int) ( $item->menu_order ?? 0 ),
		'parent'      => (int) ( $item->menu_item_parent ?? 0 ),
		'type'        => (string) ( $item->type ?? '' ),
		'object'      => (string) ( $item->object ?? '' ),
		'object_id'   => (int) ( $item->object_id ?? 0 ),
		'title'       => (string) ( $item->title ?? '' ),
		'url'         => (string) ( $item->url ?? '' ),
		'target'      => (string) ( $item->target ?? '' ),
		'attr_title'  => (string) ( $item->attr_title ?? '' ),
		'description' => (string) ( $item->description ?? '' ),
		'classes'     => array_values( array_filter( (array) ( $item->classes ?? array() ), 'is_string' ) ),
		'xfn'         => (string) ( $item->xfn ?? '' ),
		'status'      => (string) get_post_status( (int) ( $item->ID ?? 0 ) ),
	);
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
sgm_r35_assert( $admin instanceof WP_User && user_can( $admin, 'manage_options' ) && user_can( $admin, 'edit_theme_options' ), 'R3.5 acceptance administrator is unavailable.' );
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
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.5 navigation bridge' );
sgm_r35_assert( ! is_wp_error( $issued ), 'Could not issue R3.5 mutation credential.' );
$credential = (string) ( $issued['credential'] ?? '' );
sgm_r35_assert( '' !== $credential, 'R3.5 credential is empty.' );

$menu_id = wp_create_nav_menu( 'R3.5 Primary Navigation' );
sgm_r35_assert( ! is_wp_error( $menu_id ) && 0 < (int) $menu_id, 'Could not create R3.5 navigation menu.' );
$menu_id = (int) $menu_id;

$legacy_url    = '/legacy-contact/';
$suggested_url = home_url( '/contact/' );
$matching_id   = wp_update_nav_menu_item(
	$menu_id,
	0,
	array(
		'menu-item-title'       => 'Legacy Contact',
		'menu-item-url'         => $legacy_url,
		'menu-item-target'      => '_blank',
		'menu-item-classes'     => 'nav-link legacy-link',
		'menu-item-xfn'         => 'nofollow',
		'menu-item-description' => 'Legacy navigation description',
		'menu-item-attr-title'  => 'Legacy contact title',
		'menu-item-position'    => 1,
		'menu-item-parent-id'   => 0,
		'menu-item-status'      => 'publish',
		'menu-item-type'        => 'custom',
	),
);
sgm_r35_assert( ! is_wp_error( $matching_id ) && 0 < (int) $matching_id, 'Could not create matching navigation item.' );
$matching_id = (int) $matching_id;

$untouched_id = wp_update_nav_menu_item(
	$menu_id,
	0,
	array(
		'menu-item-title'       => 'Untouched About',
		'menu-item-url'         => home_url( '/about/' ),
		'menu-item-target'      => '',
		'menu-item-classes'     => 'nav-link about-link',
		'menu-item-xfn'         => '',
		'menu-item-description' => 'Untouched navigation item',
		'menu-item-attr-title'  => 'About title',
		'menu-item-position'    => 2,
		'menu-item-parent-id'   => 0,
		'menu-item-status'      => 'publish',
		'menu-item-type'        => 'custom',
	),
);
sgm_r35_assert( ! is_wp_error( $untouched_id ) && 0 < (int) $untouched_id, 'Could not create untouched navigation item.' );
$untouched_id = (int) $untouched_id;

$before_matching  = sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $matching_id ) );
$before_untouched = sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $untouched_id ) );

$capabilities = sgm_r35_request(
	'GET',
	'/seo-geo-manager/v1/remote/capabilities',
	sgm_r35_headers( $credential, 'r35-capabilities-1', 'r35-capabilities-nonce-1' )
);
sgm_r35_status( $capabilities, 200, 'R3.5 capabilities' );
$capability_data = sgm_r35_data( $capabilities );
$remote_data     = is_array( $capability_data['data'] ?? null ) ? $capability_data['data'] : array();
sgm_r35_assert( 'R3.5' === ( $remote_data['mutation_transport']['milestone'] ?? '' ), 'R3.5 milestone is not advertised.' );
sgm_r35_assert( in_array( 'navigation-change', $remote_data['mutation_transport']['supported_operations'] ?? array(), true ), 'Navigation mutation is not advertised.' );
sgm_r35_assert( 'site.diagnostics' === ( $remote_data['read_surface']['/remote/navigation'] ?? '' ), 'Navigation inspection route is not advertised.' );

$inspection = sgm_r35_request(
	'GET',
	'/seo-geo-manager/v1/remote/navigation',
	sgm_r35_headers( $credential, 'r35-navigation-read-1', 'r35-navigation-read-nonce-1' ),
	array(),
	array( 'menu_id' => $menu_id )
);
sgm_r35_status( $inspection, 200, 'R3.5 navigation inspection' );
$inspection_data = sgm_r35_data( $inspection );
$navigation      = is_array( $inspection_data['data'] ?? null ) ? $inspection_data['data'] : array();
$initial_fingerprint = (string) ( $navigation['fingerprint'] ?? '' );
sgm_r35_assert( true === ( $navigation['bounded'] ?? false ), 'Navigation inspection is not bounded.' );
sgm_r35_assert( $menu_id === (int) ( $navigation['menu_id'] ?? 0 ) && 2 === (int) ( $navigation['item_count'] ?? 0 ), 'Navigation inspection menu identity/count mismatch.' );
sgm_r35_assert( 64 === strlen( $initial_fingerprint ) && ctype_xdigit( $initial_fingerprint ), 'Navigation inspection fingerprint is invalid.' );
sgm_r35_assert( false === ( $navigation['policy']['raw_menu_item_meta_returned'] ?? true ), 'Navigation inspection leaked raw menu item metadata.' );

$plan_body = array(
	'operation' => 'navigation-change',
	'payload'   => array(
		'schema_version'       => 1,
		'menu_id'              => $menu_id,
		'expected_fingerprint' => $initial_fingerprint,
		'current_urls'         => array( $legacy_url ),
		'expected_occurrences' => 1,
		'suggested_url'        => $suggested_url,
	),
);
$plan = sgm_r35_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r35_headers( $credential, 'r35-plan-1', 'r35-plan-nonce-1' ),
	$plan_body
);
sgm_r35_status( $plan, 200, 'R3.5 navigation plan' );
$plan_data = sgm_r35_data( $plan );
$plan_row  = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview   = is_array( $plan_row['preview'] ?? null ) ? $plan_row['preview'] : array();
$plan_id   = (string) ( $plan_row['plan_id'] ?? '' );
sgm_r35_assert( str_starts_with( $plan_id, 'sgmp_' ) && 'prepared' === ( $plan_row['status'] ?? '' ), 'Navigation plan is incomplete.' );
sgm_r35_assert( 'navigation-change' === ( $plan_row['operation'] ?? '' ), 'Navigation plan operation mismatch.' );
sgm_r35_assert( true === ( $preview['has_changes'] ?? false ) && true === ( $preview['policy']['public_navigation_blocked'] ?? false ), 'Navigation Preview did not keep public writes blocked.' );
sgm_r35_assert( $initial_fingerprint === ( $preview['fingerprint'] ?? '' ) && $suggested_url === ( $preview['suggested_url'] ?? '' ), 'Navigation Preview fingerprint/target mismatch.' );
sgm_r35_assert( $before_matching === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $matching_id ) ), 'Navigation Preview mutated the matching item.' );
sgm_r35_assert( $before_untouched === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $untouched_id ) ), 'Navigation Preview mutated the untouched item.' );

$environment_fingerprint = (string) ( EnvironmentPolicy::snapshot()['fingerprint'] ?? '' );
$blocked_apply = sgm_r35_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply',
	sgm_r35_headers( $credential, 'r35-apply-blocked-1', 'r35-apply-blocked-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r35_status( $blocked_apply, 409, 'R3.5 navigation apply without public approval' );
$blocked_data = sgm_r35_data( $blocked_apply );
sgm_r35_assert( 'seo_geo_manager_navigation_public_approval_required' === ( $blocked_data['code'] ?? '' ), 'Navigation write was not blocked without explicit public approval.' );
sgm_r35_assert( $before_matching === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $matching_id ) ), 'Blocked navigation Apply mutated the matching item.' );
sgm_r35_assert( $before_untouched === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $untouched_id ) ), 'Blocked navigation Apply mutated the untouched item.' );

$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
	'allow_public_navigation' => true,
);
$apply_headers = sgm_r35_headers( $credential, 'r35-apply-1', 'r35-apply-nonce-1' );
$apply = sgm_r35_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_headers, $apply_body );
sgm_r35_status( $apply, 200, 'R3.5 navigation apply' );
$apply_data   = sgm_r35_data( $apply );
$applied      = is_array( $apply_data['data'] ?? null ) ? $apply_data['data'] : array();
$operation_id = (string) ( $applied['operation_id'] ?? '' );
sgm_r35_assert( preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) === 1 && 'applied' === ( $applied['status'] ?? '' ), 'Navigation Apply operation missing.' );
sgm_r35_assert( 'navigation-menu' === ( $applied['target_type'] ?? '' ), 'Navigation Apply target type mismatch.' );
sgm_r35_assert( array( 'navigation_url' ) === ( $applied['changed_fields'] ?? array() ), 'Navigation Apply changed-fields boundary mismatch.' );

$after_matching  = sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $matching_id ) );
$after_untouched = sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $untouched_id ) );
sgm_r35_assert( $suggested_url === $after_matching['url'], 'Navigation Apply did not write the suggested URL.' );
$expected_after_matching        = $before_matching;
$expected_after_matching['url'] = $suggested_url;
sgm_r35_assert( $expected_after_matching === $after_matching, 'Navigation Apply changed fields other than the matching URL.' );
sgm_r35_assert( $before_untouched === $after_untouched, 'Navigation Apply changed an unrelated menu item.' );

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'r35-apply-nonce-2';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r35_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_replay_headers, $apply_body );
sgm_r35_status( $apply_replay, 200, 'R3.5 navigation Apply replay' );
$replay_data = sgm_r35_data( $apply_replay );
sgm_r35_assert( true === ( $replay_data['idempotent_replay'] ?? false ) && $operation_id === ( $replay_data['data']['operation_id'] ?? '' ), 'Navigation Apply replay was not idempotent.' );

$verify = sgm_r35_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r35_headers( $credential, 'r35-verify-1', 'r35-verify-nonce-1' ),
	array( 'verify' => true )
);
sgm_r35_status( $verify, 200, 'R3.5 navigation verify' );
$verify_data = sgm_r35_data( $verify );
sgm_r35_assert( true === ( $verify_data['data']['verified'] ?? false ), 'Navigation Verify did not pass.' );
sgm_r35_assert( ( $verify_data['data']['after_fingerprint'] ?? '' ) === ( $verify_data['data']['current_fingerprint'] ?? '' ), 'Navigation Verify fingerprint mismatch.' );

$operation_read = sgm_r35_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r35_headers( $credential, 'r35-operation-read-1', 'r35-operation-read-nonce-1' )
);
sgm_r35_status( $operation_read, 200, 'R3.5 navigation operation read' );
$operation_data = sgm_r35_data( $operation_read );
$bounded        = is_array( $operation_data['data'] ?? null ) ? $operation_data['data'] : array();
sgm_r35_assert( 'navigation-change' === ( $bounded['operation'] ?? '' ), 'Bounded navigation operation type missing.' );
sgm_r35_assert( 'navigation-menu' === ( $bounded['target_type'] ?? '' ), 'Bounded navigation target type mismatch.' );
sgm_r35_assert( array( 'navigation_url' ) === ( $bounded['changed_fields'] ?? array() ), 'Bounded navigation changed-fields mismatch.' );
sgm_r35_assert( ! isset( $bounded['items'], $bounded['before_args'], $bounded['after_args'], $bounded['suggested_url'], $bounded['changes'] ), 'Remote navigation operation leaked private mutation state.' );

$rollback = sgm_r35_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r35_headers( $credential, 'r35-rollback-1', 'r35-rollback-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r35_status( $rollback, 200, 'R3.5 navigation rollback' );
$rollback_data = sgm_r35_data( $rollback );
sgm_r35_assert( 'rolled-back' === ( $rollback_data['data']['status'] ?? '' ), 'Navigation Rollback status mismatch.' );
sgm_r35_assert( $before_matching === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $matching_id ) ), 'Navigation Rollback did not restore the matching item exactly.' );
sgm_r35_assert( $before_untouched === sgm_r35_item_state( sgm_r35_menu_item( $menu_id, $untouched_id ) ), 'Navigation Rollback changed the untouched item.' );

$restored_inspection = sgm_r35_request(
	'GET',
	'/seo-geo-manager/v1/remote/navigation',
	sgm_r35_headers( $credential, 'r35-navigation-read-2', 'r35-navigation-read-nonce-2' ),
	array(),
	array( 'menu_id' => $menu_id )
);
sgm_r35_status( $restored_inspection, 200, 'R3.5 restored navigation inspection' );
$restored_data = sgm_r35_data( $restored_inspection );
sgm_r35_assert( $initial_fingerprint === ( $restored_data['data']['fingerprint'] ?? '' ), 'Navigation Rollback did not restore the canonical menu fingerprint.' );

$audit_json = wp_json_encode( $audit->recent( 100 ) );
sgm_r35_assert( false !== $audit_json && ! str_contains( $audit_json, $credential ) && str_contains( $audit_json, $plan_id ) && str_contains( $audit_json, $operation_id ), 'Navigation audit evidence is incomplete or secret-bearing.' );

$tokens->revoke();
wp_delete_nav_menu( $menu_id );

echo wp_json_encode(
	array(
		'ok'                              => true,
		'plugin_version'                  => SEO_GEO_MANAGER_VERSION,
		'operation'                       => 'navigation-change',
		'navigation_inspected_remotely'   => true,
		'canonical_fingerprint_shared'    => true,
		'preview_no_mutation'             => true,
		'public_approval_required'        => true,
		'apply_exact'                     => true,
		'url_only'                        => true,
		'item_attributes_preserved'       => true,
		'unrelated_item_preserved'        => true,
		'apply_idempotent_replay'         => true,
		'verify_exact'                    => true,
		'operation_bounded'               => true,
		'rollback_exact'                  => true,
		'rollback_fingerprint_exact'      => true,
		'audit_secret_free'               => true,
		'generic_proxy'                   => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
