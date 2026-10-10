<?php
/**
 * Runtime acceptance for protected migration-corrupted post slug repair.
 */

use SeoGeo\Manager\Changes\SlugRepairChangeEngine;
use SeoGeo\Manager\Support\AuthoritativePermalinkPlanner;
use SeoGeo\Manager\Support\LegacyPermalinkAuthority;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_slug_repair_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_slug_repair_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$target_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Protected Slug Repair Fixture',
		'post_name'    => 'cafe-historico',
		'post_content' => '<p>Protected slug repair regression fixture.</p>',
	),
	true
);
seo_geo_manager_slug_repair_accept( ! is_wp_error( $target_id ), 'Could not create protected slug-repair fixture.' );
$target_id = (int) $target_id;

$second_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Protected Slug Repair Clean Fixture',
		'post_name'    => 'slug-repair-clean-fixture',
		'post_content' => '<p>Clean slug repair fixture.</p>',
	),
	true
);
seo_geo_manager_slug_repair_accept( ! is_wp_error( $second_id ), 'Could not create clean protected slug-repair fixture.' );

$marker         = '{d276abfeceab40cca0e158fc6217176554b8e54a1f85b6eb004941797db52171}';
$corrupted_slug = 'caf' . $marker . 'c3' . $marker . 'a9';
$malformed      = '/blog/' . $marker . 'postname' . $marker . '/';

global $wpdb;
$updated = $wpdb->update(
	$wpdb->posts,
	array( 'post_name' => $corrupted_slug ),
	array( 'ID' => $target_id ),
	array( '%s' ),
	array( '%d' )
);
seo_geo_manager_slug_repair_accept( false !== $updated, 'Could not inject migration-corrupted post_name.' );
clean_post_cache( $target_id );

$updated = $wpdb->update(
	$wpdb->options,
	array( 'option_value' => $malformed ),
	array( 'option_name' => 'permalink_structure' ),
	array( '%s' ),
	array( '%s' )
);
seo_geo_manager_slug_repair_accept( false !== $updated, 'Could not inject malformed permalink structure.' );
wp_cache_delete( 'permalink_structure', 'options' );
wp_cache_delete( 'alloptions', 'options' );
seo_geo_manager_slug_repair_accept( $malformed === (string) get_option( 'permalink_structure', '' ), 'Malformed permalink fixture was not stored exactly.' );

$published = get_posts(
	array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	)
);
seo_geo_manager_slug_repair_accept( 2 <= count( $published ), 'Slug-repair fixture needs at least two published posts.' );

