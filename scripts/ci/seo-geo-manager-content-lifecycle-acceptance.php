<?php
/**
 * Runtime acceptance for SEO/GEO Manager content lifecycle operations.
 *
 * Executed with WP-CLI eval-file inside the isolated WordPress fixture.
 */

use SeoGeo\Manager\Changes\ContentLifecycleEngine;
use SeoGeo\Manager\Support\ContentFingerprint;
use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_lifecycle_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Execute one Manager REST request with an optional JSON body.
 *
 * @param string               $method HTTP method.
 * @param string               $route REST route.
 * @param array<string, mixed> $body JSON body.
 */
function seo_geo_manager_lifecycle_rest( string $method, string $route, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
	}

	$response = rest_do_request( $request );
	seo_geo_manager_lifecycle_accept( $response instanceof WP_REST_Response, 'REST request did not return a WordPress REST response.' );

	return $response;
}

wp_set_current_user( 1 );
seo_geo_manager_lifecycle_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

// Draft-first normal post creation.
$draft_payload = array(
	'schema_version'  => 1,
	'type'            => 'post',
	'title'           => 'Manager lifecycle draft',
	'slug'            => 'manager-lifecycle-draft',
	'excerpt'         => 'Lifecycle excerpt',
	'content'         => '<p>Lifecycle body</p>',
	'status'          => 'draft',
	'idempotency_key' => 'acceptance-create-draft-post',
);
$draft_preview = ContentLifecycleEngine::preview_create( $draft_payload );
seo_geo_manager_lifecycle_accept( is_array( $draft_preview ), 'Draft create preview failed.' );
seo_geo_manager_lifecycle_accept( true === ( $draft_preview['will_create'] ?? false ), 'Draft preview did not declare resource creation.' );

$draft_create = ContentLifecycleEngine::create( $draft_payload );
seo_geo_manager_lifecycle_accept( is_array( $draft_create ), 'Draft post creation failed.' );
$draft_id = (int) ( $draft_create['target_id'] ?? 0 );
seo_geo_manager_lifecycle_accept( 0 < $draft_id, 'Draft post did not return a target ID.' );
$draft_post = get_post( $draft_id );
seo_geo_manager_lifecycle_accept( $draft_post instanceof WP_Post, 'Draft post could not be loaded.' );
seo_geo_manager_lifecycle_accept( 'draft' === $draft_post->post_status, 'Draft post status mismatch.' );
seo_geo_manager_lifecycle_accept( 'manager-lifecycle-draft' === $draft_post->post_name, 'Draft post slug mismatch.' );
seo_geo_manager_lifecycle_accept( ! empty( $draft_create['after_fingerprint'] ), 'Draft creation did not return a fingerprint.' );
seo_geo_manager_lifecycle_accept( ! empty( $draft_create['permalink'] ), 'Draft creation did not return a permalink.' );

// Idempotent replay must resolve to the same operation/resource.
$draft_replay = ContentLifecycleEngine::create( $draft_payload );
seo_geo_manager_lifecycle_accept( is_array( $draft_replay ), 'Draft idempotent replay failed.' );
seo_geo_manager_lifecycle_accept( true === ( $draft_replay['idempotent_replay'] ?? false ), 'Draft creation was not idempotent.' );
seo_geo_manager_lifecycle_accept( $draft_id === (int) ( $draft_replay['target_id'] ?? 0 ), 'Idempotent replay returned a different target.' );

// Exact slug reuse must be blocked rather than silently suffixed by WordPress.
$collision_payload                    = $draft_payload;
$collision_payload['title']           = 'Collision attempt';
$collision_payload['idempotency_key'] = 'acceptance-create-collision';
$collision                            = ContentLifecycleEngine::preview_create( $collision_payload );
seo_geo_manager_lifecycle_accept( is_wp_error( $collision ), 'Create preview did not block a slug collision.' );
seo_geo_manager_lifecycle_accept( 'seo_geo_manager_create_slug_collision' === $collision->get_error_code(), 'Unexpected slug collision error code.' );

