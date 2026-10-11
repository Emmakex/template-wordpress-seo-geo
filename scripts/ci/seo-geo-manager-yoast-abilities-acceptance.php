<?php
/**
 * Runtime acceptance for the Yoast SEO 28.6 public WordPress Abilities adapter.
 *
 * Executed with WP-CLI eval-file inside an isolated WordPress 7.1 fixture.
 */

use SeoGeo\Manager\Changes\YoastSeoMetadataChangeAdapter;
use SeoGeo\Manager\Intelligence\SeoAuthorityScanner;
use SeoGeo\Manager\Support\YoastSeoAbilityBridge;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_yoast_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Pick one real enum value from a Yoast ability input field.
 */
function seo_geo_manager_yoast_enum_value( WP_Ability $ability, string $field ): ?string {
	$schema     = $ability->get_input_schema();
	$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
	$field_data = isset( $properties[ $field ] ) && is_array( $properties[ $field ] ) ? $properties[ $field ] : array();
	$values     = isset( $field_data['enum'] ) && is_array( $field_data['enum'] ) ? $field_data['enum'] : array();
	foreach ( $values as $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
	}

	return null;
}

$admin = get_user_by( 'login', 'admin' );
seo_geo_manager_yoast_accept( $admin instanceof WP_User, 'Acceptance administrator was not found.' );
wp_set_current_user( (int) $admin->ID );
seo_geo_manager_yoast_accept( current_user_can( 'wpseo_edit_advanced_metadata' ), 'Administrator lacks Yoast advanced metadata capability.' );
seo_geo_manager_yoast_accept( YoastSeoAbilityBridge::available(), 'Yoast SEO public post SEO abilities are unavailable.' );

$read_ability  = wp_get_ability( 'yoast-seo/get-post-seo-data' );
$write_ability = wp_get_ability( 'yoast-seo/update-post-seo-data' );
seo_geo_manager_yoast_accept( $read_ability instanceof WP_Ability, 'Yoast read ability is not registered.' );
seo_geo_manager_yoast_accept( $write_ability instanceof WP_Ability, 'Yoast write ability is not registered.' );

$scan = SeoAuthorityScanner::scan();
seo_geo_manager_yoast_accept( true === ( $scan['authority_resolved'] ?? false ), 'Yoast SEO authority was not resolved.' );
seo_geo_manager_yoast_accept( 'resolved-external-provider-writable' === ( $scan['state'] ?? '' ), 'Yoast SEO was not resolved as a writable external authority.' );
seo_geo_manager_yoast_accept( 'yoast' === ( $scan['authority']['id'] ?? '' ), 'Yoast provider identity mismatch.' );
seo_geo_manager_yoast_accept( 'yoast-abilities' === ( $scan['authority']['adapter'] ?? '' ), 'Yoast abilities adapter identity mismatch.' );
seo_geo_manager_yoast_accept( true === ( $scan['safe_to_write_seo_metadata'] ?? false ), 'Verified Yoast abilities did not enable bounded SEO writes.' );
foreach ( array( 'canonical', 'robots', 'schema' ) as $signal ) {
	seo_geo_manager_yoast_accept( true === ( $scan['outputs'][ $signal ]['writable'] ?? false ), 'Verified Yoast signal is not writable: ' . $signal );
}
foreach ( array( 'title-meta', 'open-graph', 'hreflang', 'sitemap' ) as $signal ) {
	seo_geo_manager_yoast_accept( false === ( $scan['outputs'][ $signal ]['writable'] ?? true ), 'Unsupported Yoast signal was exposed as writable: ' . $signal );
}

$post_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'Yoast abilities acceptance',
		'post_content'=> 'Provider adapter fixture.',
	),
	true
);
seo_geo_manager_yoast_accept( ! is_wp_error( $post_id ) && 0 < $post_id, 'Could not create Yoast acceptance post.' );
$post_id = (int) $post_id;

$inspection = YoastSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_yoast_accept( ! is_wp_error( $inspection ), 'Yoast SEO inspection failed.' );
$before_values      = isset( $inspection['values'] ) && is_array( $inspection['values'] ) ? $inspection['values'] : array();
$before_fingerprint = isset( $inspection['fingerprint'] ) && is_string( $inspection['fingerprint'] ) ? $inspection['fingerprint'] : '';
$before_read_only   = isset( $inspection['read_only'] ) && is_array( $inspection['read_only'] ) ? $inspection['read_only'] : array();
seo_geo_manager_yoast_accept( '' !== $before_fingerprint, 'Yoast SEO inspection did not return a fingerprint.' );
seo_geo_manager_yoast_accept( array_key_exists( 'canonical', $before_values ), 'Yoast inspection omitted canonical.' );
seo_geo_manager_yoast_accept( array_key_exists( 'noindex', $before_values ), 'Yoast inspection omitted noindex.' );
seo_geo_manager_yoast_accept( array_key_exists( 'nofollow', $before_values ), 'Yoast inspection omitted nofollow.' );
seo_geo_manager_yoast_accept( isset( $before_read_only['title_meta'] ), 'Yoast read-only title/meta context is missing.' );

