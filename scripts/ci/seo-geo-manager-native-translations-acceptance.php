<?php
/**
 * Runtime acceptance for SEO/GEO Manager native translation relationship operations.
 *
 * Executed with WP-CLI eval-file inside the shared Theme + Manager fixture.
 */

use SeoGeo\Manager\Support\EnvironmentPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

/**
 * Assert one acceptance condition.
 *
 * @param bool   $condition Assertion state.
 * @param string $message   Failure message.
 */
function seo_geo_manager_native_translation_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Execute one Manager REST request with an optional JSON body.
 *
 * @param string               $method HTTP method.
 * @param string               $route  REST route.
 * @param array<string, mixed> $body   JSON body.
 */
function seo_geo_manager_native_translation_rest( string $method, string $route, array $body = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	if ( array() !== $body ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
	}

	$response = rest_do_request( $request );
	seo_geo_manager_native_translation_accept( $response instanceof WP_REST_Response, 'REST request did not return a WordPress REST response.' );

	return $response;
}

/**
 * Return a REST response body as an array.
 *
 * @return array<string, mixed>
 */
function seo_geo_manager_native_translation_data( WP_REST_Response $response ): array {
	$data = $response->get_data();
	seo_geo_manager_native_translation_accept( is_array( $data ), 'REST response body was not an array.' );

	return $data;
}

wp_set_current_user( 1 );
seo_geo_manager_native_translation_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );
seo_geo_manager_native_translation_accept( defined( 'SEO_GEO_MANAGER_VERSION' ), 'Manager version constant is unavailable.' );
seo_geo_manager_native_translation_accept( version_compare( SEO_GEO_MANAGER_VERSION, '0.3.46', '>=' ), 'Native translation acceptance requires Manager >= 0.3.46.' );
seo_geo_manager_native_translation_accept( class_exists( '\\SeoGeo\\Core\\Language\\NativeTranslationGroupWriter' ), 'Self-contained Theme did not expose the native translation writer.' );

update_option(
	\SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME,
	array(
		'default'   => 'es',
		'routing'   => 'prefix',
		'languages' => array(
			'es' => 'es_ES',
			'en' => 'en_US',
		),
	),
	false
);

$es_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Manager Native ES',
		'post_name'    => 'manager-native-es',
		'post_content' => '<p>Contenido ES preservado.</p>',
		'post_author'  => 1,
	),
	true
);
$en_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Manager Native EN',
		'post_name'    => 'manager-native-en',
		'post_content' => '<p>Preserved EN content.</p>',
		'post_author'  => 1,
	),
	true
);
$third_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Manager Native Third',
		'post_name'    => 'manager-native-third',
		'post_content' => '<p>Concurrent relationship fixture.</p>',
		'post_author'  => 1,
	),
	true
);
seo_geo_manager_native_translation_accept( ! is_wp_error( $es_id ) && 0 < (int) $es_id, 'Could not create ES fixture post.' );
seo_geo_manager_native_translation_accept( ! is_wp_error( $en_id ) && 0 < (int) $en_id, 'Could not create EN fixture post.' );
seo_geo_manager_native_translation_accept( ! is_wp_error( $third_id ) && 0 < (int) $third_id, 'Could not create concurrent-edit fixture post.' );
$es_id    = (int) $es_id;
$en_id    = (int) $en_id;
$third_id = (int) $third_id;

$es_body_before = (string) get_post_field( 'post_content', $es_id );
$en_body_before = (string) get_post_field( 'post_content', $en_id );
$group_id       = 'manager-native-group';
$group_route    = '/seo-geo-manager/v1/content/translation-groups/' . $group_id;

$capabilities_response = seo_geo_manager_native_translation_rest( 'GET', '/seo-geo-manager/v1/capabilities' );
seo_geo_manager_native_translation_accept( 200 === $capabilities_response->get_status(), 'Capabilities endpoint rejected native translation acceptance administrator.' );
$capabilities = seo_geo_manager_native_translation_data( $capabilities_response );
seo_geo_manager_native_translation_accept( true === ( $capabilities['capabilities']['native_translation_groups']['available'] ?? false ), 'Native translation capability was not advertised as available with Theme/Core active.' );
seo_geo_manager_native_translation_accept( true === ( $capabilities['capabilities']['native_translation_groups']['provider_available'] ?? false ), 'Native translation provider was not advertised as available.' );
seo_geo_manager_native_translation_accept( false === ( $capabilities['capabilities']['native_translation_groups']['raw_meta_exposed'] ?? true ), 'Capability manifest exposed raw multilingual metadata.' );
seo_geo_manager_native_translation_accept( isset( $capabilities['operations']['content.native_translation_group.apply'] ), 'Native translation Apply operation was not advertised.' );