// Publication preview/apply uses the exact inspected fingerprint.
$draft_post = get_post( $draft_id );
seo_geo_manager_lifecycle_accept( $draft_post instanceof WP_Post, 'Draft post disappeared before publication.' );
$publish_payload = array(
	'schema_version'  => 1,
	'target'          => array(
		'id'                   => $draft_id,
		'expected_fingerprint' => ContentFingerprint::for_post( $draft_post ),
	),
	'status'          => 'publish',
	'idempotency_key' => 'acceptance-publish-post',
);
$publish_preview = ContentLifecycleEngine::preview_publication( $publish_payload );
seo_geo_manager_lifecycle_accept( is_array( $publish_preview ), 'Publication preview failed.' );
seo_geo_manager_lifecycle_accept( 'draft' === ( $publish_preview['transition']['from_status'] ?? '' ), 'Publication preview source status mismatch.' );
seo_geo_manager_lifecycle_accept( 'publish' === ( $publish_preview['transition']['to_status'] ?? '' ), 'Publication preview target status mismatch.' );

$publish_apply = ContentLifecycleEngine::apply_publication( $publish_payload );
seo_geo_manager_lifecycle_accept( is_array( $publish_apply ), 'Publication apply failed.' );
seo_geo_manager_lifecycle_accept( 'published' === ( $publish_apply['status'] ?? '' ), 'Publication operation did not report published.' );
$published_post = get_post( $draft_id );
seo_geo_manager_lifecycle_accept( $published_post instanceof WP_Post && 'publish' === $published_post->post_status, 'Post did not publish.' );

$publish_replay = ContentLifecycleEngine::apply_publication( $publish_payload );
seo_geo_manager_lifecycle_accept( is_array( $publish_replay ), 'Publication idempotent replay failed.' );
seo_geo_manager_lifecycle_accept( true === ( $publish_replay['idempotent_replay'] ?? false ), 'Publication transition was not idempotent.' );

// Scheduling a new post must persist exact future GMT state.
$scheduled_at = gmdate( 'Y-m-d\TH:i:s\Z', time() + 3600 );
$schedule_payload = array(
	'schema_version'   => 1,
	'type'             => 'post',
	'title'            => 'Manager scheduled post',
	'slug'             => 'manager-scheduled-post',
	'content'          => '<p>Scheduled body</p>',
	'status'           => 'future',
	'scheduled_at_gmt' => $scheduled_at,
	'idempotency_key'  => 'acceptance-create-scheduled-post',
);
$schedule_preview = ContentLifecycleEngine::preview_create( $schedule_payload );
seo_geo_manager_lifecycle_accept( is_array( $schedule_preview ), 'Scheduled create preview failed.' );
$schedule_create = ContentLifecycleEngine::create( $schedule_payload );
seo_geo_manager_lifecycle_accept( is_array( $schedule_create ), 'Scheduled post creation failed.' );
$scheduled_post = get_post( (int) ( $schedule_create['target_id'] ?? 0 ) );
seo_geo_manager_lifecycle_accept( $scheduled_post instanceof WP_Post, 'Scheduled post could not be loaded.' );
seo_geo_manager_lifecycle_accept( 'future' === $scheduled_post->post_status, 'Scheduled post status mismatch.' );
seo_geo_manager_lifecycle_accept( gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_at ) ) === $scheduled_post->post_date_gmt, 'Scheduled GMT date mismatch.' );

// Page creation is a first-class generic operation, not a post-only shortcut.
$page_payload = array(
	'schema_version'  => 1,
	'type'            => 'page',
	'title'           => 'Manager lifecycle page',
	'slug'            => 'manager-lifecycle-page',
	'content'         => '<p>Page body</p>',
	'status'          => 'draft',
	'idempotency_key' => 'acceptance-create-page',
);
$page_create = ContentLifecycleEngine::create( $page_payload );
seo_geo_manager_lifecycle_accept( is_array( $page_create ), 'Page creation failed.' );
$page_post = get_post( (int) ( $page_create['target_id'] ?? 0 ) );
seo_geo_manager_lifecycle_accept( $page_post instanceof WP_Post && 'page' === $page_post->post_type, 'Created resource is not a page.' );
seo_geo_manager_lifecycle_accept( 'draft' === $page_post->post_status, 'Page draft status mismatch.' );

// C4.1 taxonomy assignment: external strategy selects terms; Manager only assigns existing IDs.
$category_a = wp_insert_term( 'Manager Category A', 'category' );
$category_b = wp_insert_term( 'Manager Category B', 'category' );
$category_c = wp_insert_term( 'Manager Category C', 'category' );
$tag_a      = wp_insert_term( 'Manager Tag A', 'post_tag' );
$tag_b      = wp_insert_term( 'Manager Tag B', 'post_tag' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $category_a ), 'Could not create category A fixture.' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $category_b ), 'Could not create category B fixture.' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $category_c ), 'Could not create category C fixture.' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $tag_a ), 'Could not create tag A fixture.' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $tag_b ), 'Could not create tag B fixture.' );
$category_a_id = (int) $category_a['term_id'];
$category_b_id = (int) $category_b['term_id'];
$category_c_id = (int) $category_c['term_id'];
$tag_a_id      = (int) $tag_a['term_id'];
$tag_b_id      = (int) $tag_b['term_id'];

