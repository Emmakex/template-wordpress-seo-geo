<?php
/**
 * Runtime acceptance for the privacy-bounded Manager operation history.
 */

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

if ( ! function_exists( 'wp_delete_user' ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

function seo_geo_manager_history_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function seo_geo_manager_history_request( string $method, string $route, ?array $payload = null ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $payload ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

wp_set_current_user( 1 );
seo_geo_manager_history_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$snapshot = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_history_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read Manager site snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_history_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$post_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Operation history fixture',
		'post_name'    => 'operation-history-fixture',
		'post_content' => '<p>Private history acceptance body.</p>',
	),
	true
);
seo_geo_manager_history_accept( ! is_wp_error( $post_id ) && 0 < (int) $post_id, 'Could not create history fixture page.' );

$content = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/content/' . (int) $post_id );
seo_geo_manager_history_accept( 200 === $content['status'] && is_array( $content['data'] ), 'Could not inspect history fixture resource.' );
$fingerprint = (string) ( $content['data']['fingerprint'] ?? '' );
seo_geo_manager_history_accept( '' !== $fingerprint, 'History fixture fingerprint missing.' );

$apply = seo_geo_manager_history_request(
	'POST',
	'/seo-geo-manager/v1/changes/apply',
	array(
		'schema_version'          => 1,
		'idempotency_key'         => 'operation-history-acceptance-content',
		'environment_fingerprint' => $environment_fingerprint,
		'target'                  => array(
			'id'                   => (int) $post_id,
			'expected_fingerprint' => $fingerprint,
		),
		'changes'                 => array(
			'title'   => 'Operation history fixture revised',
			'excerpt' => 'Sensitive requested value must not enter history summary.',
		),
	)
);
seo_geo_manager_history_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Could not create content history operation.' );
$content_operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_history_accept( '' !== $content_operation_id, 'Content history operation ID missing.' );

$rollback = seo_geo_manager_history_request(
	'POST',
	'/seo-geo-manager/v1/changes/' . $content_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_history_accept( 200 === $rollback['status'] && is_array( $rollback['data'] ), 'Could not roll back content history operation.' );

$site_operation_id = wp_generate_uuid4();
$site_operation = EnvironmentPolicy::bind_operation(
	array(
		'operation_id'      => $site_operation_id,
		'operation_type'    => 'permalink-structure-with-redirects',
		'status'            => 'applied',
		'planned_redirects' => 2,
		'payload_hash'      => hash( 'sha256', 'must-not-be-public-history' ),
		'before_structure'  => '/private-before/%postname%/',
		'after_structure'   => '/private-after/%postname%/',
		'created_at_gmt'    => gmdate( 'c' ),
	)
);
seo_geo_manager_history_accept( OperationStore::save( $site_operation_id, $site_operation ), 'Could not persist site-wide history fixture.' );

$admin_history = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations' );
seo_geo_manager_history_accept( 200 === $admin_history['status'] && is_array( $admin_history['data'] ), 'Administrator operation history request failed.' );
seo_geo_manager_history_accept( true === ( $admin_history['data']['privacy_safe'] ?? false ), 'Operation history did not declare privacy-safe mode.' );
$admin_items = isset( $admin_history['data']['items'] ) && is_array( $admin_history['data']['items'] ) ? $admin_history['data']['items'] : array();
seo_geo_manager_history_accept( 2 <= count( $admin_items ), 'Administrator history did not expose indexed operations.' );

$allowed_keys = array(
	'operation_id',
	'operation_type',
	'status',
	'target_id',
	'target_type',
	'changed_fields',
	'structured_model',
	'planned_redirects',
	'environment_type',
	'created_at_gmt',
	'rolled_back_at_gmt',
	'rollback_state',
);
$content_row = null;
$site_row    = null;
foreach ( $admin_items as $row ) {
	seo_geo_manager_history_accept( is_array( $row ), 'Operation history row is not an array.' );
	$unexpected = array_diff( array_keys( $row ), $allowed_keys );
	seo_geo_manager_history_accept( array() === $unexpected, 'Operation history exposed an unexpected/private field.' );
	seo_geo_manager_history_accept( ! isset( $row['before'], $row['changes'], $row['payload_hash'], $row['environment_fingerprint'] ), 'Operation history exposed private mutation data.' );
	if ( $content_operation_id === ( $row['operation_id'] ?? '' ) ) {
		$content_row = $row;
	}
	if ( $site_operation_id === ( $row['operation_id'] ?? '' ) ) {
		$site_row = $row;
	}
}
seo_geo_manager_history_accept( is_array( $content_row ), 'Rolled-back content operation missing from history.' );
seo_geo_manager_history_accept( 'rolled-back' === ( $content_row['status'] ?? '' ), 'Content history status was not refreshed after rollback.' );
seo_geo_manager_history_accept( 'completed' === ( $content_row['rollback_state'] ?? '' ), 'Content history rollback state mismatch.' );
seo_geo_manager_history_accept( array( 'title', 'excerpt' ) === ( $content_row['changed_fields'] ?? array() ), 'Content history changed-field summary mismatch.' );
seo_geo_manager_history_accept( is_array( $site_row ), 'Administrator could not see site-wide operation history.' );
seo_geo_manager_history_accept( 2 === (int) ( $site_row['planned_redirects'] ?? 0 ), 'Site-wide redirect summary mismatch.' );

$editor_id = wp_insert_user(
	array(
		'user_login' => 'manager-history-editor',
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => 'manager-history-editor@example.test',
		'role'       => 'editor',
	)
);
seo_geo_manager_history_accept( ! is_wp_error( $editor_id ) && 0 < (int) $editor_id, 'Could not create operation-history editor.' );
wp_set_current_user( (int) $editor_id );
seo_geo_manager_history_accept( current_user_can( 'edit_post', (int) $post_id ), 'History editor cannot edit fixture resource.' );

$editor_history = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations' );
seo_geo_manager_history_accept( 200 === $editor_history['status'] && is_array( $editor_history['data'] ), 'Editor operation history request failed.' );
$editor_items = isset( $editor_history['data']['items'] ) && is_array( $editor_history['data']['items'] ) ? $editor_history['data']['items'] : array();
$editor_ids   = array_values( array_filter( array_map( static fn ( $row ): string => is_array( $row ) && isset( $row['operation_id'] ) ? (string) $row['operation_id'] : '', $editor_items ) ) );
seo_geo_manager_history_accept( in_array( $content_operation_id, $editor_ids, true ), 'Editor could not see permitted content operation.' );
seo_geo_manager_history_accept( ! in_array( $site_operation_id, $editor_ids, true ), 'Editor could see a site-wide operation without manage_options.' );

wp_set_current_user( 1 );
wp_delete_user( (int) $editor_id );
wp_delete_post( (int) $post_id, true );

echo wp_json_encode(
	array(
		'ok'                      => true,
		'bounded_history'         => true,
		'privacy_safe'            => true,
		'rollback_status_refresh' => true,
		'editor_scope_filter'     => true,
		'sitewide_admin_only'     => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
