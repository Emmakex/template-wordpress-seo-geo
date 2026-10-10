<?php
/**
 * Runtime acceptance for C6.2 guarded contextual-link href changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * @param bool   $condition Assertion state.
 * @param string $message Failure message.
 */
function seo_geo_manager_c6_write_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param array<string, mixed> $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_c6_write_request( string $method, string $route, array $payload = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

/**
 * @param mixed  $items Items.
 * @param string $classification Classification.
 * @return array<string, mixed>|null
 */
function seo_geo_manager_c6_write_find_classification( $items, string $classification ): ?array {
	if ( ! is_array( $items ) ) {
		return null;
	}
	foreach ( $items as $item ) {
		if ( is_array( $item ) && ( $item['classification'] ?? '' ) === $classification ) {
			return $item;
		}
	}

	return null;
}

/**
 * Reject private snapshots from public REST responses.
 *
 * @param mixed $value Value.
 */
function seo_geo_manager_c6_write_assert_no_private_snapshots( $value ): void {
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $child ) {
		if ( is_string( $key ) ) {
			seo_geo_manager_c6_write_accept(
				! in_array( $key, array( 'before_content', 'after_content', 'post_content', 'raw_content' ), true ),
				'C6.2 public response exposed a private content snapshot.'
			);
		}
		seo_geo_manager_c6_write_assert_no_private_snapshots( $child );
	}
}

wp_set_current_user( 1 );
seo_geo_manager_c6_write_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$target_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6.2 Published Target',
		'post_name'    => 'c6-2-published-target',
		'post_content' => '<p>Published target.</p>',
	),
	true
);
seo_geo_manager_c6_write_accept( ! is_wp_error( $target_id ) && 0 < (int) $target_id, 'Could not create C6.2 published target.' );
$target_id  = (int) $target_id;
$target_url = get_permalink( $target_id );
seo_geo_manager_c6_write_accept( is_string( $target_url ) && '' !== $target_url, 'C6.2 target permalink missing.' );

$alternate_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6.2 Alternate Target',
		'post_name'    => 'c6-2-alternate-target',
		'post_content' => '<p>Alternate target.</p>',
	),
	true
);
seo_geo_manager_c6_write_accept( ! is_wp_error( $alternate_id ) && 0 < (int) $alternate_id, 'Could not create alternate target.' );
$alternate_id = (int) $alternate_id;

$draft_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'C6.2 Draft Target',
		'post_name'    => 'c6-2-draft-target',
		'post_content' => '<p>Draft target.</p>',
	),
	true
);
seo_geo_manager_c6_write_accept( ! is_wp_error( $draft_id ) && 0 < (int) $draft_id, 'Could not create draft target.' );
$draft_id = (int) $draft_id;

$target_path   = (string) wp_parse_url( $target_url, PHP_URL_PATH );
$legacy_url    = 'https://legacy.example.test' . $target_path;
$before_source = '<p>Before <a class="keep-me" data-proof="untouched" href="' . esc_url( $legacy_url ) . '">Context target</a> after.</p>';
$source_id     = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'C6.2 Link Source',
		'post_name'    => 'c6-2-link-source',
		'post_content' => $before_source,
	),
	true
);
seo_geo_manager_c6_write_accept( ! is_wp_error( $source_id ) && 0 < (int) $source_id, 'Could not create C6.2 source.' );
$source_id = (int) $source_id;
flush_rewrite_rules( false );