$category_seed = wp_set_object_terms( $draft_id, array( $category_a_id ), 'category', false );
$tag_seed      = wp_set_object_terms( $draft_id, array( $tag_a_id ), 'post_tag', false );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $category_seed ), 'Could not seed category assignment.' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $tag_seed ), 'Could not seed tag assignment.' );
$published_post = get_post( $draft_id );
seo_geo_manager_lifecycle_accept( $published_post instanceof WP_Post, 'Published post disappeared before taxonomy acceptance.' );
$original_body = (string) $published_post->post_content;

$capabilities_response = seo_geo_manager_lifecycle_rest( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_lifecycle_accept( 200 === $capabilities_response->get_status(), 'Capabilities endpoint rejected taxonomy acceptance administrator.' );
$capabilities = $capabilities_response->get_data();
seo_geo_manager_lifecycle_accept( is_array( $capabilities ), 'Capabilities response was not an array.' );
seo_geo_manager_lifecycle_accept( true === ( $capabilities['capabilities']['taxonomy_assignment']['available'] ?? false ), 'Taxonomy assignment was not discoverable.' );
seo_geo_manager_lifecycle_accept( true === ( $capabilities['capabilities']['taxonomy_assignment']['existing_terms_only'] ?? false ), 'Taxonomy capability did not declare existing-term-only policy.' );
seo_geo_manager_lifecycle_accept( isset( $capabilities['operations']['content.taxonomy_assignment.apply'] ), 'Taxonomy Apply operation was not advertised.' );

$category_route = '/seo-geo-manager/v1/content/' . $draft_id . '/taxonomies/category';
$category_read  = seo_geo_manager_lifecycle_rest( 'GET', $category_route );
seo_geo_manager_lifecycle_accept( 200 === $category_read->get_status(), 'Category assignment GET failed.' );
$category_state = $category_read->get_data();
seo_geo_manager_lifecycle_accept( is_array( $category_state ), 'Category assignment response was invalid.' );
seo_geo_manager_lifecycle_accept( array( $category_a_id ) === ( $category_state['assignment']['term_ids'] ?? array() ), 'Initial category assignment mismatch.' );
$category_fingerprint = (string) ( $category_state['assignment']['fingerprint'] ?? '' );
seo_geo_manager_lifecycle_accept( '' !== $category_fingerprint, 'Category assignment fingerprint missing.' );

$category_preview_body = array(
	'term_ids'             => array( $category_b_id ),
	'expected_fingerprint' => $category_fingerprint,
);
$category_preview = seo_geo_manager_lifecycle_rest( 'POST', $category_route . '/changes/preview', $category_preview_body );
seo_geo_manager_lifecycle_accept( 200 === $category_preview->get_status(), 'Category assignment Preview failed.' );
$category_after_preview = wp_get_object_terms( $draft_id, 'category', array( 'fields' => 'ids' ) );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $category_after_preview ), 'Could not verify non-mutating category Preview.' );
seo_geo_manager_lifecycle_accept( array( $category_a_id ) === array_map( 'intval', $category_after_preview ), 'Category Preview mutated WordPress state.' );

$environment = EnvironmentPolicy::snapshot();
$blocked_apply_body = $category_preview_body;
$blocked_apply_body['idempotency_key'] = 'acceptance-taxonomy-category-blocked';
$blocked_apply_body['environment_fingerprint'] = $environment['fingerprint'];
$blocked_apply = seo_geo_manager_lifecycle_rest( 'POST', $category_route . '/changes/apply', $blocked_apply_body );
seo_geo_manager_lifecycle_accept( 409 === $blocked_apply->get_status(), 'Published taxonomy Apply was not blocked without explicit approval.' );
$blocked_apply_data = $blocked_apply->get_data();
seo_geo_manager_lifecycle_accept( is_array( $blocked_apply_data ) && 'seo_geo_manager_taxonomy_public_approval_required' === ( $blocked_apply_data['code'] ?? '' ), 'Unexpected published taxonomy guard error.' );