$empty_response = seo_geo_manager_native_translation_rest( 'GET', $group_route );
seo_geo_manager_native_translation_accept( 200 === $empty_response->get_status(), 'Empty native translation group inspection failed.' );
$empty = seo_geo_manager_native_translation_data( $empty_response );
seo_geo_manager_native_translation_accept( array() === ( $empty['translations'] ?? null ), 'Empty native translation group did not expose an empty relationship map.' );
seo_geo_manager_native_translation_accept( true === ( $empty['consistent'] ?? false ), 'Empty native translation group was not consistent.' );
seo_geo_manager_native_translation_accept( false === ( $empty['public_ready'] ?? true ), 'Empty native translation group was incorrectly public-ready.' );
$empty_fingerprint = isset( $empty['fingerprint'] ) && is_string( $empty['fingerprint'] ) ? $empty['fingerprint'] : '';
seo_geo_manager_native_translation_accept( 64 === strlen( $empty_fingerprint ), 'Empty native translation group fingerprint is invalid.' );
seo_geo_manager_native_translation_accept( false === str_contains( (string) wp_json_encode( $empty ), '_seo_geo_' ), 'Native translation inspection leaked raw provider metadata keys.' );

$translations = array(
	'es' => $es_id,
	'en' => $en_id,
);
$preview_body = array(
	'translations'         => $translations,
	'expected_fingerprint' => $empty_fingerprint,
);
$preview = seo_geo_manager_native_translation_rest( 'POST', $group_route . '/changes/preview', $preview_body );
seo_geo_manager_native_translation_accept( 200 === $preview->get_status(), 'Native translation Preview failed.' );
$preview_data = seo_geo_manager_native_translation_data( $preview );
seo_geo_manager_native_translation_accept( true === ( $preview_data['has_changes'] ?? false ), 'Native translation Preview did not report a change.' );
seo_geo_manager_native_translation_accept( $translations === ( $preview_data['change']['after_translations'] ?? null ), 'Native translation Preview target map mismatch.' );
seo_geo_manager_native_translation_accept( false === str_contains( (string) wp_json_encode( $preview_data ), '_seo_geo_' ), 'Native translation Preview leaked raw provider metadata keys.' );
$after_preview = seo_geo_manager_native_translation_data( seo_geo_manager_native_translation_rest( 'GET', $group_route ) );
seo_geo_manager_native_translation_accept( array() === ( $after_preview['translations'] ?? null ), 'Native translation Preview mutated provider state.' );

$environment = EnvironmentPolicy::snapshot();
$blocked_body = $preview_body;
$blocked_body['idempotency_key']        = 'acceptance-native-translation-blocked';
$blocked_body['environment_fingerprint'] = $environment['fingerprint'];
$blocked = seo_geo_manager_native_translation_rest( 'POST', $group_route . '/changes/apply', $blocked_body );
seo_geo_manager_native_translation_accept( 409 === $blocked->get_status(), 'Published native translation Apply was not blocked without explicit approval.' );
$blocked_data = seo_geo_manager_native_translation_data( $blocked );
seo_geo_manager_native_translation_accept( 'seo_geo_manager_native_translation_public_approval_required' === ( $blocked_data['code'] ?? '' ), 'Unexpected published native translation guard error.' );

$apply_body = $preview_body;
$apply_body['idempotency_key']         = 'acceptance-native-translation-apply';
$apply_body['allow_published_targets'] = true;
$apply_body['environment_fingerprint'] = $environment['fingerprint'];
$apply = seo_geo_manager_native_translation_rest( 'POST', $group_route . '/changes/apply', $apply_body );
seo_geo_manager_native_translation_accept( 200 === $apply->get_status(), 'Approved native translation Apply failed.' );
$operation = seo_geo_manager_native_translation_data( $apply );
seo_geo_manager_native_translation_accept( 'applied' === ( $operation['status'] ?? '' ), 'Native translation Apply did not report applied.' );
$operation_id = isset( $operation['operation_id'] ) && is_string( $operation['operation_id'] ) ? $operation['operation_id'] : '';
seo_geo_manager_native_translation_accept( '' !== $operation_id, 'Native translation operation ID missing.' );
seo_geo_manager_native_translation_accept( $environment['fingerprint'] === ( $operation['environment']['fingerprint'] ?? '' ), 'Native translation operation was not environment-bound.' );
seo_geo_manager_native_translation_accept( false === str_contains( (string) wp_json_encode( $operation ), '_seo_geo_' ), 'Native translation operation evidence leaked raw provider metadata keys.' );

