<?php
/**
 * Runtime acceptance for optional rendered post-write verification.
 */

use SeoGeo\Manager\Support\ContentFingerprint;

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress is not loaded.' );
}

function seo_geo_manager_rendered_accept( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * @param array<string, mixed>|null $payload Optional JSON payload.
 * @return array{status:int,data:mixed}
 */
function seo_geo_manager_rendered_request( string $method, string $route, ?array $payload = null ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( null !== $payload ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );
	}
	$response = rest_do_request( $request );

	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
}

function seo_geo_manager_rendered_error_code( $data ): string {
	return is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
}

wp_set_current_user( 1 );
seo_geo_manager_rendered_accept( current_user_can( 'manage_options' ), 'Acceptance administrator could not be loaded.' );

$snapshot = seo_geo_manager_rendered_request( 'GET', '/seo-geo-manager/v1/site/snapshot' );
seo_geo_manager_rendered_accept( 200 === $snapshot['status'] && is_array( $snapshot['data'] ), 'Could not read environment snapshot.' );
$environment_fingerprint = (string) ( $snapshot['data']['environment']['fingerprint'] ?? '' );
seo_geo_manager_rendered_accept( '' !== $environment_fingerprint, 'Environment fingerprint missing.' );

$success_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Rendered verification success',
		'post_name'    => 'rendered-verification-success',
		'post_content' => '<p>Rendered success fixture.</p>',
	),
	true
);
seo_geo_manager_rendered_accept( ! is_wp_error( $success_id ) && 0 < (int) $success_id, 'Could not create rendered success page.' );
$success_post = get_post( (int) $success_id );
seo_geo_manager_rendered_accept( $success_post instanceof WP_Post, 'Could not reload rendered success page.' );
$success_url = get_permalink( $success_post );
seo_geo_manager_rendered_accept( is_string( $success_url ) && '' !== $success_url, 'Rendered success permalink missing.' );

$success_payload = array(
	'schema_version'          => 1,
	'verify_rendered'         => true,
	'allow_published_target'  => true,
	'environment_fingerprint' => $environment_fingerprint,
	'target'                  => array(
		'id'                   => (int) $success_id,
		'expected_fingerprint' => ContentFingerprint::for_post( $success_post ),
	),
	'changes'                 => array( 'title' => 'Rendered verification success revised' ),
);

$preview = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/preview', $success_payload );
seo_geo_manager_rendered_accept( 200 === $preview['status'] && is_array( $preview['data'] ), 'Rendered verification preview failed.' );
$preview_rendered = $preview['data']['policy']['rendered_verification'] ?? array();
seo_geo_manager_rendered_accept( is_array( $preview_rendered ) && true === ( $preview_rendered['requested'] ?? false ), 'Rendered verification preview did not record the request.' );
seo_geo_manager_rendered_accept( true === ( $preview_rendered['applicable'] ?? false ), 'Published same-site target was not render-verifiable.' );

