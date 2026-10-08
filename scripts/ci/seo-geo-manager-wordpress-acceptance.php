<?php
/**
 * Runtime acceptance for SEO/GEO Manager M1/M2.
 *
 * Executed with WP-CLI eval-file inside an isolated WordPress fixture.
 */

declare(strict_types=1);

use SeoGeo\Manager\Support\ContentFingerprint;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * Fail the acceptance fixture with a compact diagnostic.
 *
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Dispatch one Manager REST request through WordPress itself.
 *
 * @param string                    $method HTTP method.
 * @param string                    $route REST route.
 * @param array<string, mixed>|null $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_request( string $method, string $route, ?array $payload = null ): array {
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
 * Return the Manager REST error code when available.
 *
 * @param mixed $data REST response data.
 */
function seo_geo_manager_error_code( $data ): string {
	return is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
}

/**
 * Read one content resource through the public Manager contract.
 *
 * @return array<string, mixed>
 */
function seo_geo_manager_read_resource( int $post_id ): array {
	$response = seo_geo_manager_request( 'GET', '/seo-geo-manager/v1/content/' . $post_id );
	seo_geo_manager_accept( 200 === $response['status'], 'Could not read Manager content resource.' );
	seo_geo_manager_accept( is_array( $response['data'] ), 'Manager content resource response is invalid.' );

	return $response['data'];
}

wp_set_current_user( 0 );
$health = seo_geo_manager_request( 'GET', '/seo-geo-manager/v1/health' );
seo_geo_manager_accept( 200 === $health['status'], 'Public Manager health endpoint failed.' );
seo_geo_manager_accept( is_array( $health['data'] ) && 'SEO/GEO Manager' === ( $health['data']['service'] ?? '' ), 'Health endpoint service identity mismatch.' );

wp_set_current_user( 1 );
seo_geo_manager_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$site_root = untrailingslashit( site_url( '/' ) );
update_option( 'home', $site_root . '/nuevaweb' );
update_option( 'seo_geo_active_preset', 'corporate' );

$blog_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Blog Transformacion Digital',
		'post_name'    => 'blog-transformacion-digital',
		'post_content' => '<p>Blog destination.</p>',
	),
	true
);
seo_geo_manager_accept( ! is_wp_error( $blog_id ), 'Could not create Blog fixture page.' );

$source_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Clone navigation source',
		'post_name'    => 'clone-navigation-source',
		'post_content' => '<p><a href="' . esc_url( $site_root . '/blog-transformacion-digital/' ) . '">Blog</a></p>',
	),
	true
);
seo_geo_manager_accept( ! is_wp_error( $source_id ), 'Could not create clone-link fixture page.' );

$draft_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Draft A',
		'post_name'    => 'draft-a',
		'post_excerpt' => 'Original excerpt',
		'post_content' => '<p>Original body.</p>',
	),
	true
);
seo_geo_manager_accept( ! is_wp_error( $draft_id ), 'Could not create draft fixture page.' );

$collision_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Collision target',
		'post_name'    => 'reserved-slug',
		'post_content' => '<p>Collision.</p>',
	),
	true
);
seo_geo_manager_accept( ! is_wp_error( $collision_id ), 'Could not create slug collision fixture.' );

$snapshot = seo_geo_manager_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Site snapshot endpoint failed.' );
seo_geo_manager_accept( str_contains( (string) ( $snapshot['data']['environment']['home_url'] ?? '' ), '/nuevaweb/' ), 'Snapshot did not use the current clone home URL.' );

$intelligence = seo_geo_manager_request( 'GET', '/seo-geo-manager/v1/site/intelligence' );
seo_geo_manager_accept( 200 === $intelligence['status'] && is_array( $intelligence['data'] ), 'Site intelligence endpoint failed.' );
$leakage = $intelligence['data']['links']['environment_leakage_candidates'] ?? array();
seo_geo_manager_accept( is_array( $leakage ) && 0 < count( $leakage ), 'Clone-domain/current-path leakage was not detected.' );
$theme_contract = $intelligence['data']['theme_contract'] ?? array();
seo_geo_manager_accept( is_array( $theme_contract ) && true === ( $theme_contract['applicable'] ?? false ), 'SEO/GEO Theme contract intelligence is not active.' );
seo_geo_manager_accept( 'corporate' === ( $theme_contract['preset'] ?? '' ), 'Corporate preset was not detected by Manager.' );