$replay = seo_geo_manager_native_translation_rest( 'POST', $group_route . '/changes/apply', $apply_body );
seo_geo_manager_native_translation_accept( 200 === $replay->get_status(), 'Native translation idempotent replay failed.' );
$replay_data = seo_geo_manager_native_translation_data( $replay );
seo_geo_manager_native_translation_accept( true === ( $replay_data['idempotent_replay'] ?? false ), 'Native translation Apply was not idempotent.' );
seo_geo_manager_native_translation_accept( $operation_id === ( $replay_data['operation_id'] ?? '' ), 'Native translation replay returned a different operation.' );

$applied = seo_geo_manager_native_translation_data( seo_geo_manager_native_translation_rest( 'GET', $group_route ) );
seo_geo_manager_native_translation_accept( $translations === ( $applied['translations'] ?? null ), 'Native translation Apply did not persist the exact reciprocal map.' );
seo_geo_manager_native_translation_accept( false === ( $applied['public_ready'] ?? true ), 'Draft-containing native translation group became public-ready.' );
$registry = \SeoGeo\Core\Runtime::translations();
seo_geo_manager_native_translation_accept( null !== $registry, 'Native translation registry is unavailable.' );
seo_geo_manager_native_translation_accept( null === $registry->for_post( $es_id ), 'Draft-containing native translation group leaked into public registry output.' );

$writer = new \SeoGeo\Core\Language\NativeTranslationGroupWriter();
$concurrent = $writer->replace(
	$group_id,
	array(
		'es' => $es_id,
		'en' => $en_id,
		'ca' => $third_id,
	)
);
seo_geo_manager_native_translation_accept( is_wp_error( $concurrent ), 'Unconfigured concurrent language fixture should have been rejected by Core.' );

// Simulate a valid newer provider edit by temporarily adding Catalan to the accepted language configuration.
update_option(
	\SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME,
	array(
		'default'   => 'es',
		'routing'   => 'prefix',
		'languages' => array(
			'es' => 'es_ES',
			'en' => 'en_US',
			'ca' => 'ca_ES',
		),
	),
	false
);
$writer = new \SeoGeo\Core\Language\NativeTranslationGroupWriter();
$concurrent = $writer->replace(
	$group_id,
	array(
		'es' => $es_id,
		'en' => $en_id,
		'ca' => $third_id,
	)
);
seo_geo_manager_native_translation_accept( ! is_wp_error( $concurrent ), 'Could not simulate a newer valid native-provider relationship edit.' );

$rollback_route = '/seo-geo-manager/v1/content/translation-groups/changes/' . $operation_id . '/rollback';
$stale_rollback = seo_geo_manager_native_translation_rest(
	'POST',
	$rollback_route,
	array( 'environment_fingerprint' => $environment['fingerprint'] )
);
seo_geo_manager_native_translation_accept( 409 === $stale_rollback->get_status(), 'Native translation rollback did not block a newer provider edit.' );
$stale_data = seo_geo_manager_native_translation_data( $stale_rollback );
seo_geo_manager_native_translation_accept( 'seo_geo_manager_native_translation_rollback_stale' === ( $stale_data['code'] ?? '' ), 'Unexpected stale native translation rollback error.' );

// Restore the exact applied state and original ES/EN language configuration so the original operation becomes safely reversible.
$restore = $writer->replace( $group_id, $translations );
seo_geo_manager_native_translation_accept( ! is_wp_error( $restore ), 'Could not restore the exact applied native translation state.' );
update_option(
	\SeoGeo\Core\Language\NativeLanguageConfiguration::OPTION_NAME,
	array(
		'default'   => 'es',
		'routing'   => 'prefix',
		'languages' => array(
			'es' => 'es_ES',
			'en' => 'en_US',
		),
	),
	false
);

