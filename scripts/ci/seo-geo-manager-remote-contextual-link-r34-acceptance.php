<?php
/**
 * WordPress runtime acceptance for SEO/GEO Manager R3.4 contextual-link remote control.
 */

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Remote\Audit;
use SeoGeo\Manager\Remote\Credentials;
use SeoGeo\Manager\Remote\TokenService;
use SeoGeo\Manager\Support\ContentFingerprint;
use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function sgm_r34_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** @return array<string, string> */
function sgm_r34_headers( string $credential, string $request_id, string $nonce ): array {
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
function sgm_r34_request( string $method, string $route, array $headers, array $body = array(), array $params = array() ): WP_REST_Response {
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
	sgm_r34_assert( $response instanceof WP_REST_Response, 'Remote request did not return WP_REST_Response.' );

	return $response;
}

/** @return array<string, mixed> */
function sgm_r34_data( WP_REST_Response $response ): array {
	$data = $response->get_data();

	return is_array( $data ) ? $data : array();
}

function sgm_r34_status( WP_REST_Response $response, int $expected, string $label ): void {
	sgm_r34_assert(
		$expected === $response->get_status(),
		$label . ' returned ' . $response->get_status() . ': ' . wp_json_encode( $response->get_data() )
	);
}

$_SERVER['HTTPS']       = 'on';
$_SERVER['SERVER_PORT'] = '443';

$admin = get_user_by( 'login', 'manageradmin' );
sgm_r34_assert( $admin instanceof WP_User && user_can( $admin, 'manage_options' ), 'R3.4 acceptance administrator is unavailable.' );
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
$issued = $tokens->issue( $scopes, (int) $admin->ID, 'CI R3.4 contextual-link bridge' );
sgm_r34_assert( ! is_wp_error( $issued ), 'Could not issue R3.4 mutation credential.' );
$credential = (string) ( $issued['credential'] ?? '' );
sgm_r34_assert( '' !== $credential, 'R3.4 credential is empty.' );

$target_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Verified target guide',
		'post_name'    => 'verified-target-guide',
		'post_content' => 'Published target fixture.',
	),
	true
);
sgm_r34_assert( ! is_wp_error( $target_id ) && 0 < (int) $target_id, 'Could not create published contextual-link target.' );
$target_id  = (int) $target_id;
$target_url = get_permalink( $target_id );
sgm_r34_assert( is_string( $target_url ) && '' !== $target_url, 'Published target permalink is unavailable.' );

$original_content = '<p>See <a class="reference-link" href="/legacy-path/" rel="nofollow">the guide</a> now.</p>';
$source_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'R3.4 contextual source',
		'post_name'    => 'r34-contextual-source',
		'post_content' => $original_content,
	),
	true
);
sgm_r34_assert( ! is_wp_error( $source_id ) && 0 < (int) $source_id, 'Could not create contextual-link source draft.' );
$source_id = (int) $source_id;
$source    = get_post( $source_id );
sgm_r34_assert( $source instanceof WP_Post, 'Could not reload contextual-link source draft.' );
$initial_fingerprint = ContentFingerprint::for_post( $source );

$capabilities = sgm_r34_request(
	'GET',
	'/seo-geo-manager/v1/remote/capabilities',
	sgm_r34_headers( $credential, 'r34-capabilities-1', 'r34-capabilities-nonce-1' )
);
sgm_r34_status( $capabilities, 200, 'R3.4 capabilities' );
$capability_data = sgm_r34_data( $capabilities );
$remote_data     = is_array( $capability_data['data'] ?? null ) ? $capability_data['data'] : array();
$current_milestone = (string) ( $remote_data['mutation_transport']['milestone'] ?? '' );
sgm_r34_assert( '' !== $current_milestone, 'Current remote mutation milestone is not advertised.' );
sgm_r34_assert( in_array( 'contextual-link-change', $remote_data['mutation_transport']['supported_operations'] ?? array(), true ), 'Contextual-link mutation is not advertised.' );
sgm_r34_assert( 'site.diagnostics' === ( $remote_data['read_surface']['/remote/contextual-links'] ?? '' ), 'Contextual-link discovery route is not advertised.' );

$discovery = sgm_r34_request(
	'GET',
	'/seo-geo-manager/v1/remote/contextual-links',
	sgm_r34_headers( $credential, 'r34-discovery-1', 'r34-discovery-nonce-1' ),
	array(),
	array( 'source_id' => $source_id )
);
sgm_r34_status( $discovery, 200, 'R3.4 contextual-link discovery' );
$discovery_data = sgm_r34_data( $discovery );
$reader         = is_array( $discovery_data['data'] ?? null ) ? $discovery_data['data'] : array();
$items          = is_array( $reader['items'] ?? null ) ? array_values( $reader['items'] ) : array();
sgm_r34_assert( true === ( $reader['bounded'] ?? false ) && false === ( $reader['policy']['raw_post_content_returned'] ?? true ), 'Contextual-link discovery is not privacy-bounded.' );
sgm_r34_assert( 1 === count( $items ) && is_array( $items[0] ), 'Contextual-link discovery did not return the exact source edge.' );
$edge               = $items[0];
$edge_id            = (string) ( $edge['edge_id'] ?? '' );
$source_fingerprint = (string) ( $edge['source']['fingerprint'] ?? '' );
sgm_r34_assert( 64 === strlen( $edge_id ) && ctype_xdigit( $edge_id ), 'Discovered contextual edge ID is invalid.' );
sgm_r34_assert( $initial_fingerprint === $source_fingerprint, 'Discovery source fingerprint mismatch.' );
sgm_r34_assert( 'the guide' === ( $edge['anchor_text'] ?? '' ) && '/legacy-path/' === ( $edge['href'] ?? '' ), 'Discovery edge evidence mismatch.' );

