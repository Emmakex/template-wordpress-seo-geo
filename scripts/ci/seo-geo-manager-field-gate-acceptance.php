<?php
/**
 * Runtime acceptance for the read-only Build / Finish field gate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

if ( ! function_exists( 'wp_delete_user' ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

function seo_geo_manager_field_gate_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param array<string, mixed> $query Query parameters.
 */
function seo_geo_manager_field_gate_request( string $route, array $query = array() ): array {
	$request = new WP_REST_Request( 'GET', $route );
	$request->set_query_params( $query );
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

wp_set_current_user( 1 );
seo_geo_manager_field_gate_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$published_posts = get_posts(
	array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'numberposts'      => 100,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	)
);
if ( array() === $published_posts ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Field Gate Post',
			'post_name'    => 'field-gate-post',
			'post_content' => '<p>Field gate acceptance.</p>',
		),
		true
	);
	seo_geo_manager_field_gate_accept( ! is_wp_error( $post_id ), 'Could not create field-gate published post.' );
	$published_posts = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
}

$legacy_base = trailingslashit( home_url( '/legacy-source/' ) );
$legacy_rows = array();
foreach ( $published_posts as $post ) {
	if ( ! $post instanceof WP_Post ) {
		continue;
	}
	$legacy_rows[] = array(
		'id'   => (int) $post->ID,
		'slug' => (string) $post->post_name,
		'link' => $legacy_base . rawurlencode( (string) $post->post_name ) . '/',
	);
}
seo_geo_manager_field_gate_accept( array() !== $legacy_rows, 'Historical fixture has no published posts.' );

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
$before_history   = seo_geo_manager_field_gate_request( '/seo-geo-manager/v1/operations' );
seo_geo_manager_field_gate_accept( 200 === $before_history['status'] && is_array( $before_history['data'] ), 'Could not read operation history before field gate.' );
$before_count = isset( $before_history['data']['items'] ) && is_array( $before_history['data']['items'] ) ? count( $before_history['data']['items'] ) : 0;

$without_legacy = seo_geo_manager_field_gate_request(
	'/seo-geo-manager/v1/field-gate/preflight',
	array( 'include_rendered' => 0 )
);
seo_geo_manager_field_gate_accept( 200 === $without_legacy['status'] && is_array( $without_legacy['data'] ), 'Field gate without historical source failed.' );
seo_geo_manager_field_gate_accept( true === ( $without_legacy['data']['read_only'] ?? false ), 'Field gate did not declare read-only mode.' );
seo_geo_manager_field_gate_accept( false === ( $without_legacy['data']['write_performed'] ?? true ), 'Field gate reported a write.' );
seo_geo_manager_field_gate_accept( 'not-requested' === ( $without_legacy['data']['field_gate']['historical_authority']['status'] ?? '' ), 'Missing legacy source was not represented explicitly.' );
seo_geo_manager_field_gate_accept( null === ( $without_legacy['data']['permalinks']['legacy_authority'] ?? null ), 'Field gate unexpectedly inspected historical authority.' );

$with_legacy = seo_geo_manager_field_gate_request(
	'/seo-geo-manager/v1/field-gate/preflight',
	array(
		'include_rendered' => 0,
		'legacy_base_url'  => $legacy_base,
	)
);
seo_geo_manager_field_gate_accept( 200 === $with_legacy['status'] && is_array( $with_legacy['data'] ), 'Field gate with historical source failed.' );
$data = $with_legacy['data'];

seo_geo_manager_field_gate_accept( 'build-finish-field-preflight' === ( $data['mode'] ?? '' ), 'Field gate mode mismatch.' );
seo_geo_manager_field_gate_accept( true === ( $data['read_only'] ?? false ), 'Historical field gate did not remain read-only.' );
seo_geo_manager_field_gate_accept( false === ( $data['write_performed'] ?? true ), 'Historical field gate reported a write.' );
seo_geo_manager_field_gate_accept( true === ( $data['policy']['no_content_write'] ?? false ), 'Field gate content-write policy missing.' );
seo_geo_manager_field_gate_accept( true === ( $data['policy']['no_permalink_write'] ?? false ), 'Field gate permalink-write policy missing.' );
seo_geo_manager_field_gate_accept( true === ( $data['policy']['no_redirect_runtime_activation'] ?? false ), 'Field gate redirect-runtime policy missing.' );
seo_geo_manager_field_gate_accept( true === ( $data['policy']['no_operation_created'] ?? false ), 'Field gate operation policy missing.' );
seo_geo_manager_field_gate_accept( true === ( $data['permalinks']['legacy_authority']['seo_authority_verified'] ?? false ), 'Historical authority did not verify.' );
seo_geo_manager_field_gate_accept( true === ( $data['permalinks']['authoritative_plan']['safe_structure_candidate'] ?? false ), 'Authoritative permalink plan was not safe.' );
seo_geo_manager_field_gate_accept( true === ( $data['permalinks']['authoritative_plan']['apply_available'] ?? false ), 'Exact-path authoritative plan was not direct-apply eligible.' );
seo_geo_manager_field_gate_accept( 'verified' === ( $data['field_gate']['historical_authority']['status'] ?? '' ), 'Field-gate historical authority summary mismatch.' );
seo_geo_manager_field_gate_accept( 'direct-ready' === ( $data['field_gate']['permalink_plan']['status'] ?? '' ), 'Field-gate permalink summary mismatch.' );
seo_geo_manager_field_gate_accept( false === ( $data['options']['include_rendered'] ?? true ), 'Field gate rendered option mismatch.' );

$after_structure = (string) get_option( 'permalink_structure', '' );
seo_geo_manager_field_gate_accept( $before_structure === $after_structure, 'Field gate mutated permalink_structure.' );

$after_history = seo_geo_manager_field_gate_request( '/seo-geo-manager/v1/operations' );
seo_geo_manager_field_gate_accept( 200 === $after_history['status'] && is_array( $after_history['data'] ), 'Could not read operation history after field gate.' );
$after_count = isset( $after_history['data']['items'] ) && is_array( $after_history['data']['items'] ) ? count( $after_history['data']['items'] ) : 0;
seo_geo_manager_field_gate_accept( $before_count === $after_count, 'Field gate created an operation record.' );

$editor_id = wp_insert_user(
	array(
		'user_login' => 'manager-field-gate-editor',
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => 'manager-field-gate-editor@example.test',
		'role'       => 'editor',
	)
);
seo_geo_manager_field_gate_accept( ! is_wp_error( $editor_id ) && 0 < (int) $editor_id, 'Could not create field-gate editor.' );
wp_set_current_user( (int) $editor_id );
$forbidden = seo_geo_manager_field_gate_request( '/seo-geo-manager/v1/field-gate/preflight' );
seo_geo_manager_field_gate_accept( 403 === $forbidden['status'], 'Editor could execute administrator field gate.' );
wp_set_current_user( 1 );
wp_delete_user( (int) $editor_id );

$result = array(
	'ok'                     => true,
	'read_only'              => true,
	'no_operation_created'   => true,
	'permalink_unchanged'    => true,
	'historical_authority'   => true,
	'direct_plan_ready'      => true,
	'administrator_only'     => true,
	'build_finish_status'    => $data['field_gate']['build_finish']['status'] ?? '',
	'guarded_write_eligible' => true === ( $data['field_gate']['guarded_write_eligible'] ?? false ),
);

echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
