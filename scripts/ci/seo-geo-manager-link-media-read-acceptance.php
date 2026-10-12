<?php
/**
 * Runtime acceptance for C6.1 contextual-link and media reference discovery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_c6_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_c6_request( string $method, string $route, array $params = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( (string) $key, $value );
	}
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * @param mixed  $items Items.
 * @param string $key Key.
 * @param mixed  $value Expected value.
 * @return array<string, mixed>|null
 */
function seo_geo_manager_c6_find( $items, string $key, $value ): ?array {
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
 * Reject raw content/private attachment metadata from bounded read responses.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_c6_assert_bounded( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_c6_accept(
				! in_array( $key, array( 'post_content', 'raw_content', '_wp_attachment_metadata', 'image_meta', 'exif' ), true ),
				'C6 reader exposed a raw/private content field.'
			);
		}
		seo_geo_manager_c6_assert_bounded( $child );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_c6_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$target_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6 Link Target',
		'post_name'    => 'c6-link-target',
		'post_content' => '<p>Target body.</p>',
	),
	true
);
seo_geo_manager_c6_accept( ! is_wp_error( $target_id ) && 0 < (int) $target_id, 'Could not create C6 target page.' );
$target_id  = (int) $target_id;
$target_url = get_permalink( $target_id );
seo_geo_manager_c6_accept( is_string( $target_url ) && '' !== $target_url, 'C6 target permalink missing.' );
$target_path = (string) wp_parse_url( $target_url, PHP_URL_PATH );
$legacy_url  = 'https://legacy.example.test' . $target_path;

$source_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6 Link Source',
		'post_name'    => 'c6-link-source',
		'post_content' => '<p><a href="' . esc_url( $target_url ) . '">Current target</a> <a href="' . esc_url( $legacy_url ) . '">Legacy host target</a> <a href="https://example.net/reference">External reference</a> <a href="mailto:team@example.test">Email team</a></p>',
	),
	true
);
seo_geo_manager_c6_accept( ! is_wp_error( $source_id ) && 0 < (int) $source_id, 'Could not create C6 source page.' );
$source_id = (int) $source_id;
flush_rewrite_rules( false );

$links = seo_geo_manager_c6_request(
	'GET',
	'/seo-geo-manager/v1/links/contextual',
	array( 'source_id' => $source_id )
);
seo_geo_manager_c6_accept( 200 === $links['status'] && is_array( $links['data'] ), 'Contextual-link reader failed.' );
seo_geo_manager_c6_accept( 4 === (int) ( $links['data']['link_count'] ?? 0 ), 'Contextual-link reader count mismatch.' );
seo_geo_manager_c6_accept( false === ( $links['data']['policy']['raw_post_content_returned'] ?? true ), 'Contextual-link reader raw-content policy mismatch.' );
seo_geo_manager_c6_accept( false === ( $links['data']['policy']['mutation_supported'] ?? true ), 'C6.1 unexpectedly advertises link mutation.' );

$current = seo_geo_manager_c6_find( $links['data']['items'] ?? array(), 'classification', 'internal-current' );
seo_geo_manager_c6_accept( is_array( $current ), 'Current internal target was not classified.' );
seo_geo_manager_c6_accept( $target_id === (int) ( $current['target']['id'] ?? 0 ), 'Current internal target ID mismatch.' );
seo_geo_manager_c6_accept( true === ( $current['target_verified'] ?? false ), 'Current internal target was not verified.' );
seo_geo_manager_c6_accept( '' !== (string) ( $current['edge_id'] ?? '' ), 'Contextual edge identity missing.' );
seo_geo_manager_c6_accept( '' !== (string) ( $current['source']['fingerprint'] ?? '' ), 'Contextual source fingerprint missing.' );

$leak = seo_geo_manager_c6_find( $links['data']['items'] ?? array(), 'classification', 'environment-leakage-candidate' );
seo_geo_manager_c6_accept( is_array( $leak ), 'Legacy/source-host leakage candidate was not classified.' );
seo_geo_manager_c6_accept( $target_id === (int) ( $leak['target']['id'] ?? 0 ), 'Leakage candidate did not resolve to the current local target.' );
$external = seo_geo_manager_c6_find( $links['data']['items'] ?? array(), 'classification', 'external' );
seo_geo_manager_c6_accept( is_array( $external ) && false === ( $external['target_verified'] ?? true ), 'External link classification mismatch.' );
$system = seo_geo_manager_c6_find( $links['data']['items'] ?? array(), 'classification', 'system' );
seo_geo_manager_c6_accept( is_array( $system ), 'System link classification mismatch.' );
seo_geo_manager_c6_assert_bounded( $links['data'] );