$page_type    = seo_geo_manager_yoast_enum_value( $write_ability, 'schema_page_type' );
$article_type = seo_geo_manager_yoast_enum_value( $write_ability, 'schema_article_type' );
seo_geo_manager_yoast_accept( null !== $page_type, 'Could not resolve a valid Yoast Schema page type.' );
seo_geo_manager_yoast_accept( null !== $article_type, 'Could not resolve a valid Yoast Schema article type.' );

$values = array(
	'canonical'           => home_url( '/yoast-abilities-canonical/' ),
	'noindex'             => true,
	'nofollow'            => true,
	'schema_page_type'    => $page_type,
	'schema_article_type' => $article_type,
);
$payload = array(
	'resource_id'          => $post_id,
	'values'               => $values,
	'expected_fingerprint' => $before_fingerprint,
	'idempotency_key'      => 'yoast-abilities-acceptance-' . $post_id,
);

$preview = YoastSeoMetadataChangeAdapter::preview( $payload );
seo_geo_manager_yoast_accept( ! is_wp_error( $preview ), 'Yoast SEO Preview failed.' );
seo_geo_manager_yoast_accept( true === ( $preview['has_changes'] ?? false ), 'Yoast SEO Preview did not expose a change.' );
seo_geo_manager_yoast_accept( $values === ( $preview['change']['after'] ?? null ), 'Yoast SEO Preview changed the requested normalized state.' );
seo_geo_manager_yoast_accept( true === ( $preview['policy']['provider_public_abilities_only'] ?? false ), 'Yoast Preview does not assert public-provider-only writes.' );
seo_geo_manager_yoast_accept( false === ( $preview['policy']['private_provider_meta_writes'] ?? true ), 'Yoast Preview incorrectly allows private provider meta writes.' );

$applied = YoastSeoMetadataChangeAdapter::apply( $payload );
seo_geo_manager_yoast_accept( ! is_wp_error( $applied ), 'Yoast SEO Apply failed.' );
seo_geo_manager_yoast_accept( 'applied' === ( $applied['status'] ?? '' ), 'Yoast SEO Apply did not persist applied operation state.' );
$operation_id = isset( $applied['operation_id'] ) && is_string( $applied['operation_id'] ) ? $applied['operation_id'] : '';
seo_geo_manager_yoast_accept( '' !== $operation_id, 'Yoast SEO Apply did not return an operation ID.' );

$after = YoastSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_yoast_accept( ! is_wp_error( $after ), 'Yoast SEO readback failed after Apply.' );
seo_geo_manager_yoast_accept( $values === ( $after['values'] ?? null ), 'Yoast SEO Apply did not persist exact bounded values.' );
seo_geo_manager_yoast_accept( ( $applied['after_fingerprint'] ?? '' ) === ( $after['fingerprint'] ?? null ), 'Yoast SEO Apply fingerprint does not match exact provider readback.' );
seo_geo_manager_yoast_accept( $before_read_only === ( $after['read_only'] ?? null ), 'Yoast write unexpectedly changed read-only title/meta or Open Graph state.' );

$replay = YoastSeoMetadataChangeAdapter::apply( $payload );
seo_geo_manager_yoast_accept( ! is_wp_error( $replay ), 'Yoast SEO idempotent replay failed.' );
seo_geo_manager_yoast_accept( true === ( $replay['idempotent_replay'] ?? false ), 'Yoast SEO idempotent replay was not identified.' );
seo_geo_manager_yoast_accept( $operation_id === ( $replay['operation_id'] ?? '' ), 'Yoast SEO idempotent replay returned a different operation.' );

$rollback = YoastSeoMetadataChangeAdapter::rollback( $operation_id );
seo_geo_manager_yoast_accept( ! is_wp_error( $rollback ), 'Yoast SEO rollback failed.' );
seo_geo_manager_yoast_accept( 'rolled-back' === ( $rollback['status'] ?? '' ), 'Yoast SEO rollback did not persist rolled-back state.' );
$restored = YoastSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_yoast_accept( ! is_wp_error( $restored ), 'Yoast SEO readback failed after rollback.' );
seo_geo_manager_yoast_accept( $before_values === ( $restored['values'] ?? null ), 'Yoast SEO rollback did not restore the exact initial provider state.' );
seo_geo_manager_yoast_accept( $before_fingerprint === ( $restored['fingerprint'] ?? '' ), 'Yoast SEO rollback did not restore the initial fingerprint.' );

