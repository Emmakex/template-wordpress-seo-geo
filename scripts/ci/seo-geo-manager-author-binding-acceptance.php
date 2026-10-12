<?php
/**
 * Runtime acceptance for SEO/GEO Manager existing-user author binding.
 *
 * Executed with WP-CLI eval-file inside the shared C4 WordPress fixture.
 */

use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_author_accept( bool $condition, string $message ): void {
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
function seo_geo_manager_author_rest( string $method, string $route, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
	}

	$response = rest_do_request( $request );
	seo_geo_manager_author_accept( $response instanceof WP_REST_Response, 'REST request did not return a WordPress REST response.' );

	return $response;
}

wp_set_current_user( 1 );
seo_geo_manager_author_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$post = get_page_by_path( 'manager-lifecycle-draft', OBJECT, 'post' );
seo_geo_manager_author_accept( $post instanceof WP_Post, 'Published lifecycle post was not found for author acceptance.' );
seo_geo_manager_author_accept( 'publish' === $post->post_status, 'Author acceptance target is not published.' );
seo_geo_manager_author_accept( 1 === (int) $post->post_author, 'Author acceptance target did not start with administrator ownership.' );

$page = get_page_by_path( 'manager-lifecycle-page', OBJECT, 'page' );
seo_geo_manager_author_accept( $page instanceof WP_Post, 'Lifecycle page was not found for author acceptance.' );

$target_author_id = wp_create_user(
	'manager-author-target',
	wp_generate_password( 32, true, true ),
	'manager-author-target@example.test'
);
$newer_author_id = wp_create_user(
	'manager-author-newer',
	wp_generate_password( 32, true, true ),
	'manager-author-newer@example.test'
);
$subscriber_id = wp_create_user(
	'manager-author-subscriber',
	wp_generate_password( 32, true, true ),
	'manager-author-subscriber@example.test'
);
$editor_id = wp_create_user(
	'manager-author-editor',
	wp_generate_password( 32, true, true ),
	'manager-author-editor@example.test'
);
seo_geo_manager_author_accept( ! is_wp_error( $target_author_id ), 'Could not create target Author fixture.' );
seo_geo_manager_author_accept( ! is_wp_error( $newer_author_id ), 'Could not create newer Author fixture.' );
seo_geo_manager_author_accept( ! is_wp_error( $subscriber_id ), 'Could not create Subscriber fixture.' );
seo_geo_manager_author_accept( ! is_wp_error( $editor_id ), 'Could not create Editor fixture.' );

$target_author = new WP_User( (int) $target_author_id );
$target_author->set_role( 'author' );
$newer_author = new WP_User( (int) $newer_author_id );
$newer_author->set_role( 'author' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
$editor = new WP_User( (int) $editor_id );
$editor->set_role( 'editor' );

$original_body       = (string) $post->post_content;
$original_title      = (string) $post->post_title;
$original_status     = (string) $post->post_status;
$original_categories = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
$original_tags       = wp_get_object_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );
seo_geo_manager_author_accept( ! is_wp_error( $original_categories ), 'Could not read category baseline before author binding.' );
seo_geo_manager_author_accept( ! is_wp_error( $original_tags ), 'Could not read tag baseline before author binding.' );