$media_id = wp_insert_attachment(
	array(
		'post_title'     => 'C6 Reference Image',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/png',
		'guid'           => home_url( '/wp-content/uploads/c6-reference.png' ),
	),
	false,
	0,
	true
);
seo_geo_manager_c6_accept( ! is_wp_error( $media_id ) && 0 < (int) $media_id, 'Could not create C6 image attachment.' );
$media_id = (int) $media_id;
update_post_meta( $media_id, '_wp_attachment_image_alt', 'Verified C6 image alt' );
wp_update_attachment_metadata(
	$media_id,
	array(
		'width'  => 1,
		'height' => 1,
		'file'   => 'c6-reference.png',
	)
);

$media_list = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/media' );
seo_geo_manager_c6_accept( 200 === $media_list['status'] && is_array( $media_list['data'] ), 'Media reference list failed.' );
$list_item = seo_geo_manager_c6_find( $media_list['data']['items'] ?? array(), 'id', $media_id );
seo_geo_manager_c6_accept( is_array( $list_item ), 'C6 image missing from media list.' );
seo_geo_manager_c6_accept( 'Verified C6 image alt' === ( $list_item['alt'] ?? '' ), 'Media alt read mismatch.' );
seo_geo_manager_c6_accept( true === ( $list_item['alt_present'] ?? false ), 'Media alt presence mismatch.' );
seo_geo_manager_c6_accept( 1 === (int) ( $list_item['dimensions']['width'] ?? 0 ), 'Media width mismatch.' );
seo_geo_manager_c6_accept( false === ( $media_list['data']['policy']['fabricated_metadata'] ?? true ), 'Media reader fabrication policy mismatch.' );
seo_geo_manager_c6_assert_bounded( $media_list['data'] );

$media_detail = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_accept( 200 === $media_detail['status'] && is_array( $media_detail['data'] ), 'Media reference detail failed.' );
$before_media_fingerprint = (string) ( $media_detail['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_c6_accept( '' !== $before_media_fingerprint, 'Media fingerprint missing.' );
update_post_meta( $media_id, '_wp_attachment_image_alt', 'Updated C6 image alt' );
$media_after = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_c6_accept( 200 === $media_after['status'] && is_array( $media_after['data'] ), 'Media reference re-read failed.' );
seo_geo_manager_c6_accept( 'Updated C6 image alt' === ( $media_after['data']['media']['alt'] ?? '' ), 'Media reader did not observe updated alt.' );
seo_geo_manager_c6_accept( $before_media_fingerprint !== (string) ( $media_after['data']['media']['fingerprint'] ?? '' ), 'Media fingerprint did not change after alt change.' );
seo_geo_manager_c6_assert_bounded( $media_after['data'] );

$capabilities = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_c6_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'C6 capability discovery failed.' );
seo_geo_manager_c6_accept( true === ( $capabilities['data']['capabilities']['contextual_link_read']['available'] ?? false ), 'Contextual-link read capability missing.' );
seo_geo_manager_c6_accept( true === ( $capabilities['data']['capabilities']['media_read']['available'] ?? false ), 'Media read capability missing.' );
seo_geo_manager_c6_accept( '/links/contextual' === ( $capabilities['data']['operations']['links.contextual.read']['path'] ?? '' ), 'Contextual-link operation path missing.' );
seo_geo_manager_c6_accept( '/media/{media_id}' === ( $capabilities['data']['operations']['media.read']['path'] ?? '' ), 'Media read operation path missing.' );

$subscriber_id = wp_create_user( 'manager-c6-subscriber', wp_generate_password( 32, true, true ), 'manager-c6-subscriber@example.test' );
seo_geo_manager_c6_accept( ! is_wp_error( $subscriber_id ), 'Could not create C6 subscriber fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$links_denied = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/links/contextual', array( 'source_id' => $source_id ) );
seo_geo_manager_c6_accept( 403 === $links_denied['status'], 'Subscriber incorrectly received contextual links.' );
$media_denied = seo_geo_manager_c6_request( 'GET', '/seo-geo-manager/v1/media' );
seo_geo_manager_c6_accept( 403 === $media_denied['status'], 'Subscriber incorrectly received media references.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                          => true,
		'plugin_version'              => SEO_GEO_MANAGER_VERSION,
		'contextual_links_bounded'    => true,
		'current_target_verified'     => true,
		'environment_leakage_flagged' => true,
		'media_references_bounded'    => true,
		'media_fingerprint_alt_aware' => true,
		'least_privilege_enforced'    => true,
		'raw_content_returned'        => false,
		'mutations_exposed_by_c6_1'   => false,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