$snapshot = seo_geo_manager_c6_write_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_c6_write_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_c6_write_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$read = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/links/contextual' );
$read->set_param( 'source_id', $source_id );
$read_response = rest_do_request( $read );
$read_data     = $read_response->get_data();
seo_geo_manager_c6_write_accept( 200 === $read_response->get_status() && is_array( $read_data ), 'Could not inspect C6.2 source links.' );
$edge = seo_geo_manager_c6_write_find_classification( $read_data['items'] ?? array(), 'environment-leakage-candidate' );
seo_geo_manager_c6_write_accept( is_array( $edge ), 'C6.2 source did not expose the expected leakage edge.' );
$edge_id     = (string) ( $edge['edge_id'] ?? '' );
$fingerprint = (string) ( $edge['source']['fingerprint'] ?? '' );
seo_geo_manager_c6_write_accept( 64 === strlen( $edge_id ) && '' !== $fingerprint, 'C6.2 edge identity/fingerprint missing.' );

$base_payload = array(
	'schema_version'        => 1,
	'source_id'             => $source_id,
	'edge_id'               => $edge_id,
	'expected_fingerprint'  => $fingerprint,
	'target_resource_id'    => $target_id,
	'target_url'            => 'https://attacker.example.invalid/ignored',
);

$preview = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/preview', $base_payload );
seo_geo_manager_c6_write_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'C6.2 Preview failed.' );
seo_geo_manager_c6_write_accept( true === ( $preview['data']['has_changes'] ?? false ), 'C6.2 Preview reported no change.' );
seo_geo_manager_c6_write_accept( $target_url === ( $preview['data']['edge']['after_href'] ?? '' ), 'C6.2 did not resolve href from target_resource_id.' );
seo_geo_manager_c6_write_accept( $legacy_url === ( $preview['data']['edge']['before_href'] ?? '' ), 'C6.2 Preview before href mismatch.' );
seo_geo_manager_c6_write_accept( true === ( $preview['data']['policy']['public_source_blocked'] ?? false ), 'C6.2 Preview did not surface public-source approval gate.' );
seo_geo_manager_c6_write_accept( true === ( $preview['data']['policy']['href_only'] ?? false ), 'C6.2 href-only policy missing.' );
seo_geo_manager_c6_write_assert_no_private_snapshots( $preview['data'] );

$draft_payload                       = $base_payload;
$draft_payload['target_resource_id'] = $draft_id;
$draft_preview                       = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/preview', $draft_payload );
seo_geo_manager_c6_write_accept( 409 === $draft_preview['status'], 'C6.2 accepted a draft destination.' );
seo_geo_manager_c6_write_accept( 'seo_geo_manager_contextual_link_target_not_public' === ( $draft_preview['data']['code'] ?? '' ), 'Unexpected draft-target rejection code.' );

$stale_payload                         = $base_payload;
$stale_payload['expected_fingerprint'] = str_repeat( 'a', 64 );
$stale_preview                         = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/preview', $stale_payload );
seo_geo_manager_c6_write_accept( 409 === $stale_preview['status'], 'C6.2 accepted a stale source fingerprint.' );

$blocked_apply                            = $base_payload;
$blocked_apply['environment_fingerprint'] = $environment_fingerprint;
$blocked_apply['idempotency_key']         = 'c6-2-public-approval-block';
$blocked = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/apply', $blocked_apply );
seo_geo_manager_c6_write_accept( 409 === $blocked['status'], 'C6.2 mutated a published source without explicit approval.' );
seo_geo_manager_c6_write_accept( 'seo_geo_manager_contextual_link_public_approval_required' === ( $blocked['data']['code'] ?? '' ), 'Unexpected public-source approval rejection code.' );
seo_geo_manager_c6_write_accept( $before_source === (string) get_post_field( 'post_content', $source_id ), 'Blocked C6.2 Apply changed source content.' );

$apply_payload                           = $base_payload;
$apply_payload['allow_published_target'] = true;
$apply_payload['environment_fingerprint'] = $environment_fingerprint;
$apply_payload['idempotency_key']         = 'c6-2-safe-apply';
$apply = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/apply', $apply_payload );
seo_geo_manager_c6_write_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'C6.2 Apply failed.' );
$operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_c6_write_accept( 36 === strlen( $operation_id ), 'C6.2 operation ID missing.' );
seo_geo_manager_c6_write_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'C6.2 operation status mismatch.' );
seo_geo_manager_c6_write_accept( $target_url === ( $apply['data']['after_href'] ?? '' ), 'C6.2 stored href does not match current target permalink.' );
seo_geo_manager_c6_write_assert_no_private_snapshots( $apply['data'] );

