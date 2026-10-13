<?php
/**
 * REST endpoints for Theme strategic-media bindings.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Changes\ThemeStrategicMediaChangeAdapter;
use SeoGeo\Manager\Intelligence\ThemeStrategicMediaReader;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/** Expose bounded Theme strategic media operations to external orchestration. */
final class ThemeStrategicMediaController {
	private const NAMESPACE = 'seo-geo-manager/v1';
	private const ADAPTER   = 'theme-strategic-media';
	private const BASE_PATH = '/theme/media-bindings/(?P<resource_id>\d+)/(?P<model_id>[a-z0-9_-]+)/(?P<slot_id>[a-z0-9_-]+)';

	public static function register_routes(): void {
		$args = array(
			'resource_id' => array( 'sanitize_callback' => 'absint' ),
			'model_id'    => array( 'sanitize_callback' => 'sanitize_key' ),
			'slot_id'     => array( 'sanitize_callback' => 'sanitize_key' ),
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH . '/changes/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			self::BASE_PATH . '/changes/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => $args,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/theme/media-bindings/changes/(?P<operation_id>[a-f0-9\-]{36})/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'verify' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/theme/media-bindings/changes/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	public static function can_read(): bool {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_pages' );
	}

	public static function can_write(): bool {
		return self::can_read();
	}

	/** @return WP_REST_Response|WP_Error */
	public static function show( WP_REST_Request $request ) {
		$result = ThemeStrategicMediaReader::read(
			absint( $request->get_param( 'resource_id' ) ),
			(string) $request->get_param( 'model_id' ),
			(string) $request->get_param( 'slot_id' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function preview( WP_REST_Request $request ) {
		$result = ThemeStrategicMediaChangeAdapter::preview( self::payload( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function apply( WP_REST_Request $request ) {
		$payload = self::payload( $request );
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ThemeStrategicMediaChangeAdapter::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$operation_id = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';
		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				$stored = EnvironmentPolicy::bind_operation( $stored );
				OperationStore::save( $operation_id, $stored );
				$result = $stored;
			}
		}
		$result['environment'] = $result['environment'] ?? EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function verify( WP_REST_Request $request ) {
		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_operation_not_found', 'Theme media operation not found.', array( 'status' => 404 ) );
		}
		if ( 'applied' !== ( $operation['status'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_verify_unavailable', 'Only applied Theme media operations can be verified.', array( 'status' => 409 ) );
		}

		$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_guard ) ) {
			return $operation_guard;
		}

		$resource_id = isset( $operation['resource_id'] ) ? absint( $operation['resource_id'] ) : 0;
		$model_id    = isset( $operation['model_id'] ) && is_string( $operation['model_id'] ) ? sanitize_key( $operation['model_id'] ) : '';
		$slot_id     = isset( $operation['slot_id'] ) && is_string( $operation['slot_id'] ) ? sanitize_key( $operation['slot_id'] ) : '';
		$current     = ThemeStrategicMediaReader::read( $resource_id, $model_id, $slot_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$binding              = isset( $current['binding'] ) && is_array( $current['binding'] ) ? $current['binding'] : array();
		$current_attachment_id = isset( $binding['attachment_id'] ) ? absint( $binding['attachment_id'] ) : 0;
		$current_fingerprint  = isset( $binding['fingerprint'] ) && is_string( $binding['fingerprint'] ) ? $binding['fingerprint'] : '';
		$expected_attachment_id = isset( $operation['after_attachment_id'] ) ? absint( $operation['after_attachment_id'] ) : 0;
		$expected_fingerprint = isset( $operation['after_fingerprint'] ) && is_string( $operation['after_fingerprint'] ) ? $operation['after_fingerprint'] : '';

		if (
			$current_attachment_id !== $expected_attachment_id
			|| '' === $expected_fingerprint
			|| '' === $current_fingerprint
			|| ! hash_equals( $expected_fingerprint, $current_fingerprint )
		) {
			return new WP_Error(
				'seo_geo_manager_theme_media_verification_stale',
				'Strategic Theme media no longer matches the applied operation.',
				array( 'status' => 409 )
			);
		}

		return new WP_REST_Response(
			array(
				'verified'            => true,
				'operation_id'        => $operation_id,
				'resource_id'         => $resource_id,
				'model_id'            => $model_id,
				'slot_id'             => $slot_id,
				'attachment_id'       => $current_attachment_id,
				'current_fingerprint' => $current_fingerprint,
				'environment'         => EnvironmentPolicy::snapshot(),
			),
			200
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public static function rollback( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) || self::ADAPTER !== ( $operation['adapter'] ?? '' ) ) {
			return new WP_Error( 'seo_geo_manager_theme_media_operation_not_found', 'Theme media operation not found.', array( 'status' => 404 ) );
		}
		$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
		if ( is_wp_error( $operation_guard ) ) {
			return $operation_guard;
		}

		$result = ThemeStrategicMediaChangeAdapter::rollback( $operation_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/** @return array<string, mixed> */
	private static function payload( WP_REST_Request $request ): array {
		$payload                = $request->get_json_params();
		$payload                = is_array( $payload ) ? $payload : array();
		$payload['resource_id'] = absint( $request->get_param( 'resource_id' ) );
		$payload['model_id']    = sanitize_key( (string) $request->get_param( 'model_id' ) );
		$payload['slot_id']     = sanitize_key( (string) $request->get_param( 'slot_id' ) );

		return $payload;
	}
}