$draft = seo_geo_manager_read_resource( (int) $draft_id );
$fingerprint = isset( $draft['fingerprint'] ) && is_string( $draft['fingerprint'] ) ? $draft['fingerprint'] : '';
seo_geo_manager_accept( '' !== $fingerprint, 'Draft fingerprint is missing.' );

$base_payload = array(
	'schema_version' => 1,
	'target'         => array(
		'id'                   => (int) $draft_id,
		'expected_fingerprint' => $fingerprint,
	),
	'changes'        => array(
		'title'   => 'Draft A revised',
		'excerpt' => 'Manager excerpt',
	),
);

$preview = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/preview', $base_payload );
seo_geo_manager_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Change-set preview failed.' );
seo_geo_manager_accept( true === ( $preview['data']['has_changes'] ?? false ), 'Preview did not report a field change.' );
seo_geo_manager_accept( 'Draft A' === ( $preview['data']['diff']['title']['from'] ?? '' ), 'Preview title source value mismatch.' );
seo_geo_manager_accept( 'Draft A revised' === ( $preview['data']['diff']['title']['to'] ?? '' ), 'Preview title target value mismatch.' );

$apply_payload                    = $base_payload;
$apply_payload['idempotency_key'] = 'acceptance-apply-1';
$apply                            = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $apply_payload );
seo_geo_manager_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Change-set apply failed.' );
seo_geo_manager_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'Applied operation status mismatch.' );
$operation_id = isset( $apply['data']['operation_id'] ) && is_string( $apply['data']['operation_id'] ) ? $apply['data']['operation_id'] : '';
seo_geo_manager_accept( '' !== $operation_id, 'Applied operation ID is missing.' );
seo_geo_manager_accept( 'Draft A revised' === get_post_field( 'post_title', (int) $draft_id ), 'Applied title did not persist.' );

$replay = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $apply_payload );
seo_geo_manager_accept( 200 === $replay['status'] && is_array( $replay['data'] ), 'Idempotent replay failed.' );
seo_geo_manager_accept( $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'Idempotent replay created a different operation.' );
seo_geo_manager_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'Idempotent replay flag is missing.' );

$conflict_payload = $apply_payload;
$conflict_payload['changes']['title'] = 'Different payload';
$idempotency_conflict = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $conflict_payload );
seo_geo_manager_accept( 409 === $idempotency_conflict['status'], 'Reused idempotency key with different payload was not rejected.' );
seo_geo_manager_accept( 'seo_geo_manager_idempotency_conflict' === seo_geo_manager_error_code( $idempotency_conflict['data'] ), 'Unexpected idempotency conflict code.' );

$operation_read = seo_geo_manager_request( 'GET', '/seo-geo-manager/v1/changes/' . $operation_id );
seo_geo_manager_accept( 200 === $operation_read['status'], 'Operation read endpoint failed.' );

$rollback = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/' . $operation_id . '/rollback' );
seo_geo_manager_accept( 200 === $rollback['status'] && is_array( $rollback['data'] ), 'Manager rollback failed.' );
seo_geo_manager_accept( 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'Rollback status mismatch.' );
seo_geo_manager_accept( 'Draft A' === get_post_field( 'post_title', (int) $draft_id ), 'Rollback did not restore the previous title.' );

$after_rollback = seo_geo_manager_read_resource( (int) $draft_id );
$stale_fingerprint = (string) ( $after_rollback['fingerprint'] ?? '' );
wp_update_post(
	array(
		'ID'         => (int) $draft_id,
		'post_title' => 'Human edit',
	)
);
$stale_payload = array(
	'schema_version'  => 1,
	'idempotency_key' => 'acceptance-stale-1',
	'target'          => array(
		'id'                   => (int) $draft_id,
		'expected_fingerprint' => $stale_fingerprint,
	),
	'changes'         => array( 'title' => 'Automation should not win' ),
);
$stale = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $stale_payload );
seo_geo_manager_accept( 409 === $stale['status'], 'Stale change set was not rejected.' );
seo_geo_manager_accept( 'seo_geo_manager_change_stale' === seo_geo_manager_error_code( $stale['data'] ), 'Unexpected stale-write error code.' );