$applied_content = (string) get_post_field( 'post_content', $source_id );
seo_geo_manager_c6_write_accept( false === str_contains( $applied_content, $legacy_url ), 'C6.2 Apply left the legacy href in source content.' );
seo_geo_manager_c6_write_accept( str_contains( $applied_content, $target_url ), 'C6.2 Apply did not store target permalink.' );
seo_geo_manager_c6_write_accept( str_contains( $applied_content, 'class="keep-me"' ), 'C6.2 Apply changed unrelated anchor class.' );
seo_geo_manager_c6_write_accept( str_contains( $applied_content, 'data-proof="untouched"' ), 'C6.2 Apply changed unrelated anchor metadata.' );
seo_geo_manager_c6_write_accept( str_contains( $applied_content, '>Context target</a>' ), 'C6.2 Apply changed anchor text.' );

$replay = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/apply', $apply_payload );
seo_geo_manager_c6_write_accept( 200 === $replay['status'] && is_array( $replay['data'] ), 'C6.2 idempotent replay failed.' );
seo_geo_manager_c6_write_accept( $operation_id === ( $replay['data']['operation_id'] ?? '' ), 'C6.2 idempotent replay returned another operation.' );
seo_geo_manager_c6_write_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'C6.2 replay marker missing.' );
seo_geo_manager_c6_write_accept( $applied_content === (string) get_post_field( 'post_content', $source_id ), 'C6.2 replay performed a second mutation.' );

$conflict_payload                       = $apply_payload;
$conflict_payload['target_resource_id'] = $alternate_id;
$conflict = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/apply', $conflict_payload );
seo_geo_manager_c6_write_accept( 409 === $conflict['status'], 'C6.2 reused an idempotency key with a different payload.' );
seo_geo_manager_c6_write_accept( 'seo_geo_manager_idempotency_conflict' === ( $conflict['data']['code'] ?? '' ), 'Unexpected idempotency conflict code.' );

