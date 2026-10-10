<?php
/**
 * Regression acceptance for historical authority recovery when migration markers
 * corrupt percent-encoded post_name values and historical paths use %category%.
 */

use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\LegacyPermalinkAuthority;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_corrupted_slug_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_corrupted_slug_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$target_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Corrupted Slug Authority Fixture',
		'post_name'    => 'cafe-historico',
		'post_content' => '<p>Corrupted slug authority regression fixture.</p>',
	),
	true
);
seo_geo_manager_corrupted_slug_accept( ! is_wp_error( $target_id ), 'Could not create corrupted-slug fixture post.' );
$target_id = (int) $target_id;

$clean_a_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Dynamic Category Alpha Fixture',
		'post_name'    => 'dynamic-category-alpha-fixture',
		'post_content' => '<p>Dynamic category alpha fixture.</p>',
	),
	true
);
seo_geo_manager_corrupted_slug_accept( ! is_wp_error( $clean_a_id ), 'Could not create dynamic category alpha fixture post.' );
$clean_a_id = (int) $clean_a_id;

$clean_b_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Dynamic Category Beta Fixture',
		'post_name'    => 'dynamic-category-beta-fixture',
		'post_content' => '<p>Dynamic category beta fixture.</p>',
	),
	true
);
seo_geo_manager_corrupted_slug_accept( ! is_wp_error( $clean_b_id ), 'Could not create dynamic category beta fixture post.' );
$clean_b_id = (int) $clean_b_id;

$alpha_id = wp_create_category( 'Dynamic Alpha' );
$beta_id  = wp_create_category( 'Dynamic Beta' );
seo_geo_manager_corrupted_slug_accept( 0 < (int) $alpha_id && 0 < (int) $beta_id, 'Could not create dynamic permalink fixture categories.' );

$marker         = '{d276abfeceab40cca0e158fc6217176554b8e54a1f85b6eb004941797db52171}';
$corrupted_slug = 'caf' . $marker . 'c3' . $marker . 'a9';

/*
 * Simulate the imported database directly. Normal wp_insert_post() sanitization
 * would remove the braces, while the field failure exists precisely because the
 * migration touched already-stored percent-encoded post_name values.
 */
global $wpdb;
$updated = $wpdb->update(
	$wpdb->posts,
	array( 'post_name' => $corrupted_slug ),
	array( 'ID' => $target_id ),
	array( '%s' ),
	array( '%d' )
);
seo_geo_manager_corrupted_slug_accept( false !== $updated, 'Could not inject migration-corrupted post_name fixture.' );
clean_post_cache( $target_id );
seo_geo_manager_corrupted_slug_accept( $corrupted_slug === (string) get_post_field( 'post_name', $target_id ), 'Corrupted post_name fixture was not stored exactly.' );

$published_posts = get_posts(
	array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	)
);
seo_geo_manager_corrupted_slug_accept( 3 <= count( $published_posts ), 'Historical fixture does not have enough published posts for dynamic category validation.' );

$category_by_post = array();
$index            = 0;
foreach ( $published_posts as $post ) {
	if ( ! $post instanceof WP_Post ) {
		continue;
	}
	$category_id = 0 === $index % 2 ? (int) $alpha_id : (int) $beta_id;
	$result      = wp_set_post_categories( (int) $post->ID, array( $category_id ), false );
	seo_geo_manager_corrupted_slug_accept( ! is_wp_error( $result ), 'Could not assign fixture category to published post.' );
	$category_by_post[ (int) $post->ID ] = $category_id;
	++$index;
}

$malformed_structure = '/' . $marker . 'category' . $marker . '/' . $marker . 'postname' . $marker . '/';
update_option( 'permalink_structure', $malformed_structure );
$before_structure = (string) get_option( 'permalink_structure', '' );
seo_geo_manager_corrupted_slug_accept( $malformed_structure === $before_structure, 'Could not prepare malformed dynamic permalink fixture.' );

