<?php
/**
 * Runtime acceptance for SEO output authority and bounded native metadata writes.
 *
 * Executed inside the isolated Manager WordPress fixture.
 */

use SeoGeo\Core\Seo\NativeSeoMetadataWriter;
use SeoGeo\Manager\Changes\NativeSeoMetadataChangeAdapter;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_authority_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$admin = get_user_by( 'login', 'admin' );
seo_geo_manager_authority_accept( $admin instanceof WP_User, 'Acceptance administrator was not found.' );
wp_set_current_user( (int) $admin->ID );

$original_plugins = get_option( 'active_plugins', array() );
$original_plugins = is_array( $original_plugins ) ? $original_plugins : array();

update_option( 'active_plugins', array() );
$theme = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( true === ( $theme['authority_resolved'] ?? false ), 'Theme-native SEO authority was not resolved.' );
seo_geo_manager_authority_accept( 'resolved-theme-native-writable' === ( $theme['state'] ?? '' ), 'Unexpected Theme-native SEO authority state.' );
seo_geo_manager_authority_accept( 'seo-geo-theme' === ( $theme['authority']['id'] ?? '' ), 'Theme-native authority identity mismatch.' );
seo_geo_manager_authority_accept( true === ( $theme['safe_to_write_seo_metadata'] ?? false ), 'Theme-native resolver did not enable the verified native writer.' );
seo_geo_manager_authority_accept( true === ( $theme['outputs']['title-meta']['writable'] ?? false ), 'Native title/description signal is not writable.' );
seo_geo_manager_authority_accept( true === ( $theme['outputs']['canonical']['writable'] ?? false ), 'Native canonical signal is not writable.' );
seo_geo_manager_authority_accept( true === ( $theme['outputs']['robots']['writable'] ?? false ), 'Native robots/indexability signal is not writable.' );
foreach ( array( 'open-graph', 'schema', 'hreflang', 'sitemap' ) as $read_only_signal ) {
	seo_geo_manager_authority_accept(
		false === ( $theme['outputs'][ $read_only_signal ]['writable'] ?? true ),
		'Unverified Theme SEO output was incorrectly exposed as writable: ' . $read_only_signal
	);
}
seo_geo_manager_authority_accept( class_exists( NativeSeoMetadataWriter::class ), 'Native SEO metadata writer is unavailable in the self-contained Theme.' );

$post_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Native SEO acceptance',
	),
	true
);
seo_geo_manager_authority_accept( ! is_wp_error( $post_id ) && 0 < $post_id, 'Could not create native SEO acceptance page.' );
$post_id = (int) $post_id;

$inspection = NativeSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_authority_accept( ! is_wp_error( $inspection ), 'Native SEO inspection failed.' );
$before_fingerprint = isset( $inspection['fingerprint'] ) && is_string( $inspection['fingerprint'] ) ? $inspection['fingerprint'] : '';
seo_geo_manager_authority_accept( '' !== $before_fingerprint, 'Native SEO inspection did not return a fingerprint.' );

$values = array(
	'title'        => 'Native SEO controlled title',
	'description'  => 'Bounded SEO metadata acceptance description.',
	'canonical'    => home_url( '/native-seo-acceptance/' ),
	'indexability' => 'noindex-follow',
);
$payload = array(
	'resource_id'          => $post_id,
	'values'               => $values,
	'expected_fingerprint' => $before_fingerprint,
	'idempotency_key'      => 'native-seo-acceptance-' . $post_id,
);

$preview = NativeSeoMetadataChangeAdapter::preview( $payload );
seo_geo_manager_authority_accept( ! is_wp_error( $preview ), 'Native SEO Preview failed.' );
seo_geo_manager_authority_accept( true === ( $preview['has_changes'] ?? false ), 'Native SEO Preview did not expose a change.' );
seo_geo_manager_authority_accept( $values === ( $preview['change']['after'] ?? null ), 'Native SEO Preview changed the requested normalized state.' );

$applied = NativeSeoMetadataChangeAdapter::apply( $payload );
seo_geo_manager_authority_accept( ! is_wp_error( $applied ), 'Native SEO Apply failed.' );
seo_geo_manager_authority_accept( 'applied' === ( $applied['status'] ?? '' ), 'Native SEO Apply did not persist applied operation state.' );
$operation_id = isset( $applied['operation_id'] ) && is_string( $applied['operation_id'] ) ? $applied['operation_id'] : '';
seo_geo_manager_authority_accept( '' !== $operation_id, 'Native SEO Apply did not return an operation ID.' );

$writer      = new NativeSeoMetadataWriter();
$after_state = $writer->inspect( $post_id );
seo_geo_manager_authority_accept( ! is_wp_error( $after_state ), 'Native SEO readback failed after Apply.' );
seo_geo_manager_authority_accept( $values === ( $after_state['values'] ?? null ), 'Native SEO Apply did not persist exact values.' );
seo_geo_manager_authority_accept(
	( $applied['after_fingerprint'] ?? '' ) === ( $after_state['fingerprint'] ?? null ),
	'Native SEO Apply fingerprint does not match exact readback.'
);