$rollback = seo_geo_manager_c6_write_request(
	'POST',
	'/seo-geo-manager/v1/links/contextual/changes/' . $operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_write_accept( 200 === $rollback['status'] && is_array( $rollback['data'] ), 'C6.2 rollback failed.' );
seo_geo_manager_c6_write_accept( 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'C6.2 rollback status mismatch.' );
seo_geo_manager_c6_write_accept( '' !== (string) ( $rollback['data']['rollback_fingerprint'] ?? '' ), 'C6.2 rollback fingerprint missing.' );
seo_geo_manager_c6_write_accept( $before_source === (string) get_post_field( 'post_content', $source_id ), 'C6.2 rollback did not restore exact source content.' );
seo_geo_manager_c6_write_assert_no_private_snapshots( $rollback['data'] );

$read_after_rollback = new WP_REST_Request( 'GET', '/seo-geo-manager/v1/links/contextual' );
$read_after_rollback->set_param( 'source_id', $source_id );
$read_after_response = rest_do_request( $read_after_rollback );
$read_after_data     = $read_after_response->get_data();
seo_geo_manager_c6_write_accept( 200 === $read_after_response->get_status() && is_array( $read_after_data ), 'Could not re-read source after rollback.' );
$edge_after = seo_geo_manager_c6_write_find_classification( $read_after_data['items'] ?? array(), 'environment-leakage-candidate' );
seo_geo_manager_c6_write_accept( is_array( $edge_after ), 'Rollback did not restore leakage edge.' );

$second_payload = array(
	'schema_version'           => 1,
	'source_id'                => $source_id,
	'edge_id'                  => (string) ( $edge_after['edge_id'] ?? '' ),
	'expected_fingerprint'     => (string) ( $edge_after['source']['fingerprint'] ?? '' ),
	'target_resource_id'       => $target_id,
	'allow_published_target'   => true,
	'environment_fingerprint'  => $environment_fingerprint,
	'idempotency_key'          => 'c6-2-stale-rollback',
);
$second_apply = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/apply', $second_payload );
seo_geo_manager_c6_write_accept( 200 === $second_apply['status'] && is_array( $second_apply['data'] ), 'Second C6.2 Apply failed.' );
$second_operation = (string) ( $second_apply['data']['operation_id'] ?? '' );
seo_geo_manager_c6_write_accept( 36 === strlen( $second_operation ), 'Second C6.2 operation ID missing.' );

$current_after_second = (string) get_post_field( 'post_content', $source_id );
wp_update_post(
	array(
		'ID'           => $source_id,
		'post_content' => $current_after_second . '<p>Newer operator edit.</p>',
	)
);
$stale_rollback = seo_geo_manager_c6_write_request(
	'POST',
	'/seo-geo-manager/v1/links/contextual/changes/' . $second_operation . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_c6_write_accept( 409 === $stale_rollback['status'], 'C6.2 rollback overwrote a newer source edit.' );
seo_geo_manager_c6_write_accept( 'seo_geo_manager_contextual_link_rollback_stale' === ( $stale_rollback['data']['code'] ?? '' ), 'Unexpected stale rollback code.' );
seo_geo_manager_c6_write_accept( str_contains( (string) get_post_field( 'post_content', $source_id ), 'Newer operator edit.' ), 'Stale rollback removed the newer edit.' );

$history = seo_geo_manager_c6_write_request( 'GET', '/seo-geo-manager/v1/operations' );
seo_geo_manager_c6_write_accept( 200 === $history['status'] && is_array( $history['data'] ), 'Operation history summary failed after C6.2.' );
seo_geo_manager_c6_write_assert_no_private_snapshots( $history['data'] );

$capabilities = seo_geo_manager_c6_write_request( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_c6_write_accept( 200 === $capabilities['status'] && is_array( $capabilities['data'] ), 'C6.2 capability discovery failed.' );
seo_geo_manager_c6_write_accept( true === ( $capabilities['data']['capabilities']['contextual_link_write']['available'] ?? false ), 'C6.2 write capability missing.' );
seo_geo_manager_c6_write_accept( '/links/contextual/changes/apply' === ( $capabilities['data']['operations']['links.contextual.apply']['path'] ?? '' ), 'C6.2 apply operation missing.' );

$subscriber_id = wp_create_user( 'manager-c6-write-subscriber', wp_generate_password( 32, true, true ), 'manager-c6-write-subscriber@example.test' );
seo_geo_manager_c6_write_accept( ! is_wp_error( $subscriber_id ), 'Could not create C6.2 subscriber fixture.' );
$subscriber = new WP_User( (int) $subscriber_id );
$subscriber->set_role( 'subscriber' );
wp_set_current_user( (int) $subscriber_id );
$denied = seo_geo_manager_c6_write_request( 'POST', '/seo-geo-manager/v1/links/contextual/changes/preview', $base_payload );
seo_geo_manager_c6_write_accept( 403 === $denied['status'], 'Subscriber incorrectly received C6.2 Preview access.' );

wp_set_current_user( 1 );

echo wp_json_encode(
	array(
		'ok'                            => true,
		'plugin_version'                => SEO_GEO_MANAGER_VERSION,
		'href_only'                     => true,
		'target_resource_id_only'       => true,
		'arbitrary_target_url_ignored'  => true,
		'published_approval_guard'      => true,
		'idempotent_apply'              => true,
		'idempotency_conflict_guard'    => true,
		'exact_content_rollback'        => true,
		'stale_rollback_guard'          => true,
		'private_snapshots_returned'    => false,
		'least_privilege_enforced'      => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
