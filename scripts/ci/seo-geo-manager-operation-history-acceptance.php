<?php
/**
 * Runtime acceptance for privacy-bounded Manager operation evidence and recovery.
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

$media_id = wp_insert_attachment(
	array(
		'post_title'     => 'Recovery manifest image',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/png',
		'guid'           => home_url( '/wp-content/uploads/recovery-manifest-image.png' ),
	),
	false,
	0,
	true
);
seo_geo_manager_history_accept( ! is_wp_error( $media_id ) && 0 < (int) $media_id, 'Could not create recovery image fixture.' );
$media_id = (int) $media_id;
wp_update_attachment_metadata(
	$media_id,
	array(
		'width'  => 800,
		'height' => 600,
		'file'   => 'recovery-manifest-image.png',
	)
);
delete_post_meta( $media_id, '_wp_attachment_image_alt' );

$media_read = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/media/' . $media_id );
seo_geo_manager_history_accept( 200 === $media_read['status'] && is_array( $media_read['data'] ), 'Could not inspect recovery image fixture.' );
$media_fingerprint = (string) ( $media_read['data']['media']['fingerprint'] ?? '' );
seo_geo_manager_history_accept( '' !== $media_fingerprint, 'Recovery image fingerprint missing.' );

$media_apply = seo_geo_manager_history_request(
	'POST',
	'/seo-geo-manager/v1/media/' . $media_id . '/alt/changes/apply',
	array(
		'schema_version'          => 1,
		'expected_fingerprint'    => $media_fingerprint,
		'alt'                     => 'Recovery manifest ALT',
		'allow_public_media'      => true,
		'environment_fingerprint' => $environment_fingerprint,
		'idempotency_key'         => 'operation-history-recovery-media-alt',
	)
);
seo_geo_manager_history_accept( 200 === $media_apply['status'] && is_array( $media_apply['data'] ), 'Could not create supported recovery operation.' );
$media_operation_id = (string) ( $media_apply['data']['operation_id'] ?? '' );
seo_geo_manager_history_accept( 36 === strlen( $media_operation_id ), 'Supported recovery operation ID missing.' );

$manifest = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations/' . $media_operation_id . '/recovery' );
seo_geo_manager_history_accept( 200 === $manifest['status'] && is_array( $manifest['data'] ), 'Recovery manifest request failed.' );
seo_geo_manager_history_accept( true === ( $manifest['data']['privacy_safe'] ?? false ), 'Recovery manifest did not declare privacy-safe mode.' );
seo_geo_manager_history_accept( 'operation-recovery-manifest' === ( $manifest['data']['mode'] ?? '' ), 'Unexpected recovery manifest mode.' );
seo_geo_manager_history_accept( true === ( $manifest['data']['recovery']['supported'] ?? false ), 'Media ALT operation was not allowlisted for recovery.' );
seo_geo_manager_history_accept( true === ( $manifest['data']['recovery']['verify']['available'] ?? false ), 'Recovery Verify is unavailable for an applied Media ALT operation.' );
seo_geo_manager_history_accept( 'GET' === ( $manifest['data']['recovery']['verify']['method'] ?? '' ), 'Recovery Verify did not remain GET-only.' );
seo_geo_manager_history_accept( '/seo-geo-manager/v1/media/alt/changes/' . $media_operation_id . '/verify' === ( $manifest['data']['recovery']['verify']['path'] ?? '' ), 'Recovery Verify path is not the type-specific Media ALT route.' );
seo_geo_manager_history_accept( true === ( $manifest['data']['recovery']['rollback']['available'] ?? false ), 'Recovery rollback is unavailable for an applied Media ALT operation.' );
seo_geo_manager_history_accept( 'POST' === ( $manifest['data']['recovery']['rollback']['method'] ?? '' ), 'Recovery rollback method mismatch.' );
seo_geo_manager_history_accept( '/seo-geo-manager/v1/media/alt/changes/' . $media_operation_id . '/rollback' === ( $manifest['data']['recovery']['rollback']['path'] ?? '' ), 'Recovery rollback path is not the type-specific Media ALT route.' );
seo_geo_manager_history_accept( false === ( $manifest['data']['policy']['generic_rollback_endpoint'] ?? true ), 'Recovery manifest exposed a generic rollback endpoint.' );
seo_geo_manager_history_accept( false === ( $manifest['data']['policy']['generic_mutation_endpoint'] ?? true ), 'Recovery manifest exposed a generic mutation endpoint.' );
seo_geo_manager_history_accept( ! isset( $manifest['data']['operation']['before_fingerprint'], $manifest['data']['operation']['after_fingerprint'], $manifest['data']['operation']['environment_fingerprint'] ), 'Recovery manifest exposed private fingerprints.' );

$verify_path = (string) ( $manifest['data']['recovery']['verify']['path'] ?? '' );
$verify = seo_geo_manager_history_request( 'GET', $verify_path );
seo_geo_manager_history_accept( 200 === $verify['status'] && true === ( $verify['data']['verified'] ?? false ), 'Recovery manifest Verify route did not verify the applied operation.' );

$rollback_path = (string) ( $manifest['data']['recovery']['rollback']['path'] ?? '' );
$media_rollback = seo_geo_manager_history_request(
	'POST',
	$rollback_path,
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_history_accept( 200 === $media_rollback['status'] && 'rolled-back' === ( $media_rollback['data']['status'] ?? '' ), 'Recovery manifest rollback route did not restore the operation.' );
seo_geo_manager_history_accept( false === metadata_exists( 'post', $media_id, '_wp_attachment_image_alt' ), 'Recovery rollback did not restore the exact missing-ALT state.' );

$rolled_back_manifest = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations/' . $media_operation_id . '/recovery' );
seo_geo_manager_history_accept( 200 === $rolled_back_manifest['status'] && is_array( $rolled_back_manifest['data'] ), 'Rolled-back recovery manifest request failed.' );
seo_geo_manager_history_accept( true === ( $rolled_back_manifest['data']['recovery']['supported'] ?? false ), 'Rolled-back operation lost its supported recovery family.' );
seo_geo_manager_history_accept( false === ( $rolled_back_manifest['data']['recovery']['verify']['available'] ?? true ), 'Rolled-back operation still exposes Verify as available.' );
seo_geo_manager_history_accept( false === ( $rolled_back_manifest['data']['recovery']['rollback']['available'] ?? true ), 'Rolled-back operation still exposes rollback as available.' );

$unsupported_manifest = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations/' . $site_operation_id . '/recovery' );
seo_geo_manager_history_accept( 200 === $unsupported_manifest['status'] && is_array( $unsupported_manifest['data'] ), 'Unsupported operation recovery manifest failed.' );
seo_geo_manager_history_accept( false === ( $unsupported_manifest['data']['recovery']['supported'] ?? true ), 'Unsupported site-wide operation was incorrectly mapped to recovery endpoints.' );
seo_geo_manager_history_accept( null === ( $unsupported_manifest['data']['recovery']['verify']['path'] ?? null ), 'Unsupported operation exposed a Verify route.' );
seo_geo_manager_history_accept( null === ( $unsupported_manifest['data']['recovery']['rollback']['path'] ?? null ), 'Unsupported operation exposed a rollback route.' );

$admin_history = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations' );
seo_geo_manager_history_accept( 200 === $admin_history['status'] && is_array( $admin_history['data'] ), 'Administrator operation history request failed.' );
seo_geo_manager_history_accept( true === ( $admin_history['data']['privacy_safe'] ?? false ), 'Operation history did not declare privacy-safe mode.' );
$admin_items = isset( $admin_history['data']['items'] ) && is_array( $admin_history['data']['items'] ) ? $admin_history['data']['items'] : array();
seo_geo_manager_history_accept( 3 <= count( $admin_items ), 'Administrator history did not expose indexed operations.' );

$allowed_keys = array(
	'operation_id',
	'operation_type',
	'status',
	'target_id',
	'target_type',
	'changed_fields',
	'structured_model',
	'planned_redirects',
	'rendered_verification_status',
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

$editor_site_recovery = seo_geo_manager_history_request( 'GET', '/seo-geo-manager/v1/operations/' . $site_operation_id . '/recovery' );
seo_geo_manager_history_accept( 404 === $editor_site_recovery['status'], 'Editor could inspect recovery for a hidden site-wide operation.' );

wp_set_current_user( 1 );
wp_delete_user( (int) $editor_id );
wp_delete_post( (int) $post_id, true );
wp_delete_post( $media_id, true );

echo wp_json_encode(
	array(
		'ok'                         => true,
		'bounded_history'            => true,
		'privacy_safe'               => true,
		'rollback_status_refresh'    => true,
		'editor_scope_filter'        => true,
		'sitewide_admin_only'        => true,
		'recovery_manifest_bounded'  => true,
		'type_specific_verify'       => true,
		'type_specific_rollback'     => true,
		'unsupported_not_routed'     => true,
		'recovery_private_data_safe' => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