// Capability discovery exposes the semantic relationship operation, not user-management powers.
$capabilities_response = seo_geo_manager_author_rest( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_author_accept( 200 === $capabilities_response->get_status(), 'Capabilities endpoint rejected author acceptance administrator.' );
$capabilities = $capabilities_response->get_data();
seo_geo_manager_author_accept( is_array( $capabilities ), 'Capabilities response was not an array.' );
seo_geo_manager_author_accept( true === ( $capabilities['capabilities']['author_binding']['available'] ?? false ), 'Author binding was not discoverable.' );
seo_geo_manager_author_accept( true === ( $capabilities['capabilities']['author_binding']['existing_users_only'] ?? false ), 'Author binding did not declare existing-user-only policy.' );
seo_geo_manager_author_accept( true === ( $capabilities['capabilities']['author_binding']['public_profile_fields_only'] ?? false ), 'Author binding did not declare bounded public-profile output.' );
seo_geo_manager_author_accept( isset( $capabilities['operations']['content.author_binding.apply'] ), 'Author binding Apply operation was not advertised.' );

$route = '/seo-geo-manager/v1/content/' . $post->ID . '/author';
$read  = seo_geo_manager_author_rest( 'GET', $route );
seo_geo_manager_author_accept( 200 === $read->get_status(), 'Author binding GET failed.' );
$state = $read->get_data();
seo_geo_manager_author_accept( is_array( $state ), 'Author binding GET returned invalid data.' );
seo_geo_manager_author_accept( 1 === (int) ( $state['binding']['author_id'] ?? 0 ), 'Initial author binding mismatch.' );
$fingerprint = (string) ( $state['binding']['fingerprint'] ?? '' );
seo_geo_manager_author_accept( '' !== $fingerprint, 'Author binding fingerprint missing.' );
seo_geo_manager_author_accept( isset( $state['author']['display_name'] ), 'Author binding omitted public display name.' );
seo_geo_manager_author_accept( ! isset( $state['author']['user_email'] ), 'Author binding leaked email.' );
seo_geo_manager_author_accept( ! isset( $state['author']['user_login'] ), 'Author binding leaked login.' );

// Preview another existing eligible author and prove it is non-mutating.
$preview_body = array(
	'author_id'            => (int) $target_author_id,
	'expected_fingerprint' => $fingerprint,
);
$preview = seo_geo_manager_author_rest( 'POST', $route . '/changes/preview', $preview_body );
seo_geo_manager_author_accept( 200 === $preview->get_status(), 'Author binding Preview failed.' );
$preview_data = $preview->get_data();
seo_geo_manager_author_accept( is_array( $preview_data ), 'Author binding Preview returned invalid data.' );
seo_geo_manager_author_accept( 1 === (int) ( $preview_data['change']['before_author_id'] ?? 0 ), 'Author Preview before ID mismatch.' );
seo_geo_manager_author_accept( (int) $target_author_id === (int) ( $preview_data['change']['after_author_id'] ?? 0 ), 'Author Preview after ID mismatch.' );
$post_after_preview = get_post( $post->ID );
seo_geo_manager_author_accept( $post_after_preview instanceof WP_Post, 'Target post disappeared after author Preview.' );
seo_geo_manager_author_accept( 1 === (int) $post_after_preview->post_author, 'Author Preview mutated WordPress state.' );

$environment = EnvironmentPolicy::snapshot();

// Public mutation requires explicit approval after Preview.
$blocked_body = $preview_body;
$blocked_body['idempotency_key'] = 'acceptance-author-binding-blocked';
$blocked_body['environment_fingerprint'] = $environment['fingerprint'];
$blocked = seo_geo_manager_author_rest( 'POST', $route . '/changes/apply', $blocked_body );
seo_geo_manager_author_accept( 409 === $blocked->get_status(), 'Published author Apply was not blocked without explicit approval.' );
$blocked_data = $blocked->get_data();
seo_geo_manager_author_accept( is_array( $blocked_data ) && 'seo_geo_manager_author_binding_public_approval_required' === ( $blocked_data['code'] ?? '' ), 'Unexpected published author guard error.' );

$apply_body = $preview_body;
$apply_body['idempotency_key'] = 'acceptance-author-binding-apply';
$apply_body['allow_published_target'] = true;
$apply_body['environment_fingerprint'] = $environment['fingerprint'];
$apply = seo_geo_manager_author_rest( 'POST', $route . '/changes/apply', $apply_body );
seo_geo_manager_author_accept( 200 === $apply->get_status(), 'Approved author binding Apply failed.' );
$operation = $apply->get_data();
seo_geo_manager_author_accept( is_array( $operation ), 'Author Apply did not return an operation.' );
seo_geo_manager_author_accept( 'applied' === ( $operation['status'] ?? '' ), 'Author Apply did not report applied.' );
$operation_id = (string) ( $operation['operation_id'] ?? '' );
seo_geo_manager_author_accept( '' !== $operation_id, 'Author Apply operation ID missing.' );
seo_geo_manager_author_accept( (int) $target_author_id === (int) ( $operation['after_author_id'] ?? 0 ), 'Author operation target ID mismatch.' );
seo_geo_manager_author_accept( $environment['fingerprint'] === ( $operation['environment']['fingerprint'] ?? '' ), 'Author operation was not environment-bound.' );

// Same bounded request is retry-safe.
$replay = seo_geo_manager_author_rest( 'POST', $route . '/changes/apply', $apply_body );
seo_geo_manager_author_accept( 200 === $replay->get_status(), 'Author idempotent replay failed.' );
$replay_data = $replay->get_data();
seo_geo_manager_author_accept( is_array( $replay_data ) && true === ( $replay_data['idempotent_replay'] ?? false ), 'Author Apply was not idempotent.' );
seo_geo_manager_author_accept( $operation_id === ( $replay_data['operation_id'] ?? '' ), 'Author replay returned a different operation.' );

$readback = seo_geo_manager_author_rest( 'GET', $route );
seo_geo_manager_author_accept( 200 === $readback->get_status(), 'Author binding readback failed.' );
$readback_data = $readback->get_data();
seo_geo_manager_author_accept( is_array( $readback_data ) && (int) $target_author_id === (int) ( $readback_data['binding']['author_id'] ?? 0 ), 'Author Apply did not persist exact target author.' );

// Relationship mutation must not rewrite content, status, title or taxonomy relationships.
$post_after_apply = get_post( $post->ID );
seo_geo_manager_author_accept( $post_after_apply instanceof WP_Post, 'Target post disappeared after author Apply.' );
seo_geo_manager_author_accept( $original_body === (string) $post_after_apply->post_content, 'Author binding changed post_content.' );
seo_geo_manager_author_accept( $original_title === (string) $post_after_apply->post_title, 'Author binding changed post_title.' );
seo_geo_manager_author_accept( $original_status === (string) $post_after_apply->post_status, 'Author binding changed post_status.' );
$categories_after_apply = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
$tags_after_apply       = wp_get_object_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );
seo_geo_manager_author_accept( ! is_wp_error( $categories_after_apply ) && array_map( 'intval', $original_categories ) === array_map( 'intval', $categories_after_apply ), 'Author binding changed category relationships.' );
seo_geo_manager_author_accept( ! is_wp_error( $tags_after_apply ) && array_map( 'intval', $original_tags ) === array_map( 'intval', $tags_after_apply ), 'Author binding changed tag relationships.' );