$forged_plan = sgm_r34_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r34_headers( $credential, 'r34-forged-plan-1', 'r34-forged-plan-nonce-1' ),
	array(
		'operation' => 'contextual-link-change',
		'payload'   => array(
			'schema_version'       => 1,
			'source_id'            => $source_id,
			'edge_id'              => str_repeat( 'a', 64 ),
			'expected_fingerprint' => $source_fingerprint,
			'target_resource_id'   => $target_id,
		),
	)
);
sgm_r34_status( $forged_plan, 409, 'R3.4 forged edge rejection' );
$forged_data = sgm_r34_data( $forged_plan );
sgm_r34_assert( 'seo_geo_manager_contextual_link_edge_mismatch' === ( $forged_data['code'] ?? '' ), 'Forged contextual edge returned an unexpected error.' );
sgm_r34_assert( $original_content === (string) get_post_field( 'post_content', $source_id ), 'Forged edge attempt mutated source content.' );

$plan = sgm_r34_request(
	'POST',
	'/seo-geo-manager/v1/remote/plans',
	sgm_r34_headers( $credential, 'r34-plan-1', 'r34-plan-nonce-1' ),
	array(
		'operation' => 'contextual-link-change',
		'payload'   => array(
			'schema_version'       => 1,
			'source_id'            => $source_id,
			'edge_id'              => $edge_id,
			'expected_fingerprint' => $source_fingerprint,
			'target_resource_id'   => $target_id,
		),
	)
);
sgm_r34_status( $plan, 200, 'R3.4 contextual-link plan' );
$plan_data = sgm_r34_data( $plan );
$plan_row  = is_array( $plan_data['data'] ?? null ) ? $plan_data['data'] : array();
$preview   = is_array( $plan_row['preview'] ?? null ) ? $plan_row['preview'] : array();
$plan_id   = (string) ( $plan_row['plan_id'] ?? '' );
sgm_r34_assert( str_starts_with( $plan_id, 'sgmp_' ) && 'prepared' === ( $plan_row['status'] ?? '' ), 'Contextual-link plan is incomplete.' );
sgm_r34_assert( 'contextual-link-change' === ( $plan_row['operation'] ?? '' ), 'Contextual-link plan operation mismatch.' );
sgm_r34_assert( true === ( $preview['has_changes'] ?? false ) && true === ( $preview['policy']['href_only'] ?? false ), 'Contextual-link Preview did not prove href-only mutation.' );
sgm_r34_assert( $edge_id === ( $preview['edge']['edge_id'] ?? '' ) && $target_id === ( $preview['target']['id'] ?? 0 ), 'Contextual-link Preview edge/target mismatch.' );
sgm_r34_assert( $original_content === (string) get_post_field( 'post_content', $source_id ), 'Contextual-link Preview mutated the source.' );

$environment_fingerprint = (string) ( EnvironmentPolicy::snapshot()['fingerprint'] ?? '' );
$apply_body = array(
	'confirm'                 => true,
	'environment_fingerprint' => $environment_fingerprint,
);
$apply_headers = sgm_r34_headers( $credential, 'r34-apply-1', 'r34-apply-nonce-1' );
$apply = sgm_r34_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_headers, $apply_body );
sgm_r34_status( $apply, 200, 'R3.4 contextual-link apply' );
$apply_data   = sgm_r34_data( $apply );
$applied      = is_array( $apply_data['data'] ?? null ) ? $apply_data['data'] : array();
$operation_id = (string) ( $applied['operation_id'] ?? '' );
sgm_r34_assert( preg_match( '/^[a-f0-9\-]{36}$/', $operation_id ) === 1 && 'applied' === ( $applied['status'] ?? '' ), 'Contextual-link Apply operation missing.' );

$after_content = (string) get_post_field( 'post_content', $source_id );
sgm_r34_assert( str_contains( $after_content, 'href="' . esc_attr( $target_url ) . '"' ), 'Contextual-link Apply did not write target permalink.' );
sgm_r34_assert( str_contains( $after_content, 'class="reference-link"' ) && str_contains( $after_content, 'rel="nofollow"' ), 'Contextual-link Apply modified anchor attributes.' );
sgm_r34_assert( str_contains( $after_content, '>the guide</a>' ), 'Contextual-link Apply modified anchor text.' );
sgm_r34_assert( 'draft' === get_post_status( $source_id ), 'Contextual-link Apply changed source publication status.' );