$replay = NativeSeoMetadataChangeAdapter::apply( $payload );
seo_geo_manager_authority_accept( ! is_wp_error( $replay ), 'Native SEO idempotent replay failed.' );
seo_geo_manager_authority_accept( true === ( $replay['idempotent_replay'] ?? false ), 'Native SEO idempotent replay was not identified.' );
seo_geo_manager_authority_accept( $operation_id === ( $replay['operation_id'] ?? '' ), 'Native SEO idempotent replay returned a different operation.' );

$rollback = NativeSeoMetadataChangeAdapter::rollback( $operation_id );
seo_geo_manager_authority_accept( ! is_wp_error( $rollback ), 'Native SEO rollback failed.' );
seo_geo_manager_authority_accept( 'rolled-back' === ( $rollback['status'] ?? '' ), 'Native SEO rollback did not persist rolled-back state.' );
$restored = $writer->inspect( $post_id );
seo_geo_manager_authority_accept( ! is_wp_error( $restored ), 'Native SEO readback failed after rollback.' );
seo_geo_manager_authority_accept(
	array(
		'title'        => null,
		'description'  => null,
		'canonical'    => null,
		'indexability' => null,
	) === ( $restored['values'] ?? null ),
	'Native SEO rollback did not restore the exact initial state.'
);
seo_geo_manager_authority_accept( $before_fingerprint === ( $restored['fingerprint'] ?? '' ), 'Native SEO rollback did not restore the initial fingerprint.' );

$published_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Native SEO published acceptance',
	),
	true
);
seo_geo_manager_authority_accept( ! is_wp_error( $published_id ) && 0 < $published_id, 'Could not create published native SEO acceptance page.' );
$published_id         = (int) $published_id;
$published_inspection = NativeSeoMetadataChangeAdapter::inspect( $published_id );
seo_geo_manager_authority_accept( ! is_wp_error( $published_inspection ), 'Published native SEO inspection failed.' );
$published_payload = array(
	'resource_id'          => $published_id,
	'values'               => $values,
	'expected_fingerprint' => (string) ( $published_inspection['fingerprint'] ?? '' ),
	'idempotency_key'      => 'native-seo-published-acceptance-' . $published_id,
);
$published_block = NativeSeoMetadataChangeAdapter::apply( $published_payload );
seo_geo_manager_authority_accept( is_wp_error( $published_block ), 'Published native SEO Apply succeeded without explicit approval.' );
seo_geo_manager_authority_accept(
	'seo_geo_manager_native_seo_public_approval_required' === $published_block->get_error_code(),
	'Published native SEO Apply returned the wrong approval guard.'
);

update_option(
	'active_plugins',
	array(
		'wordpress-seo/wp-seo.php',
		'wordpress-seo-premium/wp-seo-premium.php',
	)
);
$yoast = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( true === ( $yoast['authority_resolved'] ?? false ), 'Yoast SEO authority was not resolved.' );
seo_geo_manager_authority_accept( 'resolved-external-provider-read-only' === ( $yoast['state'] ?? '' ), 'Unexpected Yoast SEO authority state.' );
seo_geo_manager_authority_accept( 'yoast' === ( $yoast['authority']['id'] ?? '' ), 'Yoast family was not collapsed to one authority.' );
seo_geo_manager_authority_accept( 1 === count( $yoast['provider_families'] ?? array() ), 'Yoast free/premium variants were treated as separate authorities.' );
seo_geo_manager_authority_accept( false === ( $yoast['safe_to_write_seo_metadata'] ?? true ), 'External provider resolver enabled SEO writes before its write adapter exists.' );
$provider_block = NativeSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_authority_accept( is_wp_error( $provider_block ), 'Native SEO adapter wrote through a competing external authority.' );
seo_geo_manager_authority_accept(
	'seo_geo_manager_native_seo_authority_blocked' === $provider_block->get_error_code(),
	'Native SEO adapter returned the wrong provider authority guard.'
);

update_option(
	'active_plugins',
	array(
		'wordpress-seo/wp-seo.php',
		'seo-by-rank-math/rank-math.php',
	)
);
$conflict = SeoAuthorityScanner::scan();
seo_geo_manager_authority_accept( false === ( $conflict['authority_resolved'] ?? true ), 'Multiple SEO providers were incorrectly resolved to one authority.' );
seo_geo_manager_authority_accept( true === ( $conflict['conflict'] ?? false ), 'Multiple SEO providers did not raise an authority conflict.' );
seo_geo_manager_authority_accept( 'multiple-provider-conflict' === ( $conflict['state'] ?? '' ), 'Unexpected multiple-provider authority state.' );
seo_geo_manager_authority_accept( false === ( $conflict['safe_to_write_seo_metadata'] ?? true ), 'SEO metadata writes were enabled during an authority conflict.' );

update_option( 'active_plugins', $original_plugins );
wp_delete_post( $post_id, true );
wp_delete_post( $published_id, true );

$result = array(
	'ok'                            => true,
	'plugin_version'                => defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : '',
	'theme_native_resolution'       => true,
	'bounded_native_writes'         => true,
	'exact_readback'                => true,
	'idempotent_replay'             => true,
	'stale_safe_rollback'           => true,
	'published_approval_guard'      => true,
	'provider_family_resolution'    => true,
	'free_pro_collapse'             => true,
	'provider_write_block'          => true,
	'multiple_provider_conflict'    => true,
	'unverified_outputs_read_only'  => true,
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