$rollback = seo_geo_manager_native_translation_rest(
	'POST',
	$rollback_route,
	array( 'environment_fingerprint' => $environment['fingerprint'] )
);
seo_geo_manager_native_translation_accept( 200 === $rollback->get_status(), 'Native translation rollback failed after exact applied state was restored.' );
$rollback_data = seo_geo_manager_native_translation_data( $rollback );
seo_geo_manager_native_translation_accept( 'rolled-back' === ( $rollback_data['status'] ?? '' ), 'Native translation rollback did not report rolled-back.' );
$rolled_back = seo_geo_manager_native_translation_data( seo_geo_manager_native_translation_rest( 'GET', $group_route ) );
seo_geo_manager_native_translation_accept( array() === ( $rolled_back['translations'] ?? null ), 'Native translation rollback did not restore the original empty relationship group.' );

// Re-apply from the fresh empty fingerprint, then prove public readiness only after the draft translation is published.
$fresh_fingerprint = isset( $rolled_back['fingerprint'] ) && is_string( $rolled_back['fingerprint'] ) ? $rolled_back['fingerprint'] : '';
$second_apply_body = array(
	'translations'             => $translations,
	'expected_fingerprint'     => $fresh_fingerprint,
	'idempotency_key'          => 'acceptance-native-translation-public-ready',
	'allow_published_targets'  => true,
	'environment_fingerprint'  => $environment['fingerprint'],
);
$second_apply = seo_geo_manager_native_translation_rest( 'POST', $group_route . '/changes/apply', $second_apply_body );
seo_geo_manager_native_translation_accept( 200 === $second_apply->get_status(), 'Second native translation Apply failed.' );

$publish_en = wp_update_post(
	array(
		'ID'          => $en_id,
		'post_status' => 'publish',
	),
	true
);
seo_geo_manager_native_translation_accept( ! is_wp_error( $publish_en ), 'Could not publish EN fixture after relationship Apply.' );
$public_group = seo_geo_manager_native_translation_data( seo_geo_manager_native_translation_rest( 'GET', $group_route ) );
seo_geo_manager_native_translation_accept( true === ( $public_group['public_ready'] ?? false ), 'Fully published native translation group did not become public-ready.' );
$registry = new \SeoGeo\Core\Language\NativeTranslationRegistry();
$es_relationship = $registry->for_post( $es_id );
$en_relationship = $registry->for_post( $en_id );
seo_geo_manager_native_translation_accept( null !== $es_relationship && null !== $en_relationship, 'Fully published Manager-created relationship did not resolve through the native registry.' );
seo_geo_manager_native_translation_accept( $translations === $es_relationship->translations(), 'ES native registry relationship map mismatch.' );
seo_geo_manager_native_translation_accept( $translations === $en_relationship->translations(), 'EN native registry relationship map mismatch.' );

$author_user_id = wp_create_user( 'native_relation_author', wp_generate_password( 24, true, true ), 'native-author@example.test' );
seo_geo_manager_native_translation_accept( ! is_wp_error( $author_user_id ), 'Could not create least-privilege Author fixture.' );
$author_user = get_user_by( 'id', (int) $author_user_id );
seo_geo_manager_native_translation_accept( $author_user instanceof WP_User, 'Author fixture could not be loaded.' );
$author_user->set_role( 'author' );
wp_set_current_user( (int) $author_user_id );
$forbidden = seo_geo_manager_native_translation_rest( 'GET', $group_route );
seo_geo_manager_native_translation_accept( 403 === $forbidden->get_status(), 'Author without edit authority over all group resources was not rejected.' );
$forbidden_data = seo_geo_manager_native_translation_data( $forbidden );
seo_geo_manager_native_translation_accept( 'seo_geo_manager_native_translation_forbidden' === ( $forbidden_data['code'] ?? '' ), 'Unexpected per-resource native translation authorization error.' );
wp_set_current_user( 1 );

seo_geo_manager_native_translation_accept( $es_body_before === (string) get_post_field( 'post_content', $es_id ), 'Native translation operations changed ES post_content.' );
seo_geo_manager_native_translation_accept( $en_body_before === (string) get_post_field( 'post_content', $en_id ), 'Native translation operations changed EN post_content.' );
seo_geo_manager_native_translation_accept( false === str_contains( (string) wp_json_encode( $public_group ), '_seo_geo_' ), 'Native translation final readback leaked raw provider metadata keys.' );

printf(
	"%s\n",
	(string) wp_json_encode(
		array(
			'ok'                     => true,
			'plugin_version'         => SEO_GEO_MANAGER_VERSION,
			'provider'               => 'native-core',
			'group_id'               => $group_id,
			'published_relationship' => true,
			'stale_rollback_blocked' => true,
			'rollback_verified'      => true,
			'least_privilege'        => true,
			'raw_meta_exposed'       => false,
		)
	)
);
