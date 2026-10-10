<?php
/**
 * Runtime acceptance for SEO/GEO Manager content lifecycle operations.
 *
 * Executed with WP-CLI eval-file inside the isolated WordPress fixture.
 */

use SeoGeo\Manager\Changes\ContentLifecycleEngine;
use SeoGeo\Manager\Support\ContentFingerprint;

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
	'schema_version'    => 1,
	'type'              => 'post',
	'title'             => 'Manager scheduled post',
	'slug'              => 'manager-scheduled-post',
	'content'           => '<p>Scheduled body</p>',
	'status'            => 'future',
	'scheduled_at_gmt'  => $scheduled_at,
	'idempotency_key'   => 'acceptance-create-scheduled-post',
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
		'ok'                         => true,
		'plugin_version'             => SEO_GEO_MANAGER_VERSION,
		'post_create'                => true,
		'page_create'                => true,
		'idempotent_create'          => true,
		'slug_collision_guard'       => true,
		'publish_now'                => true,
		'schedule_future'            => true,
		'publication_stale_guard'    => true,
		'per_type_capability_guard'  => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