$current = seo_geo_manager_read_resource( (int) $draft_id );
$slug_preview = array(
	'schema_version' => 1,
	'target'         => array(
		'id'                   => (int) $draft_id,
		'expected_fingerprint' => (string) $current['fingerprint'],
	),
	'changes'        => array( 'slug' => 'reserved-slug' ),
);
$slug_collision = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/preview', $slug_preview );
seo_geo_manager_accept( 409 === $slug_collision['status'], 'Slug collision was not rejected.' );
seo_geo_manager_accept( 'seo_geo_manager_slug_collision' === seo_geo_manager_error_code( $slug_collision['data'] ), 'Unexpected slug-collision error code.' );

$second_payload = array(
	'schema_version'  => 1,
	'idempotency_key' => 'acceptance-apply-2',
	'target'          => array(
		'id'                   => (int) $draft_id,
		'expected_fingerprint' => (string) $current['fingerprint'],
	),
	'changes'         => array( 'title' => 'Manager second edit' ),
);
$second_apply = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $second_payload );
seo_geo_manager_accept( 200 === $second_apply['status'] && is_array( $second_apply['data'] ), 'Second Manager apply failed.' );
$second_operation = (string) ( $second_apply['data']['operation_id'] ?? '' );
wp_update_post(
	array(
		'ID'         => (int) $draft_id,
		'post_title' => 'Human after Manager',
	)
);
$blocked_rollback = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/' . $second_operation . '/rollback' );
seo_geo_manager_accept( 409 === $blocked_rollback['status'], 'Rollback overwrote a newer human edit.' );
seo_geo_manager_accept( 'seo_geo_manager_rollback_stale' === seo_geo_manager_error_code( $blocked_rollback['data'] ), 'Unexpected stale rollback error code.' );

$live_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Published Manager Guard',
		'post_name'    => 'published-manager-guard',
		'post_content' => '<p>Live.</p>',
	),
	true
);
seo_geo_manager_accept( ! is_wp_error( $live_id ), 'Could not create published guard fixture.' );
$live_post = get_post( (int) $live_id );
seo_geo_manager_accept( $live_post instanceof WP_Post, 'Could not load published guard fixture.' );
$live_fingerprint = ContentFingerprint::for_post( $live_post );
$live_payload = array(
	'schema_version'  => 1,
	'idempotency_key' => 'acceptance-live-blocked',
	'target'          => array(
		'id'                   => (int) $live_id,
		'expected_fingerprint' => $live_fingerprint,
	),
	'changes'         => array( 'title' => 'Published changed' ),
);
$live_blocked = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $live_payload );
seo_geo_manager_accept( 409 === $live_blocked['status'], 'Published target changed without explicit approval.' );
seo_geo_manager_accept( 'seo_geo_manager_published_target_blocked' === seo_geo_manager_error_code( $live_blocked['data'] ), 'Unexpected published-target guard code.' );

$live_payload['idempotency_key']       = 'acceptance-live-approved';
$live_payload['allow_published_target'] = true;
$live_apply = seo_geo_manager_request( 'POST', '/seo-geo-manager/v1/changes/apply', $live_payload );
seo_geo_manager_accept( 200 === $live_apply['status'], 'Explicitly approved published-target update failed for administrator.' );
seo_geo_manager_accept( 'Published changed' === get_post_field( 'post_title', (int) $live_id ), 'Approved published-target title did not persist.' );

$result = array(
	'ok'                         => true,
	'plugin_version'             => defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : '',
	'clone_home_url'             => home_url( '/' ),
	'leakage_candidates'         => count( $leakage ),
	'theme_preset'               => $theme_contract['preset'] ?? null,
	'preview_apply_rollback'     => true,
	'idempotent_replay'          => true,
	'stale_write_protection'     => true,
	'slug_collision_protection'  => true,
	'stale_rollback_protection'  => true,
	'published_target_guard'     => true,
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