// A newer human/automation author edit must make rollback stale-safe.
$newer = wp_update_post(
	array(
		'ID'          => $post->ID,
		'post_author' => (int) $newer_author_id,
	),
	true
);
seo_geo_manager_author_accept( ! is_wp_error( $newer ), 'Could not create newer author edit for stale rollback test.' );
$rollback_route = '/seo-geo-manager/v1/content/authors/changes/' . $operation_id . '/rollback';
$rollback_body  = array( 'environment_fingerprint' => $environment['fingerprint'] );
$stale_rollback = seo_geo_manager_author_rest( 'POST', $rollback_route, $rollback_body );
seo_geo_manager_author_accept( 409 === $stale_rollback->get_status(), 'Author rollback overwrote a newer edit.' );
$stale_data = $stale_rollback->get_data();
seo_geo_manager_author_accept( is_array( $stale_data ) && 'seo_geo_manager_author_binding_rollback_stale' === ( $stale_data['code'] ?? '' ), 'Unexpected stale author rollback error.' );

// Return to the exact state produced by Apply, then rollback can safely restore the original author.
$restore_applied = wp_update_post(
	array(
		'ID'          => $post->ID,
		'post_author' => (int) $target_author_id,
	),
	true
);
seo_geo_manager_author_accept( ! is_wp_error( $restore_applied ), 'Could not restore applied author state before accepted rollback.' );
$rollback = seo_geo_manager_author_rest( 'POST', $rollback_route, $rollback_body );
seo_geo_manager_author_accept( 200 === $rollback->get_status(), 'Author binding rollback failed.' );
$rollback_data = $rollback->get_data();
seo_geo_manager_author_accept( is_array( $rollback_data ) && 'rolled-back' === ( $rollback_data['status'] ?? '' ), 'Author rollback did not report rolled-back.' );
$final = seo_geo_manager_author_rest( 'GET', $route );
$final_data = $final->get_data();
seo_geo_manager_author_accept( is_array( $final_data ) && 1 === (int) ( $final_data['binding']['author_id'] ?? 0 ), 'Author rollback did not restore the original author.' );

// Subscriber is an existing user but not an eligible public author for normal posts.
$subscriber_state = seo_geo_manager_author_rest( 'GET', $route );
$subscriber_state_data = $subscriber_state->get_data();
seo_geo_manager_author_accept( is_array( $subscriber_state_data ), 'Could not refresh author state for subscriber test.' );
$subscriber_preview = seo_geo_manager_author_rest(
	'POST',
	$route . '/changes/preview',
	array(
		'author_id'            => (int) $subscriber_id,
		'expected_fingerprint' => (string) ( $subscriber_state_data['binding']['fingerprint'] ?? '' ),
	)
);
seo_geo_manager_author_accept( 403 === $subscriber_preview->get_status(), 'Subscriber was incorrectly accepted as a normal post author.' );
$subscriber_error = $subscriber_preview->get_data();
seo_geo_manager_author_accept( is_array( $subscriber_error ) && 'seo_geo_manager_author_binding_user_ineligible' === ( $subscriber_error['code'] ?? '' ), 'Unexpected ineligible-author error.' );