$apply_replay_headers = $apply_headers;
$apply_replay_headers['x-seo-geo-manager-nonce']     = 'r34-apply-nonce-2';
$apply_replay_headers['x-seo-geo-manager-timestamp'] = (string) time();
$apply_replay = sgm_r34_request( 'POST', '/seo-geo-manager/v1/remote/plans/' . $plan_id . '/apply', $apply_replay_headers, $apply_body );
sgm_r34_status( $apply_replay, 200, 'R3.4 contextual-link Apply replay' );
$replay_data = sgm_r34_data( $apply_replay );
sgm_r34_assert( true === ( $replay_data['idempotent_replay'] ?? false ) && $operation_id === ( $replay_data['data']['operation_id'] ?? '' ), 'Contextual-link Apply replay was not idempotent.' );

$verify = sgm_r34_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/verify',
	sgm_r34_headers( $credential, 'r34-verify-1', 'r34-verify-nonce-1' ),
	array( 'verify' => true )
);
sgm_r34_status( $verify, 200, 'R3.4 contextual-link verify' );
$verify_data = sgm_r34_data( $verify );
sgm_r34_assert( true === ( $verify_data['data']['verified'] ?? false ), 'Contextual-link Verify did not pass.' );
sgm_r34_assert( ( $verify_data['data']['after_fingerprint'] ?? '' ) === ( $verify_data['data']['current_fingerprint'] ?? '' ), 'Contextual-link Verify fingerprint mismatch.' );

$operation_read = sgm_r34_request(
	'GET',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id,
	sgm_r34_headers( $credential, 'r34-operation-read-1', 'r34-operation-read-nonce-1' )
);
sgm_r34_status( $operation_read, 200, 'R3.4 contextual-link operation read' );
$operation_data = sgm_r34_data( $operation_read );
$bounded        = is_array( $operation_data['data'] ?? null ) ? $operation_data['data'] : array();
sgm_r34_assert( 'contextual-link-change' === ( $bounded['operation'] ?? '' ), 'Bounded contextual-link operation type missing.' );
sgm_r34_assert( array( 'contextual_href' ) === ( $bounded['changed_fields'] ?? array() ), 'Bounded contextual-link changed fields mismatch.' );
sgm_r34_assert( ! isset( $bounded['before_content'], $bounded['after_content'], $bounded['before_href'], $bounded['after_href'], $bounded['changes'] ), 'Remote contextual-link operation leaked private mutation state.' );

$rollback = sgm_r34_request(
	'POST',
	'/seo-geo-manager/v1/remote/operations/' . $operation_id . '/rollback',
	sgm_r34_headers( $credential, 'r34-rollback-1', 'r34-rollback-nonce-1' ),
	array(
		'confirm'                 => true,
		'environment_fingerprint' => $environment_fingerprint,
	)
);
sgm_r34_status( $rollback, 200, 'R3.4 contextual-link rollback' );
$rollback_data = sgm_r34_data( $rollback );
sgm_r34_assert( 'rolled-back' === ( $rollback_data['data']['status'] ?? '' ), 'Contextual-link Rollback status mismatch.' );
$restored = get_post( $source_id );
sgm_r34_assert( $restored instanceof WP_Post && $original_content === $restored->post_content, 'Contextual-link Rollback did not restore exact source content.' );
$rollback_fingerprint = ContentFingerprint::for_post( $restored );
$stored_rollback       = OperationStore::get( $operation_id );
sgm_r34_assert( is_array( $stored_rollback ) && $rollback_fingerprint === ( $stored_rollback['rollback_fingerprint'] ?? '' ), 'Contextual-link Rollback fingerprint does not match restored WordPress state.' );

$audit_json = wp_json_encode( $audit->recent( 100 ) );
sgm_r34_assert( false !== $audit_json && ! str_contains( $audit_json, $credential ) && str_contains( $audit_json, $plan_id ) && str_contains( $audit_json, $operation_id ), 'Contextual-link audit evidence is incomplete or secret-bearing.' );

$tokens->revoke();
wp_delete_post( $source_id, true );
wp_delete_post( $target_id, true );

echo wp_json_encode(
	array(
		'ok'                       => true,
		'plugin_version'           => SEO_GEO_MANAGER_VERSION,
		'operation'                => 'contextual-link-change',
		'edge_discovered_remotely' => true,
		'fabricated_edge_rejected' => true,
		'href_only'                => true,
		'anchor_preserved'         => true,
		'attrs_preserved'          => true,
		'preview_no_mutation'      => true,
		'apply_exact'              => true,
		'apply_idempotent_replay'  => true,
		'verify_exact'             => true,
		'operation_bounded'        => true,
		'rollback_exact'           => true,
		'rollback_fingerprint_current' => true,
		'audit_secret_free'        => true,
		'generic_proxy'            => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