$published_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'Yoast published acceptance',
	),
	true
);
seo_geo_manager_yoast_accept( ! is_wp_error( $published_id ) && 0 < $published_id, 'Could not create published Yoast acceptance post.' );
$published_id         = (int) $published_id;
$published_inspection = YoastSeoMetadataChangeAdapter::inspect( $published_id );
seo_geo_manager_yoast_accept( ! is_wp_error( $published_inspection ), 'Published Yoast SEO inspection failed.' );
$published_payload = array(
	'resource_id'          => $published_id,
	'values'               => $values,
	'expected_fingerprint' => (string) ( $published_inspection['fingerprint'] ?? '' ),
	'idempotency_key'      => 'yoast-published-acceptance-' . $published_id,
);
$published_block = YoastSeoMetadataChangeAdapter::apply( $published_payload );
seo_geo_manager_yoast_accept( is_wp_error( $published_block ), 'Published Yoast SEO Apply succeeded without explicit approval.' );
seo_geo_manager_yoast_accept( 'seo_geo_manager_yoast_seo_public_approval_required' === $published_block->get_error_code(), 'Published Yoast SEO Apply returned the wrong approval guard.' );

$cap_request  = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/capabilities' );
$cap_response = rest_do_request( $cap_request );
$cap_data     = $cap_response->get_data();
seo_geo_manager_yoast_accept( 200 === $cap_response->get_status() && is_array( $cap_data ), 'Yoast capability discovery failed.' );
seo_geo_manager_yoast_accept( true === ( $cap_data['capabilities']['yoast_seo_metadata']['available'] ?? false ), 'Yoast capability was not advertised as available.' );
seo_geo_manager_yoast_accept( 'yoast-abilities' === ( $cap_data['capabilities']['yoast_seo_metadata']['adapter'] ?? '' ), 'Yoast capability adapter identity mismatch.' );
seo_geo_manager_yoast_accept( array( 'canonical', 'robots', 'schema' ) === ( $cap_data['capabilities']['yoast_seo_metadata']['writable_signals'] ?? array() ), 'Yoast capability writable signals changed unexpectedly.' );
seo_geo_manager_yoast_accept( false === ( $cap_data['capabilities']['native_seo_metadata']['available'] ?? true ), 'Native SEO adapter remained writable while Yoast owned SEO output.' );
seo_geo_manager_yoast_accept( true === ( $cap_data['operations']['content.yoast_seo_metadata.apply']['available'] ?? false ), 'Yoast Apply operation is missing from capability discovery.' );

$active_plugins = get_option( 'active_plugins', array() );
$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();
update_option( 'active_plugins', array_values( array_unique( array_merge( $active_plugins, array( 'seo-by-rank-math/rank-math.php' ) ) ) ) );
$conflict = SeoAuthorityScanner::scan();
seo_geo_manager_yoast_accept( true === ( $conflict['conflict'] ?? false ), 'Yoast + second provider did not raise an authority conflict.' );
seo_geo_manager_yoast_accept( false === ( $conflict['safe_to_write_seo_metadata'] ?? true ), 'Provider conflict incorrectly remained writable.' );
$blocked = YoastSeoMetadataChangeAdapter::inspect( $post_id );
seo_geo_manager_yoast_accept( is_wp_error( $blocked ), 'Yoast adapter remained writable during multiple-provider conflict.' );
seo_geo_manager_yoast_accept( 'seo_geo_manager_yoast_seo_authority_blocked' === $blocked->get_error_code(), 'Yoast conflict returned the wrong authority guard.' );
update_option( 'active_plugins', $active_plugins );

wp_delete_post( $post_id, true );
wp_delete_post( $published_id, true );

$result = array(
	'ok'                         => true,
	'plugin_version'             => defined( 'SEO_GEO_MANAGER_VERSION' ) ? SEO_GEO_MANAGER_VERSION : '',
	'yoast_version'              => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '',
	'abilities_registered'       => true,
	'provider_authority_writable'=> true,
	'canonical_write'            => true,
	'robots_write'               => true,
	'schema_write'               => true,
	'title_meta_read_only'       => true,
	'open_graph_read_only'       => true,
	'exact_readback'             => true,
	'idempotent_replay'          => true,
	'stale_safe_rollback'        => true,
	'published_approval_guard'   => true,
	'capability_discovery'       => true,
	'multiple_provider_conflict' => true,
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
