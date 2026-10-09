<?php
/**
 * End-to-end acceptance for atomic authoritative permalink + 301 Apply.
 */

use SeoGeo\Manager\Support\PermalinkRedirectRuntime;
use SeoGeo\Manager\Support\PermalinkStructureRuntime;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_atomic_redirect_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function seo_geo_manager_atomic_redirect_post( string $route, array $payload ): array {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( (string) wp_json_encode( $payload ) );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

wp_set_current_user( 1 );
seo_geo_manager_atomic_redirect_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );
seo_geo_manager_atomic_redirect_accept( false === ( PermalinkRedirectRuntime::snapshot()['active'] ?? true ), 'Atomic acceptance requires an inactive redirect runtime.' );

$original_home      = (string) get_option( 'home', '' );
$original_structure = (string) get_option( 'permalink_structure', '' );
update_option( 'home', 'https://example.com/nuevaweb/' );
wp_cache_delete( 'home', 'options' );
wp_cache_delete( 'alloptions', 'options' );

foreach ( (array) get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1 ) ) as $existing_post_id ) {
	wp_delete_post( (int) $existing_post_id, true );
}

$post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Atomic Redirect Post',
		'post_name'    => 'atomic-redirect-post',
		'post_content' => '<p>Atomic redirect acceptance fixture.</p>',
	),
	true
);
seo_geo_manager_atomic_redirect_accept( ! is_wp_error( $post_id ) && 0 < (int) $post_id, 'Could not create atomic redirect fixture post.' );

$current_structure = '/current/%postname%/';
$target_structure  = '/blog/%postname%/';
$structure_write   = PermalinkStructureRuntime::set( $current_structure );
seo_geo_manager_atomic_redirect_accept( ! is_wp_error( $structure_write ), 'Could not set atomic acceptance starting structure.' );

add_rewrite_rule( '^new/([^/]+)/?$', 'index.php?name=$matches[1]', 'top' );
flush_rewrite_rules( false );

$post_link_filter = static function ( $permalink, $post ) use ( $post_id, $target_structure ) {
	if ( ! $post instanceof WP_Post || (int) $post->ID !== (int) $post_id ) {
		return $permalink;
	}
	if ( $target_structure !== (string) get_option( 'permalink_structure', '' ) ) {
		return $permalink;
	}

	return home_url( '/new/atomic-redirect-post/' );
};
add_filter( 'post_link', $post_link_filter, 10, 2 );

