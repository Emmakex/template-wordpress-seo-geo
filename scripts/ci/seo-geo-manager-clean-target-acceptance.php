<?php
/**
 * Regression acceptance for the SEO/GEO migration principle: historical URLs are
 * preservation authority, but a strongly dominant clean architecture can become
 * the target while bounded outliers receive one-hop 301 redirects.
 */

use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\LegacyPermalinkAuthority;
use SeoGeo\Manager\Support\SeoMigrationTargetPlanner;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_clean_target_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_clean_target_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

/* Ensure the corpus is large enough for the dominant-target threshold. */
$published_ids = get_posts(
	array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'fields'           => 'ids',
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	)
);
$published_ids = array_values( array_map( 'intval', is_array( $published_ids ) ? $published_ids : array() ) );

for ( $i = count( $published_ids ); $i < 30; ++$i ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Clean Target Fixture ' . $i,
			'post_name'    => 'clean-target-fixture-' . $i,
			'post_content' => '<p>SEO/GEO clean target fixture.</p>',
		),
		true
	);
	seo_geo_manager_clean_target_accept( ! is_wp_error( $id ), 'Could not create clean-target fixture post.' );
	$published_ids[] = (int) $id;
}

$published_ids = array_values( array_unique( array_map( 'intval', $published_ids ) ) );
sort( $published_ids, SORT_NUMERIC );
seo_geo_manager_clean_target_accept( 20 <= count( $published_ids ), 'Clean-target corpus is too small.' );

$exception_a = $published_ids[ count( $published_ids ) - 2 ];
$exception_b = $published_ids[ count( $published_ids ) - 1 ];
$legacy_base = trailingslashit( home_url( '/legacy-source-clean-target/' ) );
$legacy_rows = array();

