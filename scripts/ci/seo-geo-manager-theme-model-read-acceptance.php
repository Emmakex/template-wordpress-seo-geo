<?php
/**
 * Runtime acceptance for Theme semantic model discovery/readback.
 *
 * Runs after the structured writer acceptance in the same WordPress fixture so
 * the Corporate Contact model already has a real mapped page to inspect.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_theme_model_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param string                    $method HTTP method.
 * @param string                    $route REST route.
 * @param array<string, mixed>|null $payload Optional payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_theme_model_request( string $method, string $route, ?array $payload = null ): array {
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
 * @param mixed  $items List.
 * @param string $key Key.
 * @param string $value Value.
 * @return array<string, mixed>|null
 */
function seo_geo_manager_theme_model_find( $items, string $key, string $value ): ?array {
	if ( ! is_array( $items ) ) {
		return null;
	}
	foreach ( $items as $item ) {
		if ( is_array( $item ) && ( $item[ $key ] ?? null ) === $value ) {
			return $item;
		}
	}

	return null;
}

/**
 * Reject private/raw WordPress body fields anywhere in a bounded reader response.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_theme_model_assert_no_raw_content( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_theme_model_accept( 'post_content' !== $key && 'raw_content' !== $key, 'Theme model reader exposed a raw content field.' );
		}
		seo_geo_manager_theme_model_assert_no_raw_content( $child );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_theme_model_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$list = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models' );
seo_geo_manager_theme_model_accept( 200 === $list['status'] && is_array( $list['data'] ), 'Theme model list failed.' );
seo_geo_manager_theme_model_accept( true === ( $list['data']['applicable'] ?? false ), 'Theme model list is not applicable.' );
seo_geo_manager_theme_model_accept( 'corporate' === ( $list['data']['preset'] ?? '' ), 'Theme model list preset mismatch.' );
seo_geo_manager_theme_model_accept( 3 <= (int) ( $list['data']['model_count'] ?? 0 ), 'Theme model list is unexpectedly small.' );
$contact_summary = seo_geo_manager_theme_model_find( $list['data']['models'] ?? array(), 'model_id', 'corporate-contact-v1' );
seo_geo_manager_theme_model_accept( is_array( $contact_summary ), 'Corporate Contact model missing from model list.' );
seo_geo_manager_theme_model_accept( true === ( $contact_summary['mapped'] ?? false ), 'Corporate Contact model did not resolve to its page.' );
seo_geo_manager_theme_model_accept( 'contact' === ( $contact_summary['page_key'] ?? '' ), 'Corporate Contact page key mismatch.' );
seo_geo_manager_theme_model_accept( 0 < (int) ( $contact_summary['resource']['id'] ?? 0 ), 'Corporate Contact resource ID is missing.' );
seo_geo_manager_theme_model_accept( '' !== (string) ( $contact_summary['resource']['fingerprint'] ?? '' ), 'Corporate Contact fingerprint is missing.' );
seo_geo_manager_theme_model_assert_no_raw_content( $list['data'] );

$detail = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models/corporate-contact-v1' );
seo_geo_manager_theme_model_accept( 200 === $detail['status'] && is_array( $detail['data'] ), 'Theme model detail failed.' );
seo_geo_manager_theme_model_accept( 'corporate-contact-v1' === ( $detail['data']['model']['model_id'] ?? '' ), 'Theme model detail ID mismatch.' );
seo_geo_manager_theme_model_accept( 'contact' === ( $detail['data']['model']['page_key'] ?? '' ), 'Theme model detail page key mismatch.' );
seo_geo_manager_theme_model_accept( true === ( $detail['data']['mapping']['mapped'] ?? false ), 'Theme model detail did not map the page.' );
$contact_id = (int) ( $detail['data']['mapping']['resource']['id'] ?? 0 );
seo_geo_manager_theme_model_accept( 0 < $contact_id, 'Theme model detail resource ID missing.' );
$before_fingerprint = (string) ( $detail['data']['mapping']['resource']['fingerprint'] ?? '' );
seo_geo_manager_theme_model_accept( '' !== $before_fingerprint, 'Theme model detail fingerprint missing.' );

$heading = seo_geo_manager_theme_model_find( $detail['data']['slots'] ?? array(), 'id', 'contact-heading' );
seo_geo_manager_theme_model_accept( is_array( $heading ), 'Contact heading slot missing.' );
seo_geo_manager_theme_model_accept( 'text' === ( $heading['type'] ?? '' ), 'Contact heading slot type mismatch.' );
seo_geo_manager_theme_model_accept( true === ( $heading['required'] ?? false ), 'Contact heading required contract was lost.' );
seo_geo_manager_theme_model_accept( true === ( $heading['writable'] ?? false ), 'Contact heading is not marked writable.' );
seo_geo_manager_theme_model_accept( 1 === (int) ( $heading['marker_count'] ?? 0 ), 'Contact heading marker cardinality mismatch.' );
seo_geo_manager_theme_model_accept( 'Old contact heading' === ( $heading['value'] ?? '' ), 'Contact heading current value mismatch.' );

$link = seo_geo_manager_theme_model_find( $detail['data']['slots'] ?? array(), 'id', 'contact-primary-cta' );
seo_geo_manager_theme_model_accept( is_array( $link ), 'Contact primary CTA slot missing.' );
seo_geo_manager_theme_model_accept( 'link' === ( $link['type'] ?? '' ), 'Contact CTA slot type mismatch.' );
seo_geo_manager_theme_model_accept( 1 === (int) ( $link['marker_count'] ?? 0 ), 'Contact CTA marker cardinality mismatch.' );
seo_geo_manager_theme_model_accept( 'Old contact CTA' === ( $link['value']['text'] ?? '' ), 'Contact CTA current text mismatch.' );
seo_geo_manager_theme_model_accept( '/old-contact/' === ( $link['value']['url'] ?? '' ), 'Contact CTA current URL mismatch.' );

$optional_email = seo_geo_manager_theme_model_find( $detail['data']['slots'] ?? array(), 'id', 'contact-email' );
seo_geo_manager_theme_model_accept( is_array( $optional_email ), 'Optional contact-email slot contract missing.' );
seo_geo_manager_theme_model_accept( 0 === (int) ( $optional_email['marker_count'] ?? -1 ), 'Absent optional slot marker count mismatch.' );
seo_geo_manager_theme_model_accept( null === ( $optional_email['value'] ?? null ), 'Absent optional slot unexpectedly returned a value.' );
seo_geo_manager_theme_model_assert_no_raw_content( $detail['data'] );

$unknown = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models/does-not-exist' );
seo_geo_manager_theme_model_accept( 404 === $unknown['status'], 'Unknown Theme model was not rejected with 404.' );
seo_geo_manager_theme_model_accept( 'seo_geo_manager_theme_model_not_found' === ( $unknown['data']['code'] ?? '' ), 'Unknown Theme model returned an unexpected error code.' );

// Prove the read -> existing writer -> read loop without creating a parallel write path.
$snapshot = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_theme_model_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_theme_model_accept( '' !== $environment_fingerprint, 'Environment fingerprint is missing.' );

$apply = seo_geo_manager_theme_model_request(
	'POST',
	'/seo-geo-manager/v1/theme/structured/apply',
	array(
		'schema_version'          => 1,
		'model_id'                => 'corporate-contact-v1',
		'idempotency_key'         => 'theme-model-reader-write-loop',
		'allow_published_target'  => true,
		'environment_fingerprint' => $environment_fingerprint,
		'target'                  => array(
			'id'                   => $contact_id,
			'expected_fingerprint' => $before_fingerprint,
		),
		'slots'                   => array(
			'contact-heading' => 'Reader verified heading',
		),
	)
);
seo_geo_manager_theme_model_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Existing Theme structured writer failed after reader inspection.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_theme_model_accept( '' !== $operation_id, 'Reader/write loop operation ID missing.' );

$after = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models/corporate-contact-v1' );
seo_geo_manager_theme_model_accept( 200 === $after['status'] && is_array( $after['data'] ), 'Theme model re-read failed after apply.' );
$after_heading = seo_geo_manager_theme_model_find( $after['data']['slots'] ?? array(), 'id', 'contact-heading' );
seo_geo_manager_theme_model_accept( 'Reader verified heading' === ( $after_heading['value'] ?? '' ), 'Theme model reader did not observe the applied slot value.' );
seo_geo_manager_theme_model_accept( $before_fingerprint !== (string) ( $after['data']['mapping']['resource']['fingerprint'] ?? '' ), 'Theme model fingerprint did not change after write.' );

$rollback = seo_geo_manager_theme_model_request(
	'POST',
	'/seo-geo-manager/v1/changes/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_theme_model_accept( 200 === $rollback['status'], 'Theme model reader/write loop rollback failed.' );

$restored = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models/corporate-contact-v1' );
seo_geo_manager_theme_model_accept( 200 === $restored['status'] && is_array( $restored['data'] ), 'Theme model re-read failed after rollback.' );
$restored_heading = seo_geo_manager_theme_model_find( $restored['data']['slots'] ?? array(), 'id', 'contact-heading' );
seo_geo_manager_theme_model_accept( 'Old contact heading' === ( $restored_heading['value'] ?? '' ), 'Theme model reader did not observe rollback restoration.' );

// A post-only Author may see the model catalogue contract but may not read the mapped page values.
$author_id = wp_create_user( 'manager-theme-model-author', wp_generate_password( 32, true, true ), 'manager-theme-model-author@example.test' );
seo_geo_manager_theme_model_accept( ! is_wp_error( $author_id ), 'Could not create Theme model Author fixture.' );
$author = new WP_User( (int) $author_id );
$author->set_role( 'author' );
wp_set_current_user( (int) $author_id );
$author_list = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models' );
seo_geo_manager_theme_model_accept( 200 === $author_list['status'], 'Author could not read bounded Theme model catalogue.' );
$author_detail = seo_geo_manager_theme_model_request( 'GET', '/seo-geo-manager/v1/theme/models/corporate-contact-v1' );
seo_geo_manager_theme_model_accept( 403 === $author_detail['status'], 'Author incorrectly received mapped page slot values.' );
seo_geo_manager_theme_model_accept( 'seo_geo_manager_theme_model_forbidden' === ( $author_detail['data']['code'] ?? '' ), 'Unexpected Theme model permission error code.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                    => true,
		'plugin_version'        => SEO_GEO_MANAGER_VERSION,
		'preset'                => 'corporate',
		'model_id'              => 'corporate-contact-v1',
		'model_list'            => true,
		'bounded_slot_read'     => true,
		'fingerprint_read'      => true,
		'writer_readback'       => true,
		'rollback_readback'     => true,
		'page_permission_guard' => true,
		'raw_content_returned'  => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
