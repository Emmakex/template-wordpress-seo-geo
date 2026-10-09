<?php
/**
 * Runtime acceptance for read-only permalink repair, redirect planning and legacy authority recovery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_permalink_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function seo_geo_manager_permalink_request( string $route ): array {
	$request  = new WP_REST_Request( 'GET', $route );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

function seo_geo_manager_permalink_post_request( string $route, array $payload ): array {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( (string) wp_json_encode( $payload ) );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

function seo_geo_manager_set_permalink_fixture( string $structure ): void {
	global $wpdb;
	$updated = $wpdb->update(
		$wpdb->options,
		array( 'option_value' => $structure ),
		array( 'option_name' => 'permalink_structure' ),
		array( '%s' ),
		array( '%s' )
	);
	seo_geo_manager_permalink_accept( false !== $updated, 'Could not persist permalink fixture.' );
	wp_cache_delete( 'permalink_structure', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

function seo_geo_manager_has_permalink_collision( array $plan, string $type ): bool {
	foreach ( (array) ( $plan['collisions'] ?? array() ) as $collision ) {
		if ( is_array( $collision ) && $type === (string) ( $collision['type'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

wp_set_current_user( 1 );
seo_geo_manager_permalink_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$original_home = (string) get_option( 'home', '' );
update_option( 'home', 'https://example.com/nuevaweb/' );
wp_cache_delete( 'home', 'options' );
wp_cache_delete( 'alloptions', 'options' );
seo_geo_manager_permalink_accept( 'https://example.com/nuevaweb/' === trailingslashit( home_url( '/' ) ), 'Could not simulate a clone home path.' );

$original = (string) get_option( 'permalink_structure', '' );
$token    = '{d276abfeceab40cca0e158fc6217176554b8e54a1f85b6eb004941797db52171}';
$broken   = '/' . $token . 'category' . $token . '/' . $token . 'postname' . $token . '/';
seo_geo_manager_set_permalink_fixture( $broken );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Corrupted permalink fixture was not stored byte-for-byte.' );

// Core install creates a published "Hello world" post. Remove pre-existing posts so
// the baseline proves one unique old source before the ambiguity fixture is added.
$existing_post_ids = get_posts(
	array(
		'post_type'   => 'post',
		'post_status' => 'any',
		'fields'      => 'ids',
		'numberposts' => -1,
	)
);
foreach ( (array) $existing_post_ids as $existing_post_id ) {
	wp_delete_post( (int) $existing_post_id, true );
}

$category = wp_insert_term( 'Permalink Plan Category', 'category', array( 'slug' => 'permalink-plan-category' ) );
seo_geo_manager_permalink_accept( ! is_wp_error( $category ), 'Could not create permalink-plan category.' );
$category_id = (int) ( $category['term_id'] ?? 0 );
$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Permalink Plan Post',
		'post_name'    => 'permalink-plan-post',
		'post_content' => '<p>Permalink redirect planning fixture.</p>',
	),
	true
);
seo_geo_manager_permalink_accept( ! is_wp_error( $post_id ) && 0 < (int) $post_id, 'Could not create permalink-plan post.' );
wp_set_post_categories( (int) $post_id, array( $category_id ), false );

$preview = seo_geo_manager_permalink_request( '/seo-geo-manager/v1/permalinks/preview' );
seo_geo_manager_permalink_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Permalink preview endpoint failed.' );
seo_geo_manager_permalink_accept( $broken === ( $preview['data']['current_structure'] ?? '' ), 'Permalink preview did not expose the exact current structure.' );
seo_geo_manager_permalink_accept( '/%category%/%postname%/' === ( $preview['data']['proposed_structure'] ?? '' ), 'Permalink preview did not restore known placeholder tokens.' );
seo_geo_manager_permalink_accept( 'syntactic-placeholder-recovery' === ( $preview['data']['candidate_kind'] ?? '' ), 'Permalink preview did not label the proposal as syntactic recovery.' );
seo_geo_manager_permalink_accept( false === ( $preview['data']['seo_authority_verified'] ?? true ), 'Syntactic recovery was incorrectly treated as historical SEO authority.' );
seo_geo_manager_permalink_accept( true === ( $preview['data']['safe_candidate'] ?? false ), 'Deterministic permalink repair candidate was not classified as syntactically safe.' );
seo_geo_manager_permalink_accept( true === ( $preview['data']['apply_blocked'] ?? false ), 'Permalink preview must never enable Apply in this microphase.' );
seo_geo_manager_permalink_accept( false === ( $preview['data']['write_performed'] ?? true ), 'Permalink preview unexpectedly reported a write.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Permalink preview mutated the WordPress option.' );

$plan = seo_geo_manager_permalink_request( '/seo-geo-manager/v1/permalinks/redirect-plan' );
seo_geo_manager_permalink_accept( 200 === $plan['status'] && is_array( $plan['data'] ), 'Permalink redirect-plan endpoint failed.' );
seo_geo_manager_permalink_accept( true === ( $plan['data']['complete_scan'] ?? false ), 'Redirect plan did not complete its bounded scan.' );
seo_geo_manager_permalink_accept( true === ( $plan['data']['safe_to_apply'] ?? false ), 'Collision-free redirect plan was not classified as safe.' );
seo_geo_manager_permalink_accept( 0 === (int) ( $plan['data']['collision_count'] ?? -1 ), 'Unexpected collision in baseline redirect plan.' );
seo_geo_manager_permalink_accept( 0 === (int) ( $plan['data']['ambiguous_old_source_count'] ?? -1 ), 'Baseline redirect plan incorrectly reported an ambiguous old source.' );
seo_geo_manager_permalink_accept( false === ( $plan['data']['requires_authoritative_legacy_urls'] ?? true ), 'Baseline redirect plan incorrectly required authoritative legacy URLs.' );
seo_geo_manager_permalink_accept( true === ( $plan['data']['apply_blocked'] ?? false ), 'Redirect planning must not enable Apply before the runtime/approval phase.' );
seo_geo_manager_permalink_accept( false === ( $plan['data']['write_performed'] ?? true ), 'Redirect plan unexpectedly reported a write.' );
seo_geo_manager_permalink_accept( '' !== (string) ( $plan['data']['plan_fingerprint'] ?? '' ), 'Redirect plan fingerprint missing.' );

$fixture_redirect = null;
foreach ( (array) ( $plan['data']['redirects'] ?? array() ) as $redirect ) {
	if ( is_array( $redirect ) && (int) ( $redirect['post_id'] ?? 0 ) === (int) $post_id ) {
		$fixture_redirect = $redirect;
		break;
	}
}
seo_geo_manager_permalink_accept( is_array( $fixture_redirect ), 'Redirect plan did not include the fixture post.' );
seo_geo_manager_permalink_accept( false !== strpos( rawurldecode( (string) $fixture_redirect['old_url'] ), $token . 'category' . $token ), 'Old URL did not preserve the malformed structure.' );
seo_geo_manager_permalink_accept( false !== strpos( (string) $fixture_redirect['new_url'], '/permalink-plan-category/permalink-plan-post/' ), 'New URL did not resolve the normalized category/postname structure.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Redirect planning mutated permalink_structure.' );

$second_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Second Permalink Plan Post',
		'post_name'    => 'second-permalink-plan-post',
		'post_content' => '<p>Second permalink redirect planning fixture.</p>',
	),
	true
);
seo_geo_manager_permalink_accept( ! is_wp_error( $second_post_id ) && 0 < (int) $second_post_id, 'Could not create second permalink-plan post.' );
wp_set_post_categories( (int) $second_post_id, array( $category_id ), false );

$ambiguous_plan = seo_geo_manager_permalink_request( '/seo-geo-manager/v1/permalinks/redirect-plan' );
seo_geo_manager_permalink_accept( 200 === $ambiguous_plan['status'] && is_array( $ambiguous_plan['data'] ), 'Ambiguous-source redirect-plan endpoint failed.' );
seo_geo_manager_permalink_accept( false === ( $ambiguous_plan['data']['safe_to_apply'] ?? true ), 'Redirect plan allowed multiple posts that share the same old source URL.' );
seo_geo_manager_permalink_accept( 0 < (int) ( $ambiguous_plan['data']['ambiguous_old_source_count'] ?? 0 ), 'Redirect plan did not count the ambiguous old source.' );
seo_geo_manager_permalink_accept( true === ( $ambiguous_plan['data']['requires_authoritative_legacy_urls'] ?? false ), 'Ambiguous old source did not require authoritative legacy URLs.' );
seo_geo_manager_permalink_accept( seo_geo_manager_has_permalink_collision( $ambiguous_plan['data'], 'duplicate-old-source' ), 'Redirect plan did not expose duplicate-old-source.' );
seo_geo_manager_permalink_accept( 'recover-authoritative-legacy-urls' === ( $ambiguous_plan['data']['next_action'] ?? '' ), 'Ambiguous-source plan exposed the wrong next action.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Ambiguous-source scan mutated permalink_structure.' );

$legacy_rows = array(
	array(
		'id'   => 901,
		'slug' => 'permalink-plan-post',
		'link' => 'https://example.com/blog/permalink-plan-post/',
	),
	array(
		'id'   => 902,
		'slug' => 'second-permalink-plan-post',
		'link' => 'https://example.com/blog/second-permalink-plan-post/',
	),
);
$legacy_http_filter = static function ( $preempt, $args, $url ) use ( $legacy_rows ) {
	if ( ! is_string( $url ) || 0 !== strpos( $url, 'https://example.com/wp-json/wp/v2/posts' ) ) {
		return $preempt;
	}
	return array(
		'headers'  => array( 'x-wp-totalpages' => '1', 'content-type' => 'application/json' ),
		'body'     => (string) wp_json_encode( $legacy_rows ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $legacy_http_filter, 10, 3 );
$authority = seo_geo_manager_permalink_post_request(
	'/seo-geo-manager/v1/permalinks/legacy-authority-preview',
	array( 'legacy_base_url' => 'https://example.com/' )
);
remove_filter( 'pre_http_request', $legacy_http_filter, 10 );
seo_geo_manager_permalink_accept( 200 === $authority['status'] && is_array( $authority['data'] ), 'Legacy authority preview endpoint failed.' );
seo_geo_manager_permalink_accept( true === ( $authority['data']['complete_scan'] ?? false ), 'Legacy authority scan was not complete.' );
seo_geo_manager_permalink_accept( 2 === (int) ( $authority['data']['matched_posts'] ?? 0 ), 'Legacy authority did not match both local posts.' );
seo_geo_manager_permalink_accept( true === ( $authority['data']['mapping_authoritative'] ?? false ), 'Legacy slug mapping was not classified as authoritative.' );
seo_geo_manager_permalink_accept( '/blog/%postname%/' === ( $authority['data']['inferred_structure'] ?? '' ), 'Legacy authority did not infer the historical /blog/%postname%/ structure.' );
seo_geo_manager_permalink_accept( true === ( $authority['data']['seo_authority_verified'] ?? false ), 'Historical structure was not verified after complete exact slug mapping.' );
seo_geo_manager_permalink_accept( true === ( $authority['data']['apply_blocked'] ?? false ), 'Legacy authority preview must remain read-only.' );
seo_geo_manager_permalink_accept( false === ( $authority['data']['write_performed'] ?? true ), 'Legacy authority preview unexpectedly reported a write.' );
seo_geo_manager_permalink_accept( '' !== (string) ( $authority['data']['authority_fingerprint'] ?? '' ), 'Legacy authority fingerprint missing.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Legacy authority preview mutated permalink_structure.' );

wp_delete_post( (int) $second_post_id, true );

$parent_page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Permalink Plan Category',
		'post_name'   => 'permalink-plan-category',
	),
	true
);
seo_geo_manager_permalink_accept( ! is_wp_error( $parent_page ) && 0 < (int) $parent_page, 'Could not create collision parent page.' );
$child_page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Permalink Plan Post',
		'post_name'   => 'permalink-plan-post',
		'post_parent' => (int) $parent_page,
	),
	true
);
seo_geo_manager_permalink_accept( ! is_wp_error( $child_page ) && 0 < (int) $child_page, 'Could not create collision child page.' );

$collision_plan = seo_geo_manager_permalink_request( '/seo-geo-manager/v1/permalinks/redirect-plan' );
seo_geo_manager_permalink_accept( 200 === $collision_plan['status'] && is_array( $collision_plan['data'] ), 'Collision redirect-plan endpoint failed.' );
seo_geo_manager_permalink_accept( false === ( $collision_plan['data']['safe_to_apply'] ?? true ), 'Redirect plan ignored a future-URL collision with an existing page.' );
seo_geo_manager_permalink_accept( 0 < (int) ( $collision_plan['data']['collision_count'] ?? 0 ), 'Redirect plan did not expose the expected collision.' );
seo_geo_manager_permalink_accept( seo_geo_manager_has_permalink_collision( $collision_plan['data'], 'target-collides-with-existing-public-resource' ), 'Expected future-target collision type was not exposed.' );
seo_geo_manager_permalink_accept( $broken === (string) get_option( 'permalink_structure', '' ), 'Collision scan mutated permalink_structure.' );

seo_geo_manager_set_permalink_fixture( $original );
update_option( 'home', $original_home );
wp_cache_delete( 'home', 'options' );
wp_cache_delete( 'alloptions', 'options' );

echo wp_json_encode(
	array(
		'ok'                         => true,
		'preview_only'               => true,
		'proposed_structure'         => $preview['data']['proposed_structure'] ?? '',
		'planned_redirects'          => $plan['data']['planned_redirects'] ?? 0,
		'collision_guard'            => true,
		'ambiguous_old_source_guard' => true,
		'legacy_authority_guard'     => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