// Post-type awareness: an Editor can be previewed as a page author because it owns edit_pages.
$page_route = '/seo-geo-manager/v1/content/' . $page->ID . '/author';
$page_read = seo_geo_manager_author_rest( 'GET', $page_route );
seo_geo_manager_author_accept( 200 === $page_read->get_status(), 'Page author GET failed.' );
$page_state = $page_read->get_data();
seo_geo_manager_author_accept( is_array( $page_state ), 'Page author state was invalid.' );
$page_preview = seo_geo_manager_author_rest(
	'POST',
	$page_route . '/changes/preview',
	array(
		'author_id'            => (int) $editor_id,
		'expected_fingerprint' => (string) ( $page_state['binding']['fingerprint'] ?? '' ),
	)
);
seo_geo_manager_author_accept( 200 === $page_preview->get_status(), 'Editor could not be previewed as a page author.' );
$page_after_preview = get_post( $page->ID );
seo_geo_manager_author_accept( $page_after_preview instanceof WP_Post && 1 === (int) $page_after_preview->post_author, 'Page author Preview mutated the page.' );

// Least privilege: an Author may edit its own post but cannot assign a different author without edit_others_posts.
$operator_author_id = wp_create_user(
	'manager-author-operator',
	wp_generate_password( 32, true, true ),
	'manager-author-operator@example.test'
);
seo_geo_manager_author_accept( ! is_wp_error( $operator_author_id ), 'Could not create least-privilege Author operator.' );
$operator_author = new WP_User( (int) $operator_author_id );
$operator_author->set_role( 'author' );
$owned_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Author binding least privilege',
		'post_content' => '<p>Owned by least-privilege author.</p>',
		'post_author'  => (int) $operator_author_id,
	),
	true
);
seo_geo_manager_author_accept( ! is_wp_error( $owned_post_id ), 'Could not create least-privilege author post.' );

wp_set_current_user( (int) $operator_author_id );
$owned_route = '/seo-geo-manager/v1/content/' . (int) $owned_post_id . '/author';
$owned_read = seo_geo_manager_author_rest( 'GET', $owned_route );
seo_geo_manager_author_accept( 200 === $owned_read->get_status(), 'Author operator could not inspect its own post author binding.' );
$owned_state = $owned_read->get_data();
seo_geo_manager_author_accept( is_array( $owned_state ), 'Least-privilege author state was invalid.' );
$cross_author_preview = seo_geo_manager_author_rest(
	'POST',
	$owned_route . '/changes/preview',
	array(
		'author_id'            => (int) $target_author_id,
		'expected_fingerprint' => (string) ( $owned_state['binding']['fingerprint'] ?? '' ),
	)
);
seo_geo_manager_author_accept( 403 === $cross_author_preview->get_status(), 'Author operator incorrectly reassigned its post to another user.' );
$cross_author_error = $cross_author_preview->get_data();
seo_geo_manager_author_accept( is_array( $cross_author_error ) && 'seo_geo_manager_author_binding_edit_others_required' === ( $cross_author_error['code'] ?? '' ), 'Unexpected edit-others author error.' );

wp_set_current_user( 1 );

// Final integrity check after all author-binding scenarios.
$integrity_post = get_post( $post->ID );
seo_geo_manager_author_accept( $integrity_post instanceof WP_Post, 'Target post disappeared before final integrity check.' );
seo_geo_manager_author_accept( 1 === (int) $integrity_post->post_author, 'Final author relationship was not restored.' );
seo_geo_manager_author_accept( $original_body === (string) $integrity_post->post_content, 'Author acceptance changed final post_content.' );
seo_geo_manager_author_accept( $original_title === (string) $integrity_post->post_title, 'Author acceptance changed final post_title.' );
seo_geo_manager_author_accept( $original_status === (string) $integrity_post->post_status, 'Author acceptance changed final post_status.' );

echo wp_json_encode(
	array(
		'ok'                         => true,
		'plugin_version'             => SEO_GEO_MANAGER_VERSION,
		'author_binding_read'        => true,
		'author_binding_preview'     => true,
		'existing_users_only'        => true,
		'public_profile_bounded'     => true,
		'published_guard'            => true,
		'environment_binding'        => true,
		'author_idempotency'         => true,
		'author_readback'            => true,
		'author_stale_rollback'      => true,
		'author_rollback'            => true,
		'ineligible_user_guard'      => true,
		'post_type_author_guard'     => true,
		'edit_others_guard'          => true,
		'content_integrity_preserved'=> true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
