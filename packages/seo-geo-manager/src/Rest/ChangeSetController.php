<?php
/**
 * REST endpoints for controlled Manager change sets.
 *
 * @package SeoGeoManager
 */

declare(strict_types=1);

namespace SeoGeo\Manager\Rest;

use SeoGeo\Manager\Changes\ChangeSetEngine;
use SeoGeo\Manager\Changes\OperationStore;
use SeoGeo\Manager\Support\EnvironmentPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ChangeSetController {
	private const NAMESPACE = 'seo-geo-manager/v1';

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/changes/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'preview' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/apply',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'apply' ),
				'permission_callback' => array( self::class, 'can_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/(?P<operation_id>[a-f0-9\-]{36})',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/changes/(?P<operation_id>[a-f0-9\-]{36})/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'rollback' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'operation_id' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public static function can_write(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function preview( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result  = ChangeSetEngine::preview( is_array( $payload ) ? $payload : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['environment'] = EnvironmentPolicy::snapshot();

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$result = ChangeSetEngine::apply( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$environment           = EnvironmentPolicy::snapshot();
		$result['environment'] = $environment;
		$operation_id          = isset( $result['operation_id'] ) && is_string( $result['operation_id'] ) ? $result['operation_id'] : '';

		if ( '' !== $operation_id ) {
			$stored = OperationStore::get( $operation_id );
			if ( is_array( $stored ) && ! isset( $stored['environment'] ) ) {
				OperationStore::save( $operation_id, EnvironmentPolicy::bind_operation( $stored ) );
			}
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function show( WP_REST_Request $request ) {
		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( ! is_array( $operation ) ) {
			return new WP_Error(
				'seo_geo_manager_operation_not_found',
				'Manager operation not found.',
				array( 'status' => 404 )
			);
		}

		$target_id = isset( $operation['target_id'] ) ? (int) $operation['target_id'] : 0;
		if ( 1 > $target_id || ! current_user_can( 'edit_post', $target_id ) ) {
			return new WP_Error(
				'seo_geo_manager_forbidden',
				'You cannot inspect this Manager operation.',
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( $operation, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rollback( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$guard   = EnvironmentPolicy::validate_payload( $payload );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$operation_id = (string) $request->get_param( 'operation_id' );
		$operation    = OperationStore::get( $operation_id );
		if ( is_array( $operation ) ) {
			$operation_guard = EnvironmentPolicy::validate_operation_environment( $operation );
			if ( is_wp_error( $operation_guard ) ) {
				return $operation_guard;
			}
		}

		$result = ChangeSetEngine::rollback( $operation_id );

		return self::respond( $result, 200 );
	}

	/**
	 * @param array<string, mixed>|WP_Error $result Result.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function respond( $result, int $status ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, $status );
	}
}