$legacy_base = trailingslashit( home_url( '/legacy-slug-repair/' ) );
$legacy_rows = array();
foreach ( $published as $post ) {
	if ( ! $post instanceof WP_Post ) {
		continue;
	}
	$post_id         = (int) $post->ID;
	$historical_slug = $target_id === $post_id ? 'cafe-historico' : (string) $post->post_name;
	$legacy_rows[]   = array(
		'id'   => $post_id,
		'slug' => $historical_slug,
		'link' => $legacy_base . 'blog/' . rawurlencode( $historical_slug ) . '/',
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
seo_geo_manager_slug_repair_accept( true === ( $authority['mapping_authoritative'] ?? false ), 'Historical mapping did not verify before slug repair.' );
seo_geo_manager_slug_repair_accept( 1 === (int) ( $authority['recovered_by_id_count'] ?? 0 ), 'Corrupted fixture was not recovered by guarded ID fallback.' );

$plan = AuthoritativePermalinkPlanner::preview( $legacy_base, (string) ( $authority['authority_fingerprint'] ?? '' ) );
seo_geo_manager_slug_repair_accept( 1 === (int) ( $plan['local_slug_repair_count'] ?? 0 ), 'Authoritative plan did not expose one protected slug repair.' );
seo_geo_manager_slug_repair_accept( 'repair-corrupted-local-slugs-first' === ( $plan['next_action'] ?? '' ), 'Authoritative plan did not block on protected slug repair.' );

$before_structure = (string) get_option( 'permalink_structure', '' );
$preview = SlugRepairChangeEngine::preview(
	$legacy_base,
	(string) ( $authority['authority_fingerprint'] ?? '' ),
	(string) ( $plan['plan_fingerprint'] ?? '' )
);
seo_geo_manager_slug_repair_accept( false === ( $preview['write_performed'] ?? true ), 'Slug-repair preview unexpectedly wrote state.' );
seo_geo_manager_slug_repair_accept( true === ( $preview['safe_to_apply'] ?? false ), 'Slug-repair preview was not safe to apply.' );
seo_geo_manager_slug_repair_accept( 1 === (int) ( $preview['repair_count'] ?? 0 ), 'Slug-repair preview did not contain exactly one repair.' );
seo_geo_manager_slug_repair_accept( $corrupted_slug === (string) get_post_field( 'post_name', $target_id ), 'Preview changed the local slug.' );
seo_geo_manager_slug_repair_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Preview changed permalink_structure.' );

$payload = array(
	'legacy_base_url'       => $legacy_base,
	'authority_fingerprint' => (string) ( $authority['authority_fingerprint'] ?? '' ),
	'plan_fingerprint'      => (string) ( $plan['plan_fingerprint'] ?? '' ),
	'repair_fingerprint'    => (string) ( $preview['repair_fingerprint'] ?? '' ),
	'idempotency_key'       => 'slug-repair-acceptance-' . $target_id,
	'confirm_slug_repair'   => true,
);
$operation = SlugRepairChangeEngine::apply( $payload );
seo_geo_manager_slug_repair_accept( ! is_wp_error( $operation ), 'Protected slug repair Apply failed.' );
seo_geo_manager_slug_repair_accept( 'post-slug-repair' === ( $operation['operation_type'] ?? '' ), 'Slug repair operation type is incorrect.' );
seo_geo_manager_slug_repair_accept( 'applied' === ( $operation['status'] ?? '' ), 'Slug repair operation did not reach applied state.' );
seo_geo_manager_slug_repair_accept( 'cafe-historico' === (string) get_post_field( 'post_name', $target_id ), 'Protected slug repair did not persist the verified historical slug.' );
seo_geo_manager_slug_repair_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Slug repair Apply changed permalink_structure.' );
seo_geo_manager_slug_repair_accept( false === ( $operation['permalink_structure_changed'] ?? true ), 'Slug repair operation incorrectly reported a permalink structure write.' );
seo_geo_manager_slug_repair_accept( false === ( $operation['redirect_runtime_changed'] ?? true ), 'Slug repair operation incorrectly reported a redirect-runtime write.' );

$replay = SlugRepairChangeEngine::apply( $payload );
seo_geo_manager_slug_repair_accept( ! is_wp_error( $replay ), 'Slug repair idempotent replay failed.' );
seo_geo_manager_slug_repair_accept( true === ( $replay['idempotent_replay'] ?? false ), 'Slug repair idempotent replay was not identified.' );

$fresh_plan = AuthoritativePermalinkPlanner::preview( $legacy_base );
seo_geo_manager_slug_repair_accept( 0 === (int) ( $fresh_plan['local_slug_repair_count'] ?? -1 ), 'Fresh plan still contains local slug repairs after Apply.' );
seo_geo_manager_slug_repair_accept( '/blog/%postname%/' === (string) ( $fresh_plan['target_structure'] ?? '' ), 'Target structure changed after protected slug repair.' );

$intervening = wp_update_post(
	array(
		'ID'        => $target_id,
		'post_name' => 'intervening-slug-change',
	),
	true
);
seo_geo_manager_slug_repair_accept( ! is_wp_error( $intervening ), 'Could not prepare stale rollback guard.' );
$stale = SlugRepairChangeEngine::rollback( (string) ( $operation['operation_id'] ?? '' ) );
seo_geo_manager_slug_repair_accept( is_wp_error( $stale ), 'Slug repair rollback did not reject an intervening slug change.' );
seo_geo_manager_slug_repair_accept( 'seo_geo_manager_slug_repair_rollback_stale' === $stale->get_error_code(), 'Slug repair stale rollback returned the wrong error.' );

$restored_target = wp_update_post(
	array(
		'ID'        => $target_id,
		'post_name' => 'cafe-historico',
	),
	true
);
seo_geo_manager_slug_repair_accept( ! is_wp_error( $restored_target ), 'Could not restore post-repair state for rollback acceptance.' );

$rollback = SlugRepairChangeEngine::rollback( (string) ( $operation['operation_id'] ?? '' ) );
seo_geo_manager_slug_repair_accept( ! is_wp_error( $rollback ), 'Protected slug repair rollback failed.' );
seo_geo_manager_slug_repair_accept( 'rolled-back' === ( $rollback['status'] ?? '' ), 'Slug repair rollback did not reach rolled-back state.' );
seo_geo_manager_slug_repair_accept( true === ( $rollback['rollback_verified'] ?? false ), 'Slug repair rollback was not verified.' );
seo_geo_manager_slug_repair_accept( $corrupted_slug === (string) get_post_field( 'post_name', $target_id ), 'Rollback did not restore the exact migration-corrupted slug.' );
seo_geo_manager_slug_repair_accept( $before_structure === (string) get_option( 'permalink_structure', '' ), 'Rollback changed permalink_structure.' );

$rolled_back_authority = LegacyPermalinkAuthority::preview( $legacy_base );
seo_geo_manager_slug_repair_accept( 1 === (int) ( $rolled_back_authority['recovered_by_id_count'] ?? 0 ), 'Rollback did not restore the original authority recovery state.' );

echo wp_json_encode(
	array(
		'ok'                          => true,
		'slug_repair_preview'         => true,
		'slug_repair_apply'           => true,
		'slug_repair_idempotent'      => true,
		'slug_repair_stale_guard'     => true,
		'slug_repair_rollback'        => true,
		'permalink_structure_untouched'=> true,
		'redirect_runtime_untouched'  => true,
	)
);
