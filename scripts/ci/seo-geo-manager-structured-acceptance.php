<?php
/**
 * Runtime acceptance for the Theme structured-content write adapter.
 *
 * Executed after the core Manager M1/M2 acceptance in the same isolated site.
 */

use SeoGeo\Manager\Support\ContentFingerprint;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_structured_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param string                    $method HTTP method.
 * @param string                    $route REST route.
 * @param array<string, mixed>|null $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_structured_request( string $method, string $route, ?array $payload = null ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}

	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * @param mixed $data REST response data.
 */
function seo_geo_manager_structured_error_code( $data ): string {
	return is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
}

wp_set_current_user( 1 );
seo_geo_manager_structured_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$contact_content = '<section class="seo-geo-corporate-contact">'
	. '<h2 class="seo-geo-content-slot--contact-heading seo-geo-placeholder--copy">Old contact heading</h2>'
	. '<p class="seo-geo-content-slot--contact-intro seo-geo-placeholder--copy">Old contact intro</p>'
	. '<a class="seo-geo-content-slot--contact-primary-cta seo-geo-placeholder--copy" href="/old-contact/">Old contact CTA</a>'
	. '<h2 class="seo-geo-content-slot--final-cta-heading seo-geo-placeholder--copy">Old final heading</h2>'
	. '<p class="seo-geo-content-slot--final-cta-body seo-geo-placeholder--copy">Old final body</p>'
	. '<a class="seo-geo-content-slot--final-cta-button seo-geo-placeholder--copy" href="/old-final/">Old final CTA</a>'
	. '</section>';

$contact_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Contact',
		'post_name'    => 'contact',
		'post_content' => $contact_content,
	),
	true
);
seo_geo_manager_structured_accept( ! is_wp_error( $contact_id ), 'Could not create structured Contact fixture.' );

$contact_post = get_post( (int) $contact_id );
seo_geo_manager_structured_accept( $contact_post instanceof WP_Post, 'Could not load structured Contact fixture.' );
$fingerprint = ContentFingerprint::for_post( $contact_post );

$snapshot = seo_geo_manager_structured_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_structured_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Structured acceptance could not read site snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_structured_accept( '' !== $environment_fingerprint, 'Structured acceptance environment fingerprint is missing.' );

$payload = array(
	'schema_version' => 1,
	'model_id'       => 'corporate-contact-v1',
	'target'         => array(
		'id'                   => (int) $contact_id,
		'expected_fingerprint' => $fingerprint,
	),
	'slots'          => array(
		'contact-heading'     => 'Talk to our digital team',
		'contact-primary-cta' => array(
			'text' => 'Start a conversation',
			'url'  => '/contact/#form',
		),
	),
);

$preview = seo_geo_manager_structured_request( 'POST', '/seo-geo-manager/v1/theme/structured/preview', $payload );
seo_geo_manager_structured_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Structured preview failed.' );
seo_geo_manager_structured_accept( true === ( $preview['data']['has_changes'] ?? false ), 'Structured preview did not report changes.' );
seo_geo_manager_structured_accept( 'corporate-contact-v1' === ( $preview['data']['model_id'] ?? '' ), 'Structured preview model mismatch.' );
seo_geo_manager_structured_accept( 'contact' === ( $preview['data']['page_key'] ?? '' ), 'Structured preview page mapping mismatch.' );
seo_geo_manager_structured_accept( 'Old contact heading' === ( $preview['data']['slots']['contact-heading']['from'] ?? '' ), 'Structured text source diff mismatch.' );
seo_geo_manager_structured_accept( 'Talk to our digital team' === ( $preview['data']['slots']['contact-heading']['to'] ?? '' ), 'Structured text target diff mismatch.' );
seo_geo_manager_structured_accept( '/old-contact/' === ( $preview['data']['slots']['contact-primary-cta']['from']['url'] ?? '' ), 'Structured link source URL mismatch.' );
seo_geo_manager_structured_accept( '/contact/#form' === ( $preview['data']['slots']['contact-primary-cta']['to']['url'] ?? '' ), 'Structured link target URL mismatch.' );

$blocked                    = $payload;
$blocked['idempotency_key'] = 'structured-production-blocked';
$environment_rejection = seo_geo_manager_structured_request( 'POST', '/seo-geo-manager/v1/theme/structured/apply', $blocked );
seo_geo_manager_structured_accept( 409 === $environment_rejection['status'], 'Structured production write without environment approval was not rejected.' );
seo_geo_manager_structured_accept( 'seo_geo_manager_environment_approval_required' === seo_geo_manager_structured_error_code( $environment_rejection['data'] ), 'Unexpected structured environment guard code.' );

$apply_payload                            = $payload;
$apply_payload['idempotency_key']         = 'structured-apply-1';
$apply_payload['environment_fingerprint'] = $environment_fingerprint;
$apply                                    = seo_geo_manager_structured_request( 'POST', '/seo-geo-manager/v1/theme/structured/apply', $apply_payload );
seo_geo_manager_structured_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Structured apply failed.' );
seo_geo_manager_structured_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'Structured operation status mismatch.' );
seo_geo_manager_structured_accept( true === ( $apply['data']['structured_model']['layout_preserved'] ?? false ), 'Structured operation did not assert layout preservation.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_structured_accept( '' !== $operation_id, 'Structured operation ID is missing.' );

$stored_content = (string) get_post_field( 'post_content', (int) $contact_id );
seo_geo_manager_structured_accept( str_contains( $stored_content, '>Talk to our digital team</h2>' ), 'Structured text did not persist.' );
seo_geo_manager_structured_accept( str_contains( $stored_content, 'href="/contact/#form">Start a conversation</a>' ), 'Structured link did not persist.' );
seo_geo_manager_structured_accept( str_contains( $stored_content, 'Old contact intro' ), 'Structured apply modified an untargeted slot.' );
seo_geo_manager_structured_accept( str_contains( $stored_content, 'seo-geo-corporate-contact' ), 'Structured apply damaged the Theme-owned layout wrapper.' );

$operation = seo_geo_manager_structured_request( 'GET', '/seo-geo-manager/v1/changes/' . $operation_id );
seo_geo_manager_structured_accept( 200 === $operation['status'] && is_array( $operation['data'] ), 'Could not read structured operation.' );
seo_geo_manager_structured_accept( 'corporate-contact-v1' === ( $operation['data']['structured_model']['model_id'] ?? '' ), 'Structured operation metadata was not persisted.' );
seo_geo_manager_structured_accept( $environment_fingerprint === ( $operation['data']['environment']['fingerprint'] ?? '' ), 'Structured operation environment binding mismatch.' );

$rollback = seo_geo_manager_structured_request(
	'POST',
	'/seo-geo-manager/v1/changes/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_structured_accept( 200 === $rollback['status'], 'Structured operation rollback failed.' );
seo_geo_manager_structured_accept( $contact_content === (string) get_post_field( 'post_content', (int) $contact_id ), 'Structured rollback did not restore exact prior content.' );

$result = array(
	'ok'                        => true,
	'plugin_version'            => defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : '',
	'model_id'                  => 'corporate-contact-v1',
	'layout_preservation'       => true,
	'environment_approval'      => true,
	'structured_rollback'       => true,
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