$legacy_rows = array(
	array(
		'id'   => 9901,
		'slug' => 'atomic-redirect-post',
		'link' => 'https://example.com/blog/atomic-redirect-post/',
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

$authority = seo_geo_manager_atomic_redirect_post(
	'/seo-geo-manager/v1/permalinks/legacy-authority-preview',
	array( 'legacy_base_url' => 'https://example.com/' )
);
seo_geo_manager_atomic_redirect_accept( 200 === $authority['status'] && is_array( $authority['data'] ), 'Atomic legacy authority request failed.' );
seo_geo_manager_atomic_redirect_accept( true === ( $authority['data']['seo_authority_verified'] ?? false ), 'Atomic fixture did not produce verified historical authority.' );

$plan = seo_geo_manager_atomic_redirect_post(
	'/seo-geo-manager/v1/permalinks/authoritative-plan',
	array(
		'legacy_base_url'       => 'https://example.com/',
		'authority_fingerprint' => (string) ( $authority['data']['authority_fingerprint'] ?? '' ),
	)
);
seo_geo_manager_atomic_redirect_accept( 200 === $plan['status'] && is_array( $plan['data'] ), 'Atomic authoritative plan request failed.' );
seo_geo_manager_atomic_redirect_accept( true === ( $plan['data']['safe_structure_candidate'] ?? false ), 'Atomic authoritative plan was not safe.' );
seo_geo_manager_atomic_redirect_accept( true === ( $plan['data']['requires_redirect_runtime'] ?? false ), 'Atomic fixture did not require redirect runtime.' );
seo_geo_manager_atomic_redirect_accept( 1 === (int) ( $plan['data']['planned_redirects'] ?? 0 ), 'Atomic fixture redirect count mismatch.' );
seo_geo_manager_atomic_redirect_accept( 'authoritative-301' === ( $plan['data']['seo_preservation_mode'] ?? '' ), 'Atomic fixture did not enter authoritative-301 mode.' );

$runtime_preview = seo_geo_manager_atomic_redirect_post(
	'/seo-geo-manager/v1/permalinks/redirect-runtime/preview',
	array(
		'legacy_base_url'       => 'https://example.com/',
		'authority_fingerprint' => (string) ( $authority['data']['authority_fingerprint'] ?? '' ),
	)
);
seo_geo_manager_atomic_redirect_accept( 200 === $runtime_preview['status'] && is_array( $runtime_preview['data'] ), 'Atomic runtime preview failed.' );
seo_geo_manager_atomic_redirect_accept( true === ( $runtime_preview['data']['atomic_apply_available'] ?? false ), 'Atomic runtime preview did not enable guarded Apply.' );

$environment_fingerprint = (string) ( $plan['data']['environment']['fingerprint'] ?? '' );
$apply_payload = array(
	'legacy_base_url'          => 'https://example.com/',
	'authority_fingerprint'    => (string) ( $authority['data']['authority_fingerprint'] ?? '' ),
	'plan_fingerprint'         => (string) ( $plan['data']['plan_fingerprint'] ?? '' ),
	'current_fingerprint'      => (string) ( $plan['data']['current_fingerprint'] ?? '' ),
	'idempotency_key'          => 'atomic-permalink-redirect-acceptance-1',
	'confirm_permalink_change' => true,
	'confirm_redirect_runtime' => true,
	'environment_fingerprint'  => $environment_fingerprint,
);
$applied = seo_geo_manager_atomic_redirect_post( '/seo-geo-manager/v1/permalinks/redirect-apply', $apply_payload );
seo_geo_manager_atomic_redirect_accept( 200 === $applied['status'] && is_array( $applied['data'] ), 'Atomic permalink + 301 Apply failed.' );
seo_geo_manager_atomic_redirect_accept( 'applied' === ( $applied['data']['status'] ?? '' ), 'Atomic operation was not persisted as applied.' );
seo_geo_manager_atomic_redirect_accept( 'permalink-structure-with-redirects' === ( $applied['data']['operation_type'] ?? '' ), 'Atomic operation type mismatch.' );
seo_geo_manager_atomic_redirect_accept( $target_structure === (string) get_option( 'permalink_structure', '' ), 'Atomic Apply did not persist target structure.' );
seo_geo_manager_atomic_redirect_accept( 1 === (int) ( $applied['data']['planned_redirects'] ?? 0 ), 'Atomic Apply redirect count mismatch.' );

$runtime = PermalinkRedirectRuntime::snapshot();
seo_geo_manager_atomic_redirect_accept( true === ( $runtime['active'] ?? false ) && true === ( $runtime['effective'] ?? false ), 'Atomic redirect runtime is not effective after Apply.' );
$resolved = PermalinkRedirectRuntime::resolve( '/nuevaweb/blog/atomic-redirect-post/?utm_source=atomic-ci' );
seo_geo_manager_atomic_redirect_accept( is_array( $resolved ) && 301 === (int) ( $resolved['status'] ?? 0 ), 'Atomic historical route did not resolve with 301.' );
seo_geo_manager_atomic_redirect_accept( '/new/atomic-redirect-post/' === ( $resolved['target_path'] ?? '' ), 'Atomic historical route resolved to the wrong target.' );

$replayed = seo_geo_manager_atomic_redirect_post( '/seo-geo-manager/v1/permalinks/redirect-apply', $apply_payload );
seo_geo_manager_atomic_redirect_accept( 200 === $replayed['status'] && is_array( $replayed['data'] ), 'Atomic idempotent replay failed.' );
seo_geo_manager_atomic_redirect_accept( ( $applied['data']['operation_id'] ?? '' ) === ( $replayed['data']['operation_id'] ?? '' ), 'Atomic replay returned another operation.' );
seo_geo_manager_atomic_redirect_accept( true === ( $replayed['data']['idempotent_replay'] ?? false ), 'Atomic replay was not marked idempotent.' );

$operation_id = (string) ( $applied['data']['operation_id'] ?? '' );
$rolled_back  = seo_geo_manager_atomic_redirect_post(
	'/seo-geo-manager/v1/permalinks/operations/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_atomic_redirect_accept( 200 === $rolled_back['status'] && is_array( $rolled_back['data'] ), 'Atomic rollback failed.' );
seo_geo_manager_atomic_redirect_accept( 'rolled-back' === ( $rolled_back['data']['status'] ?? '' ), 'Atomic rollback status mismatch.' );
seo_geo_manager_atomic_redirect_accept( $current_structure === (string) get_option( 'permalink_structure', '' ), 'Atomic rollback did not restore the starting structure.' );
seo_geo_manager_atomic_redirect_accept( false === ( PermalinkRedirectRuntime::snapshot()['active'] ?? true ), 'Atomic rollback did not remove the redirect runtime.' );

remove_filter( 'pre_http_request', $legacy_http_filter, 10 );
remove_filter( 'post_link', $post_link_filter, 10 );
wp_delete_post( (int) $post_id, true );
PermalinkStructureRuntime::restore( $original_structure );
update_option( 'home', $original_home );
wp_cache_delete( 'home', 'options' );
wp_cache_delete( 'alloptions', 'options' );

echo wp_json_encode(
	array(
		'ok'                    => true,
		'authoritative_301'     => true,
		'armed_transition'      => true,
		'atomic_apply'          => true,
		'idempotent_replay'     => true,
		'atomic_rollback'       => true,
		'runtime_removed'       => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