$success_request_guard = false;
$success_filter = static function ( $preempt, array $args, string $url ) use ( $success_url, &$success_request_guard ) {
	if ( $url !== $success_url ) {
		return $preempt;
	}
	$success_request_guard = 0 === (int) ( $args['redirection'] ?? -1 ) && 2097152 === (int) ( $args['limit_response_size'] ?? 0 );

	return array(
		'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
		'body'     => '<!doctype html><html><head><title>Fixture</title></head><body><main>Rendered success.</main></body></html>',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $success_filter, 10, 3 );

$success_payload['idempotency_key'] = 'rendered-verification-success';
$apply = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/apply', $success_payload );
remove_filter( 'pre_http_request', $success_filter, 10 );
seo_geo_manager_rendered_accept( 200 === $apply['status'] && is_array( $apply['data'] ), 'Rendered verification apply failed.' );
seo_geo_manager_rendered_accept( $success_request_guard, 'Rendered verification did not enforce no redirects and bounded response size.' );
seo_geo_manager_rendered_accept( 'applied' === ( $apply['data']['status'] ?? '' ), 'Successful rendered operation status mismatch.' );
$success_evidence = $apply['data']['rendered_verification'] ?? array();
seo_geo_manager_rendered_accept( is_array( $success_evidence ) && 'passed' === ( $success_evidence['status'] ?? '' ), 'Rendered verification did not pass.' );
seo_geo_manager_rendered_accept( 200 === (int) ( $success_evidence['http_status'] ?? 0 ), 'Rendered HTTP status evidence mismatch.' );
seo_geo_manager_rendered_accept( '' !== (string) ( $success_evidence['body_sha256'] ?? '' ), 'Rendered body digest missing.' );
seo_geo_manager_rendered_accept( ! array_key_exists( 'body', $success_evidence ), 'Rendered verification leaked the response body.' );
$success_operation_id = (string) ( $apply['data']['operation_id'] ?? '' );
seo_geo_manager_rendered_accept( '' !== $success_operation_id, 'Rendered success operation ID missing.' );

$success_filter_replay = static function ( $preempt, array $args, string $url ) use ( $success_url ) {
	if ( $url === $success_url ) {
		throw new RuntimeException( 'Idempotent replay unexpectedly repeated the public HTTP verification.' );
	}
	return $preempt;
};
add_filter( 'pre_http_request', $success_filter_replay, 10, 3 );
$replay = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/apply', $success_payload );
remove_filter( 'pre_http_request', $success_filter_replay, 10 );
seo_geo_manager_rendered_accept( 200 === $replay['status'] && $success_operation_id === ( $replay['data']['operation_id'] ?? '' ), 'Rendered idempotent replay failed.' );
seo_geo_manager_rendered_accept( true === ( $replay['data']['idempotent_replay'] ?? false ), 'Rendered idempotent replay flag missing.' );

$failure_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Rendered verification failure',
		'post_name'    => 'rendered-verification-failure',
		'post_content' => '<p>Rendered failure fixture.</p>',
	),
	true
);
seo_geo_manager_rendered_accept( ! is_wp_error( $failure_id ) && 0 < (int) $failure_id, 'Could not create rendered failure page.' );
$failure_post = get_post( (int) $failure_id );
seo_geo_manager_rendered_accept( $failure_post instanceof WP_Post, 'Could not reload rendered failure page.' );
$failure_url = get_permalink( $failure_post );
seo_geo_manager_rendered_accept( is_string( $failure_url ) && '' !== $failure_url, 'Rendered failure permalink missing.' );