foreach ( $published_ids as $post_id ) {
	$post = get_post( $post_id );
	seo_geo_manager_clean_target_accept( $post instanceof WP_Post, 'Could not load clean-target fixture post.' );
	$slug = (string) $post->post_name;
	seo_geo_manager_clean_target_accept( '' !== $slug, 'Clean-target fixture contains an empty slug.' );

	$prefix = 'blog';
	if ( $post_id === $exception_a ) {
		$prefix = 'uncategorized';
	} elseif ( $post_id === $exception_b ) {
		$prefix = 'legacy-topic';
	}

	$legacy_rows[] = array(
		'id'   => $post_id,
		'slug' => $slug,
		'link' => $legacy_base . $prefix . '/' . rawurlencode( $slug ) . '/',
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

$before_structure = (string) get_option( 'permalink_structure', '' );
$authority        = LegacyPermalinkAuthority::preview( $legacy_base );
seo_geo_manager_clean_target_accept( false === ( $authority['write_performed'] ?? true ), 'Historical authority unexpectedly wrote state.' );
seo_geo_manager_clean_target_accept( true === ( $authority['mapping_authoritative'] ?? false ), 'Historical URL map is not authoritative.' );
seo_geo_manager_clean_target_accept( count( $legacy_rows ) === (int) ( $authority['matched_posts'] ?? -1 ), 'Historical URL map did not match every post.' );
seo_geo_manager_clean_target_accept( 0 === (int) ( $authority['missing_count'] ?? -1 ), 'Historical URL map contains missing posts.' );

$target = SeoMigrationTargetPlanner::preview( $authority );
seo_geo_manager_clean_target_accept( false === ( $target['write_performed'] ?? true ), 'Clean-target planner unexpectedly wrote state.' );
seo_geo_manager_clean_target_accept( true === ( $target['verified_for_migration'] ?? false ), 'Dominant clean migration target was not verified.' );
seo_geo_manager_clean_target_accept( '/blog/%postname%/' === ( $target['candidate'] ?? '' ), 'Clean-target planner did not choose /blog/%postname%/.' );
seo_geo_manager_clean_target_accept( 'dominant-stable-literal-post-prefix' === ( $target['basis'] ?? '' ), 'Clean-target basis changed unexpectedly.' );
seo_geo_manager_clean_target_accept( count( $legacy_rows ) - 2 === (int) ( $target['support_count'] ?? -1 ), 'Clean-target support count is incorrect.' );
seo_geo_manager_clean_target_accept( 2 === (int) ( $target['outlier_count'] ?? -1 ), 'Clean-target outlier count is incorrect.' );
seo_geo_manager_clean_target_accept( 2 === (int) ( $target['minimum_redirects_from_outliers'] ?? -1 ), 'Redirect lower bound is incorrect.' );
seo_geo_manager_clean_target_accept( true === ( $target['policy']['legacy_architecture_is_not_mandatory'] ?? false ), 'Legacy-architecture policy is missing.' );
seo_geo_manager_clean_target_accept( true === ( $target['policy']['one_hop_301_for_outliers'] ?? false ), 'One-hop redirect policy is missing.' );

$plan = AuthoritativePermalinkPlanner::preview( $legacy_base, (string) ( $authority['authority_fingerprint'] ?? '' ) );
seo_geo_manager_clean_target_accept( false === ( $plan['write_performed'] ?? true ), 'Clean-target authoritative planner unexpectedly wrote state.' );
seo_geo_manager_clean_target_accept( 'seo-geo-clean-target' === ( $plan['target_strategy'] ?? '' ), 'Planner did not select SEO/GEO clean-target strategy.' );
seo_geo_manager_clean_target_accept( '/blog/%postname%/' === ( $plan['target_structure'] ?? '' ), 'Planner target structure is not /blog/%postname%/.' );
seo_geo_manager_clean_target_accept( true === ( $plan['safe_structure_candidate'] ?? false ), 'Clean target was not considered structurally safe.' );
seo_geo_manager_clean_target_accept( true === ( $plan['requires_redirect_runtime'] ?? false ), 'Clean target did not require redirect runtime for historical outliers.' );
seo_geo_manager_clean_target_accept( false === ( $plan['apply_available'] ?? true ), 'Direct Apply should remain unavailable when redirects are required.' );
seo_geo_manager_clean_target_accept( 2 === (int) ( $plan['planned_redirects'] ?? -1 ), 'Planner did not produce exactly two bounded redirects.' );
seo_geo_manager_clean_target_accept( count( $legacy_rows ) - 2 === (int) ( $plan['path_preservation_count'] ?? -1 ), 'Planner did not preserve the dominant historical paths.' );
seo_geo_manager_clean_target_accept( 0 === (int) ( $plan['local_slug_repair_count'] ?? -1 ), 'Unexpected slug repair appeared in the clean-target fixture.' );
seo_geo_manager_clean_target_accept( 0 === (int) ( $plan['collision_count'] ?? -1 ), 'Unexpected collision appeared in the clean-target fixture.' );
seo_geo_manager_clean_target_accept( 'one-hop-301-to-clean-target' === ( $plan['seo_preservation_mode'] ?? '' ), 'SEO preservation mode did not record one-hop clean-target redirects.' );
seo_geo_manager_clean_target_accept( 'preview-atomic-permalink-apply' === ( $plan['next_action'] ?? '' ), 'Planner did not route the clean target to atomic preview.' );

$redirect_sources = array_column( (array) ( $plan['redirects'] ?? array() ), 'source_path' );
seo_geo_manager_clean_target_accept( in_array( '/uncategorized/' . get_post_field( 'post_name', $exception_a ) . '/', $redirect_sources, true ), 'Uncategorized historical outlier redirect is missing.' );
seo_geo_manager_clean_target_accept( in_array( '/legacy-topic/' . get_post_field( 'post_name', $exception_b ) . '/', $redirect_sources, true ), 'Legacy-topic historical outlier redirect is missing.' );
seo_geo_manager_clean_target_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Read-only clean-target planning changed permalink_structure.' );

echo wp_json_encode(
	array(
		'ok'                              => true,
		'historical_map_authoritative'    => true,
		'clean_target_verified'           => true,
		'legacy_architecture_not_required'=> true,
		'dominant_paths_preserved'        => true,
		'outliers_use_one_hop_301'        => true,
		'read_only'                       => true,
	)
);