$legacy_base = trailingslashit( home_url( '/legacy-source-corrupted/' ) );
$legacy_rows = array();
foreach ( $published_posts as $post ) {
	if ( ! $post instanceof WP_Post ) {
		continue;
	}

	$post_id         = (int) $post->ID;
	$historical_slug = $post_id === $target_id ? 'cafe-historico' : (string) $post->post_name;
	$category_id     = (int) ( $category_by_post[ $post_id ] ?? 0 );
	$category        = 0 < $category_id ? get_category( $category_id ) : null;
	seo_geo_manager_corrupted_slug_accept( $category instanceof WP_Term, 'Could not resolve fixture category.' );
	$category_slug = (string) $category->slug;
	$legacy_rows[] = array(
		'id'   => $post_id,
		'slug' => $historical_slug,
		'link' => $legacy_base . rawurlencode( $category_slug ) . '/' . rawurlencode( $historical_slug ) . '/',
	);
}

add_filter(
	'pre_http_request',
	static function ( $preempt, $parsed_args, $url ) use ( $legacy_base, $legacy_rows ) {
		unset( $parsed_args );
		$endpoint = trailingslashit( $legacy_base ) . 'wp-json/wp/v2/posts';
		if ( ! is_string( $url ) || 0 !== strpos( $url, $endpoint ) ) {
			return $preempt;
		}

		return array(
			'headers'  => array(
				'x-wp-total'      => (string) count( $legacy_rows ),
				'x-wp-totalpages' => '1',
				'content-type'    => 'application/json; charset=UTF-8',
			),
			'body'     => (string) wp_json_encode( $legacy_rows ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

$authority = LegacyPermalinkAuthority::preview( $legacy_base );

seo_geo_manager_corrupted_slug_accept( false === ( $authority['write_performed'] ?? true ), 'Historical authority unexpectedly wrote state.' );
seo_geo_manager_corrupted_slug_accept( count( $legacy_rows ) === (int) ( $authority['matched_posts'] ?? -1 ), 'Historical authority did not recover every published post.' );
seo_geo_manager_corrupted_slug_accept( 0 === (int) ( $authority['missing_count'] ?? -1 ), 'Corrupted slug remained missing after safe ID fallback.' );
seo_geo_manager_corrupted_slug_accept( 1 === (int) ( $authority['recovered_by_id_count'] ?? 0 ), 'Exactly one corrupted slug should have used ID fallback.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['mapping_authoritative'] ?? false ), 'Recovered mapping was not authoritative.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['seo_authority_verified'] ?? false ), 'Dynamic historical authority was not SEO verified.' );
seo_geo_manager_corrupted_slug_accept( '/%category%/%postname%/' === ( $authority['inferred_structure'] ?? '' ), 'Dynamic historical structure was not inferred from the safe syntactic candidate.' );
seo_geo_manager_corrupted_slug_accept( 'syntactic-candidate-rendered-match' === ( $authority['structure_validation_method'] ?? '' ), 'Dynamic structure validation method was not recorded.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['structure_validation']['verified'] ?? false ), 'Dynamic structure validation did not verify.' );
seo_geo_manager_corrupted_slug_accept( count( $legacy_rows ) - 1 === (int) ( $authority['structure_validation']['eligible_posts'] ?? -1 ), 'Dynamic structure eligible-post count is incorrect.' );
seo_geo_manager_corrupted_slug_accept( count( $legacy_rows ) - 1 === (int) ( $authority['structure_validation']['matched_posts'] ?? -1 ), 'Dynamic structure did not match every clean post.' );
seo_geo_manager_corrupted_slug_accept( 1 === (int) ( $authority['structure_validation']['skipped_corrupted_posts'] ?? 0 ), 'Corrupted slug was not excluded from dynamic structure proof.' );
seo_geo_manager_corrupted_slug_accept( 0 === (int) ( $authority['structure_validation']['mismatch_count'] ?? -1 ), 'Dynamic structure proof contains unexpected mismatches.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['policy']['corrupted_slug_id_fallback_only'] ?? false ), 'Corrupted-slug-only fallback policy missing.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['policy']['clean_slug_id_fallback_forbidden'] ?? false ), 'Clean-slug fallback prohibition policy missing.' );
seo_geo_manager_corrupted_slug_accept( true === ( $authority['policy']['dynamic_structure_rendered_match'] ?? false ), 'Dynamic rendered-match policy missing.' );

$recovered_rows = array_values(
	array_filter(
		(array) ( $authority['rows'] ?? array() ),
		static fn ( $row ): bool => is_array( $row ) && $target_id === (int) ( $row['post_id'] ?? 0 )
	)
);
seo_geo_manager_corrupted_slug_accept( 1 === count( $recovered_rows ), 'Recovered target row was not unique.' );
seo_geo_manager_corrupted_slug_accept( 'corrupted-slug-id-fallback' === ( $recovered_rows[0]['match_method'] ?? '' ), 'Recovered row did not record the guarded match method.' );
seo_geo_manager_corrupted_slug_accept( 'cafe-historico' === ( $recovered_rows[0]['historical_slug'] ?? '' ), 'Recovered row lost the historical slug.' );
seo_geo_manager_corrupted_slug_accept( $corrupted_slug === ( $recovered_rows[0]['slug'] ?? '' ), 'Recovered row did not preserve the current local slug evidence.' );

$plan = AuthoritativePermalinkPlanner::preview( $legacy_base, (string) ( $authority['authority_fingerprint'] ?? '' ) );
seo_geo_manager_corrupted_slug_accept( false === ( $plan['write_performed'] ?? true ), 'Authoritative planner unexpectedly wrote state.' );
seo_geo_manager_corrupted_slug_accept( false === ( $plan['safe_structure_candidate'] ?? true ), 'Planner incorrectly approved a structure while local post_name is corrupted.' );
seo_geo_manager_corrupted_slug_accept( false === ( $plan['apply_available'] ?? true ), 'Planner incorrectly enabled Apply while local post_name is corrupted.' );
seo_geo_manager_corrupted_slug_accept( 1 === (int) ( $plan['local_slug_repair_count'] ?? 0 ), 'Planner did not surface exactly one local slug repair.' );
seo_geo_manager_corrupted_slug_accept( 'repair-corrupted-local-slugs-first' === ( $plan['next_action'] ?? '' ), 'Planner did not route to protected slug repair first.' );
seo_geo_manager_corrupted_slug_accept( 'blocked-local-slug-repair' === ( $plan['seo_preservation_mode'] ?? '' ), 'Planner preservation mode did not reflect local slug repair block.' );

$repair_collisions = array_values(
	array_filter(
		(array) ( $plan['collisions'] ?? array() ),
		static fn ( $row ): bool => is_array( $row ) && 'corrupted-local-slug-requires-repair' === ( $row['type'] ?? '' )
	)
);
seo_geo_manager_corrupted_slug_accept( 1 === count( $repair_collisions ), 'Planner did not expose the corrupted local slug collision.' );
seo_geo_manager_corrupted_slug_accept( $corrupted_slug === (string) get_post_field( 'post_name', $target_id ), 'Read-only previews changed the corrupted local slug.' );
seo_geo_manager_corrupted_slug_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Read-only previews changed permalink_structure.' );

/* A normal clean mismatch must never be rescued merely because the IDs match. */
$clean_wrong_slug = 'clean-but-not-historical';
$updated          = $wpdb->update(
	$wpdb->posts,
	array( 'post_name' => $clean_wrong_slug ),
	array( 'ID' => $target_id ),
	array( '%s' ),
	array( '%d' )
);
seo_geo_manager_corrupted_slug_accept( false !== $updated, 'Could not prepare clean mismatch guard fixture.' );
clean_post_cache( $target_id );

$clean_mismatch = LegacyPermalinkAuthority::preview( $legacy_base );
seo_geo_manager_corrupted_slug_accept( 0 === (int) ( $clean_mismatch['recovered_by_id_count'] ?? -1 ), 'Clean slug mismatch incorrectly used ID fallback.' );
seo_geo_manager_corrupted_slug_accept( 1 === (int) ( $clean_mismatch['missing_count'] ?? 0 ), 'Clean slug mismatch was not left unresolved.' );
seo_geo_manager_corrupted_slug_accept( false === ( $clean_mismatch['seo_authority_verified'] ?? true ), 'Clean slug mismatch incorrectly verified SEO authority.' );
seo_geo_manager_corrupted_slug_accept( $clean_wrong_slug === (string) get_post_field( 'post_name', $target_id ), 'Clean mismatch preview changed local state.' );
seo_geo_manager_corrupted_slug_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Clean mismatch preview changed permalink_structure.' );

echo wp_json_encode(
	array(
		'ok'                                  => true,
		'corrupted_slug_id_fallback'          => true,
		'clean_slug_id_fallback_blocked'      => true,
		'dynamic_category_structure_verified' => true,
		'planner_requires_slug_repair'        => true,
		'read_only'                           => true,
	)
);