$failure_filter = static function ( $preempt, array $args, string $url ) use ( $failure_url ) {
	if ( $url !== $failure_url ) {
		return $preempt;
	}

	return array(
		'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
		'body'     => '<!doctype html><html><body>Temporarily unavailable.</body></html>',
		'response' => array( 'code' => 503, 'message' => 'Unavailable' ),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $failure_filter, 10, 3 );
$failure_payload = array(
	'schema_version'          => 1,
	'idempotency_key'         => 'rendered-verification-failure',
	'verify_rendered'         => true,
	'allow_published_target'  => true,
	'environment_fingerprint' => $environment_fingerprint,
	'target'                  => array(
		'id'                   => (int) $failure_id,
		'expected_fingerprint' => ContentFingerprint::for_post( $failure_post ),
	),
	'changes'                 => array( 'title' => 'Rendered failure persisted' ),
);
$failed = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/apply', $failure_payload );
remove_filter( 'pre_http_request', $failure_filter, 10 );
seo_geo_manager_rendered_accept( 409 === $failed['status'], 'Rendered HTTP failure did not fail the verification gate.' );
seo_geo_manager_rendered_accept( 'seo_geo_manager_rendered_verification_failed' === seo_geo_manager_rendered_error_code( $failed['data'] ), 'Unexpected rendered verification failure code.' );
seo_geo_manager_rendered_accept( 'Rendered failure persisted' === get_post_field( 'post_title', (int) $failure_id ), 'Rendered verification failure auto-rolled back a persisted mutation.' );
$failure_error_data = is_array( $failed['data'] ) && isset( $failed['data']['data'] ) && is_array( $failed['data']['data'] ) ? $failed['data']['data'] : array();
$failure_operation_id = (string) ( $failure_error_data['operation_id'] ?? '' );
seo_geo_manager_rendered_accept( '' !== $failure_operation_id, 'Rendered failure operation ID missing.' );

$failure_operation = seo_geo_manager_rendered_request( 'GET', '/seo-geo-manager/v1/changes/' . $failure_operation_id );
seo_geo_manager_rendered_accept( 200 === $failure_operation['status'] && is_array( $failure_operation['data'] ), 'Rendered failure operation could not be inspected.' );
seo_geo_manager_rendered_accept( 'rendered-verification-failed' === ( $failure_operation['data']['status'] ?? '' ), 'Rendered failure operation status was not persisted.' );
seo_geo_manager_rendered_accept( $environment_fingerprint === ( $failure_operation['data']['environment']['fingerprint'] ?? '' ), 'Rendered failure operation was not bound to the current environment.' );
seo_geo_manager_rendered_accept( ! isset( $failure_operation['data']['rendered_verification']['body'] ), 'Stored rendered evidence leaked response body.' );

$rollback = seo_geo_manager_rendered_request(
	'POST',
	'/seo-geo-manager/v1/changes/' . $failure_operation_id . '/rollback',
	array( 'environment_fingerprint' => $environment_fingerprint )
);
seo_geo_manager_rendered_accept( 200 === $rollback['status'] && 'rolled-back' === ( $rollback['data']['status'] ?? '' ), 'Manual rollback after rendered verification failure failed.' );
seo_geo_manager_rendered_accept( 'Rendered verification failure' === get_post_field( 'post_title', (int) $failure_id ), 'Manual rollback did not restore the pre-write title.' );

$draft_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Rendered verification draft',
		'post_name'    => 'rendered-verification-draft',
		'post_content' => '<p>Draft.</p>',
	),
	true
);
seo_geo_manager_rendered_accept( ! is_wp_error( $draft_id ) && 0 < (int) $draft_id, 'Could not create rendered draft page.' );
$draft_post = get_post( (int) $draft_id );
seo_geo_manager_rendered_accept( $draft_post instanceof WP_Post, 'Could not reload rendered draft page.' );
$draft_payload = array(
	'schema_version'          => 1,
	'idempotency_key'         => 'rendered-verification-draft',
	'verify_rendered'         => true,
	'environment_fingerprint' => $environment_fingerprint,
	'target'                  => array(
		'id'                   => (int) $draft_id,
		'expected_fingerprint' => ContentFingerprint::for_post( $draft_post ),
	),
	'changes'                 => array( 'title' => 'Draft must remain unchanged' ),
);
$draft_preview = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/preview', $draft_payload );
seo_geo_manager_rendered_accept( 200 === $draft_preview['status'], 'Draft rendered preview unexpectedly failed.' );
seo_geo_manager_rendered_accept( false === ( $draft_preview['data']['policy']['rendered_verification']['applicable'] ?? true ), 'Draft rendered preview did not report public verification as unavailable.' );
$draft_apply = seo_geo_manager_rendered_request( 'POST', '/seo-geo-manager/v1/changes/apply', $draft_payload );
seo_geo_manager_rendered_accept( 409 === $draft_apply['status'], 'Draft rendered Apply was not blocked before mutation.' );
seo_geo_manager_rendered_accept( 'seo_geo_manager_rendered_verification_unavailable' === seo_geo_manager_rendered_error_code( $draft_apply['data'] ), 'Unexpected draft rendered verification guard code.' );
seo_geo_manager_rendered_accept( 'Rendered verification draft' === get_post_field( 'post_title', (int) $draft_id ), 'Draft rendered verification guard mutated the resource.' );

wp_delete_post( (int) $success_id, true );
wp_delete_post( (int) $failure_id, true );
wp_delete_post( (int) $draft_id, true );

echo wp_json_encode(
	array(
		'ok'                         => true,
		'bounded_same_origin'        => true,
		'no_redirect_guard'          => true,
		'body_not_persisted'         => true,
		'idempotent_evidence'        => true,
		'no_auto_rollback_on_http'   => true,
		'manual_rollback_after_fail' => true,
		'draft_guard'                => true,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