$apply_body = $category_preview_body;
$apply_body['idempotency_key'] = 'acceptance-taxonomy-category-apply';
$apply_body['allow_published_target'] = true;
$apply_body['environment_fingerprint'] = $environment['fingerprint'];
$category_apply = seo_geo_manager_lifecycle_rest( 'POST', $category_route . '/changes/apply', $apply_body );
seo_geo_manager_lifecycle_accept( 200 === $category_apply->get_status(), 'Approved category assignment Apply failed.' );
$category_operation = $category_apply->get_data();
seo_geo_manager_lifecycle_accept( is_array( $category_operation ), 'Category Apply did not return an operation.' );
seo_geo_manager_lifecycle_accept( 'applied' === ( $category_operation['status'] ?? '' ), 'Category Apply did not report applied.' );
$category_operation_id = (string) ( $category_operation['operation_id'] ?? '' );
seo_geo_manager_lifecycle_accept( '' !== $category_operation_id, 'Category Apply operation ID missing.' );
seo_geo_manager_lifecycle_accept( $environment['fingerprint'] === ( $category_operation['environment']['fingerprint'] ?? '' ), 'Category operation was not environment-bound.' );

$category_replay = seo_geo_manager_lifecycle_rest( 'POST', $category_route . '/changes/apply', $apply_body );
seo_geo_manager_lifecycle_accept( 200 === $category_replay->get_status(), 'Category idempotent replay failed.' );
$category_replay_data = $category_replay->get_data();
seo_geo_manager_lifecycle_accept( is_array( $category_replay_data ) && true === ( $category_replay_data['idempotent_replay'] ?? false ), 'Category Apply was not idempotent.' );
seo_geo_manager_lifecycle_accept( $category_operation_id === ( $category_replay_data['operation_id'] ?? '' ), 'Category replay returned a different operation.' );

$category_verify = seo_geo_manager_lifecycle_rest( 'GET', $category_route );
$category_verify_data = $category_verify->get_data();
seo_geo_manager_lifecycle_accept( 200 === $category_verify->get_status(), 'Category readback failed.' );
seo_geo_manager_lifecycle_accept( is_array( $category_verify_data ) && array( $category_b_id ) === ( $category_verify_data['assignment']['term_ids'] ?? array() ), 'Category Apply did not persist exact target IDs.' );

$stale_change = wp_set_object_terms( $draft_id, array( $category_c_id ), 'category', false );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $stale_change ), 'Could not create newer taxonomy edit for stale rollback test.' );
$rollback_route = '/seo-geo-manager/v1/content/taxonomies/changes/' . $category_operation_id . '/rollback';
$rollback_body = array( 'environment_fingerprint' => $environment['fingerprint'] );
$stale_rollback = seo_geo_manager_lifecycle_rest( 'POST', $rollback_route, $rollback_body );
seo_geo_manager_lifecycle_accept( 409 === $stale_rollback->get_status(), 'Taxonomy rollback overwrote a newer edit.' );
$stale_rollback_data = $stale_rollback->get_data();
seo_geo_manager_lifecycle_accept( is_array( $stale_rollback_data ) && 'seo_geo_manager_taxonomy_rollback_stale' === ( $stale_rollback_data['code'] ?? '' ), 'Unexpected stale taxonomy rollback error.' );

$restore_applied_state = wp_set_object_terms( $draft_id, array( $category_b_id ), 'category', false );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $restore_applied_state ), 'Could not restore applied category state before accepted rollback.' );
$category_rollback = seo_geo_manager_lifecycle_rest( 'POST', $rollback_route, $rollback_body );
seo_geo_manager_lifecycle_accept( 200 === $category_rollback->get_status(), 'Category rollback failed.' );
$category_rollback_data = $category_rollback->get_data();
seo_geo_manager_lifecycle_accept( is_array( $category_rollback_data ) && 'rolled-back' === ( $category_rollback_data['status'] ?? '' ), 'Category rollback did not report rolled-back.' );
$category_final = seo_geo_manager_lifecycle_rest( 'GET', $category_route );
$category_final_data = $category_final->get_data();
seo_geo_manager_lifecycle_accept( is_array( $category_final_data ) && array( $category_a_id ) === ( $category_final_data['assignment']['term_ids'] ?? array() ), 'Category rollback did not restore the original assignment.' );

// A flat taxonomy uses the same generic contract; Preview must not be category-specific.
$tag_route = '/seo-geo-manager/v1/content/' . $draft_id . '/taxonomies/post_tag';
$tag_read = seo_geo_manager_lifecycle_rest( 'GET', $tag_route );
seo_geo_manager_lifecycle_accept( 200 === $tag_read->get_status(), 'Tag assignment GET failed.' );
$tag_state = $tag_read->get_data();
seo_geo_manager_lifecycle_accept( is_array( $tag_state ) && array( $tag_a_id ) === ( $tag_state['assignment']['term_ids'] ?? array() ), 'Initial tag assignment mismatch.' );
$tag_preview = seo_geo_manager_lifecycle_rest(
	'POST',
	$tag_route . '/changes/preview',
	array(
		'term_ids'             => array( $tag_b_id ),
		'expected_fingerprint' => (string) ( $tag_state['assignment']['fingerprint'] ?? '' ),
	)
);
seo_geo_manager_lifecycle_accept( 200 === $tag_preview->get_status(), 'Tag assignment Preview failed.' );

$published_post_after_taxonomy = get_post( $draft_id );
seo_geo_manager_lifecycle_accept( $published_post_after_taxonomy instanceof WP_Post, 'Published post disappeared after taxonomy cycle.' );
seo_geo_manager_lifecycle_accept( $original_body === (string) $published_post_after_taxonomy->post_content, 'Taxonomy assignment changed post_content.' );

// Stale fingerprint protection must refuse publication after a human/newer edit.
$stale_payload = array(
	'schema_version'  => 1,
	'type'            => 'post',
	'title'           => 'Manager stale guard',
	'slug'            => 'manager-stale-guard',
	'content'         => '<p>Initial body</p>',
	'status'          => 'draft',
	'idempotency_key' => 'acceptance-create-stale-post',
);
$stale_create = ContentLifecycleEngine::create( $stale_payload );
seo_geo_manager_lifecycle_accept( is_array( $stale_create ), 'Stale-guard fixture creation failed.' );
$stale_id   = (int) ( $stale_create['target_id'] ?? 0 );
$stale_post = get_post( $stale_id );
seo_geo_manager_lifecycle_accept( $stale_post instanceof WP_Post, 'Stale-guard fixture could not be loaded.' );
$old_fingerprint = ContentFingerprint::for_post( $stale_post );
wp_update_post(
	array(
		'ID'         => $stale_id,
		'post_title' => 'Human edit after inspection',
	)
);
$stale_publication = ContentLifecycleEngine::preview_publication(
	array(
		'schema_version' => 1,
		'target'         => array(
			'id'                   => $stale_id,
			'expected_fingerprint' => $old_fingerprint,
		),
		'status'         => 'publish',
	)
);
seo_geo_manager_lifecycle_accept( is_wp_error( $stale_publication ), 'Stale publication preview was not blocked.' );
seo_geo_manager_lifecycle_accept( 'seo_geo_manager_publication_stale' === $stale_publication->get_error_code(), 'Unexpected stale publication error code.' );

// Per-type capability enforcement: an Author may create posts but not pages.
$author_id = wp_create_user( 'manager-lifecycle-author', wp_generate_password( 32, true, true ), 'manager-lifecycle-author@example.test' );
seo_geo_manager_lifecycle_accept( ! is_wp_error( $author_id ), 'Could not create Author capability fixture.' );
$author = new WP_User( (int) $author_id );
$author->set_role( 'author' );
wp_set_current_user( (int) $author_id );
$author_post = ContentLifecycleEngine::preview_create(
	array(
		'schema_version' => 1,
		'type'           => 'post',
		'title'          => 'Author post preview',
		'status'         => 'draft',
	)
);
seo_geo_manager_lifecycle_accept( is_array( $author_post ), 'Author could not preview normal post creation.' );
$author_page = ContentLifecycleEngine::preview_create(
	array(
		'schema_version' => 1,
		'type'           => 'page',
		'title'          => 'Author page preview',
		'status'         => 'draft',
	)
);
seo_geo_manager_lifecycle_accept( is_wp_error( $author_page ), 'Author incorrectly received page creation authority.' );
seo_geo_manager_lifecycle_accept( 'seo_geo_manager_create_forbidden' === $author_page->get_error_code(), 'Unexpected page capability error code.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                        => true,
		'plugin_version'            => SEO_GEO_MANAGER_VERSION,
		'post_create'               => true,
		'page_create'               => true,
		'idempotent_create'         => true,
		'slug_collision_guard'      => true,
		'publish_now'               => true,
		'schedule_future'           => true,
		'taxonomy_assignment'       => true,
		'taxonomy_preview'          => true,
		'taxonomy_idempotency'      => true,
		'taxonomy_stale_rollback'   => true,
		'taxonomy_category_and_tag' => true,
		'publication_stale_guard'   => true,
		'per_type_capability_guard' => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
